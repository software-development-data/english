<?php
declare(strict_types=1);
require_once __DIR__.'/hub-games.php';
function eqs_days(PDO $p,int $uid,int $since): array {$raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$uid])->fetchColumn();$pr=$raw?json_decode($raw,true):[];return array_values(array_filter($pr['days']??[],fn($d)=>$d>=date('Y-m-d',$since)&&$d<=date('Y-m-d',$since+6*86400)));}
function eqs_public(PDO $p,int $uid,array $i): array {
 $other=(int)$i['sender_id']===$uid?(int)$i['recipient_id']:(int)$i['sender_id'];$i['person']=eqd_public($p,$uid,hub_public_profile($p,$uid,$other));
 if($i['status']==='accepted'&&hub_friend($p,$uid,$other)){
  if($i['kind']==='compare'){$i['comparison']=[];foreach([$uid,$other] as $id){$raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$id])->fetchColumn();$pr=$raw?json_decode($raw,true):[];$i['comparison'][]=['id'=>$id,'xp'=>$pr['xp']??0,'completed'=>count($pr['completed']??[]),'study_days'=>count($pr['days']??[])];}}
  if($i['kind']==='sevenday'){$i['challenge_days']=['you'=>eqs_days($p,$uid,(int)$i['updated_at']),'friend'=>eqs_days($p,$other,(int)$i['updated_at']),'ends_at'=>(int)$i['updated_at']+7*86400];}
  if(in_array($i['kind'],['memory','vocabulary'],true)){$c=hub_q($p,'SELECT * FROM eqd_challenges WHERE invite_id=?',[$i['id']])->fetch();if($c){$mine=(int)$i['sender_id']===$uid?'sender_run':'recipient_run';$theirs=$mine==='sender_run'?'recipient_run':'sender_run';$i['run']=hub_public_run(hub_run($p,$uid,$c[$mine]));$opponent=hub_run($p,$other,$c[$theirs]);if($i['run']['finished']&&$opponent['finished'])$i['opponent_score']=$opponent['state']['correct'];}}
 }
 return $i;
}
function eq_study(PDO $p,int $uid,string $op,array $in): never {
 if($op==='study_list'){$rows=hub_q($p,'SELECT * FROM eqd_invitations WHERE sender_id=? OR recipient_id=? ORDER BY id DESC LIMIT 100',[$uid,$uid])->fetchAll();$out=[];foreach($rows as $i){$other=(int)$i['sender_id']===$uid?(int)$i['recipient_id']:(int)$i['sender_id'];if(!hub_blocked($p,$uid,$other))$out[]=eqs_public($p,$uid,$i);}reply(200,['ok'=>true,'invitations'=>$out]);}
 if($op==='study_create'){
  $to=hub_int($in,'id');hub_target($p,$uid,$to);if(!hub_friend($p,$uid,$to))fail(403,'Chỉ mời bạn bè đã kết nối.');$kind=field($in,'kind');if(!in_array($kind,['course','memory','vocabulary','compare','sevenday','speaking','schedule','reminder'],true))fail(422,'Loại lời mời chưa hợp lệ.');$key=hub_text($in,'key',64);if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key))fail(422,'Mã gửi chưa hợp lệ.');$old=hub_q($p,'SELECT id FROM eqd_invitations WHERE sender_id=? AND client_key=?',[$uid,$key])->fetchColumn();if($old)reply(200,['ok'=>true,'id'=>(int)$old]);limit_attempts($p,'studyinvite:'.$uid,15,600);
  $course=field($in,'course_id')?:null;if($kind==='course'){require_once __DIR__.'/courses.php';eqc_course($p,$course??'');}else $course=null;$start=$in['starts_at']??null;if($start!==null&&(!is_int($start)||$start<time()||$start>time()+366*86400))fail(422,'Lịch hẹn cần ở tương lai, trong vòng một năm.');if(in_array($kind,['speaking','schedule'],true)&&$start===null)fail(422,'Cần chọn lịch hẹn.');
  hub_q($p,'INSERT INTO eqd_invitations(sender_id,recipient_id,kind,title,notes,course_id,starts_at,created_at,updated_at,client_key) VALUES (?,?,?,?,?,?,?,?,?,?)',[$uid,$to,$kind,hub_text($in,'title',160),hub_text($in,'notes',2000,true),$course,$start,time(),time(),$key]);$id=(int)$p->lastInsertId();hub_notify($p,$to,$uid,'Bạn có lời mời học: '.hub_text($in,'title',160),'study',$id);reply(200,['ok'=>true,'id'=>$id]);
 }
 if($op==='study_action'){
  $id=hub_int($in,'id');$act=field($in,'action');$p->beginTransaction();try{
   $i=hub_q($p,'SELECT * FROM eqd_invitations WHERE id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id])->fetch();if(!$i||!in_array($uid,[(int)$i['sender_id'],(int)$i['recipient_id']],true)){$p->rollBack();fail(404,'Không tìm thấy lời mời.');}$other=(int)$i['sender_id']===$uid?(int)$i['recipient_id']:(int)$i['sender_id'];hub_target($p,$uid,$other);
   if($act==='cancel'){hub_q($p,"UPDATE eqd_invitations SET status='cancelled' WHERE id=?",[$id]);$p->commit();reply(200,['ok'=>true]);}
   if((int)$i['recipient_id']!==$uid||!in_array($act,['accept','reject'],true)){$p->rollBack();fail(403,'Chỉ người nhận được trả lời lời mời.');}if($i['status']!=='pending'){$p->commit();reply(200,['ok'=>true]);}if(!hub_friend($p,$uid,$other)){$p->rollBack();fail(403,'Hai bên cần còn là bạn bè.');}
   $room=null;if($act==='accept'&&in_array($i['kind'],['speaking','schedule'],true)){
    if(!eqd_can_message($p,$uid,$other)||!eqd_can_message($p,$other,$uid)){$p->rollBack();fail(403,'Cần hai bên cho phép nhắn tin.');}
    hub_q($p,'INSERT INTO eqx_rooms(owner_id,title,kind,created_at) VALUES (?,?,?,?)',[$other,'Speaking · '.$i['title'],'group',time()]);$room=(int)$p->lastInsertId();foreach([$uid,$other] as $u)hub_q($p,'INSERT INTO eqx_room_members(room_id,user_id) VALUES (?,?)',[$room,$u]);
   }
   if($act==='accept'&&in_array($i['kind'],['memory','vocabulary'],true)){
    $catalog=eq_catalog();$pool=[];foreach($catalog as $t)foreach($i['kind']==='memory'?$t['words']:$t['practice'] as $q)if(!isset($q['kind']))$pool[]=$q;shuffle($pool);$pool=array_slice($pool,0,5);$state=['index'=>0,'correct'=>0,'attempts'=>0];if($i['kind']==='memory'){$deck=[];foreach($pool as $n=>$w){$deck[]=['pair'=>$n,'text'=>$w['en']];$deck[]=['pair'=>$n,'text'=>$w['vi']];}shuffle($deck);$state+=['deck'=>$deck,'matched'=>[]];}else $state['questions']=$pool;
    $runs=[];foreach([(int)$i['sender_id'],(int)$i['recipient_id']] as $u){$run=bin2hex(random_bytes(16));$runs[]=$run;hub_q($p,'INSERT INTO eqx_runs(id,user_id,game,state,started_at,updated_at) VALUES (?,?,?,?,?,?)',[$run,$u,$i['kind'],json_encode($state,JSON_UNESCAPED_UNICODE),time(),time()]);}hub_q($p,'INSERT INTO eqd_challenges(invite_id,game,sender_run,recipient_run) VALUES (?,?,?,?)',[$id,$i['kind'],$runs[0],$runs[1]]);
   }
   hub_q($p,'UPDATE eqd_invitations SET status=?,room_id=?,updated_at=? WHERE id=?',[$act==='accept'?'accepted':'rejected',$room,time(),$id]);$p->commit();hub_notify($p,$other,$uid,'Lời mời học đã được '.($act==='accept'?'chấp nhận.':'từ chối.'),'study',$id);reply(200,['ok'=>true]);
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 }
 fail(404,'Chức năng học cùng bạn chưa tồn tại.');
}

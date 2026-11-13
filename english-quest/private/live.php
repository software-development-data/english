<?php
declare(strict_types=1);
function eq5_live_config(array $c):array{$v=$c['livekit']??[];return ['url'=>getenv('LIVEKIT_URL')?:($v['url']??''),'key'=>getenv('LIVEKIT_API_KEY')?:($v['api_key']??''),'secret'=>getenv('LIVEKIT_API_SECRET')?:($v['api_secret']??'')];}
function eq5_live_ready(array $v):bool{return str_starts_with($v['url'],'wss://')&&$v['key']!==''&&strlen($v['secret'])>=20;}
function eq5_jwt(array $c,array $claims):string{$b=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');$h=$b(json_encode(['alg'=>'HS256','typ'=>'JWT']));$v=$b(json_encode(['iss'=>$c['key'],'nbf'=>time()-5,'exp'=>time()+120]+$claims));return $h.'.'.$v.'.'.$b(hash_hmac('sha256',$h.'.'.$v,$c['secret'],true));}
function eq5_live_room(PDO $p,int $uid,int $id):array{$r=hub_q($p,'SELECT l.*,u.display_name FROM eq5_live l JOIN eq_users u ON u.id=l.owner_id WHERE l.id=?',[$id])->fetch();if(!$r||hub_blocked($p,$uid,(int)$r['owner_id'])||(int)hub_profile($p,(int)$r['owner_id'])['suspended'])fail(404,'Phòng live không khả dụng.');return $r;}
function eq5_live(PDO $p,int $uid,array $config,string $op,array $in):never{
 try{$p->query('SELECT id FROM eq5_live LIMIT 1');}catch(PDOException $e){fail(503,'Cần cài database/v5.sql để mở phòng livestream.');}
 $c=eq5_live_config($config);$ready=eq5_live_ready($c);
 if($op==='live_list'){$rows=hub_q($p,"SELECT l.id,l.owner_id,l.title,l.status,l.seen_at,u.display_name FROM eq5_live l JOIN eq_users u ON u.id=l.owner_id WHERE l.status='open' AND l.seen_at>? ORDER BY l.id DESC LIMIT 40",[time()-180])->fetchAll();$rows=array_values(array_filter($rows,fn($r)=>!hub_blocked($p,$uid,(int)$r['owner_id'])&&!(int)hub_profile($p,(int)$r['owner_id'])['suspended']));reply(200,['ok'=>true,'configured'=>$ready,'rooms'=>$rows]);}
 if(!$ready)fail(503,'Livestream chưa được mở. Quản trị viên cần cấu hình LiveKit trước khi phát camera.');
 if($op==='live_create'){
  $title=hub_text($in,'title',180);$p->beginTransaction();try{hub_q($p,'SELECT user_id FROM eqx_profiles WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid]);$old=hub_q($p,"SELECT id FROM eq5_live WHERE owner_id=? AND status='open' AND seen_at>?",[$uid,time()-180])->fetchColumn();if($old){$p->commit();reply(200,['ok'=>true,'id'=>(int)$old]);}
  if((int)hub_q($p,'SELECT COUNT(*) FROM eq5_live WHERE owner_id=? AND created_at>?',[$uid,strtotime('today')])->fetchColumn()>=5){$p->rollBack();fail(429,'Bạn đã tạo 5 phòng hôm nay.');}
  hub_q($p,"INSERT INTO eq5_live(owner_id,title,room_key,status,created_at,seen_at) VALUES (?,?,?,'open',?,?)",[$uid,$title,'eq5-'.bin2hex(random_bytes(16)),time(),time()]);$id=(int)$p->lastInsertId();$p->commit();reply(200,['ok'=>true,'id'=>$id]);}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 }
 $r=eq5_live_room($p,$uid,hub_int($in,'room'));$owner=(int)$r['owner_id']===$uid;
 if($op==='live_end'){
  if(!$owner)fail(403,'Chỉ chủ phòng được kết thúc live.');if($r['status']==='ended')reply(200,['ok'=>true]);
  if(!function_exists('curl_init'))fail(503,'Máy chủ cần bật cURL để kết thúc livestream.');
  $url=preg_replace('/^wss:/','https:',$c['url']);$token=eq5_jwt($c,['sub'=>'server','video'=>['room'=>$r['room_key'],'roomCreate'=>true]]);
  $ch=curl_init(rtrim($url,'/').'/twirp/livekit.RoomService/DeleteRoom');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token],CURLOPT_POSTFIELDS=>json_encode(['room'=>$r['room_key']])]);curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if(!in_array($status,[200,404],true))fail(502,'Chưa đóng được phòng trên dịch vụ video. Hãy thử kết thúc lại.');hub_q($p,"UPDATE eq5_live SET status='ended' WHERE id=?",[$r['id']]);reply(200,['ok'=>true]);
 }
 $open=$r['status']==='open'&&(int)$r['seen_at']>time()-180;
 if($op==='live_status')reply(200,['ok'=>true,'open'=>$open,'room'=>['id'=>(int)$r['id'],'title'=>$r['title'],'owner'=>$owner,'display_name'=>$r['display_name']]]);
 if(!$open)fail(410,'Buổi livestream đã kết thúc hoặc chủ phòng đã mất kết nối.');
 if($op==='live_heartbeat'){if(!$owner)fail(403,'Chỉ chủ phòng được cập nhật phiên live.');hub_q($p,'UPDATE eq5_live SET seen_at=? WHERE id=?',[time(),$r['id']]);reply(200,['ok'=>true]);}
 if($op==='live_join'){$name=hub_q($p,'SELECT display_name FROM eq_users WHERE id=?',[$uid])->fetchColumn();$token=eq5_jwt($c,['sub'=>'eq-user-'.$uid,'name'=>$name,'video'=>['room'=>$r['room_key'],'roomJoin'=>true,'canPublish'=>$owner,'canSubscribe'=>true,'canPublishData'=>false,'canPublishSources'=>$owner?['camera','microphone']:[]]]);reply(200,['ok'=>true,'url'=>$c['url'],'token'=>$token,'owner'=>$owner,'title'=>$r['title']]);}
 if($op==='live_messages'){$rows=hub_q($p,'SELECT m.id,m.body,m.created_at,m.user_id,u.display_name FROM eq5_live_chat m JOIN eq_users u ON u.id=m.user_id WHERE m.room_id=? ORDER BY m.id DESC LIMIT 50',[$r['id']])->fetchAll();reply(200,['ok'=>true,'messages'=>array_reverse(array_values(array_filter($rows,fn($m)=>!hub_blocked($p,$uid,(int)$m['user_id']))))]);}
 if($op==='live_chat'){limit_attempts($p,'live-chat:'.$uid,15,60);$body=hub_text($in,'body',600);hub_q($p,'INSERT INTO eq5_live_chat(room_id,user_id,body,created_at) VALUES (?,?,?,?)',[$r['id'],$uid,$body,time()]);reply(200,['ok'=>true]);}
 fail(404,'Không tìm thấy tính năng livestream.');
}

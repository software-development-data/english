<?php
declare(strict_types=1);
require_once __DIR__.'/hub-common.php';require_once __DIR__.'/discovery.php';
function hub_handle(PDO $p,bool $mysql,array $config,string $action,array $in): never {
 $u=current_account($p);if(!$u)fail(401,'Vui lòng đăng nhập.');$uid=(int)$u['id'];
 try{$profile=hub_profile($p,$uid);}catch(PDOException $e){fail(503,'Chưa cài migration V4. Chạy private/migrate-v4.php hoặc nhập database/v4.sql.');}
 limit_attempts($p,'hub:'.$uid,180,60);
 if($action==='hub_upload'||$action==='hub_file'){require_once __DIR__.'/hub-files.php';hub_files($p,$uid,$action,$in);}
 $op=$action==='hub_boot'?'boot':field($in,'op');
 if(str_starts_with($op,'course_')){require_once __DIR__.'/courses.php';eq_courses($p,$uid,$config,$op,$in);}
 if(str_starts_with($op,'study_')){require_once __DIR__.'/study.php';eq_study($p,$uid,$op,$in);}
 if(in_array($op,['discover','nearby','location','discovery_settings','discovery_save','discovery_map'],true))eq_discovery($p,$uid,$config,$op,$in);
 if(in_array($op,['profile_me','profile_email_begin','profile_email_cancel','profile_password_change','profile_revoke_others'],true)){require_once __DIR__.'/profile.php';eq_profile($p,$uid,$config,$op,$in);}
 if(in_array($op,['speaking_catalog','speaking_begin','speaking_result','speaking_history_full','speaking_conversations','speaking_conversation_create','speaking_conversation','speaking_turn'],true)){require_once __DIR__.'/speaking.php';eq_speaking($p,$uid,$config,$op,$in);}
 if(str_starts_with($op,'gift_')){require_once __DIR__.'/community-gifts.php';eq_gifts($p,$uid,$op,$in);}
 if(str_starts_with($op,'pet_')){require_once __DIR__.'/pets.php';eq5_pets($p,$uid,$op,$in);}
 if(str_starts_with($op,'live_')){require_once __DIR__.'/live.php';eq5_live($p,$uid,$config,$op,$in);}
 if(str_starts_with($op,'wallet_')){require_once __DIR__.'/wardrobe.php';eq5_wardrobe($p,$uid,$op,$in);}
 if(str_starts_with($op,'farm_')){require_once __DIR__.'/farm.php';eq5_farm($p,$uid,$op,$in);}
 if(str_starts_with($op,'checkin_')){require_once __DIR__.'/checkin.php';eq5_checkin($p,$uid,$op,$in);}
 if($op==='boot'){
  $raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$uid])->fetchColumn();$profile['xp']=(int)((json_decode($raw?:'{}',true)['xp'])??0);
  $usage=(int)hub_q($p,'SELECT COUNT(*) FROM eqx_ai_usage WHERE user_id=? AND day=?',[$uid,date('Y-m-d')])->fetchColumn();
  $key=getenv('GEMINI_API_KEY')?:($config['gemini']['api_key']??'');
  reply(200,['ok'=>true,'me'=>public_user($u),'profile'=>$profile,'plan'=>hub_plan($p,$uid),'ai_used'=>$usage,'ai_ready'=>!empty($config['gemini']['enabled'])&&$key!==''&&!str_starts_with($key,'THAY_'),'notifications'=>hub_q($p,'SELECT * FROM eqx_notifications WHERE user_id=? ORDER BY id DESC LIMIT 30',[$uid])->fetchAll(),'csrf'=>csrf()]);
 }
 if($op==='profile'){
  reply(200,['ok'=>true,'profile'=>eqd_public($p,$uid,hub_public_profile($p,$uid,hub_int($in,'id')))]);
 }
 if($op==='profile_save'){
  $name=hub_text($in,'name',240);if(preg_match_all('/./us',$name)>60)fail(422,'Tên hiển thị tối đa 60 ký tự.');$username=strtolower(hub_text($in,'username',40));if(!preg_match('/^[a-z][a-z0-9_]{2,39}$/D',$username))fail(422,'Username cần 3–40 ký tự a-z, 0-9, dấu gạch dưới.');
  $level=field($in,'level');if(!in_array($level,['A1','A2','B1','B2','C1','C2'],true))fail(422,'Trình độ chưa hợp lệ.');
  $minutes=$in['minutes']??10;if(!is_int($minutes)||$minutes<5||$minutes>180)fail(422,'Thời gian học từ 5–180 phút.');
  $avatar=$in['avatar_id']??null;$cover=$in['cover_id']??null;
  foreach([$avatar,$cover] as $id)if($id!==null){if(!is_int($id))fail(422,'Ảnh chưa hợp lệ.');$f=hub_owned_file($p,$uid,$id);if(!str_starts_with($f['mime'],'image/'))fail(422,'Cần tệp ảnh.');}
  $p->beginTransaction();try{
   hub_q($p,'UPDATE eqx_profiles SET username=?,bio=?,level=?,goal=?,minutes=?,discover=?,online_visible=?,avatar_id=?,cover_id=? WHERE user_id=?',[$username,hub_text($in,'bio',2000,true),$level,hub_text($in,'goal',160),$minutes,!empty($in['discover'])?1:0,!empty($in['online_visible'])?1:0,$avatar,$cover,$uid]);
   hub_q($p,'UPDATE eq_users SET display_name=? WHERE id=?',[$name,$uid]);$p->commit();
  }catch(PDOException $e){$p->rollBack();if((string)$e->getCode()==='23000')fail(409,'Username đã được sử dụng.');throw $e;}
  reply(200,['ok'=>true]);
 }
 if($op==='friends'){
  $rows=hub_q($p,'SELECT * FROM eqx_friends WHERE low_id=? OR high_id=? ORDER BY id DESC',[$uid,$uid])->fetchAll();$out=[];foreach($rows as $r){$other=(int)$r['low_id']===$uid?(int)$r['high_id']:(int)$r['low_id'];if(hub_blocked($p,$uid,$other))continue;$r['person']=eqd_public($p,$uid,hub_public_profile($p,$uid,$other));$out[]=$r;}
  reply(200,['ok'=>true,'friends'=>$out]);
 }
 if($op==='friend_action'){
  $target=hub_int($in,'id');hub_target($p,$uid,$target);[$a,$b]=hub_pair($uid,$target);$act=field($in,'action');$r=hub_q($p,'SELECT * FROM eqx_friends WHERE low_id=? AND high_id=?',[$a,$b])->fetch();
  if($act==='request'){
   if(!$r){limit_attempts($p,'friendrequest:'.$uid,20,600);try{hub_q($p,'INSERT INTO eqx_friends(low_id,high_id,requester_id,status) VALUES (?,?,?,?)',[$a,$b,$uid,'pending']);hub_notify($p,$target,$uid,'Có lời mời kết bạn mới.','profile',$uid);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}}
  }elseif($act==='accept'){
   if(!$r||$r['status']!=='pending'||(int)$r['requester_id']===$uid)fail(403,'Bạn không thể chấp nhận lời mời này.');hub_q($p,"UPDATE eqx_friends SET status='accepted' WHERE id=?",[$r['id']]);hub_notify($p,$target,$uid,'Lời mời kết bạn đã được chấp nhận.','profile',$uid);
  }elseif($act==='remove'||$act==='reject'){
   if($r)hub_q($p,'DELETE FROM eqx_friends WHERE id=?',[$r['id']]);
  }else fail(422,'Hành động chưa hợp lệ.');reply(200,['ok'=>true]);
 }
 if($op==='follow'||$op==='block'){
  $target=hub_int($in,'id');if($target===$uid)fail(422,'Không thể chọn chính mình.');$table=$op==='block'?'eqx_blocks':'eqx_follows';
  if(!empty($in['enabled'])){if($op==='follow')hub_target($p,$uid,$target);try{hub_q($p,"INSERT INTO $table(user_id,target_id) VALUES (?,?)",[$uid,$target]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}}
  else hub_q($p,"DELETE FROM $table WHERE user_id=? AND target_id=?",[$uid,$target]);
  reply(200,['ok'=>true]);
 }
 if($op==='blocks')reply(200,['ok'=>true,'blocks'=>hub_q($p,'SELECT b.target_id,u.display_name FROM eqx_blocks b JOIN eq_users u ON u.id=b.target_id WHERE b.user_id=?',[$uid])->fetchAll()]);
 if($op==='report'){
  $type=field($in,'type');if(!in_array($type,['user','post','message'],true))fail(422,'Loại báo cáo chưa hợp lệ.');$id=hub_int($in,'id');if($type==='post')hub_post($p,$uid,$id);if($type==='message'){$m=hub_q($p,'SELECT room_id FROM eqx_messages WHERE id=?',[$id])->fetch();if(!$m)fail(404,'Không tìm thấy tin.');hub_member($p,$uid,(int)$m['room_id']);}
  limit_attempts($p,'report:'.$uid,10,3600);hub_q($p,'INSERT INTO eqx_reports(user_id,target_type,target_id,reason,created_at) VALUES (?,?,?,?,?)',[$uid,$type,$id,hub_text($in,'reason',2000),time()]);reply(200,['ok'=>true]);
 }
 if($op==='notifications_read'){hub_q($p,'UPDATE eqx_notifications SET seen=1 WHERE user_id=?',[$uid]);reply(200,['ok'=>true]);}
 if($op==='sessions')reply(200,['ok'=>true,'sessions'=>hub_q($p,'SELECT token,created_at,seen_at,revoked FROM eqx_sessions WHERE user_id=? ORDER BY seen_at DESC LIMIT 40',[$uid])->fetchAll(),'current'=>hash('sha256',session_id())]);
 if($op==='session_revoke'){
  $token=hub_text($in,'token',64);hub_q($p,'UPDATE eqx_sessions SET revoked=1 WHERE token=? AND user_id=?',[$token,$uid]);reply(200,['ok'=>true]);
 }
 if($op==='email_request'||$op==='email_confirm'){
  require_once __DIR__.'/hub-mail.php';hub_mail($p,$config,$u,$op,$in);
 }
 if(in_array($op,['feed','post_create','post_delete','post_react','comments','comment_create','groups','group_create','group_join','group_leave','post_edit','comment_delete'],true)){require_once __DIR__.'/hub-social.php';hub_social($p,$uid,$op,$in);}
 if(in_array($op,['rooms','room_create','room_member','messages','message_send','message_edit','message_recall'],true)){require_once __DIR__.'/hub-chat.php';hub_chat($p,$uid,$op,$in);}
 if(in_array($op,['drive','folder_create','file_update','file_delete','file_share'],true)){require_once __DIR__.'/hub-files.php';hub_files($p,$uid,$op,$in);}
 if(in_array($op,['plans','order_create','orders','admin','admin_action'],true)){require_once __DIR__.'/hub-admin.php';hub_admin($p,$mysql,$uid,$profile,$config,$op,$in);}
 if(in_array($op,['game_list','game_start','game_answer','game_state','game_leaderboard','assistant','assistant_context','assistant_preferences','assistant_history','tutor','tutor_history','speaking_save','speaking_history'],true)){require_once __DIR__.'/hub-games.php';hub_games($p,$mysql,$uid,$config,$op,$in);}
 fail(404,'Không tìm thấy chức năng.');
}

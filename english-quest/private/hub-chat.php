<?php
declare(strict_types=1);
function hub_chat(PDO $p,int $uid,string $op,array $in): never {
 if($op==='rooms'){
  $rows=hub_q($p,'SELECT r.*,m.read_id,(SELECT COUNT(*) FROM eqx_messages x WHERE x.room_id=r.id AND x.id>m.read_id AND x.user_id<>?) AS unread FROM eqx_rooms r JOIN eqx_room_members m ON m.room_id=r.id WHERE m.user_id=? ORDER BY COALESCE((SELECT MAX(x.id) FROM eqx_messages x WHERE x.room_id=r.id),0) DESC,r.id DESC LIMIT 60',[$uid,$uid])->fetchAll();$out=[];
  foreach($rows as $r){$blocked=false;foreach(hub_q($p,'SELECT user_id FROM eqx_room_members WHERE room_id=?',[$r['id']])->fetchAll() as $m)if(hub_blocked($p,$uid,(int)$m['user_id']))$blocked=true;if(!$blocked){
   $last=hub_q($p,'SELECT body,recalled,file_id,created_at,user_id FROM eqx_messages WHERE room_id=? ORDER BY id DESC LIMIT 1',[$r['id']])->fetch();
   $r['preview']=$last?((int)$last['recalled']?'Tin nhắn đã thu hồi':($last['body']?:'Tệp đính kèm')):'';$r['last_at']=$last?$last['created_at']:$r['created_at'];
   if($r['kind']==='direct'){$peer=hub_q($p,'SELECT u.id,u.display_name,pr.avatar_id FROM eqx_room_members m JOIN eq_users u ON u.id=m.user_id JOIN eqx_profiles pr ON pr.user_id=u.id WHERE m.room_id=? AND m.user_id<>?',[$r['id'],$uid])->fetch();if($peer){$r['title']=$peer['display_name'];$r['avatar_id']=$peer['avatar_id'];$r['peer_id']=$peer['id'];}}
   $out[]=$r;
  }}reply(200,['ok'=>true,'rooms'=>$out]);
 }
 if($op==='room_create'){
  limit_attempts($p,'rooms:'.$uid,20,3600);$ids=$in['members']??[];if(!is_array($ids)||count($ids)<1||count($ids)>20)fail(422,'Chọn 1–20 người dùng.');$ids=array_values(array_unique($ids));foreach($ids as $id){if(!is_int($id))fail(422,'Thành viên chưa hợp lệ.');hub_target($p,$uid,$id);if(!eqd_can_message($p,$uid,$id))fail(403,'Người này chỉ nhận tin nhắn theo cài đặt riêng tư của họ.');} $ids[]=$uid;
  $kind=count($ids)===2?'direct':'group';$pair=$kind==='direct'?implode('-',hub_pair($uid,$ids[0])):null;
  if($pair){$existing=hub_q($p,'SELECT id FROM eqx_rooms WHERE pair_key=?',[$pair])->fetchColumn();if($existing)reply(200,['ok'=>true,'room'=>(int)$existing]);}
  $p->beginTransaction();try{hub_q($p,'INSERT INTO eqx_rooms(owner_id,title,kind,pair_key,created_at) VALUES (?,?,?,?,?)',[$uid,hub_text($in,'title',160),$kind,$pair,time()]);$room=(int)$p->lastInsertId();foreach($ids as $id)hub_q($p,'INSERT INTO eqx_room_members(room_id,user_id) VALUES (?,?)',[$room,$id]);$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}reply(200,['ok'=>true,'room'=>$room]);
 }
 if($op==='room_member'){
  $room=hub_member($p,$uid,hub_int($in,'room'));if((int)$room['owner_id']!==$uid||$room['kind']==='direct')fail(403,'Chỉ chủ nhóm chat được đổi thành viên.');$id=hub_int($in,'id');if($id===$uid)fail(422,'Không thể tự xóa chủ nhóm.');
  if(!empty($in['remove']))hub_q($p,'DELETE FROM eqx_room_members WHERE room_id=? AND user_id=?',[$room['id'],$id]);else{hub_target($p,$uid,$id);if(!eqd_can_message($p,$uid,$id))fail(403,'Người này chỉ nhận tin nhắn theo cài đặt riêng tư của họ.');if((int)hub_q($p,'SELECT COUNT(*) FROM eqx_room_members WHERE room_id=?',[$room['id']])->fetchColumn()>=21)fail(422,'Nhóm tối đa 21 thành viên.');try{hub_q($p,'INSERT INTO eqx_room_members(room_id,user_id) VALUES (?,?)',[$room['id'],$id]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}}reply(200,['ok'=>true]);
 }
 if($op==='messages'){
  $room=hub_member($p,$uid,hub_int($in,'room'));$query=hub_text($in,'query',100,true);$before=(int)($in['before']??PHP_INT_MAX);
  $rows=hub_q($p,'SELECT x.*,u.display_name,f.mime AS file_mime FROM eqx_messages x JOIN eq_users u ON u.id=x.user_id LEFT JOIN eqx_files f ON f.id=x.file_id AND f.deleted=0 WHERE x.room_id=? AND x.id<? AND x.body LIKE ? ORDER BY x.id DESC LIMIT 60',[$room['id'],$before,'%'.$query.'%'])->fetchAll();
  // Reading older pages or search results must not mark unseen recent messages as read.
  if($query===''&&!isset($in['before'])&&$rows){$max=(int)$rows[0]['id'];hub_q($p,'UPDATE eqx_room_members SET delivered_id=CASE WHEN delivered_id<? THEN ? ELSE delivered_id END,read_id=CASE WHEN read_id<? THEN ? ELSE read_id END WHERE room_id=? AND user_id=?',[$max,$max,$max,$max,$room['id'],$uid]);}
  $gifts=[];try{if($rows){$ids=array_column($rows,'id');$gifts=hub_q($p,'SELECT message_id,gift_key,note FROM eqs_gifts WHERE room_id=? AND message_id IN ('.implode(',',array_fill(0,count($ids),'?')).')',array_merge([$room['id']],$ids))->fetchAll();}}catch(PDOException $e){if((string)$e->getCode()!=='42S02'&&!((string)$e->getCode()==='HY000'&&str_contains(strtolower($e->getMessage()),'no such table')))throw $e;}
  $giftMap=[];foreach($gifts as $g)$giftMap[(int)$g['message_id']]=$g;foreach($rows as &$item){if(!(int)$item['recalled']&&isset($giftMap[(int)$item['id']]))$item['gift']=$giftMap[(int)$item['id']];}unset($item);
  foreach($rows as &$r)if((int)$r['recalled']){$r['body']='Tin nhắn đã thu hồi';$r['file_id']=null;}unset($r);
  reply(200,['ok'=>true,'messages'=>array_reverse($rows),'members'=>hub_q($p,'SELECT m.user_id,m.read_id,m.delivered_id,u.display_name FROM eqx_room_members m JOIN eq_users u ON u.id=m.user_id WHERE m.room_id=?',[$room['id']])->fetchAll(),'next_before'=>$rows?(int)end($rows)['id']:null]);
 }
 if($op==='message_send'){
  $room=hub_member($p,$uid,hub_int($in,'room'));foreach(hub_q($p,'SELECT user_id FROM eqx_room_members WHERE room_id=?',[$room['id']])->fetchAll() as $member)if((int)$member['user_id']!==$uid&&!eqd_can_message($p,$uid,(int)$member['user_id']))fail(403,'Thành viên đã hạn chế nhận tin nhắn.');limit_attempts($p,'send:'.$uid,40,60);$key=hub_text($in,'key',64);if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key))fail(422,'Mã tin nhắn chưa hợp lệ.');$body=hub_text($in,'body',4000,true);$file=$in['file_id']??null;if($body===''&&$file===null)fail(422,'Tin nhắn trống.');if($file!==null){if(!is_int($file))fail(422,'Tệp chưa hợp lệ.');hub_owned_file($p,$uid,$file);}
  $reply=$in['reply_id']??null;if($reply!==null&&(!is_int($reply)||!hub_q($p,'SELECT id FROM eqx_messages WHERE id=? AND room_id=?',[$reply,$room['id']])->fetchColumn()))fail(422,'Tin trả lời chưa hợp lệ.');
  try{hub_q($p,'INSERT INTO eqx_messages(room_id,user_id,body,file_id,reply_id,client_key,created_at) VALUES (?,?,?,?,?,?,?)',[$room['id'],$uid,$body,$file,$reply,$key,time()]);$id=(int)$p->lastInsertId();foreach(hub_q($p,'SELECT user_id FROM eqx_room_members WHERE room_id=?',[$room['id']])->fetchAll() as $m)hub_notify($p,(int)$m['user_id'],$uid,'Bạn có tin nhắn mới.','room',(int)$room['id']);}
  catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;$old=hub_q($p,'SELECT id,room_id FROM eqx_messages WHERE user_id=? AND client_key=?',[$uid,$key])->fetch();if(!$old||(int)$old['room_id']!==(int)$room['id'])fail(409,'Mã gửi đã được sử dụng.');$id=(int)$old['id'];}reply(200,['ok'=>true,'id'=>$id]);
 }
 if($op==='message_edit'||$op==='message_recall'){
  $id=hub_int($in,'id');$m=hub_q($p,'SELECT * FROM eqx_messages WHERE id=? AND user_id=?',[$id,$uid])->fetch();if(!$m)fail(404,'Không tìm thấy tin của bạn.');hub_member($p,$uid,(int)$m['room_id']);
  if($op==='message_recall')hub_q($p,"UPDATE eqx_messages SET recalled=1,body='',file_id=NULL WHERE id=?",[$id]);
  else{if((int)$m['recalled']||time()-(int)$m['created_at']>900)fail(422,'Chỉ sửa được tin trong 15 phút.');hub_q($p,'UPDATE eqx_messages SET body=?,edited=1 WHERE id=?',[hub_text($in,'body',4000),$id]);}reply(200,['ok'=>true]);
 }
 fail(404,'Không tìm thấy chức năng Messenger.');
}

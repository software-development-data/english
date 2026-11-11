<?php
declare(strict_types=1);
function eq_gift_catalog(): array {return [
 'star'=>['name'=>'Ngôi sao may mắn','message'=>'You can do it!'],
 'flower'=>['name'=>'Bông hoa cảm ơn','message'=>'Thank you for helping me!'],
 'coffee'=>['name'=>'Cà phê học cùng','message'=>'Let’s study together!'],
 'trophy'=>['name'=>'Chiếc cúp nỗ lực','message'=>'I am proud of your progress!'],
 'rainbow'=>['name'=>'Cầu vồng hy vọng','message'=>'Every day is a new beginning!'],
 'heart'=>['name'=>'Trái tim động viên','message'=>'Keep going, my friend!']];}
function eq_gifts(PDO $p,int $uid,string $op,array $in): never {
 try{$p->query('SELECT id FROM eqs_gifts LIMIT 1');}catch(PDOException $e){fail(503,'Quản trị viên cần chạy migrate-community.php để mở tính năng tặng quà.');}
 $catalog=eq_gift_catalog();$today=strtotime('today');
 if($op==='gift_catalog')reply(200,['ok'=>true,'gifts'=>$catalog,'daily_limit'=>20,'sent_today'=>(int)hub_q($p,'SELECT COUNT(*) FROM eqs_gifts WHERE sender_id=? AND created_at>=?',[$uid,$today])->fetchColumn()]);
 if($op==='gift_history'){$before=(int)($in['before']??PHP_INT_MAX);$rows=hub_q($p,'SELECT g.*,s.display_name AS sender_name,r.display_name AS recipient_name FROM eqs_gifts g JOIN eq_users s ON s.id=g.sender_id JOIN eq_users r ON r.id=g.recipient_id WHERE (g.sender_id=? OR g.recipient_id=?) AND g.id<? ORDER BY g.id DESC LIMIT 40',[$uid,$uid,$before])->fetchAll();$out=array_values(array_filter($rows,fn($r)=>!hub_blocked($p,$uid,(int)$r['sender_id'])&&!hub_blocked($p,$uid,(int)$r['recipient_id'])));reply(200,['ok'=>true,'gifts'=>$out,'next_before'=>$rows?(int)end($rows)['id']:null]);}
 if($op==='gift_send'){
  $room=hub_member($p,$uid,hub_int($in,'room'));if($room['kind']!=='direct')fail(422,'Chọn cuộc trò chuyện riêng với bạn học để tặng quà.');
  $to=(int)hub_q($p,'SELECT user_id FROM eqx_room_members WHERE room_id=? AND user_id<>?',[$room['id'],$uid])->fetchColumn();hub_target($p,$uid,$to);if(!hub_friend($p,$uid,$to)||!eqd_can_message($p,$uid,$to))fail(403,'Chỉ tặng quà cho bạn bè đang cho phép nhắn tin.');
  $gift=field($in,'gift');if(!isset($catalog[$gift]))fail(422,'Quà chưa hợp lệ.');$note=hub_text($in,'note',600,true);$key=hub_text($in,'key',64);if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key))fail(422,'Mã gửi chưa hợp lệ.');
  $p->beginTransaction();try{
   hub_q($p,'SELECT user_id FROM eqx_profiles WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid]);
   $old=hub_q($p,'SELECT * FROM eqs_gifts WHERE sender_id=? AND client_key=?',[$uid,$key])->fetch();if($old){$p->commit();if((int)$old['room_id']!==(int)$room['id']||$old['gift_key']!==$gift||$old['note']!==$note)fail(409,'Mã gửi đã dùng cho món quà khác.');reply(200,['ok'=>true,'id'=>(int)$old['id'],'message_id'=>(int)$old['message_id']]);}
   if((int)hub_q($p,'SELECT COUNT(*) FROM eqs_gifts WHERE sender_id=? AND created_at>=?',[$uid,$today])->fetchColumn()>=20){$p->rollBack();fail(429,'Bạn đã gửi 20 món quà hôm nay. Hãy quay lại vào ngày mai nhé!');}
   $now=time();$text='🎁 '.$catalog[$gift]['name'].($note!==''?' — '.$note:'');
   hub_q($p,'INSERT INTO eqx_messages(room_id,user_id,body,client_key,created_at) VALUES (?,?,?,?,?)',[$room['id'],$uid,$text,'gift-'.bin2hex(random_bytes(16)),$now]);$message=(int)$p->lastInsertId();
   hub_q($p,'INSERT INTO eqs_gifts(sender_id,recipient_id,room_id,message_id,gift_key,note,client_key,created_at) VALUES (?,?,?,?,?,?,?,?)',[$uid,$to,$room['id'],$message,$gift,$note,$key,$now]);$id=(int)$p->lastInsertId();hub_notify($p,$to,$uid,'Bạn nhận được '.$catalog[$gift]['name'].'.','room',(int)$room['id']);$p->commit();
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
  reply(200,['ok'=>true,'id'=>$id,'message_id'=>$message]);
 }
 fail(404,'Không tìm thấy tính năng quà tặng.');
}

<?php
declare(strict_types=1);
require_once __DIR__.'/wardrobe.php';
function eq5_checkin_day():string{return (new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');}
function eq5_checkin_view(PDO $p,int $uid):array{
 $today=eq5_checkin_day();$rows=hub_q($p,'SELECT day,streak,reward_coins,claimed_at FROM eq5_checkins WHERE user_id=? ORDER BY day DESC LIMIT 31',[$uid])->fetchAll();$last=$rows[0]??null;$claimedToday=$last&&$last['day']===$today;
 return ['today'=>$today,'claimed_today'=>$claimedToday,'streak'=>$claimedToday?(int)$last['streak']:(($last&&$last['day']===(new DateTimeImmutable($today,new DateTimeZone('Asia/Ho_Chi_Minh')))->modify('-1 day')->format('Y-m-d'))?(int)$last['streak']:0),'last_claim'=>$last,'calendar'=>array_reverse($rows),'milestones'=>[4,7,30]];
}
function eq5_checkin(PDO $p,int $uid,string $op,array $in):never{
 if(!in_array($op,['checkin_state','checkin_claim'],true))fail(404,'Không tìm thấy điểm danh.');
 require_once __DIR__.'/v5-schema.php';$mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';foreach(eq_v5_schema($mysql) as $sql)$p->exec($sql);
 if($op==='checkin_state')reply(200,['ok'=>true,'checkin'=>eq5_checkin_view($p,$uid)]);
 $p->beginTransaction();try{
  $today=eq5_checkin_day();$existing=hub_q($p,'SELECT day,streak,reward_coins,claimed_at FROM eq5_checkins WHERE user_id=? AND day=?'.($mysql?' FOR UPDATE':''),[$uid,$today])->fetch();
  if($existing){$p->commit();reply(200,['ok'=>true,'duplicate'=>true,'message'=>'Hôm nay bạn đã nhận điểm danh rồi.','checkin'=>eq5_checkin_view($p,$uid)]);}
  $last=hub_q($p,'SELECT day,streak FROM eq5_checkins WHERE user_id=? ORDER BY day DESC LIMIT 1'.($mysql?' FOR UPDATE':''),[$uid])->fetch();$yesterday=(new DateTimeImmutable($today,new DateTimeZone('Asia/Ho_Chi_Minh')))->modify('-1 day')->format('Y-m-d');$streak=$last&&$last['day']===$yesterday?(int)$last['streak']+1:1;$cycle=[10,15,20,40,50,60,120];$reward=$streak===30?300:$cycle[($streak-1)%count($cycle)];
  $pr=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetchColumn();eq5_wallet_sync($p,$uid,$pr?json_decode($pr,true):[]);$wallet=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetch();$meta=json_decode($wallet['state'],true)?:[];$meta['bonus_earned']=(int)($meta['bonus_earned']??0)+$reward;
  hub_q($p,'INSERT INTO eq5_checkins(user_id,day,streak,reward_coins,claimed_at) VALUES (?,?,?,?,?)',[$uid,$today,$streak,$reward,time()]);hub_q($p,'UPDATE eq5_wallet SET earned=earned+?,state=? WHERE user_id=?',[$reward,json_encode($meta,JSON_UNESCAPED_UNICODE),$uid]);$p->commit();reply(200,['ok'=>true,'message'=>"Điểm danh ngày $streak thành công · +$reward xu.",'checkin'=>eq5_checkin_view($p,$uid),'reward_coins'=>$reward]);
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

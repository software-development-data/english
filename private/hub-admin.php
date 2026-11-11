<?php
declare(strict_types=1);
function hub_prices(array $c): array {return [];}
function hub_admin(PDO $p,bool $mysql,int $uid,array $profile,array $config,string $op,array $in): never {
 if($op==='plans')reply(200,['ok'=>true,'plan'=>hub_plan($p,$uid),'prices'=>(object)[],'manual_payment_enabled'=>false,'payments_enabled'=>false,'instructions'=>'English Quest miễn phí cho mọi tài khoản.']);
 if($op==='orders')reply(200,['ok'=>true,'orders'=>[],'payments_enabled'=>false]);
 if($op==='order_create')fail(410,'Website miễn phí. Tính năng thanh toán đã được tắt.');
 if(!in_array($profile['role'],['admin','moderator'],true))fail(403,'Bạn không có quyền quản trị.');
 if($op==='admin'){
  $out=['ok'=>true,'reports'=>hub_q($p,"SELECT * FROM eqx_reports WHERE status='open' ORDER BY id DESC LIMIT 60")->fetchAll()];
  if($profile['role']==='admin'){$out['users']=hub_q($p,'SELECT u.id,u.display_name,r.username,r.role,r.suspended FROM eq_users u LEFT JOIN eqx_profiles r ON r.user_id=u.id ORDER BY u.id DESC LIMIT 100')->fetchAll();$out['audit']=hub_q($p,'SELECT * FROM eqx_audit ORDER BY id DESC LIMIT 50')->fetchAll();$out['counts']=['users'=>(int)hub_q($p,'SELECT COUNT(*) FROM eq_users')->fetchColumn(),'posts'=>(int)hub_q($p,'SELECT COUNT(*) FROM eqx_posts WHERE deleted=0')->fetchColumn(),'messages'=>(int)hub_q($p,'SELECT COUNT(*) FROM eqx_messages')->fetchColumn()];}reply(200,$out);
 }
 $act=field($in,'action');$id=hub_int($in,'id');
 if($act==='hide_post'){hub_q($p,'UPDATE eqx_posts SET deleted=1 WHERE id=?',[$id]);hub_audit($p,$uid,$act,$id);reply(200,['ok'=>true]);}
 if($act==='hide_comment'){hub_q($p,'UPDATE eqx_comments SET deleted=1 WHERE id=?',[$id]);hub_audit($p,$uid,$act,$id);reply(200,['ok'=>true]);}
 if($act==='resolve_report'){hub_q($p,"UPDATE eqx_reports SET status='resolved' WHERE id=?",[$id]);hub_audit($p,$uid,$act,$id);reply(200,['ok'=>true]);}
 if($profile['role']!=='admin')fail(403,'Chỉ admin được thực hiện.');
 if($act==='suspend'||$act==='role'){
  if($id===$uid)fail(422,'Không thay quyền hoặc khóa chính mình.');if(!hub_q($p,'SELECT id FROM eq_users WHERE id=?',[$id])->fetchColumn())fail(404,'Tài khoản chưa tồn tại.');hub_profile($p,$id);
  if($act==='suspend'){hub_q($p,'UPDATE eqx_profiles SET suspended=? WHERE user_id=?',[!empty($in['enabled'])?1:0,$id]);hub_q($p,'UPDATE eq_users SET auth_version=auth_version+1 WHERE id=?',[$id]);}
  else{$role=field($in,'role');if(!in_array($role,['user','moderator','admin'],true))fail(422,'Vai trò chưa hợp lệ.');hub_q($p,'UPDATE eqx_profiles SET role=? WHERE user_id=?',[$role,$id]);}
  hub_audit($p,$uid,$act,$id);reply(200,['ok'=>true]);
 }
 if($act==='confirm_order')fail(410,'Thanh toán và kích hoạt gói trả phí đã được tắt.');
 fail(422,'Hành động quản trị chưa hỗ trợ.');
}

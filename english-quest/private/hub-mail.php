<?php
declare(strict_types=1);
require_once __DIR__.'/auth-workflow.php';
function hub_mail(PDO $p,array $config,array $user,string $op,array $in): never {
 if($op==='email_confirm'){eq_verify_token($p,field($in,'token'));reply(200,['ok'=>true,'message'=>'Email đã được xác minh.']);}
 limit_attempts($p,'signed-verify:'.$user['id'],3,3600);
 if(!eq_mail_ready($config))fail(503,'SMTP chưa cấu hình. Chưa gửi được email xác minh.');
 if(!eq_deliver_token($p,$config,$user,'verify'))fail(503,'Chưa gửi được email xác minh. Hãy thử lại sau.');
 reply(200,['ok'=>true,'message'=>'Đã gửi liên kết xác minh có hiệu lực một giờ.']);
}

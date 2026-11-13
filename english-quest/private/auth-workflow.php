<?php
declare(strict_types=1);
require_once __DIR__.'/hub-common.php';
function eq_auth_error(int $status,string $code,string $message,array $extra=[]): never {reply($status,['ok'=>false,'code'=>$code,'message'=>$message]+$extra);}
function eq_auth_state(PDO $p,int $uid): ?array {return hub_q($p,'SELECT * FROM eq_auth_accounts WHERE user_id=?',[$uid])->fetch()?:null;}
function eq_auth_access(PDO $p,array $u): bool {$r=eq_auth_state($p,(int)$u['id']);return $r&&$r['status']==='active'&&((int)$r['legacy_access']===1||($r['verified_email']===$u['email']&&$r['email_verified_at']!==null));}
function eq_auth_log(PDO $p,string $event,?int $uid=null,string $email=''): void {hub_q($p,'INSERT INTO eq_auth_security(user_id,event,ip_hash,email_hash,created_at) VALUES (?,?,?,?,?)',[$uid,$event,hash('sha256',$_SERVER['REMOTE_ADDR']??'cli'),$email!==''?hash('sha256',$email):null,time()]);}
function eq_captcha(): array {
 if(!isset($_SESSION['eq_captcha'])||($_SESSION['eq_captcha']['expires']??0)<=time()){
  $a=random_int(2,9);$b=random_int(2,9);$id=bin2hex(random_bytes(16));$_SESSION['eq_captcha']=['id'=>$id,'answer_hash'=>hash('sha256',$id.':'.($a+$b)),'prompt'=>"$a + $b = ?",'issued'=>microtime(true),'expires'=>time()+600];
 }
 $c=$_SESSION['eq_captcha'];return ['id'=>$c['id'],'prompt'=>$c['prompt'],'expires_at'=>$c['expires']];
}
function eq_check_captcha(PDO $p,array $in): void {
 $hash=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$bad=(int)hub_q($p,"SELECT COUNT(*) FROM eq_auth_security WHERE ip_hash=? AND event IN ('bot_honeypot','captcha_failed','register_too_fast') AND created_at>?",[$hash,time()-300])->fetchColumn();
 if($bad>=5)eq_auth_error(429,'BOT_COOLDOWN','Có nhiều yêu cầu đăng ký bất thường. Vui lòng chờ vài phút rồi thử lại.');
 if(field($in,'website')!==''){eq_auth_log($p,'bot_honeypot');eq_auth_error(422,'BOT_CHECK','Không xác nhận được yêu cầu đăng ký.');}
 $c=$_SESSION['eq_captcha']??null;unset($_SESSION['eq_captcha']);
 if(!$c||$c['expires']<=time()||!hash_equals($c['id'],field($in,'captcha_id'))||!hash_equals($c['answer_hash'],hash('sha256',$c['id'].':'.trim(field($in,'captcha_answer'))))){eq_auth_log($p,'captcha_failed');eq_auth_error(422,'CAPTCHA_INVALID','Câu xác nhận chưa đúng hoặc đã hết hạn. Hãy lấy câu mới.');}
 if(microtime(true)-$c['issued']<2){eq_auth_log($p,'register_too_fast');eq_auth_error(422,'FORM_TOO_FAST','Vui lòng đọc và điền biểu mẫu trước khi gửi.');}
}
function eq_mail_config(array $config): array {
 $m=$config['mail']??[];$map=['host'=>'SMTP_HOST','port'=>'SMTP_PORT','username'=>'SMTP_USERNAME','password'=>'SMTP_PASSWORD','encryption'=>'SMTP_ENCRYPTION'];
 foreach($map as $key=>$env){$v=getenv($env);if($v!==false)$m[$key]=$v;}
 $m+=['host'=>'smtp.hostinger.com','port'=>465,'username'=>'contact@nearlink.pro','password'=>'','encryption'=>'ssl'];
 $m['from_email']=getenv('SMTP_FROM_EMAIL')?:'contact@nearlink.pro';$m['from_name']=getenv('SMTP_FROM_NAME')?:'English Quest Pro';$m['port']=(int)$m['port'];return $m;
}
function eq_mail_ready(array $config): bool {$m=eq_mail_config($config);$local=in_array($m['host'],['127.0.0.1','localhost','::1'],true);return $m['host']!==''&&$m['port']>0&&$m['port']<65536&&filter_var($m['from_email'],FILTER_VALIDATE_EMAIL)&&($local||($m['username']!==''&&$m['password']!==''&&!str_starts_with($m['password'],'THAY_')&&in_array($m['encryption'],['ssl','tls'],true)));}
function eq_send_email(array $config,string $email,string $token,string $purpose): void {
 foreach(['Exception','SMTP','PHPMailer'] as $file)require_once __DIR__.'/vendor/PHPMailer/src/'.$file.'.php';$m=eq_mail_config($config);
 if(!eq_mail_ready($config))throw new RuntimeException('SMTP_NOT_READY');
 $mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$m['host'];$mail->Port=$m['port'];$mail->SMTPSecure=$m['encryption'];$mail->SMTPAuth=$m['username']!=='';$mail->Username=$m['username'];$mail->Password=$m['password'];$mail->Timeout=12;$mail->CharSet='UTF-8';$mail->setFrom($m['from_email'],$m['from_name']);$mail->addAddress($email);
 $change=$purpose==='email_change';$verify=$purpose==='verify'||$change;$link=rtrim($config['app_url'],'/').'/#'.($change?'email_change':($verify?'verify':'reset')).'='.$token;
 $mail->Subject=$change?'Xác minh email mới English Quest Pro':($verify?'Xác minh email English Quest Pro':'Đặt lại mật khẩu English Quest Pro');
 $mail->Body="Xin chào,\n\n".($change?'Mở liên kết để xác nhận đổi email. Email cũ vẫn hoạt động cho đến khi bạn xác nhận:':($verify?'Mở liên kết để xác minh email và kích hoạt tài khoản:':'Mở liên kết để đặt mật khẩu mới:'))."\n\n".$link."\n\nLiên kết có hiệu lực ".($verify?'1 giờ':'15 phút')." và chỉ dùng một lần.\nNếu không phải bạn yêu cầu, hãy bỏ qua email này.\n\nEnglish Quest Pro\ncontact@nearlink.pro";$mail->send();
}
function eq_issue_token(PDO $p,array $user,string $purpose): string {
 $token=bin2hex(random_bytes(32));$p->beginTransaction();try{
  hub_q($p,'SELECT id FROM eq_users WHERE id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$user['id']]);
  hub_q($p,'UPDATE eq_auth_tokens SET expires_at=? WHERE user_id=? AND purpose=? AND used_at IS NULL AND expires_at>?',[time(),$user['id'],$purpose,time()]);
  hub_q($p,'INSERT INTO eq_auth_tokens(token_hash,user_id,email,purpose,expires_at,created_at) VALUES (?,?,?,?,?,?)',[hash('sha256',$token),$user['id'],$user['email'],$purpose,time()+($purpose==='verify'?3600:900),time()]);$p->commit();
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}return $token;
}
function eq_deliver_token(PDO $p,array $config,array $user,string $purpose): bool {
 $token=eq_issue_token($p,$user,$purpose);try{eq_send_email($config,$user['email'],$token,$purpose);eq_auth_log($p,$purpose.'_sent',(int)$user['id'],$user['email']);return true;}
 catch(Throwable $e){hub_q($p,'UPDATE eq_auth_tokens SET expires_at=? WHERE token_hash=?',[time(),hash('sha256',$token)]);eq_auth_log($p,$purpose.'_mail_failed',(int)$user['id'],$user['email']);error_log('ENGLISH QUEST: SMTP delivery failed; verify credentials and provider settings.');return false;}
}
function eq_verify_token(PDO $p,string $token): array {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token))eq_auth_error(422,'TOKEN_INVALID','Liên kết xác minh không hợp lệ, hết hạn hoặc đã dùng.');
 $p->beginTransaction();try{
  $r=hub_q($p,"SELECT * FROM eq_auth_tokens WHERE token_hash=? AND purpose='verify'".($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[hash('sha256',$token)])->fetch();
  if(!$r||$r['used_at']!==null||(int)$r['expires_at']<=time()){$p->rollBack();eq_auth_error(422,'TOKEN_INVALID','Liên kết xác minh không hợp lệ, hết hạn hoặc đã dùng.');}
  $u=hub_q($p,'SELECT * FROM eq_users WHERE id=?',[$r['user_id']])->fetch();if(!$u||$u['email']!==$r['email']){$p->rollBack();eq_auth_error(422,'TOKEN_INVALID','Email đã thay đổi. Hãy yêu cầu liên kết mới.');}
  hub_q($p,"UPDATE eq_auth_accounts SET status='active',verified_email=?,email_verified_at=?,legacy_access=0 WHERE user_id=?",[$u['email'],time(),$u['id']]);
  hub_q($p,"UPDATE eq_auth_tokens SET used_at=? WHERE user_id=? AND purpose='verify' AND used_at IS NULL",[time(),$u['id']]);hub_profile($p,(int)$u['id']);hub_q($p,'UPDATE eqx_profiles SET verified_email=? WHERE user_id=?',[$u['email'],$u['id']]);eq_auth_log($p,'email_verified',(int)$u['id'],$u['email']);$p->commit();return $u;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
function eq_auth_handle(PDO $p,array $config,string $action,array $in): never {
 $ip=$_SERVER['REMOTE_ADDR']??'unknown';
 if($action==='register'){
  limit_attempts($p,'registration-speed:'.$ip,30,300);$email=email_value(field($in,'email'));$password=password_value(field($in,'password'));$name=trim(field($in,'display_name'));
  if(preg_match_all('/./us',$name)<1||preg_match_all('/./us',$name)>60||preg_match('/[\x00-\x1f\x7f]/u',$name))eq_auth_error(422,'NAME_INVALID','Tên hiển thị cần từ 1 đến 60 ký tự.');
  eq_check_captcha($p,$in);
  if(!eq_mail_ready($config))eq_auth_error(503,'SMTP_NOT_READY','Email hệ thống chưa được cấu hình. Quản trị viên cần cấu hình SMTP để mở đăng ký xác minh email.');
  $p->beginTransaction();try{
   hub_q($p,'INSERT INTO eq_users(email,password_hash,display_name) VALUES (?,?,?)',[$email,password_hash($password,PASSWORD_DEFAULT),$name]);$uid=(int)$p->lastInsertId();
   hub_q($p,"INSERT INTO eq_auth_accounts(user_id,status,legacy_access,created_at) VALUES (?,'pending',0,?)",[$uid,time()]);eq_auth_log($p,'registered_pending',$uid,$email);$p->commit();
  }catch(PDOException $e){if($p->inTransaction())$p->rollBack();if((string)$e->getCode()==='23000')eq_auth_error(409,'EMAIL_EXISTS','Email này đã được đăng ký. Hãy đăng nhập hoặc gửi lại email xác minh.');throw $e;}
  $_SESSION=[];session_regenerate_id(true);$csrf=csrf();$user=['id'=>$uid,'email'=>$email];
  if(!eq_deliver_token($p,$config,$user,'verify'))eq_auth_error(503,'EMAIL_SEND_FAILED','Tài khoản đã được lưu ở trạng thái chờ xác minh nhưng chưa gửi được email. Hãy thử gửi lại sau.',['pending_verification'=>true,'email'=>$email,'csrf'=>$csrf,'user'=>null]);
  reply(200,['ok'=>true,'pending_verification'=>true,'email'=>$email,'user'=>null,'csrf'=>$csrf,'message'=>'Đã tạo tài khoản chờ xác minh. Hãy mở email để kích hoạt trước khi đăng nhập.']);
 }
 if($action==='verify'){eq_verify_token($p,field($in,'token'));reply(200,['ok'=>true,'message'=>'Email đã được xác minh. Bạn có thể đăng nhập.','csrf'=>csrf()]);}
 if($action==='resend_verify'||$action==='forgot'){
  $email=email_value(field($in,'email'));$purpose=$action==='forgot'?'reset':'verify';limit_attempts($p,$purpose.'-mail-ip:'.$ip,10,900);limit_attempts($p,$purpose.'-mail-email:'.$email,3,3600);
  if(!eq_mail_ready($config))eq_auth_error(503,'SMTP_NOT_READY','Email hệ thống chưa sẵn sàng. Vui lòng thử lại sau.');
  $u=hub_q($p,'SELECT * FROM eq_users WHERE email=?',[$email])->fetch();
  if($u){$state=eq_auth_state($p,(int)$u['id']);if($purpose==='reset'||!$state||$state['verified_email']!==$email||$state['email_verified_at']===null)eq_deliver_token($p,$config,$u,$purpose);}
  reply(200,['ok'=>true,'message'=>$purpose==='reset'?'Nếu email này có tài khoản, bạn sẽ nhận được liên kết đặt lại mật khẩu trong hộp thư hoặc thư rác.':'Nếu email này cần xác minh, bạn sẽ nhận được liên kết kích hoạt. Hãy kiểm tra hộp thư và thư rác.','retry_after'=>60]);
 }
 if($action==='reset'){
  limit_attempts($p,'reset:'.$ip,15,900);$password=password_value(field($in,'password'));if(!hash_equals($password,field($in,'password_confirmation')))eq_auth_error(422,'PASSWORD_MISMATCH','Mật khẩu nhập lại chưa khớp.');$token=field($in,'token');
  if(!preg_match('/^[a-f0-9]{64}$/D',$token))eq_auth_error(422,'TOKEN_INVALID','Liên kết không hợp lệ, hết hạn hoặc đã dùng.');$p->beginTransaction();try{
   $t=hub_q($p,"SELECT * FROM eq_auth_tokens WHERE token_hash=? AND purpose='reset'".($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[hash('sha256',$token)])->fetch();
   if(!$t||$t['used_at']!==null||(int)$t['expires_at']<=time()){$p->rollBack();eq_auth_error(422,'TOKEN_INVALID','Liên kết không hợp lệ, hết hạn hoặc đã dùng.');}
   $u=hub_q($p,'SELECT * FROM eq_users WHERE id=?',[$t['user_id']])->fetch();if(!$u||$u['email']!==$t['email']){$p->rollBack();eq_auth_error(422,'TOKEN_INVALID','Liên kết không còn hợp lệ.');}
   hub_q($p,'UPDATE eq_users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]);
   hub_q($p,"UPDATE eq_auth_tokens SET used_at=? WHERE user_id=? AND purpose='reset' AND used_at IS NULL",[time(),$u['id']]);hub_q($p,'UPDATE eq_password_resets SET expires_at=? WHERE user_id=?',[time(),$u['id']]);hub_q($p,'UPDATE eqx_sessions SET revoked=1 WHERE user_id=?',[$u['id']]);eq_auth_log($p,'password_reset',(int)$u['id'],$u['email']);$p->commit();
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
  $_SESSION=[];session_regenerate_id(true);ok_session(null,'Mật khẩu đã được đặt lại. Bạn có thể đăng nhập bằng mật khẩu mới.');
 }
 fail(404,'Không tìm thấy chức năng tài khoản.');
}

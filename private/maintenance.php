<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
 $c=require(getenv('EQ_CONFIG_FILE')?:__DIR__.'/config.php');$d=$c['db'];$p=new PDO($d['dsn']??"mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",$d['user']??null,$d['password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$now=time();
 // Keep identity, progress, certificates and account content. Purge only expired ephemeral records.
 foreach([['DELETE FROM eqp_email_changes WHERE created_at<?',[$now-86400]],['DELETE FROM eqs_practice_sessions WHERE started_at<? AND id NOT IN(SELECT session_id FROM eqs_attempts)',[$now-86400]],['DELETE FROM eqd_locations WHERE expires_at<=?',[$now]],['DELETE FROM eq_auth_tokens WHERE expires_at<?',[$now-86400]],['DELETE FROM eq_auth_security WHERE created_at<?',[$now-30*86400]],['DELETE FROM eq_rate_limits WHERE expires_at<?',[$now-86400]],['DELETE FROM eqx_email_tokens WHERE expires_at<?',[$now]],['DELETE FROM eq_password_resets WHERE expires_at<?',[$now]],['UPDATE eqx_profiles SET lat=NULL,lng=NULL,location_enabled=0',[]]] as [$sql,$args]){$q=$p->prepare($sql);$q->execute($args);}
 echo "Expired location/token data removed; learning progress retained.\n";
}catch(Throwable $e){fwrite(STDERR,'Maintenance failed: '.get_class($e).' code '.$e->getCode().". No credentials printed.\n");exit(1);}

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/hub-schema.php';
$config=require(getenv('EQ_CONFIG_FILE')?:__DIR__.'/config.php');$d=$config['db'];
try{
 $pdo=new PDO($d['dsn']??"mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",$d['user']??null,$d['password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
 $pdo->query('SELECT id FROM eq_users LIMIT 1');
 foreach(hub_schema($mysql) as $sql){try{$pdo->exec($sql);}catch(PDOException $e){if(!str_starts_with($sql,'CREATE INDEX')||!in_array((string)$e->getCode(),['42000','HY000'],true)||!str_contains(strtolower($e->getMessage()),'already exists')&&!str_contains(strtolower($e->getMessage()),'duplicate key name'))throw $e;}}
 $q=$pdo->prepare('SELECT version FROM eqx_migrations WHERE version=?');$q->execute(['v4-community-assistant']);if(!$q->fetchColumn())$pdo->prepare('INSERT INTO eqx_migrations(version,applied_at) VALUES (?,?)')->execute(['v4-community-assistant',time()]);
 echo "V4 additive migration OK. Existing accounts and progress preserved.\n";
 if(($argv[1]??'')==='--admin'){
  $email=$argv[2]??'';$q=$pdo->prepare('SELECT id FROM eq_users WHERE email=?');$q->execute([$email]);$id=$q->fetchColumn();if(!$id)throw new RuntimeException('Account not found; register first');
  require_once __DIR__.'/hub-common.php';hub_profile($pdo,(int)$id);$pdo->prepare("UPDATE eqx_profiles SET role='admin' WHERE user_id=?")->execute([$id]);
  $pdo->prepare('INSERT INTO eqx_audit(actor_id,action,target_id,detail,created_at) VALUES (?,?,?,?,?)')->execute([$id,'cli_admin_grant',$id,'Local CLI owner grant',time()]);echo "Admin role granted to existing account.\n";
 }
}catch(Throwable $e){fwrite(STDERR,'Migration/admin setup failed: '.get_class($e).' code '.$e->getCode().". Check DB, migration permissions, and registered email.\n");exit(1);}

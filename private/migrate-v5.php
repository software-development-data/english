<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/v5-schema.php';
try{$c=require(getenv('EQ_CONFIG_FILE')?:__DIR__.'/config.php');$d=$c['db'];$p=new PDO($d['dsn']??"mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",$d['user']??null,$d['password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->query('SELECT id FROM eq_users LIMIT 1');foreach(eq_v5_schema($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') as $sql)$p->exec($sql);echo "V5 installed. Existing accounts and progress preserved.\n";}catch(Throwable $e){fwrite(STDERR,"V5 migration failed. Check configuration and database permissions.\n");exit(1);}

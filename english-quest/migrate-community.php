<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/community-schema.php';
try{$c=require(getenv('EQ_CONFIG_FILE')?:__DIR__.'/config.php');$d=$c['db'];$p=new PDO($d['dsn']??"mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",$d['user']??null,$d['password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->query('SELECT id FROM eqx_messages LIMIT 1');foreach(eq_community_schema($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') as $sql){try{$p->exec($sql);}catch(PDOException $e){if(!str_starts_with($sql,'CREATE INDEX')||!str_contains(strtolower($e->getMessage()),'already exists')&&!str_contains(strtolower($e->getMessage()),'duplicate key name'))throw $e;}}echo "Community gifts installed. Existing accounts and progress preserved.\n";}catch(Throwable $e){fwrite(STDERR,"Community migration failed. Check configuration, previous migrations and database permissions.\n");exit(1);}

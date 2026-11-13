<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/upgrade-schema.php';require_once __DIR__.'/hub-common.php';
try{
 $c=require(getenv('EQ_CONFIG_FILE')?:__DIR__.'/config.php');$d=$c['db'];$p=new PDO($d['dsn']??"mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",$d['user']??null,$d['password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$p->query('SELECT id FROM eq_users LIMIT 1');
 foreach(eq_upgrade_schema($mysql) as $sql){try{$p->exec($sql);}catch(PDOException $e){if(!str_starts_with($sql,'CREATE INDEX')||!str_contains(strtolower($e->getMessage()),'already exists')&&!str_contains(strtolower($e->getMessage()),'duplicate key name'))throw $e;}}
 if($mysql){$levelSize=(int)hub_q($p,"SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='eqc_courses' AND column_name='level'")->fetchColumn();if($levelSize<16)$p->exec('ALTER TABLE eqc_courses MODIFY COLUMN level VARCHAR(16) NOT NULL');$hasLanguage=hub_q($p,"SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='eqc_courses' AND column_name='language_code'")->fetchColumn();if(!$hasLanguage)$p->exec("ALTER TABLE eqc_courses ADD COLUMN language_code VARCHAR(16) NOT NULL DEFAULT 'en-US'");}else{$columns=$p->query('PRAGMA table_info(eqc_courses)')->fetchAll(PDO::FETCH_ASSOC);if(!in_array('language_code',array_column($columns,'name'),true))$p->exec("ALTER TABLE eqc_courses ADD COLUMN language_code VARCHAR(16) NOT NULL DEFAULT 'en-US'");}
 $p->exec("UPDATE eqc_courses SET language_code='zh-CN' WHERE id LIKE 'chinese-%'");
 hub_q($p,"INSERT INTO eq_auth_accounts(user_id,status,legacy_access,created_at) SELECT u.id,'active',1,? FROM eq_users u WHERE NOT EXISTS(SELECT 1 FROM eq_auth_accounts a WHERE a.user_id=u.id)",[time()]);
 if(is_file(__DIR__.'/starter-course.json')){require_once __DIR__.'/course-store.php';foreach(['starter-course.json','chinese-starter-course.json'] as $courseFile){$file=__DIR__.'/'.$courseFile;if(is_file($file))eqc_import($p,json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR),true);}}
 if(is_file(__DIR__.'/expanded-curriculum.json')){require_once __DIR__.'/course-store.php';foreach(json_decode(file_get_contents(__DIR__.'/expanded-curriculum.json'),true,64,JSON_THROW_ON_ERROR) as $curriculumCourse)eqc_import($p,$curriculumCourse,true);}
 echo "Upgrade schema installed. Existing identities/progress preserved; new web registrations require verification.\n";
}catch(Throwable $e){fwrite(STDERR,'Upgrade failed: '.get_class($e).' code '.$e->getCode().". Check base migrations, DB permissions, and source files.\n");exit(1);}

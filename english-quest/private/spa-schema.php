<?php
declare(strict_types=1);
function eq_spa_schema(bool $mysql): array {
 $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$int=$mysql?'BIGINT UNSIGNED':'INTEGER';$text=$mysql?'LONGTEXT':'TEXT';
 $tables=[
 'eqp_email_changes'=>"user_id $int PRIMARY KEY,email VARCHAR(254) NOT NULL,old_email VARCHAR(254) NOT NULL,auth_version INTEGER NOT NULL,created_at $int NOT NULL",
 'eqs_practice_sessions'=>"id VARCHAR(40) PRIMARY KEY,user_id $int NOT NULL,task_key VARCHAR(100) NOT NULL,payload $text NOT NULL,started_at $int NOT NULL",
 'eqs_attempts'=>"id $id,user_id $int NOT NULL,session_id VARCHAR(40) NOT NULL UNIQUE,legacy_id $int NOT NULL,mode VARCHAR(16) NOT NULL,level VARCHAR(2) NOT NULL,topic VARCHAR(100) NOT NULL,content_match INTEGER NOT NULL,result_json $text NOT NULL,record_file_id $int NULL,record_consent INTEGER NOT NULL DEFAULT 0,created_at $int NOT NULL",
 'eqs_rewards'=>"user_id $int NOT NULL,day VARCHAR(10) NOT NULL,task_key VARCHAR(100) NOT NULL,xp INTEGER NOT NULL,PRIMARY KEY(user_id,day,task_key)",
 'eqs_conversations'=>"id VARCHAR(40) PRIMARY KEY,user_id $int NOT NULL,topic VARCHAR(60) NOT NULL,level VARCHAR(2) NOT NULL,created_at $int NOT NULL",
 'eqs_turns'=>"id $id,conversation_id VARCHAR(40) NOT NULL,client_key VARCHAR(64) NOT NULL,question TEXT NOT NULL,answer TEXT NULL,source VARCHAR(16) NOT NULL DEFAULT 'pending',status VARCHAR(16) NOT NULL DEFAULT 'pending',created_at $int NOT NULL,UNIQUE(conversation_id,client_key)",
 ];$sql=[];foreach($tables as $name=>$columns)$sql[]="CREATE TABLE IF NOT EXISTS $name ($columns)".($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'');
 foreach(['eqs_practice_user'=>'eqs_practice_sessions(user_id,started_at)','eqs_attempts_user'=>'eqs_attempts(user_id,id)','eqs_conversations_user'=>'eqs_conversations(user_id,created_at)','eqs_turns_conversation'=>'eqs_turns(conversation_id,id)'] as $name=>$spec)$sql[]="CREATE INDEX $name ON $spec";return $sql;
}

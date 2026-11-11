<?php
declare(strict_types=1);
function eq_upgrade_schema(bool $mysql): array {
 $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$int=$mysql?'BIGINT UNSIGNED':'INTEGER';$big=$mysql?'LONGTEXT':'TEXT';
 $defs=[
 'eq_auth_accounts'=>"user_id $int PRIMARY KEY,status VARCHAR(16) NOT NULL,verified_email VARCHAR(254) NULL,email_verified_at $int NULL,legacy_access INTEGER NOT NULL DEFAULT 0,created_at $int NOT NULL",
 'eq_auth_tokens'=>"token_hash VARCHAR(64) PRIMARY KEY,user_id $int NOT NULL,email VARCHAR(254) NOT NULL,purpose VARCHAR(12) NOT NULL,expires_at $int NOT NULL,used_at $int NULL,created_at $int NOT NULL",
 'eq_auth_security'=>"id $id,user_id $int NULL,event VARCHAR(40) NOT NULL,ip_hash VARCHAR(64) NOT NULL,email_hash VARCHAR(64) NULL,created_at $int NOT NULL",
 'eqd_settings'=>"user_id $int PRIMARY KEY,allow_email_search INTEGER NOT NULL DEFAULT 0,country VARCHAR(2) NOT NULL DEFAULT '',city VARCHAR(120) NOT NULL DEFAULT '',language VARCHAR(80) NOT NULL DEFAULT 'vi',timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',study_window VARCHAR(30) NOT NULL DEFAULT 'evening',interests VARCHAR(240) NOT NULL DEFAULT '',seeking INTEGER NOT NULL DEFAULT 1,show_region INTEGER NOT NULL DEFAULT 0,age_band VARCHAR(12) NOT NULL DEFAULT 'unspecified',message_policy VARCHAR(12) NOT NULL DEFAULT 'friends',location_enabled INTEGER NOT NULL DEFAULT 0,share_achievements INTEGER NOT NULL DEFAULT 1",
 'eqd_locations'=>"user_id $int PRIMARY KEY,lat DECIMAL(5,2) NOT NULL,lng DECIMAL(6,2) NOT NULL,updated_at $int NOT NULL,expires_at $int NOT NULL",
 'eqd_invitations'=>"id $id,sender_id $int NOT NULL,recipient_id $int NOT NULL,kind VARCHAR(16) NOT NULL,title VARCHAR(160) NOT NULL,notes TEXT NOT NULL,course_id VARCHAR(60) NULL,starts_at $int NULL,status VARCHAR(16) NOT NULL DEFAULT 'pending',room_id $int NULL,created_at $int NOT NULL,updated_at $int NOT NULL,client_key VARCHAR(64) NOT NULL,UNIQUE(sender_id,client_key)",
 'eqd_challenges'=>"invite_id $int PRIMARY KEY,game VARCHAR(16) NOT NULL,sender_run VARCHAR(40) NOT NULL,recipient_run VARCHAR(40) NOT NULL",
 'eqc_courses'=>"id VARCHAR(60) PRIMARY KEY,title VARCHAR(160) NOT NULL,description TEXT NOT NULL,level VARCHAR(16) NOT NULL,language_code VARCHAR(16) NOT NULL DEFAULT 'en-US',skill VARCHAR(30) NOT NULL,published INTEGER NOT NULL DEFAULT 0,position INTEGER NOT NULL DEFAULT 1,version INTEGER NOT NULL DEFAULT 1,final_json $big NOT NULL",
 'eqc_units'=>"id VARCHAR(60) PRIMARY KEY,course_id VARCHAR(60) NOT NULL,title VARCHAR(160) NOT NULL,position INTEGER NOT NULL,published INTEGER NOT NULL DEFAULT 1,exam_json $big NOT NULL",
 'eqc_lessons'=>"id VARCHAR(60) PRIMARY KEY,unit_id VARCHAR(60) NOT NULL,title VARCHAR(160) NOT NULL,position INTEGER NOT NULL,published INTEGER NOT NULL DEFAULT 1,content_json $big NOT NULL,exercises_json $big NOT NULL",
 'eqc_progress'=>"user_id $int NOT NULL,target_id VARCHAR(60) NOT NULL,kind VARCHAR(12) NOT NULL,score INTEGER NOT NULL DEFAULT 0,total INTEGER NOT NULL DEFAULT 0,passed INTEGER NOT NULL DEFAULT 0,completed_at $int NULL,PRIMARY KEY(user_id,target_id,kind)",
 'eqc_runs'=>"id VARCHAR(40) PRIMARY KEY,user_id $int NOT NULL,course_id VARCHAR(60) NOT NULL,target_id VARCHAR(60) NOT NULL,kind VARCHAR(12) NOT NULL,state $big NOT NULL,finished INTEGER NOT NULL DEFAULT 0,started_at $int NOT NULL,updated_at $int NOT NULL",
 'eqc_certificates'=>"id VARCHAR(40) PRIMARY KEY,user_id $int NOT NULL,course_id VARCHAR(60) NOT NULL,course_title VARCHAR(160) NOT NULL,display_name VARCHAR(60) NOT NULL,score INTEGER NOT NULL,issued_at $int NOT NULL,UNIQUE(user_id,course_id)",
 'eqc_media'=>"lesson_id VARCHAR(60) NOT NULL,file_id $int NOT NULL,PRIMARY KEY(lesson_id,file_id)",
 'eqc_reviews'=>"user_id $int NOT NULL,lesson_id VARCHAR(60) NOT NULL,word_index INTEGER NOT NULL,due_at $int NOT NULL,interval_days INTEGER NOT NULL DEFAULT 0,PRIMARY KEY(user_id,lesson_id,word_index)",
 ];
 $sql=[];foreach($defs as $name=>$cols)$sql[]="CREATE TABLE IF NOT EXISTS $name ($cols)".($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'');
 foreach(['eq_auth_tokens_user'=>'eq_auth_tokens(user_id,purpose,expires_at)','eq_auth_security_ip'=>'eq_auth_security(ip_hash,created_at)','eqd_location_expiry'=>'eqd_locations(expires_at,lat,lng)','eqd_invite_recipient'=>'eqd_invitations(recipient_id,status,id)','eqd_invite_sender'=>'eqd_invitations(sender_id,id)','eqc_lessons_unit'=>'eqc_lessons(unit_id,position)','eqc_units_course'=>'eqc_units(course_id,position)','eqc_runs_user'=>'eqc_runs(user_id,updated_at)','eqc_media_file'=>'eqc_media(file_id)'] as $name=>$spec)$sql[]="CREATE INDEX $name ON $spec";
 return $sql;
}

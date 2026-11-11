<?php
declare(strict_types=1);
function eq_v5_schema(bool $mysql): array {
 $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$int=$mysql?'BIGINT UNSIGNED':'INTEGER';$text=$mysql?'LONGTEXT':'TEXT';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
 return [
 "CREATE TABLE IF NOT EXISTS eq5_wallet(user_id $int PRIMARY KEY,earned $int NOT NULL DEFAULT 0,spent $int NOT NULL DEFAULT 0,state $text NOT NULL)$suffix",
 "CREATE TABLE IF NOT EXISTS eq5_pets(user_id $int PRIMARY KEY,state $text NOT NULL)$suffix",
 "CREATE TABLE IF NOT EXISTS eq5_farms(user_id $int PRIMARY KEY,state $text NOT NULL,updated_at $int NOT NULL)$suffix",
 "CREATE TABLE IF NOT EXISTS eq5_checkins(user_id $int NOT NULL,day DATE NOT NULL,streak SMALLINT UNSIGNED NOT NULL,reward_coins $int NOT NULL,claimed_at $int NOT NULL,PRIMARY KEY(user_id,day))$suffix",
 "CREATE TABLE IF NOT EXISTS eq5_events(user_id $int NOT NULL,event_key VARCHAR(64) NOT NULL,payload_hash VARCHAR(64) NOT NULL,result $text NOT NULL,created_at $int NOT NULL,PRIMARY KEY(user_id,event_key))$suffix",
 "CREATE TABLE IF NOT EXISTS eq5_live(id $id,owner_id $int NOT NULL,title VARCHAR(180) NOT NULL,room_key VARCHAR(80) NOT NULL UNIQUE,status VARCHAR(20) NOT NULL,created_at $int NOT NULL,seen_at $int NOT NULL)$suffix",
 "CREATE TABLE IF NOT EXISTS eq5_live_chat(id $id,room_id $int NOT NULL,user_id $int NOT NULL,body VARCHAR(600) NOT NULL,created_at $int NOT NULL)$suffix"
 ];
}

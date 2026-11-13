<?php
declare(strict_types=1);
// Additive schema used by the CLI migrator. Never imports NearLink seed accounts.
function hub_schema(bool $mysql): array {
 $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
 $int=$mysql?'BIGINT UNSIGNED':'INTEGER';$text=$mysql?'LONGTEXT':'TEXT';
 $tables=[
 'profiles'=>"user_id $int PRIMARY KEY, username VARCHAR(40) UNIQUE, bio TEXT NOT NULL, level VARCHAR(2) NOT NULL, goal VARCHAR(160) NOT NULL, minutes INTEGER NOT NULL DEFAULT 10, discover INTEGER NOT NULL DEFAULT 1, online_visible INTEGER NOT NULL DEFAULT 0, location_enabled INTEGER NOT NULL DEFAULT 0, lat DECIMAL(8,2) NULL, lng DECIMAL(8,2) NULL, avatar_id $int NULL, cover_id $int NULL, role VARCHAR(16) NOT NULL DEFAULT 'user', suspended INTEGER NOT NULL DEFAULT 0, verified_email VARCHAR(254) NULL, last_seen $int NOT NULL DEFAULT 0",
 'sessions'=>"token VARCHAR(64) PRIMARY KEY, user_id $int NOT NULL, created_at $int NOT NULL, seen_at $int NOT NULL, revoked INTEGER NOT NULL DEFAULT 0",
 'friends'=>"id $id, low_id $int NOT NULL, high_id $int NOT NULL, requester_id $int NOT NULL, status VARCHAR(16) NOT NULL, UNIQUE(low_id,high_id)",
 'blocks'=>"user_id $int NOT NULL, target_id $int NOT NULL, PRIMARY KEY(user_id,target_id)",
 'follows'=>"user_id $int NOT NULL, target_id $int NOT NULL, PRIMARY KEY(user_id,target_id)",
 'groups'=>"id $id, owner_id $int NOT NULL, title VARCHAR(160) NOT NULL, level VARCHAR(16) NOT NULL, description TEXT NOT NULL, created_at $int NOT NULL",
 'group_members'=>"group_id $int NOT NULL, user_id $int NOT NULL, PRIMARY KEY(group_id,user_id)",
 'posts'=>"id $id, user_id $int NOT NULL, body TEXT NOT NULL, visibility VARCHAR(16) NOT NULL, group_id $int NULL, file_id $int NULL, category VARCHAR(16) NOT NULL DEFAULT 'post', transcript TEXT NULL, deleted INTEGER NOT NULL DEFAULT 0, created_at $int NOT NULL",
 'comments'=>"id $id, post_id $int NOT NULL, user_id $int NOT NULL, parent_id $int NULL, body TEXT NOT NULL, deleted INTEGER NOT NULL DEFAULT 0, created_at $int NOT NULL",
 'reactions'=>"user_id $int NOT NULL, post_id $int NOT NULL, kind VARCHAR(8) NOT NULL, PRIMARY KEY(user_id,post_id,kind)",
 'rooms'=>"id $id, owner_id $int NOT NULL, title VARCHAR(160) NOT NULL, kind VARCHAR(16) NOT NULL, pair_key VARCHAR(60) NULL UNIQUE, created_at $int NOT NULL",
 'room_members'=>"room_id $int NOT NULL, user_id $int NOT NULL, read_id $int NOT NULL DEFAULT 0, delivered_id $int NOT NULL DEFAULT 0, PRIMARY KEY(room_id,user_id)",
 'messages'=>"id $id, room_id $int NOT NULL, user_id $int NOT NULL, body TEXT NOT NULL, file_id $int NULL, reply_id $int NULL, client_key VARCHAR(64) NOT NULL, recalled INTEGER NOT NULL DEFAULT 0, edited INTEGER NOT NULL DEFAULT 0, created_at $int NOT NULL, UNIQUE(user_id,client_key)",
 'notifications'=>"id $id, user_id $int NOT NULL, actor_id $int NOT NULL, body VARCHAR(255) NOT NULL, ref_type VARCHAR(16) NOT NULL, ref_id $int NOT NULL, seen INTEGER NOT NULL DEFAULT 0, created_at $int NOT NULL",
 'folders'=>"id $id, user_id $int NOT NULL, name VARCHAR(160) NOT NULL",
 'files'=>"id $id, user_id $int NOT NULL, folder_id $int NULL, name VARCHAR(180) NOT NULL, stored VARCHAR(100) NOT NULL, mime VARCHAR(80) NOT NULL, bytes $int NOT NULL, deleted INTEGER NOT NULL DEFAULT 0, created_at $int NOT NULL",
 'file_grants'=>"file_id $int NOT NULL, user_id $int NOT NULL, PRIMARY KEY(file_id,user_id)",
 'reports'=>"id $id, user_id $int NOT NULL, target_type VARCHAR(16) NOT NULL, target_id $int NOT NULL, reason TEXT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'open', created_at $int NOT NULL",
 'orders'=>"id $id, user_id $int NOT NULL, plan VARCHAR(8) NOT NULL, amount $int NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(16) NOT NULL, reference VARCHAR(160) NULL, created_at $int NOT NULL, paid_at $int NULL",
 'subscriptions'=>"user_id $int PRIMARY KEY, plan VARCHAR(8) NOT NULL, expires_at $int NOT NULL",
 'payment_receipts'=>"reference VARCHAR(160) PRIMARY KEY, order_id $int NOT NULL UNIQUE",
 'ai_usage'=>"id $id, user_id $int NOT NULL, task VARCHAR(24) NOT NULL, day VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, created_at $int NOT NULL",
 'support'=>"id $id, user_id $int NOT NULL, game VARCHAR(32) NOT NULL, run_id VARCHAR(40) NULL, question TEXT NOT NULL, answer TEXT NOT NULL, source VARCHAR(16) NOT NULL, created_at $int NOT NULL",
 'assistant_preferences'=>"user_id $int PRIMARY KEY, language VARCHAR(2) NOT NULL, mode VARCHAR(16) NOT NULL",
 'runs'=>"id VARCHAR(40) PRIMARY KEY, user_id $int NOT NULL, game VARCHAR(32) NOT NULL, state $text NOT NULL, started_at $int NOT NULL, updated_at $int NOT NULL, finished INTEGER NOT NULL DEFAULT 0",
 'rewards'=>"run_id VARCHAR(40) PRIMARY KEY, user_id $int NOT NULL, day VARCHAR(10) NOT NULL, xp INTEGER NOT NULL, coins INTEGER NOT NULL",
 'wallets'=>"user_id $int PRIMARY KEY, coins INTEGER NOT NULL DEFAULT 0",
 'speaking'=>"id $id, user_id $int NOT NULL, sample TEXT NOT NULL, transcript TEXT NOT NULL, created_at $int NOT NULL",
 'email_tokens'=>"token VARCHAR(64) PRIMARY KEY, user_id $int NOT NULL, email VARCHAR(254) NOT NULL, expires_at $int NOT NULL",
 'audit'=>"id $id, actor_id $int NOT NULL, action VARCHAR(40) NOT NULL, target_id $int NOT NULL, detail TEXT NOT NULL, created_at $int NOT NULL",
 'migrations'=>"version VARCHAR(30) PRIMARY KEY, applied_at $int NOT NULL",
 ];
 $sql=[];foreach($tables as $name=>$columns)$sql[]='CREATE TABLE IF NOT EXISTS eqx_'.$name.' ('.$columns.')'.($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'');
 foreach(['messages_room'=>'messages(room_id,id)','posts_created'=>'posts(created_at,id)','notify_user'=>'notifications(user_id,id)','ai_daily'=>'ai_usage(user_id,day)','sessions_user'=>'sessions(user_id)','reports_status'=>'reports(status,id)','files_owner'=>'files(user_id,deleted)','rewards_daily'=>'rewards(user_id,day)'] as $name=>$spec)$sql[]='CREATE INDEX eqx_'.$name.' ON eqx_'.$spec;
 return $sql;
}

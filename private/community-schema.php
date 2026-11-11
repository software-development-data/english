<?php
declare(strict_types=1);
function eq_community_schema(bool $mysql): array {
 $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$int=$mysql?'BIGINT UNSIGNED':'INTEGER';
 return ["CREATE TABLE IF NOT EXISTS eqs_gifts (id $id,sender_id $int NOT NULL,recipient_id $int NOT NULL,room_id $int NOT NULL,message_id $int NOT NULL,gift_key VARCHAR(30) NOT NULL,note VARCHAR(600) NOT NULL,client_key VARCHAR(64) NOT NULL,created_at $int NOT NULL,UNIQUE(sender_id,client_key))".($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''), 'CREATE INDEX idx_eqs_gifts_recipient ON eqs_gifts(recipient_id,created_at)','CREATE INDEX idx_eqs_gifts_message ON eqs_gifts(message_id)','CREATE INDEX idx_eqs_gifts_sender ON eqs_gifts(sender_id,created_at)'];
}

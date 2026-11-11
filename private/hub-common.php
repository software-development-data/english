<?php
declare(strict_types=1);
function hub_q(PDO $p,string $sql,array $args=[]): PDOStatement {$q=$p->prepare($sql);$q->execute($args);return $q;}
function hub_profile(PDO $p,int $id): array {
 $row=hub_q($p,'SELECT * FROM eqx_profiles WHERE user_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
 if(!$row){try{hub_q($p,'INSERT INTO eqx_profiles(user_id,username,bio,level,goal,minutes,discover) VALUES (?,?,?,?,?,?,?)',[$id,'learner'.$id,'','A1','Giao tiếp',10,1]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}$row=hub_q($p,'SELECT * FROM eqx_profiles WHERE user_id=?',[$id])->fetch(PDO::FETCH_ASSOC);}
 return $row;
}
function hub_guard(PDO $p,array $user): bool {
 try{
  $profile=hub_profile($p,(int)$user['id']);if((int)$profile['suspended'])return false;
  $token=hash('sha256',session_id());$row=hub_q($p,'SELECT * FROM eqx_sessions WHERE token=?',[$token])->fetch();
  if($row&&(int)$row['revoked'])return false;
  if(!$row)hub_q($p,'INSERT INTO eqx_sessions(token,user_id,created_at,seen_at) VALUES (?,?,?,?)',[$token,$user['id'],time(),time()]);
  elseif(time()-(int)$row['seen_at']>=60)hub_q($p,'UPDATE eqx_sessions SET seen_at=? WHERE token=?',[time(),$token]);
  if(time()-(int)$profile['last_seen']>=60)hub_q($p,'UPDATE eqx_profiles SET last_seen=? WHERE user_id=?',[time(),$user['id']]);
  return true;
 }catch(PDOException $e){if((string)$e->getCode()==='42S02'||(string)$e->getCode()==='HY000'&&str_contains(strtolower($e->getMessage()),'no such table'))return true;throw $e;}
}
function hub_int(array $in,string $key): int { $v=$in[$key]??0;if(!is_int($v)||$v<1)fail(422,'Mã dữ liệu chưa hợp lệ.');return $v; }
function hub_text(array $in,string $key,int $max=2000,bool $empty=false): string {$s=trim(field($in,$key));if((!$empty&&$s==='')||!preg_match('//u',$s)||strlen($s)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u',$s))fail(422,'Nội dung '.$key.' chưa hợp lệ.');return $s;}
function hub_pair(int $a,int $b): array {return [min($a,$b),max($a,$b)];}
function hub_blocked(PDO $p,int $a,int $b): bool {return (bool)hub_q($p,'SELECT 1 FROM eqx_blocks WHERE (user_id=? AND target_id=?) OR (user_id=? AND target_id=?)',[$a,$b,$b,$a])->fetchColumn();}
function hub_friend(PDO $p,int $a,int $b): bool {return (bool)hub_q($p,"SELECT 1 FROM eqx_friends WHERE low_id=? AND high_id=? AND status='accepted'",hub_pair($a,$b))->fetchColumn();}
function hub_target(PDO $p,int $me,int $target): void {if($me===$target||hub_blocked($p,$me,$target)||!hub_q($p,'SELECT id FROM eq_users WHERE id=?',[$target])->fetchColumn())fail(404,'Không tìm thấy người dùng.');}
function hub_notify(PDO $p,int $to,int $actor,string $body,string $type,int $id): void {if($to!==$actor&&!hub_blocked($p,$to,$actor))hub_q($p,'INSERT INTO eqx_notifications(user_id,actor_id,body,ref_type,ref_id,created_at) VALUES (?,?,?,?,?,?)',[$to,$actor,$body,$type,$id,time()]);}
function hub_public_profile(PDO $p,int $viewer,int $id): array {
 if(hub_blocked($p,$viewer,$id))fail(404,'Không tìm thấy hồ sơ.');
 $u=hub_q($p,'SELECT id,display_name FROM eq_users WHERE id=?',[$id])->fetch();if(!$u)fail(404,'Không tìm thấy hồ sơ.');$r=hub_profile($p,$id);
 if((int)$r['suspended'])fail(404,'Không tìm thấy hồ sơ.');
 $progress=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$id])->fetchColumn();$pr=$progress?json_decode($progress,true):[];
 return ['id'=>$id,'display_name'=>$u['display_name'],'username'=>$r['username'],'bio'=>$r['bio'],'level'=>$r['level'],'goal'=>$r['goal'],'minutes'=>(int)$r['minutes'],'avatar_id'=>$r['avatar_id'],'cover_id'=>$r['cover_id'],'xp'=>(int)($pr['xp']??0),'completed'=>count($pr['completed']??[]),'online'=>(bool)$r['online_visible']&&time()-(int)$r['last_seen']<180,'friend'=>hub_friend($p,$viewer,$id),'followed'=>(bool)hub_q($p,'SELECT 1 FROM eqx_follows WHERE user_id=? AND target_id=?',[$viewer,$id])->fetchColumn()];
}
function hub_plan(PDO $p,int $uid): array {
 // One free service level for every account. Legacy subscriptions never gate access.
 return ['name'=>'free','expires_at'=>null,'storage'=>1073741824,'ai'=>60,'payments_enabled'=>false,'global_discovery'=>true];
}
function hub_visible_post(PDO $p,int $uid,array $post): bool {
 if((int)$post['deleted']||hub_blocked($p,$uid,(int)$post['user_id']))return false;
 if((int)hub_profile($p,(int)$post['user_id'])['suspended'])return false;
 if($uid===(int)$post['user_id'])return true;
 if($post['group_id']&&!hub_q($p,'SELECT 1 FROM eqx_group_members WHERE group_id=? AND user_id=?',[$post['group_id'],$uid])->fetchColumn())return false;
 return $post['visibility']==='public'||$post['visibility']==='friends'&&hub_friend($p,$uid,(int)$post['user_id']);
}
function hub_post(PDO $p,int $uid,int $id): array {$r=hub_q($p,'SELECT * FROM eqx_posts WHERE id=?',[$id])->fetch();if(!$r||!hub_visible_post($p,$uid,$r))fail(404,'Bài viết không tồn tại hoặc bạn chưa có quyền xem.');return $r;}
function hub_member(PDO $p,int $uid,int $room): array {
 $r=hub_q($p,'SELECT r.*,m.read_id FROM eqx_rooms r JOIN eqx_room_members m ON m.room_id=r.id WHERE r.id=? AND m.user_id=?',[$room,$uid])->fetch();
 if(!$r)fail(404,'Không tìm thấy cuộc trò chuyện.');
 foreach(hub_q($p,'SELECT user_id FROM eqx_room_members WHERE room_id=?',[$room])->fetchAll() as $m)if(hub_blocked($p,$uid,(int)$m['user_id']))fail(403,'Cuộc trò chuyện bị chặn.');
 return $r;
}
function hub_owned_file(PDO $p,int $uid,int $id): array {$r=hub_q($p,'SELECT * FROM eqx_files WHERE id=? AND user_id=? AND deleted=0',[$id,$uid])->fetch();if(!$r)fail(404,'Không tìm thấy tệp của bạn.');return $r;}
function hub_audit(PDO $p,int $uid,string $action,int $id,string $detail=''): void {hub_q($p,'INSERT INTO eqx_audit(actor_id,action,target_id,detail,created_at) VALUES (?,?,?,?,?)',[$uid,$action,$id,$detail,time()]);}

<?php
declare(strict_types=1);
function hub_social(PDO $p,int $uid,string $op,array $in): never {
 if($op==='feed'){
  $query=hub_text($in,'query',100,true);$before=(int)($in['before']??PHP_INT_MAX);$group=(int)($in['group']??0);$category=($in['category']??'post')==='video'?'video':'post';$saved=!empty($in['saved']);
  $single=(int)($in['id']??0);
  $rows=hub_q($p,'SELECT t.*,u.display_name,r.username,r.avatar_id,r.level,f.mime AS file_mime FROM eqx_posts t JOIN eq_users u ON u.id=t.user_id JOIN eqx_profiles r ON r.user_id=t.user_id LEFT JOIN eqx_files f ON f.id=t.file_id AND f.deleted=0 WHERE t.deleted=0 AND r.suspended=0 AND t.category=? AND t.id<? AND t.body LIKE ?'.($group?' AND t.group_id=?':'').($single?' AND t.id=?':'').' ORDER BY t.id DESC LIMIT 100',array_merge([$category,$before,'%'.$query.'%'],$group?[$group]:[],$single?[$single]:[]))->fetchAll();$out=[];$cursor=null;
  foreach($rows as $r){$cursor=(int)$r['id'];if(!hub_visible_post($p,$uid,$r))continue;if(!empty($in['friends'])&&(int)$r['user_id']!==$uid&&!hub_friend($p,$uid,(int)$r['user_id']))continue;$mine=hub_q($p,'SELECT kind FROM eqx_reactions WHERE user_id=? AND post_id=?',[$uid,$r['id']])->fetchAll(PDO::FETCH_COLUMN);if($saved&&!in_array('save',$mine,true))continue;
   $r['liked']=in_array('like',$mine,true);$r['saved']=in_array('save',$mine,true);$r['likes']=(int)hub_q($p,"SELECT COUNT(*) FROM eqx_reactions WHERE post_id=? AND kind='like'",[$r['id']])->fetchColumn();$r['comments']=(int)hub_q($p,'SELECT COUNT(*) FROM eqx_comments WHERE post_id=? AND deleted=0',[$r['id']])->fetchColumn();$settings=eqd_settings($p,(int)$r['user_id']);$r['xp']=null;if((int)$r['user_id']===$uid||!empty($settings['share_achievements'])){$raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$r['user_id']])->fetchColumn();$r['xp']=(int)(json_decode($raw?:'{}',true)['xp']??0);}$out[]=$r;if(count($out)>=20)break;
  }reply(200,['ok'=>true,'posts'=>$out,'next_before'=>$cursor]);
 }
 if($op==='post_create'){
  limit_attempts($p,'posts:'.$uid,20,3600);$visibility=field($in,'visibility');if(!in_array($visibility,['public','friends','private'],true))fail(422,'Quyền xem chưa hợp lệ.');
  $file=$in['file_id']??null;if($file!==null){if(!is_int($file))fail(422,'Tệp chưa hợp lệ.');$f=hub_owned_file($p,$uid,$file);}
  $category=($in['category']??'post')==='video'?'video':'post';if($category==='video'&&(!isset($f)||!str_starts_with($f['mime'],'video/')))fail(422,'Chọn video MP4 hoặc WebM.');
  $group=$in['group_id']??null;if($group!==null&&(!is_int($group)||!hub_q($p,'SELECT 1 FROM eqx_group_members WHERE group_id=? AND user_id=?',[$group,$uid])->fetchColumn()))fail(403,'Bạn chưa tham gia nhóm.');
  $body=hub_text($in,'body',8000);hub_q($p,'INSERT INTO eqx_posts(user_id,body,visibility,group_id,file_id,category,transcript,created_at) VALUES (?,?,?,?,?,?,?,?)',[$uid,$body,$visibility,$group,$file,$category,hub_text($in,'transcript',12000,true),time()]);reply(200,['ok'=>true,'id'=>(int)$p->lastInsertId()]);
 }
 if($op==='post_edit'){
  $post=hub_post($p,$uid,hub_int($in,'id'));if((int)$post['user_id']!==$uid)fail(403,'Bạn không sở hữu bài này.');
  hub_q($p,'UPDATE eqx_posts SET body=? WHERE id=?',[hub_text($in,'body',8000),$post['id']]);reply(200,['ok'=>true]);
 }
 if($op==='comment_delete'){
  $c=hub_q($p,'SELECT * FROM eqx_comments WHERE id=? AND deleted=0',[hub_int($in,'id')])->fetch();if(!$c)fail(404,'Không tìm thấy bình luận.');
  $post=hub_post($p,$uid,(int)$c['post_id']);if((int)$c['user_id']!==$uid&&(int)$post['user_id']!==$uid)fail(403,'Bạn không thể xóa bình luận này.');
  hub_q($p,'UPDATE eqx_comments SET deleted=1 WHERE id=?',[$c['id']]);reply(200,['ok'=>true]);
 }
 if($op==='group_leave'){
  $id=hub_int($in,'id');$g=hub_q($p,'SELECT owner_id FROM eqx_groups WHERE id=?',[$id])->fetch();if(!$g)fail(404,'Không tìm thấy nhóm.');
  if((int)$g['owner_id']===$uid)fail(422,'Chủ nhóm cần giữ quyền quản lý nhóm.');hub_q($p,'DELETE FROM eqx_group_members WHERE group_id=? AND user_id=?',[$id,$uid]);reply(200,['ok'=>true]);
 }
 if($op==='post_delete'){$post=hub_post($p,$uid,hub_int($in,'id'));if((int)$post['user_id']!==$uid)fail(403,'Bạn không sở hữu bài này.');hub_q($p,'UPDATE eqx_posts SET deleted=1 WHERE id=?',[$post['id']]);reply(200,['ok'=>true]);}
 if($op==='post_react'){
  $post=hub_post($p,$uid,hub_int($in,'id'));$kind=field($in,'kind');if(!in_array($kind,['like','save'],true))fail(422,'Tương tác chưa hợp lệ.');
  if(!empty($in['enabled'])){try{hub_q($p,'INSERT INTO eqx_reactions(user_id,post_id,kind) VALUES (?,?,?)',[$uid,$post['id'],$kind]);if($kind==='like')hub_notify($p,(int)$post['user_id'],$uid,'Có người thích bài viết của bạn.','post',(int)$post['id']);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}}
  else hub_q($p,'DELETE FROM eqx_reactions WHERE user_id=? AND post_id=? AND kind=?',[$uid,$post['id'],$kind]);reply(200,['ok'=>true]);
 }
 if($op==='comments'){
  $id=hub_int($in,'id');hub_post($p,$uid,$id);$rows=hub_q($p,'SELECT c.*,u.display_name FROM eqx_comments c JOIN eq_users u ON u.id=c.user_id JOIN eqx_profiles pr ON pr.user_id=c.user_id WHERE c.post_id=? AND c.deleted=0 AND pr.suspended=0 ORDER BY c.id DESC LIMIT 60',[$id])->fetchAll();$rows=array_values(array_filter($rows,fn($r)=>!hub_blocked($p,$uid,(int)$r['user_id'])));reply(200,['ok'=>true,'comments'=>$rows]);
 }
 if($op==='comment_create'){
  $post=hub_post($p,$uid,hub_int($in,'id'));$parent=$in['parent_id']??null;if($parent!==null&&(!is_int($parent)||!hub_q($p,'SELECT id FROM eqx_comments WHERE id=? AND post_id=? AND deleted=0',[$parent,$post['id']])->fetchColumn()))fail(422,'Bình luận gốc chưa hợp lệ.');limit_attempts($p,'comments:'.$uid,60,3600);
  hub_q($p,'INSERT INTO eqx_comments(post_id,user_id,parent_id,body,created_at) VALUES (?,?,?,?,?)',[$post['id'],$uid,$parent,hub_text($in,'body',2000),time()]);hub_notify($p,(int)$post['user_id'],$uid,'Có bình luận mới trong bài viết của bạn.','post',(int)$post['id']);reply(200,['ok'=>true]);
 }
 if($op==='groups')reply(200,['ok'=>true,'groups'=>hub_q($p,'SELECT g.*,(SELECT COUNT(*) FROM eqx_group_members m WHERE m.group_id=g.id) AS members,(SELECT COUNT(*) FROM eqx_group_members m WHERE m.group_id=g.id AND m.user_id=?) AS joined FROM eqx_groups g ORDER BY g.id DESC LIMIT 60',[$uid])->fetchAll()]);
 if($op==='group_create'){
  limit_attempts($p,'groups:'.$uid,5,86400);$level=field($in,'level');if(!in_array($level,['A1','A2','B1','B2','C1','C2','IELTS','TOEIC'],true))fail(422,'Chọn trình độ nhóm.');
  $p->beginTransaction();try{hub_q($p,'INSERT INTO eqx_groups(owner_id,title,level,description,created_at) VALUES (?,?,?,?,?)',[$uid,hub_text($in,'title',160),$level,hub_text($in,'description',1500,true),time()]);$id=(int)$p->lastInsertId();hub_q($p,'INSERT INTO eqx_group_members(group_id,user_id) VALUES (?,?)',[$id,$uid]);$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}reply(200,['ok'=>true]);
 }
 if($op==='group_join'){
  $id=hub_int($in,'id');if(!hub_q($p,'SELECT id FROM eqx_groups WHERE id=?',[$id])->fetchColumn())fail(404,'Không tìm thấy nhóm.');try{hub_q($p,'INSERT INTO eqx_group_members(group_id,user_id) VALUES (?,?)',[$id,$uid]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}reply(200,['ok'=>true]);
 }
 fail(404,'Không tìm thấy chức năng cộng đồng.');
}

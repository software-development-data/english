<?php
declare(strict_types=1);
function hub_file_access(PDO $p,int $uid,array $f): bool {
 if((int)$f['user_id']===$uid)return true;
 if(hub_blocked($p,$uid,(int)$f['user_id']))return false;
 if((int)hub_profile($p,(int)$f['user_id'])['suspended'])return false;
 if(hub_q($p,'SELECT 1 FROM eqc_media m JOIN eqc_lessons l ON l.id=m.lesson_id JOIN eqc_units u ON u.id=l.unit_id JOIN eqc_courses c ON c.id=u.course_id WHERE m.file_id=? AND l.published=1 AND u.published=1 AND c.published=1',[$f['id']])->fetchColumn())return true;
 if(hub_q($p,'SELECT 1 FROM eqx_file_grants WHERE file_id=? AND user_id=?',[$f['id'],$uid])->fetchColumn())return true;
 if(hub_q($p,'SELECT 1 FROM eqx_profiles WHERE user_id=? AND (avatar_id=? OR cover_id=?)',[$f['user_id'],$f['id'],$f['id']])->fetchColumn())return true;
 foreach(hub_q($p,'SELECT * FROM eqx_posts WHERE file_id=? AND deleted=0',[$f['id']])->fetchAll() as $post)if(hub_visible_post($p,$uid,$post))return true;
 foreach(hub_q($p,'SELECT DISTINCT m.room_id FROM eqx_messages m JOIN eqx_room_members r ON r.room_id=m.room_id WHERE m.file_id=? AND r.user_id=? AND m.recalled=0',[$f['id'],$uid])->fetchAll() as $room){
  $blocked=false;foreach(hub_q($p,'SELECT user_id FROM eqx_room_members WHERE room_id=?',[$room['room_id']])->fetchAll() as $r)if(hub_blocked($p,$uid,(int)$r['user_id']))$blocked=true;if(!$blocked)return true;
 }return false;
}
function hub_files(PDO $p,int $uid,string $op,array $in): never {
 if($op==='hub_upload'){
  limit_attempts($p,'upload:'.$uid,25,3600);$f=$_FILES['file']??null;
  if(!$f||is_array($f['name']??null)||($f['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))fail(422,'Upload chưa thành công. Kiểm tra upload_max_filesize và post_max_size.');
  $size=filesize($f['tmp_name']);if($size<1||$size>20*1024*1024)fail(413,'Mỗi tệp tối đa 20 MB.');
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  // Fileinfo identifies WebM/MP4 containers as video even for audio-only MediaRecorder output.
  // Only select the audio presentation inside an already verified, allowed media container.
  $reported=strtolower(trim(explode(';',(string)($f['type']??''))[0]));
  if($mime==='video/webm'&&$reported==='audio/webm')$mime='audio/webm';
  if($mime==='video/mp4'&&$reported==='audio/mp4')$mime='audio/mp4';
  $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf','text/plain'=>'txt','video/mp4'=>'mp4','video/webm'=>'webm','audio/webm'=>'webm','audio/ogg'=>'ogg','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','audio/wav'=>'wav','audio/x-wav'=>'wav'];
  if(!isset($types[$mime]))fail(415,'Chỉ nhận ảnh JPG/PNG/WebP/GIF, PDF, TXT, MP4/WebM và âm thanh MP3/OGG/WAV.');
  if(str_starts_with($mime,'image/')){$dim=getimagesize($f['tmp_name']);if(!$dim||$dim[0]>10000||$dim[1]>10000||$dim[0]*$dim[1]>30000000)fail(422,'Ảnh quá lớn hoặc không hợp lệ.');}
  $name=basename(str_replace('\\','/',(string)$f['name']));$name=preg_replace('/[\x00-\x1f\x7f]/u','',$name);if(!preg_match('//u',$name)||strlen($name)>180)$name='upload.'.$types[$mime];
  $dir=__DIR__.'/data/uploads';if(!is_dir($dir))mkdir($dir,0700,true);$stored=bin2hex(random_bytes(24)).'.'.$types[$mime];$target=$dir.'/'.$stored;
  $p->beginTransaction();try{
   hub_q($p,'SELECT user_id FROM eqx_profiles WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid]);
   $used=(int)hub_q($p,'SELECT COALESCE(SUM(bytes),0) FROM eqx_files WHERE user_id=? AND deleted=0',[$uid])->fetchColumn();if($used+$size>hub_plan($p,$uid)['storage']){$p->rollBack();fail(413,'Kho lưu trữ miễn phí đã đầy. Hãy xóa tệp không cần dùng.');}
   if(!move_uploaded_file($f['tmp_name'],$target))throw new RuntimeException('Cannot store upload');chmod($target,0600);
   hub_q($p,'INSERT INTO eqx_files(user_id,name,stored,mime,bytes,created_at) VALUES (?,?,?,?,?,?)',[$uid,$name,$stored,$mime,$size,time()]);$id=(int)$p->lastInsertId();$p->commit();reply(200,['ok'=>true,'file'=>['id'=>$id,'name'=>$name,'mime'=>$mime,'bytes'=>$size]]);
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();if(is_file($target))unlink($target);throw $e;}
 }
 if($op==='hub_file'){
  $id=(int)($_GET['id']??0);$f=hub_q($p,'SELECT * FROM eqx_files WHERE id=? AND deleted=0',[$id])->fetch();if(!$f||!hub_file_access($p,$uid,$f))fail(404,'Không tìm thấy tệp hoặc bạn không có quyền.');
  $path=__DIR__.'/data/uploads/'.$f['stored'];if(!preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/D',$f['stored'])||!is_file($path))fail(404,'Tệp không còn trên máy chủ.');
  $length=filesize($path);$start=0;$end=$length-1;
  if(isset($_SERVER['HTTP_RANGE'])){
   if(!preg_match('/^bytes=(\d+)-(\d*)$/D',$_SERVER['HTTP_RANGE'],$range)) {header('Content-Range: bytes */'.$length);http_response_code(416);exit;}
   $start=(int)$range[1];$end=$range[2]!==''?min($end,(int)$range[2]):$end;if($start>$end||$start>=$length){header('Content-Range: bytes */'.$length);http_response_code(416);exit;}
   http_response_code(206);header("Content-Range: bytes $start-$end/$length");
  }
  header('Content-Type: '.$f['mime']);header('X-Content-Type-Options: nosniff');header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));header("Content-Security-Policy: default-src 'none'; sandbox");
  $inline=str_starts_with($f['mime'],'image/')||str_starts_with($f['mime'],'audio/')||str_starts_with($f['mime'],'video/');
  header('Content-Disposition: '.($inline?'inline':'attachment')."; filename=\"download\"; filename*=UTF-8''".rawurlencode($f['name']));session_write_close();$stream=fopen($path,'rb');fseek($stream,$start);$remaining=$end-$start+1;while($remaining>0&&!feof($stream)){$buf=fread($stream,min(65536,$remaining));echo $buf;$remaining-=strlen($buf);}fclose($stream);exit;
 }
 if($op==='drive'){
  $folder=$in['folder']??null;$where=$folder===null?'folder_id IS NULL':'folder_id=?';$args=$folder===null?[$uid]:[$uid,hub_int($in,'folder')];
  reply(200,['ok'=>true,'files'=>hub_q($p,"SELECT id,name,mime,bytes,folder_id,created_at FROM eqx_files WHERE user_id=? AND deleted=0 AND $where ORDER BY id DESC LIMIT 100",$args)->fetchAll(),'folders'=>hub_q($p,'SELECT * FROM eqx_folders WHERE user_id=? ORDER BY name',[$uid])->fetchAll(),'shared'=>hub_q($p,'SELECT f.id,f.name,f.mime,f.bytes FROM eqx_files f JOIN eqx_file_grants g ON g.file_id=f.id WHERE g.user_id=? AND f.deleted=0',[$uid])->fetchAll(),'used'=>(int)hub_q($p,'SELECT COALESCE(SUM(bytes),0) FROM eqx_files WHERE user_id=? AND deleted=0',[$uid])->fetchColumn(),'plan'=>hub_plan($p,$uid)]);
 }
 if($op==='folder_create'){if((int)hub_q($p,'SELECT COUNT(*) FROM eqx_folders WHERE user_id=?',[$uid])->fetchColumn()>=100)fail(422,'Tối đa 100 thư mục.');hub_q($p,'INSERT INTO eqx_folders(user_id,name) VALUES (?,?)',[$uid,hub_text($in,'name',160)]);reply(200,['ok'=>true]);}
 $file=hub_owned_file($p,$uid,hub_int($in,'id'));
 if($op==='file_update'){
  $folder=$in['folder_id']??null;if($folder!==null&&(!is_int($folder)||!hub_q($p,'SELECT 1 FROM eqx_folders WHERE id=? AND user_id=?',[$folder,$uid])->fetchColumn()))fail(422,'Thư mục chưa hợp lệ.');hub_q($p,'UPDATE eqx_files SET name=?,folder_id=? WHERE id=?',[hub_text($in,'name',180),$folder,$file['id']]);reply(200,['ok'=>true]);
 }
 if($op==='file_share'){
  $target=hub_int($in,'user_id');hub_target($p,$uid,$target);if(empty($in['enabled']))hub_q($p,'DELETE FROM eqx_file_grants WHERE file_id=? AND user_id=?',[$file['id'],$target]);else{try{hub_q($p,'INSERT INTO eqx_file_grants(file_id,user_id) VALUES (?,?)',[$file['id'],$target]);hub_notify($p,$target,$uid,'Bạn được chia sẻ một tài liệu.','file',(int)$file['id']);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}}reply(200,['ok'=>true]);
 }
 if($op==='file_delete'){hub_q($p,'UPDATE eqx_files SET deleted=1 WHERE id=?',[$file['id']]);$path=__DIR__.'/data/uploads/'.$file['stored'];if(is_file($path)&&preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/D',$file['stored']))unlink($path);reply(200,['ok'=>true]);}
 fail(404,'Không tìm thấy chức năng Drive.');
}

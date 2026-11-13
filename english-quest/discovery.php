<?php
declare(strict_types=1);
require_once __DIR__.'/hub-common.php';
function eqd_settings(PDO $p,int $uid): array {$s=hub_q($p,'SELECT * FROM eqd_settings WHERE user_id=?',[$uid])->fetch();if(!$s){try{hub_q($p,'INSERT INTO eqd_settings(user_id) VALUES (?)',[$uid]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}$s=hub_q($p,'SELECT * FROM eqd_settings WHERE user_id=?',[$uid])->fetch();}return $s;}
function eqd_can_message(PDO $p,int $from,int $to): bool {if($from===$to||hub_blocked($p,$from,$to))return false;$s=eqd_settings($p,$to);return $s['message_policy']!=='none'&&(hub_friend($p,$from,$to)||$s['message_policy']==='everyone'&&$s['age_band']==='adult');}
function eqd_distance(float $a,float $b,float $c,float $d): float {$x=sin(deg2rad($c-$a)/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin(deg2rad($d-$b)/2)**2;return 6371*2*atan2(sqrt(min(1,$x)),sqrt(max(0,1-$x)));}
function eqd_public(PDO $p,int $viewer,array $profile): array {
 $s=eqd_settings($p,(int)$profile['id']);$profile['country']=$s['show_region']?$s['country']:null;$profile['city']=$s['show_region']&&$s['age_band']==='adult'?$s['city']:null;$profile['language']=$s['language'];$profile['timezone']=$s['timezone'];$profile['study_window']=$s['study_window'];$profile['interests']=$s['interests'];$profile['seeking']=(bool)$s['seeking'];$profile['message_allowed']=eqd_can_message($p,$viewer,(int)$profile['id']);
 if($s['age_band']!=='adult')$profile['online']=false;
 if(!$s['share_achievements']){$profile['xp']=null;$profile['completed']=null;$profile['badges']=[];}else{$raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$profile['id']])->fetchColumn();$pr=$raw?json_decode($raw,true):[];$profile['badges']=[];if(count($pr['completed']??[]))$profile['badges'][]='Bước chân đầu tiên';if(count($pr['learned']??[])>=30)$profile['badges'][]='Kho từ vựng nhỏ';if(count($pr['days']??[])>=7)$profile['badges'][]='7 ngày học tập';}
 return $profile;
}
function eq_discovery(PDO $p,int $uid,array $config,string $op,array $in): never {
 $me=eqd_settings($p,$uid);
 if($op==='discovery_settings')reply(200,['ok'=>true,'settings'=>$me,'location'=>hub_q($p,'SELECT updated_at,expires_at FROM eqd_locations WHERE user_id=? AND expires_at>?',[$uid,time()])->fetch()?:null]);
 if($op==='discovery_save'){
  $country=strtoupper(hub_text($in,'country',2,true));if($country!==''&&!preg_match('/^[A-Z]{2}$/D',$country))fail(422,'Quốc gia cần mã 2 chữ, ví dụ VN.');$tz=hub_text($in,'timezone',64);if(!in_array($tz,DateTimeZone::listIdentifiers(),true))fail(422,'Múi giờ chưa hợp lệ.');$age=field($in,'age_band');if(!in_array($age,['adult','minor','unspecified'],true))fail(422,'Chọn nhóm tuổi.');$policy=field($in,'message_policy');if(!in_array($policy,['everyone','friends','none'],true))fail(422,'Quyền nhắn tin chưa hợp lệ.');if($age!=='adult')$policy=$policy==='none'?'none':'friends';$window=field($in,'study_window');if(!in_array($window,['morning','afternoon','evening','flexible'],true))fail(422,'Khung giờ học chưa hợp lệ.');
  hub_q($p,'UPDATE eqd_settings SET country=?,city=?,language=?,timezone=?,study_window=?,interests=?,seeking=?,show_region=?,age_band=?,message_policy=?,allow_email_search=?,share_achievements=? WHERE user_id=?',[$country,hub_text($in,'city',120,true),hub_text($in,'language',80),$tz,$window,hub_text($in,'interests',240,true),!empty($in['seeking'])?1:0,!empty($in['show_region'])?1:0,$age,$policy,$age==='adult'&&!empty($in['allow_email_search'])?1:0,!empty($in['share_achievements'])?1:0,$uid]);
  if($age!=='adult'){hub_q($p,'UPDATE eqd_settings SET location_enabled=0 WHERE user_id=?',[$uid]);hub_q($p,'DELETE FROM eqd_locations WHERE user_id=?',[$uid]);}reply(200,['ok'=>true]);
 }
 if($op==='location'){
  if(empty($in['enabled'])){hub_q($p,'UPDATE eqd_settings SET location_enabled=0 WHERE user_id=?',[$uid]);hub_q($p,'DELETE FROM eqd_locations WHERE user_id=?',[$uid]);hub_q($p,'UPDATE eqx_profiles SET location_enabled=0,lat=NULL,lng=NULL WHERE user_id=?',[$uid]);reply(200,['ok'=>true]);}
  if($me['age_band']!=='adult')fail(403,'Khám phá vị trí chỉ mở cho người tự xác nhận từ 18 tuổi.');$lat=$in['lat']??null;$lng=$in['lng']??null;if(!is_numeric($lat)||!is_numeric($lng)||abs((float)$lat)>90||abs((float)$lng)>180)fail(422,'Vị trí chưa hợp lệ.');limit_attempts($p,'location-update:'.$uid,1,900);
  $p->beginTransaction();try{hub_q($p,'DELETE FROM eqd_locations WHERE user_id=?',[$uid]);hub_q($p,'INSERT INTO eqd_locations(user_id,lat,lng,updated_at,expires_at) VALUES (?,?,?,?,?)',[$uid,round((float)$lat,1),round((float)$lng,1),time(),time()+43200]);hub_q($p,'UPDATE eqd_settings SET location_enabled=1 WHERE user_id=?',[$uid]);hub_q($p,'UPDATE eqx_profiles SET location_enabled=0,lat=NULL,lng=NULL WHERE user_id=?',[$uid]);$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}reply(200,['ok'=>true,'message'=>'Đã lưu vị trí làm tròn trong 12 giờ; có thể tắt và xóa bất kỳ lúc nào.']);
 }
 if($op==='discover'||$op==='nearby'){
  limit_attempts($p,'discovery:'.$uid,60,60);$mode=field($in,'mode')?:($op==='nearby'?'nearby':'global');if(!in_array($mode,['nearby','global','long','suggested'],true))fail(422,'Chế độ tìm kiếm chưa hợp lệ.');$query=hub_text($in,'query',100,true);$country=strtoupper(hub_text($in,'country',2,true));$city=hub_text($in,'city',120,true);$level=hub_text($in,'level',2,true);$goal=hub_text($in,'goal',100,true);$timezone=hub_text($in,'timezone',64,true);$language=hub_text($in,'language',80,true);$interests=hub_text($in,'interests',100,true);$radius=(int)($in['radius']??25);if(!in_array($radius,[1,5,10,25,50,100,500],true))fail(422,'Bán kính chưa hợp lệ.');
  $offset=$in['offset']??0;if(!is_int($offset)||$offset<0||$offset>PHP_INT_MAX-21)fail(422,'Trang chưa hợp lệ.');$own=hub_profile($p,$uid);$geo=null;
  if($mode==='nearby'){limit_attempts($p,'nearby:'.$uid,20,60);$geo=hub_q($p,'SELECT lat,lng FROM eqd_locations WHERE user_id=? AND expires_at>?',[$uid,time()])->fetch();if(!$geo||!$me['location_enabled']||$me['age_band']!=='adult')fail(403,'Hãy tự bật vị trí để tìm gần bạn. Bạn vẫn tìm toàn cầu mà không cần GPS.');}
  $where=['u.id<>?','COALESCE(r.discover,1)=1','COALESCE(r.suspended,0)=0',"EXISTS(SELECT 1 FROM eq_auth_accounts a WHERE a.user_id=u.id AND a.status='active')",'NOT EXISTS(SELECT 1 FROM eqx_blocks b WHERE (b.user_id=? AND b.target_id=u.id) OR (b.target_id=? AND b.user_id=u.id))'];$args=[$uid,$uid,$uid];
  if(str_contains($query,'@')){$where[]='d.allow_email_search=1 AND d.age_band=\'adult\' AND u.email=?';$args[]=strtolower(trim($query));}
  elseif($query!==''){$where[]='(u.display_name LIKE ? OR r.username LIKE ?)';array_push($args,'%'.$query.'%','%'.$query.'%');}
  foreach([[$country,'d.country=? AND d.show_region=1'],[$city,"d.city=? AND d.show_region=1 AND d.age_band='adult'"],[$level,'r.level=?'],[$timezone,'d.timezone=?'],[$language,'d.language LIKE ?'],[$goal,'r.goal LIKE ?'],[$interests,'d.interests LIKE ?']] as $i=>$filter)if($filter[0]!==''){$where[]=$filter[1];$args[]=$i>=4?'%'.$filter[0].'%':$filter[0];}
  if(!empty($in['online'])){$where[]="r.online_visible=1 AND r.last_seen>? AND d.age_band='adult'";$args[]=time()-180;}
  if(!empty($in['new'])){$where[]='u.created_at>=?';$args[]=date('Y-m-d H:i:s',time()-30*86400);}
  if(!empty($in['seeking']))$where[]='d.seeking=1';
  if($mode==='nearby'){$where[]="d.location_enabled=1 AND d.age_band='adult' AND l.expires_at>? AND l.lat BETWEEN ? AND ?";array_push($args,time(),(float)$geo['lat']-$radius/110.5-.02,(float)$geo['lat']+$radius/110.5+.02);}
  if($mode==='long'&&$me['country']!==''&&$me['city']!==''){$where[]="(COALESCE(d.country,'')<>? OR COALESCE(d.city,'')<>?) AND d.show_region=1";array_push($args,$me['country'],$me['city']);}
  $select='u.id';$order='u.id DESC';$selectArgs=[];
  if($mode==='nearby'){$select.=',6371*ACOS(LEAST(1,GREATEST(-1,SIN(RADIANS(?))*SIN(RADIANS(l.lat))+COS(RADIANS(?))*COS(RADIANS(l.lat))*COS(RADIANS(l.lng-?))))) AS _distance';$selectArgs=[(float)$geo['lat'],(float)$geo['lat'],(float)$geo['lng']];$order='FLOOR(_distance/5),u.id DESC';}
  if($mode==='suggested'){$select.=',(CASE WHEN r.level=? THEN 3 ELSE 0 END+CASE WHEN r.goal=? THEN 2 ELSE 0 END+CASE WHEN d.study_window=? THEN 1 ELSE 0 END) AS _score';$selectArgs=[$own['level'],$own['goal'],$me['study_window']];$order='_score DESC,u.id DESC';}
  $sql='SELECT '.$select.' FROM eq_users u LEFT JOIN eqx_profiles r ON r.user_id=u.id LEFT JOIN eqd_settings d ON d.user_id=u.id LEFT JOIN eqd_locations l ON l.user_id=u.id WHERE '.implode(' AND ',$where);
  if($mode==='nearby'){$sql.=' HAVING _distance<=?';$args[]=$radius;}$sql.=' ORDER BY '.$order.' LIMIT 21 OFFSET '.$offset;$rows=hub_q($p,$sql,[...$selectArgs,...$args])->fetchAll();
  $more=count($rows)>20;$rows=array_slice($rows,0,20);$out=[];
  foreach($rows as $row){$profile=eqd_public($p,$uid,hub_public_profile($p,$uid,(int)$row['id']));if(isset($row['_distance']))$profile['distance']=$row['_distance']<5?'Cùng khu vực (dưới khoảng 5 km)':'Khoảng '.(int)(floor($row['_distance']/5)*5).'–'.(int)(floor($row['_distance']/5)*5+5).' km';$out[]=$profile;}
  reply(200,['ok'=>true,'people'=>$out,'has_more'=>$more,'next_offset'=>$offset+count($out),'mode'=>$mode,'approximate'=>true]);
 }
 if($op==='discovery_map'){
  limit_attempts($p,'map:'.$uid,10,60);$rows=hub_q($p,"SELECT d.country,COUNT(*) AS learners FROM eqd_settings d JOIN eqd_locations l ON l.user_id=d.user_id JOIN eqx_profiles p ON p.user_id=d.user_id WHERE d.show_region=1 AND d.location_enabled=1 AND d.age_band='adult' AND l.expires_at>? AND p.discover=1 AND p.suspended=0 AND NOT EXISTS(SELECT 1 FROM eqx_blocks b WHERE (b.user_id=? AND b.target_id=d.user_id) OR (b.target_id=? AND b.user_id=d.user_id)) GROUP BY d.country HAVING COUNT(*)>=3",[time(),$uid,$uid])->fetchAll();reply(200,['ok'=>true,'regions'=>$rows,'precision'=>'country_only','minimum_cluster'=>3]);
 }
 fail(404,'Không tìm thấy chức năng khám phá.');
}

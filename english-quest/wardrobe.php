<?php
declare(strict_types=1);
function eq5_clothes():array{$items=[
 'hair-blue'=>['slot'=>'hair','name'=>'Tóc xanh mây','price'=>0], 'hair-pink'=>['slot'=>'hair','name'=>'Tóc hoa đào','price'=>30], 'hair-silver'=>['slot'=>'hair','name'=>'Tóc ánh trăng','price'=>40],
 'outfit-academy'=>['slot'=>'outfit','name'=>'Đồng phục học viện','price'=>0], 'outfit-flower'=>['slot'=>'outfit','name'=>'Váy hoa anh đào','price'=>60], 'outfit-sky'=>['slot'=>'outfit','name'=>'Áo choàng bầu trời','price'=>80],
 'shoes-blue'=>['slot'=>'shoes','name'=>'Giày xanh mây','price'=>0], 'shoes-pink'=>['slot'=>'shoes','name'=>'Giày nơ hồng','price'=>20], 'shoes-gold'=>['slot'=>'shoes','name'=>'Giày ngôi sao','price'=>30],
 'hat-none'=>['slot'=>'hat','name'=>'Không phụ kiện','price'=>0], 'hat-star'=>['slot'=>'hat','name'=>'Kẹp sao nhỏ','price'=>20], 'hat-crown'=>['slot'=>'hat','name'=>'Vương miện học viện','price'=>100],
 'cultural-vn-ao-dai-male'=>['slot'=>'outfit','name'=>'Áo dài Việt Nam · Nam','price'=>140,'culture'=>'Việt Nam','image'=>'/fashion/cultural/vn-ao-dai-male.webp'],
 'cultural-vn-ao-dai-female'=>['slot'=>'outfit','name'=>'Áo dài Việt Nam · Nữ','price'=>140,'culture'=>'Việt Nam','image'=>'/fashion/cultural/vn-ao-dai-female.webp'],
 'cultural-vn-ngu-than-male'=>['slot'=>'outfit','name'=>'Ngũ thân Việt Nam · Nam','price'=>160,'culture'=>'Việt Nam','image'=>'/fashion/cultural/vn-ngu-than-male.webp'],
 'cultural-vn-ngu-than-female'=>['slot'=>'outfit','name'=>'Ngũ thân Việt Nam · Nữ','price'=>160,'culture'=>'Việt Nam','image'=>'/fashion/cultural/vn-ngu-than-female.webp'],
 'cultural-vn-ba-ba-male'=>['slot'=>'outfit','name'=>'Áo bà ba Việt Nam · Nam','price'=>120,'culture'=>'Việt Nam','image'=>'/fashion/cultural/vn-ba-ba-male.webp'],
 'cultural-vn-ba-ba-female'=>['slot'=>'outfit','name'=>'Áo bà ba Việt Nam · Nữ','price'=>120,'culture'=>'Việt Nam','image'=>'/fashion/cultural/vn-ba-ba-female.webp'],
 'cultural-cn-changshan-male'=>['slot'=>'outfit','name'=>'Trường sam Trung Hoa · Nam','price'=>160,'culture'=>'Trung Hoa','image'=>'/fashion/cultural/cn-changshan-male.webp'],
 'cultural-cn-ruqun-female'=>['slot'=>'outfit','name'=>'Nhu quần Trung Hoa · Nữ','price'=>160,'culture'=>'Trung Hoa','image'=>'/fashion/cultural/cn-ruqun-female.webp'],
 'cultural-cn-hanfu-male'=>['slot'=>'outfit','name'=>'Hán phục Trung Hoa · Nam','price'=>180,'culture'=>'Trung Hoa','image'=>'/fashion/cultural/cn-hanfu-male.webp'],
 'cultural-cn-hanfu-female'=>['slot'=>'outfit','name'=>'Hán phục Trung Hoa · Nữ','price'=>180,'culture'=>'Trung Hoa','image'=>'/fashion/cultural/cn-hanfu-female.webp'],
 'cultural-fantasy-scholar-male'=>['slot'=>'outfit','name'=>'Học giả huyền ảo · Nam','price'=>180,'culture'=>'Học viện','image'=>'/fashion/cultural/fantasy-scholar-male.webp'],
 'cultural-fantasy-scholar-female'=>['slot'=>'outfit','name'=>'Học giả huyền ảo · Nữ','price'=>180,'culture'=>'Học viện','image'=>'/fashion/cultural/fantasy-scholar-female.webp']
 ];foreach(array_merge(range(1,16),[17,18,19,21,22,25,26,27]) as $i){$id=sprintf('%03d',$i);$items['cultural-cn-style-'.$id]=['slot'=>'outfit','name'=>'Hán phục cảm hứng · Mẫu '.$i,'price'=>140+($i%4)*10,'culture'=>'Trung Hoa','image'=>'/fashion/cultural/cn-style-'.$id.'.webp'];}foreach(['hat'=>[20,23,32],'shoes'=>[24,29,30],'hair'=>[28,31]] as $slot=>$variants)foreach($variants as $i){$id=sprintf('%03d',$i);$items['cultural-cn-style-'.$id]=['slot'=>$slot,'name'=>($slot==='hat'?'Nón hoa':($slot==='shoes'?'Giày hoa':'Trâm cài tóc')).' Trung Hoa · Mẫu '.ltrim($id,'0'),'price'=>80,'culture'=>'Trung Hoa','image'=>'/fashion/cultural/cn-style-'.$id.'.webp'];}return $items;}
function eq5_coin_total(array $pr):int{$total=count(array_unique($pr['learned']??[]))*2;foreach(array_unique($pr['completed']??[]) as $id){if(str_starts_with($id,'answer-'))$total+=2;elseif(preg_match('/^(vocabulary|grammar|listening|test|practice|course-lesson|course-unit|course-course)-/',$id))$total+=10;}return $total;}
function eq5_wallet_sync(PDO $p,int $uid,array $progress):void{
 $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$meta=[];
 try{$raw=hub_q($p,'SELECT state FROM eq5_wallet WHERE user_id=?',[$uid])->fetchColumn();if($raw)$meta=json_decode((string)$raw,true)?:[];}catch(PDOException $e){if((string)$e->getCode()!=='42S02'&&!str_contains(strtolower($e->getMessage()),'no such table'))throw $e;}
 $earned=eq5_coin_total($progress)+(int)($meta['farm_earned']??0)+(int)($meta['bonus_earned']??0);
 try{if($mysql)hub_q($p,'INSERT INTO eq5_wallet(user_id,earned,spent,state) VALUES (?,?,0,?) ON DUPLICATE KEY UPDATE earned=VALUES(earned)',[$uid,$earned,json_encode($meta)]);else hub_q($p,'INSERT INTO eq5_wallet(user_id,earned,spent,state) VALUES (?,?,0,?) ON CONFLICT(user_id) DO UPDATE SET earned=excluded.earned',[$uid,$earned,json_encode($meta)]);}
 catch(PDOException $e){if((string)$e->getCode()==='42S02'||str_contains(strtolower($e->getMessage()),'no such table'))return;throw $e;}
}
function eq5_wallet_view(array $r):array{$s=json_decode($r['state'],true)?:[];$s+=['owned'=>['hair-blue','outfit-academy','shoes-blue','hat-none'],'equipped'=>['hair'=>'hair-blue','outfit'=>'outfit-academy','shoes'=>'shoes-blue','hat'=>'hat-none']];return ['earned'=>(int)$r['earned'],'spent'=>(int)$r['spent'],'balance'=>max(0,(int)$r['earned']-(int)$r['spent'])]+$s;}
function eq5_wardrobe(PDO $p,int $uid,string $op,array $in):never{
 try{$p->query('SELECT user_id FROM eq5_wallet LIMIT 1');}catch(PDOException $e){fail(503,'Cần cài database/v5.sql để mở ví xu và tủ đồ.');}
 if($op==='wallet_leaderboard'){$rows=hub_q($p,'SELECT w.user_id,w.earned,u.display_name FROM eq5_wallet w JOIN eq_users u ON u.id=w.user_id JOIN eqx_profiles pr ON pr.user_id=w.user_id JOIN eqd_settings ds ON ds.user_id=w.user_id WHERE pr.suspended=0 AND ds.share_achievements=1 ORDER BY w.earned DESC,w.user_id ASC LIMIT 100')->fetchAll();$rows=array_values(array_filter($rows,fn($r)=>!hub_blocked($p,$uid,(int)$r['user_id'])));reply(200,['ok'=>true,'rows'=>array_slice($rows,0,50)]);}
 if(!in_array($op,['wallet_state','wallet_buy','wallet_equip'],true))fail(404,'Không tìm thấy tính năng tủ đồ.');
 $catalog=eq5_clothes();$item=$op==='wallet_state'?'':field($in,'item');if($item!==''&&!isset($catalog[$item]))fail(422,'Trang phục không hợp lệ.');
 $p->beginTransaction();try{
 $pr=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid])->fetchColumn();eq5_wallet_sync($p,$uid,$pr?json_decode($pr,true):[]);
 $row=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid])->fetch();$w=eq5_wallet_view($row);
 if($op==='wallet_buy'&&!in_array($item,$w['owned'],true)){if($w['balance']<$catalog[$item]['price']){$p->rollBack();fail(422,'Bạn chưa đủ xu. Học thêm bài để nhận xu nhé!');}$w['spent']+=$catalog[$item]['price'];$w['balance']-=$catalog[$item]['price'];$w['owned'][]=$item;}
 if($op==='wallet_equip'){if(!in_array($item,$w['owned'],true)){$p->rollBack();fail(403,'Bạn chưa sở hữu món đồ này.');}$w['equipped'][$catalog[$item]['slot']]=$item;}
 hub_q($p,'UPDATE eq5_wallet SET spent=?,state=? WHERE user_id=?',[$w['spent'],json_encode(array_intersect_key($w,array_flip(['owned','equipped','farm_earned']))),$uid]);$p->commit();reply(200,['ok'=>true,'wallet'=>$w,'catalog'=>$catalog]);
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

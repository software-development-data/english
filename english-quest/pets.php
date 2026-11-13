<?php
declare(strict_types=1);

function eq5_food():array{return [
 'berry'=>['name'=>'Dâu năng lượng','fullness'=>20,'bond'=>8],
 'milk'=>['name'=>'Sữa ngôi sao','fullness'=>30,'bond'=>12],
 'cookie'=>['name'=>'Bánh tình bạn','fullness'=>15,'bond'=>15]
];}
function eq5_egg_catalog():array{return [
 'cat'=>['name'=>'Trứng mèo mây','species'=>'Mèo mây','price'=>140,'image'=>'/eggs/star-shell.svg','kind'=>'cat'],
 'puppy'=>['name'=>'Trứng cún đào','species'=>'Cún đào','price'=>180,'image'=>'/eggs/star-shell.svg','kind'=>'puppy'],
 'lion'=>['name'=>'Trứng sư tử nắng','species'=>'Sư tử nắng','price'=>240,'image'=>'/eggs/star-shell.svg','kind'=>'lion'],
 'eagle'=>['name'=>'Trứng đại bàng sao','species'=>'Đại bàng sao','price'=>240,'image'=>'/eggs/star-shell.svg','kind'=>'eagle']
];}
function eq5_initial():array{return [
 'id'=>null,'kind'=>null,'name'=>'Con AI','gender'=>'female','color'=>'cream',
 'fullness'=>50,'bond'=>0,'food'=>['berry'=>3,'milk'=>2,'cookie'=>2],
 'last_tick'=>time(),'daily'=>'','spin_day'=>'','history'=>[],
 'eggs'=>[],'collection'=>[],'active_id'=>null
];}
function eq5_pet_record(array $s):array{
 $keys=['id','kind','name','gender','color','fullness','bond','food','last_tick','daily','spin_day','history'];
 $pet=array_intersect_key($s,array_flip($keys));
 $pet['level']=1+intdiv((int)($pet['bond']??0),100);
 $pet['stage']=(int)$pet['bond']>=600?'glow':((int)$pet['bond']>=300?'grown':((int)$pet['bond']>=100?'young':'baby'));
 return $pet;
}
function eq5_sync_active(array &$s):void{
 if(empty($s['kind'])||empty($s['active_id']))return;
 $record=eq5_pet_record($s);$record['id']=$s['active_id'];
 foreach($s['collection'] as $i=>$pet)if(($pet['id']??null)===$s['active_id']){$s['collection'][$i]=$record;return;}
 $s['collection'][]=$record;
}
function eq5_normalize_state(array $s):array{
 $s=array_replace_recursive(eq5_initial(),$s);
 if(!is_array($s['collection']))$s['collection']=[];
 if(!is_array($s['eggs']))$s['eggs']=[];
 if(!empty($s['kind'])){
  if(empty($s['active_id']))$s['active_id']=bin2hex(random_bytes(8));
  $found=false;foreach($s['collection'] as $pet)if(($pet['id']??null)===$s['active_id']){$found=true;break;}
  if(!$found){$record=eq5_pet_record($s);$record['id']=$s['active_id'];$s['collection'][]=$record;}
 }elseif(!empty($s['active_id'])){
  foreach($s['collection'] as $pet)if(($pet['id']??null)===$s['active_id']){$s=array_merge($s,$pet);break;}
 }
 return $s;
}
function eq5_pet_view(array $s):array{
 $s['level']=1+intdiv((int)$s['bond'],100);
 $s['level_progress']=(int)$s['bond']%100;
 $s['stage']=(int)$s['bond']>=600?'glow':((int)$s['bond']>=300?'grown':((int)$s['bond']>=100?'young':'baby'));
 $s['can_spin']=$s['spin_day']!==date('Y-m-d');
 $s['can_claim']=$s['daily']!==date('Y-m-d');
 $s['collection']=array_map('eq5_pet_record',$s['collection']??[]);
 $s['eggs']=$s['eggs']??[];
 return $s;
}
function eq5_new_pet(string $kind,string $name):array{
 $names=['cat'=>'Mochi','spirit'=>'Mochi','puppy'=>'Bông','lion'=>'Nắng','eagle'=>'Mây'];
 return [
  'id'=>bin2hex(random_bytes(8)),'kind'=>$kind,'name'=>$name!==''?$name:($names[$kind]??'Bạn nhỏ'),
  'gender'=>random_int(0,1)?'female':'male','color'=>['cream','peach','lilac'][random_int(0,2)],
  'fullness'=>50,'bond'=>0,'food'=>['berry'=>3,'milk'=>2,'cookie'=>2],
  'last_tick'=>time(),'daily'=>'','spin_day'=>'','history'=>[]
 ];
}
function eq5_apply_pet(array &$s,array $pet):void{
 foreach($pet as $key=>$value)if($key!=='level'&&$key!=='stage')$s[$key]=$value;
 $s['active_id']=$pet['id'];
}
function eq5_pets(PDO $p,int $uid,string $op,array $in):never{
 try{foreach(['eq5_pets','eq5_events'] as $table)$p->query('SELECT user_id FROM '.$table.' LIMIT 1');}catch(PDOException $e){
  require_once __DIR__.'/v5-schema.php';
  try{foreach(eq_v5_schema($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') as $sql){if(str_contains($sql,'eq5_pets(')||str_contains($sql,'eq5_events('))$p->exec($sql);}}
  catch(PDOException $setup){error_log('EQ V5 schema setup failed: '.$setup->getCode());fail(503,'Chưa khởi tạo được dữ liệu thú cưng. Quản trị viên cần nhập database/v5.sql trong phpMyAdmin hoặc cấp quyền CREATE cho tài khoản database.');}
 }
 $allowed=['pet_state','pet_adopt','pet_rename','pet_feed','pet_daily','pet_spin','pet_persona','pet_egg_buy','pet_hatch','pet_select'];
 if(!in_array($op,$allowed,true))fail(404,'Không tìm thấy tính năng thú cưng.');
 $key=$op==='pet_state'?'':hub_text($in,'key',64);
 if($key!==''&&!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key))fail(422,'Mã thao tác không hợp lệ.');
 $hash=hash('sha256',json_encode([$op,$in],JSON_UNESCAPED_UNICODE));
 $p->beginTransaction();
 try{
  hub_q($p,'SELECT user_id FROM eqx_profiles WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid]);
  if($key!==''){$old=hub_q($p,'SELECT * FROM eq5_events WHERE user_id=? AND event_key=?',[$uid,$key])->fetch();if($old){$p->commit();if(!hash_equals($old['payload_hash'],$hash))fail(409,'Mã thao tác đã dùng cho yêu cầu khác.');reply(200,json_decode($old['result'],true));}}
  $raw=hub_q($p,'SELECT state FROM eq5_pets WHERE user_id=?',[$uid])->fetchColumn();
  $s=eq5_normalize_state($raw?json_decode($raw,true)?:[]:eq5_initial());
  $hours=max(0,intdiv(time()-(int)$s['last_tick'],3600));
  $s['fullness']=max(0,$s['fullness']-min(100,$hours*2));$s['last_tick']+=$hours*3600;
  $reward=null;$error='';$wallet=null;$hatch=null;
  if($op==='pet_adopt'){
   if(!empty($s['kind']))$error='Bạn đã có bạn đồng hành rồi.';
   elseif(!in_array(field($in,'kind'),['spirit','bunny','cat'],true))$error='Thú cưng không hợp lệ.';
   else{$pet=eq5_new_pet(field($in,'kind'),hub_text($in,'name',60));$s['collection'][]=$pet;eq5_apply_pet($s,$pet);}
  }elseif($op==='pet_persona'){
   if(!in_array(field($in,'persona'),array_merge(['scholar','guardian','explorer','connector'],array_map(fn($n)=>'vn-'.str_pad((string)$n,2,'0',STR_PAD_LEFT),range(1,20))),true))$error='Nhân vật không hợp lệ.';
   else $s['persona']=field($in,'persona');
  }elseif($op==='pet_egg_buy'){
   $kind=field($in,'kind');$catalog=eq5_egg_catalog();
   if(!isset($catalog[$kind]))$error='Loại trứng không hợp lệ.';
   else{
    try{
     require_once __DIR__.'/wardrobe.php';
     $progress=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$uid])->fetchColumn();
     eq5_wallet_sync($p,$uid,$progress?json_decode($progress,true)?:[]:[]);
     $lock=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
     $wr=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.$lock,[$uid])->fetch();
     if(!$wr){$error='Ví xu chưa sẵn sàng. Hãy cài database/v5.sql trước.';}
     else{
      $balance=max(0,(int)$wr['earned']-(int)$wr['spent']);
      if($balance<$catalog[$kind]['price'])$error='Bạn chưa đủ xu để mua trứng này.';
      else{
       $s['eggs'][$kind]=(int)($s['eggs'][$kind]??0)+1;
       $spent=(int)$wr['spent']+(int)$catalog[$kind]['price'];
       hub_q($p,'UPDATE eq5_wallet SET spent=? WHERE user_id=?',[$spent,$uid]);
       $wallet=['balance'=>max(0,(int)$wr['earned']-$spent),'earned'=>(int)$wr['earned'],'spent'=>$spent];
      }
     }
    }catch(PDOException $walletError){$error='Ví xu chưa sẵn sàng. Hãy cài database/v5.sql trước.';}
   }
  }elseif($op==='pet_hatch'){
   $kind=field($in,'kind');$catalog=eq5_egg_catalog();
   if(!isset($catalog[$kind])||($s['eggs'][$kind]??0)<1)$error='Bạn chưa có trứng này. Hãy đổi xu lấy trứng trước nhé.';
   else{$s['eggs'][$kind]--;if($s['eggs'][$kind]<=0)unset($s['eggs'][$kind]);$pet=eq5_new_pet($kind,hub_text($in,'name',60));$s['collection'][]=$pet;eq5_apply_pet($s,$pet);$hatch=['kind'=>$kind,'name'=>$pet['name'],'gender'=>$pet['gender'],'color'=>$pet['color']];}
  }elseif($op==='pet_select'){
   $id=hub_text($in,'id',32);$selected=null;
   foreach($s['collection'] as $pet)if(($pet['id']??null)===$id){$selected=$pet;break;}
   if(!$selected)$error='Bạn chưa sưu tầm được bạn đồng hành này.';else eq5_apply_pet($s,$selected);
  }elseif($op!=='pet_state'&&!$s['kind'])$error='Hãy nhận nuôi bạn đồng hành trước nhé.';
  elseif($op==='pet_rename')$s['name']=hub_text($in,'name',60);
  elseif($op==='pet_feed'){
   $food=field($in,'food');$catalog=eq5_food();
   if(!isset($catalog[$food]))$error='Thức ăn không hợp lệ.';
   elseif(($s['food'][$food]??0)<1)$error='Món này đã hết. Nhận phần ăn mỗi ngày hoặc quay may mắn nhé.';
   elseif($s['fullness']>=100)$error='Bạn đồng hành đã no rồi. Hãy quay lại sau nhé.';
   else{$s['food'][$food]--;$s['fullness']=min(100,$s['fullness']+$catalog[$food]['fullness']);$s['bond']+=$catalog[$food]['bond'];}
  }elseif($op==='pet_daily'){
   if($s['daily']===date('Y-m-d'))$error='Bạn đã nhận phần ăn hôm nay.';
   else{$s['daily']=date('Y-m-d');foreach(['berry','milk','cookie'] as $f)$s['food'][$f]++;$s['bond']+=5;}
  }elseif($op==='pet_spin'){
   if($s['spin_day']===date('Y-m-d'))$error='Bạn đã dùng lượt quay miễn phí hôm nay.';
   else{$index=random_int(0,5);$prizes=[['food'=>'berry','qty'=>2],['food'=>'milk','qty'=>2],['food'=>'cookie','qty'=>2],['food'=>'berry','qty'=>3],['food'=>'milk','qty'=>3],['food'=>'cookie','qty'=>3]];$reward=$prizes[$index]+['index'=>$index];$s['food'][$reward['food']]+=$reward['qty'];$s['spin_day']=date('Y-m-d');array_unshift($s['history'],['at'=>time()]+$reward);$s['history']=array_slice($s['history'],0,20);}
  }
  if($error){$p->rollBack();fail(422,$error);}
  eq5_sync_active($s);
  $json=json_encode($s,JSON_UNESCAPED_UNICODE);
  if($raw)hub_q($p,'UPDATE eq5_pets SET state=? WHERE user_id=?',[$json,$uid]);
  else hub_q($p,'INSERT INTO eq5_pets(user_id,state) VALUES (?,?)',[$json,$uid]);
  $result=['ok'=>true,'pet'=>eq5_pet_view($s),'foods'=>eq5_food(),'reward'=>$reward,'eggs_catalog'=>eq5_egg_catalog()];
  if($wallet!==null)$result['wallet']=$wallet;
  if($hatch!==null)$result['hatch']=$hatch;
  if($key!=='')hub_q($p,'INSERT INTO eq5_events(user_id,event_key,payload_hash,result,created_at) VALUES (?,?,?,?,?)',[$uid,$key,$hash,json_encode($result,JSON_UNESCAPED_UNICODE),time()]);
  $p->commit();reply(200,$result);
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

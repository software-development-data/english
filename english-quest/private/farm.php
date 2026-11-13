<?php
declare(strict_types=1);
require_once __DIR__.'/wardrobe.php';

function eq5_farm_crops():array{return [
 'wheat'=>['name'=>'Lúa mì','seed'=>12,'seconds'=>60,'yield'=>2,'sell'=>9,'image'=>'/farm/crops/wheat.svg'],
 'tomato'=>['name'=>'Cà chua','seed'=>18,'seconds'=>180,'yield'=>2,'sell'=>14,'image'=>'/farm/crops/tomato.svg'],
 'corn'=>['name'=>'Ngô ngọt','seed'=>24,'seconds'=>300,'yield'=>3,'sell'=>13,'image'=>'/farm/crops/corn.svg'],
 'potato'=>['name'=>'Khoai tây','seed'=>20,'seconds'=>240,'yield'=>2,'sell'=>15,'image'=>'/farm/crops/potato.svg'],
 'cassava'=>['name'=>'Khoai mì','seed'=>28,'seconds'=>420,'yield'=>3,'sell'=>14,'image'=>'/farm/crops/cassava.svg']
];}
function eq5_farm_initial():array{return ['plots'=>array_fill(0,6,null),'inventory'=>['seed:wheat'=>3,'seed:tomato'=>2],'harvested'=>0,'farm_earned'=>0,'updated_at'=>time()];}
function eq5_farm_schema(PDO $p):void{
 $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$int=$mysql?'BIGINT UNSIGNED':'INTEGER';$text=$mysql?'LONGTEXT':'TEXT';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
 $p->exec("CREATE TABLE IF NOT EXISTS eq5_farms(user_id $int PRIMARY KEY,state $text NOT NULL,updated_at $int NOT NULL)$suffix");
}
function eq5_farm(PDO $p,int $uid,string $op,array $in):never{
 if(!in_array($op,['farm_state','farm_plant','farm_water','farm_fertilize','farm_buy_seed','farm_buy_supply','farm_harvest','farm_sell','farm_expand'],true))fail(404,'Không tìm thấy chức năng nông trại.');
 require_once __DIR__.'/v5-schema.php';$mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';foreach(eq_v5_schema($mysql) as $sql)$p->exec($sql);eq5_farm_schema($p);$crops=eq5_farm_crops();$p->beginTransaction();
 try{
  $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$row=hub_q($p,'SELECT state FROM eq5_farms WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetch();
  if(!$row){$state=eq5_farm_initial();hub_q($p,'INSERT INTO eq5_farms(user_id,state,updated_at) VALUES (?,?,?)',[$uid,json_encode($state,JSON_UNESCAPED_UNICODE),time()]);}
  else $state=json_decode($row['state'],true,32,JSON_THROW_ON_ERROR);
  $state+=eq5_farm_initial();$state['plots']=array_pad(array_slice($state['plots'],0,12),6,null);$state['inventory']??=[];
  if($op==='farm_state'){
   foreach($state['plots'] as &$plot)if($plot){$crop=$crops[$plot['crop']]??null;$plot['ready_at']=(int)$plot['planted_at']+(int)$plot['grow_seconds'];$plot['remaining']=max(0,$plot['ready_at']-time());$plot['ready']=$plot['remaining']===0;}unset($plot);
   $p->commit();reply(200,['ok'=>true,'farm'=>$state,'crops'=>$crops]);
  }
  $eventKey=hub_text($in,'key',55);if(!preg_match('/^[A-Za-z0-9_-]{16,55}$/D',$eventKey))fail(422,'Thao tác cần mã xác nhận hợp lệ.');
  $payloadHash=hash('sha256',json_encode([$op,$in],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  $old=hub_q($p,'SELECT payload_hash,result FROM eq5_events WHERE user_id=? AND event_key=?',[$uid,'farm:'.$eventKey])->fetch();
  if($old){if(!hash_equals($old['payload_hash'],$payloadHash)){$p->rollBack();fail(409,'Mã thao tác đã được dùng cho yêu cầu khác.');}$result=json_decode($old['result'],true,32,JSON_THROW_ON_ERROR);$p->commit();reply(200,$result+['duplicate'=>true]);}
  $plotIndex=$in['plot']??null;if(in_array($op,['farm_plant','farm_water','farm_fertilize','farm_harvest'],true)&&(!is_int($plotIndex)||$plotIndex<0||$plotIndex>=count($state['plots']))){$p->rollBack();fail(422,'Ô đất không hợp lệ.');}
  $plot=is_int($plotIndex)?$state['plots'][$plotIndex]:null;$message='';$earned=0;
  if($op==='farm_plant'){
   $cropId=hub_text($in,'crop',24);$crop=$crops[$cropId]??null;if(!$crop||$plot!==null){$p->rollBack();fail(422,'Chọn cây hợp lệ và ô đất trống.');}
   $seedKey='seed:'.$cropId;if((int)($state['inventory'][$seedKey]??0)<1){$p->rollBack();fail(422,'Bạn chưa có hạt giống này. Hãy mua tại cửa hàng nông trại.');}
   $state['inventory'][$seedKey]--;if($state['inventory'][$seedKey]===0)unset($state['inventory'][$seedKey]);$state['plots'][$plotIndex]=['crop'=>$cropId,'planted_at'=>time(),'grow_seconds'=>$crop['seconds'],'watered'=>false,'fertilized'=>false];$message='Đã gieo '.$crop['name'].'. Thời gian thu hoạch do máy chủ tính.';
  }elseif($op==='farm_water'||$op==='farm_fertilize'){
   if(!$plot){$p->rollBack();fail(422,'Ô đất này chưa có cây.');}
   $field=$op==='farm_water'?'watered':'fertilized';$item=$op==='farm_water'?'water':'fertilizer';if(!empty($plot[$field])){$p->rollBack();fail(409,$op==='farm_water'?'Cây đã được tưới.':'Cây đã được bón phân.');}
   if($op==='farm_fertilize'&&(int)($state['inventory']['fertilizer']??0)<1){$p->rollBack();fail(422,'Bạn cần phân bón. Hãy mua trong cửa hàng nông trại.');}
   if($op==='farm_fertilize')$state['inventory']['fertilizer']--; $plot[$field]=true;$plot['grow_seconds']=max(30,(int)$plot['grow_seconds']-($op==='farm_water'?30:60));$state['plots'][$plotIndex]=$plot;$message=$op==='farm_water'?'Đã tưới cây, thời gian chờ giảm 30 giây.':'Đã bón phân, thời gian chờ giảm 60 giây.';
  }elseif($op==='farm_buy_seed'){
   $cropId=hub_text($in,'crop',24);$quantity=$in['quantity']??null;if(!is_int($quantity)||$quantity<1||$quantity>20){$p->rollBack();fail(422,'Số lượng hạt giống chưa hợp lệ.');}$crop=$crops[$cropId]??null;if(!$crop){$p->rollBack();fail(422,'Hạt giống không tồn tại.');}
   $cost=$crop['seed']*$quantity;$pr=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetchColumn();eq5_wallet_sync($p,$uid,$pr?json_decode($pr,true):[]);$wallet=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetch();$w=eq5_wallet_view($wallet);if($w['balance']<$cost){$p->rollBack();fail(422,'Bạn chưa đủ xu để mua hạt giống.');}
   $state['inventory']['seed:'.$cropId]=(int)($state['inventory']['seed:'.$cropId]??0)+$quantity;$walletState=json_decode($wallet['state'],true)?:[];$walletState+=['owned'=>['hair-blue','outfit-academy','shoes-blue','hat-none'],'equipped'=>['hair'=>'hair-blue','outfit'=>'outfit-academy','shoes'=>'shoes-blue','hat'=>'hat-none']];$walletState['spent']=(int)$wallet['spent']+$cost;hub_q($p,'UPDATE eq5_wallet SET spent=?,state=? WHERE user_id=?',[$walletState['spent'],json_encode($walletState,JSON_UNESCAPED_UNICODE),$uid]);$message="Đã mua $quantity gói hạt giống.";
  }elseif($op==='farm_buy_supply'){
   $supply=hub_text($in,'supply',24);$quantity=$in['quantity']??null;if(!is_int($quantity)||$quantity<1||$quantity>20){$p->rollBack();fail(422,'Số lượng vật tư chưa hợp lệ.');}$prices=['fertilizer'=>20];if(!isset($prices[$supply])){$p->rollBack();fail(422,'Vật tư không tồn tại.');}$cost=$prices[$supply]*$quantity;$pr=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetchColumn();eq5_wallet_sync($p,$uid,$pr?json_decode($pr,true):[]);$wallet=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetch();$w=eq5_wallet_view($wallet);if($w['balance']<$cost){$p->rollBack();fail(422,'Bạn chưa đủ xu để mua vật tư.');}$state['inventory'][$supply]=(int)($state['inventory'][$supply]??0)+$quantity;$walletState=json_decode($wallet['state'],true)?:[];$walletState['spent']=(int)$wallet['spent']+$cost;hub_q($p,'UPDATE eq5_wallet SET spent=?,state=? WHERE user_id=?',[$walletState['spent'],json_encode($walletState,JSON_UNESCAPED_UNICODE),$uid]);$message="Đã mua $quantity phân bón.";
  }elseif($op==='farm_harvest'){
   if(!$plot){$p->rollBack();fail(422,'Ô đất không có cây để thu hoạch.');}$crop=$crops[$plot['crop']]??null;$readyAt=(int)$plot['planted_at']+(int)$plot['grow_seconds'];if(!$crop||time()<$readyAt){$p->rollBack();fail(409,'Cây chưa chín. Hãy quay lại sau '.max(1,$readyAt-time()).' giây.');}
   $produceKey='produce:'.$plot['crop'];$state['inventory'][$produceKey]=(int)($state['inventory'][$produceKey]??0)+$crop['yield'];$state['plots'][$plotIndex]=null;$state['harvested']++;$message='Thu hoạch thành công: '.$crop['name'].' × '.$crop['yield'].'.';
  }elseif($op==='farm_sell'){
   $cropId=hub_text($in,'crop',24);$quantity=$in['quantity']??null;if(!is_int($quantity)||$quantity<1||$quantity>100){$p->rollBack();fail(422,'Số lượng nông sản chưa hợp lệ.');}$crop=$crops[$cropId]??null;$produceKey='produce:'.$cropId;if(!$crop||(int)($state['inventory'][$produceKey]??0)<$quantity){$p->rollBack();fail(422,'Số nông sản không đủ để bán.');}
   $state['inventory'][$produceKey]-=$quantity;if($state['inventory'][$produceKey]===0)unset($state['inventory'][$produceKey]);$earned=$crop['sell']*$quantity;$state['farm_earned']=(int)$state['farm_earned']+$earned;$message='Đã bán nông sản và nhận '.$earned.' xu.';
   $pr=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetchColumn();eq5_wallet_sync($p,$uid,$pr?json_decode($pr,true):[]);$wallet=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetch();$walletState=json_decode($wallet['state'],true)?:[];$walletState['farm_earned']=(int)($walletState['farm_earned']??0)+$earned;hub_q($p,'UPDATE eq5_wallet SET earned=earned+?,state=? WHERE user_id=?',[$earned,json_encode($walletState,JSON_UNESCAPED_UNICODE),$uid]);
  }elseif($op==='farm_expand'){
   $count=count($state['plots']);if($count>=12){$p->rollBack();fail(409,'Bạn đã mở tối đa 12 ô đất.');}$cost=120+($count-6)*80;$pr=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetchColumn();eq5_wallet_sync($p,$uid,$pr?json_decode($pr,true):[]);$wallet=hub_q($p,'SELECT * FROM eq5_wallet WHERE user_id=?'.($mysql?' FOR UPDATE':''),[$uid])->fetch();$w=eq5_wallet_view($wallet);if($w['balance']<$cost){$p->rollBack();fail(422,'Bạn chưa đủ xu để mở rộng nông trại.');}$state['plots'][]=null;$walletState=json_decode($wallet['state'],true)?:[];$walletState['spent']=(int)$wallet['spent']+$cost;hub_q($p,'UPDATE eq5_wallet SET spent=?,state=? WHERE user_id=?',[$walletState['spent'],json_encode($walletState,JSON_UNESCAPED_UNICODE),$uid]);$message='Đã mở thêm một ô đất.';
  }
  $state['updated_at']=time();hub_q($p,'UPDATE eq5_farms SET state=?,updated_at=? WHERE user_id=?',[json_encode($state,JSON_UNESCAPED_UNICODE),time(),$uid]);$result=['ok'=>true,'message'=>$message,'farm'=>$state,'earned'=>$earned];hub_q($p,'INSERT INTO eq5_events(user_id,event_key,payload_hash,result,created_at) VALUES (?,?,?,?,?)',[$uid,'farm:'.$eventKey,$payloadHash,json_encode($result,JSON_UNESCAPED_UNICODE),time()]);$p->commit();reply(200,$result);
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

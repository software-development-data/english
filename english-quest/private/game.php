<?php
declare(strict_types=1);
require_once __DIR__.'/catalog.php';
// Included only after authentication/bootstrap in auth.php. Answers never come from the client.
function game_initial(): array {
 return ['version'=>1,'xp'=>0,'diamonds'=>0,'hearts'=>5,'heartAt'=>time(),'reviews'=>[], 'learned'=>[],'completed'=>[],'history'=>[],'days'=>[],'seconds'=>0,'resume'=>null,'wrong'=>[], 'runs'=>[], 'lastTick'=>time(),'daily'=>[],'dailyGoal'=>10,'bookmarks'=>[],'settings'=>['autoNext'=>true,'sound'=>true]];
}
function game_public(array $p): array { unset($p['runs'],$p['lastTick']); if (!$p['reviews']) $p['reviews']=(object)[]; return $p; }
function game_normalize(string $s): string {
 $s=strtolower(trim($s)); $s=str_replace(['’','‘'],"'",$s);
 $s=strtr($s,["i'm"=>'i am',"you're"=>'you are',"we're"=>'we are',"they're"=>'they are',"he's"=>'he is',"she's"=>'she is',"it's"=>'it is',"don't"=>'do not',"doesn't"=>'does not']);
 return trim(preg_replace('/\s+/u',' ',preg_replace('/[.,?!;:]/u','',$s)));
}
function game_award(array &$p,string $id,int $xp): int {
 if(in_array($id,$p['completed'],true)) return 0;
 $p['completed'][]=$id; $p['xp']+=$xp; return $xp;
}
function game_questions(array $t,string $section): array {
 return match($section){'grammar'=>$t['grammarPractice'],'listening'=>[$t['questions'][2]],'vocabulary'=>array_slice($t['practice'],0,15),'test'=>$t['test'],default=>[]};
}
function handle_game(PDO $pdo,bool $mysql,string $action,array $input): never {
 $user=current_account($pdo); if(!$user) fail(401,'Vui lòng đăng nhập để học.');
 $uid=(int)$user['id'];
 $lessons=eq_catalog();
 if($action==='game_catalog'){
  foreach($lessons as &$t) foreach(['practice','grammarPractice','test','questions'] as $list) foreach($t[$list] as &$q){$q['correct']=-99;$q['explanation']='';} unset($q,$t);
  reply(200,['ok'=>true,'lessons'=>$lessons]);
 }
 limit_attempts($pdo,'game:'.$uid,180,60);
 $pdo->beginTransaction();
 try{
  try{$q=$pdo->prepare('INSERT INTO eq_progress(user_id,state) VALUES (?,?)');$q->execute([$uid,json_encode(game_initial())]);}catch(PDOException $e){if($e->getCode()!=='23000')throw $e;}
  $q=$pdo->prepare('SELECT state FROM eq_progress WHERE user_id=?'.($mysql?' FOR UPDATE':''));$q->execute([$uid]);$p=json_decode($q->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
  $p+=['daily'=>[],'dailyGoal'=>10,'bookmarks'=>[]];
  $gain=(int)floor((time()-$p['heartAt'])/1800);if($gain>0){$p['hearts']=min(5,$p['hearts']+$gain);$p['heartAt']=$p['hearts']===5?time():$p['heartAt']+$gain*1800;}
  $result=[];
  if($action==='game_event'){
   $event=field($input,'event');if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/',$event))fail(422,'Mã thao tác chưa hợp lệ.');
   $q=$pdo->prepare('SELECT result FROM eq_game_events WHERE user_id=? AND event_id=?');$q->execute([$uid,$event]);$cached=$q->fetchColumn();
   if($cached!==false){$pdo->commit();reply(200,['ok'=>true,'progress'=>game_public($p),'result'=>json_decode($cached,true)]);}
   $op=field($input,'op');$topicId=field($input,'topic');$t=null;foreach($lessons as $candidate)if($candidate['id']===$topicId)$t=$candidate;
   if(in_array($op,['begin','learn','unknown','resume'],true)&&!$t)fail(422,'Không tìm thấy bài học.');
   if($op==='begin'){
    $section=field($input,'section');$qs=game_questions($t,$section);if(!$qs)fail(422,'Bài luyện chưa hợp lệ.');
    $challenge=$section==='test'&&($input['challenge']??false)===true;
    if($challenge&&$p['hearts']<1)fail(409,'Bạn đã hết trái tim. Hãy chọn luyện tập không giới hạn.');
    $run=bin2hex(random_bytes(16));$p['runs'][$run]=['topic'=>$topicId,'section'=>$section,'index'=>0,'answers'=>[],'challenge'=>$challenge];
    if(count($p['runs'])>8)array_shift($p['runs']);$result=['run'=>$run];
   }elseif($op==='answer'){
    $run=field($input,'run');$r=$p['runs'][$run]??null;if(!$r)fail(409,'Bài luyện đã kết thúc hoặc quá cũ. Hãy mở lại bài.');
    if(($input['index']??null)!==$r['index'])fail(409,'Câu hỏi đã thay đổi. Hãy mở lại bài.');
    foreach($lessons as $candidate)if($candidate['id']===$r['topic'])$t=$candidate;
    $qs=game_questions($t,$r['section']);$question=$qs[$r['index']];
    if($r['challenge']&&$p['hearts']<1)fail(409,'Hết trái tim. Chọn luyện tập để tiếp tục học.');
    $answer=$input['answer']??null;
    if(isset($question['kind'])){if(!is_string($answer)||strlen($answer)>500)fail(422,'Câu trả lời chưa hợp lệ.');$correct=game_normalize($answer)===game_normalize($question['options'][$question['correct']]);}
    else{if(!is_int($answer)||$answer<0||$answer>=count($question['options']))fail(422,'Lựa chọn chưa hợp lệ.');$correct=$answer===$question['correct'];}
    $qid=hash('sha256',json_encode([$question['prompt'],$question['options']]));$xp=0;
    if($correct){$xp=game_award($p,'answer-'.$qid,10);$p['wrong']=array_values(array_diff($p['wrong'],[$qid]));}
    else{if(!in_array($qid,$p['wrong'],true))$p['wrong'][]=$qid;if($r['challenge']){if($p['hearts']===5)$p['heartAt']=time();$p['hearts']--;}}
    $r['answers'][]=$correct;$r['index']++;
    $done=$r['index']===count($qs)||($r['challenge']&&$p['hearts']===0);
    if($done){$score=count(array_filter($r['answers']));if($score===count($qs)){$bonus=game_award($p,($r['section']==='vocabulary'?'practice':$r['section']).'-'.$r['topic'],50);if($bonus)$p['diamonds']++;$xp+=$bonus;}$p['history']=array_slice([['title'=>$t['title'],'score'=>$score,'total'=>count($qs),'date'=>date('c')],...$p['history']],0,100);unset($p['runs'][$run]);}
    else $p['runs'][$run]=$r;
    $result=['correct'=>$correct,'correctIndex'=>$question['correct'],'explanation'=>$question['explanation'],'xp'=>$xp,'finished'=>$done,'outOfHearts'=>$r['challenge']&&$p['hearts']===0];
   }elseif($op==='learn'||$op==='unknown'){
    $i=$input['index']??-1;if(!is_int($i)||!isset($t['words'][$i]))fail(422,'Thẻ từ chưa hợp lệ.');$id=$topicId.'-'.$i;
    $interval=$op==='unknown'?0:min(30,max(1,($p['reviews'][$id]['interval']??0)*2));$p['reviews'][$id]=['interval'=>$interval,'due'=>date('Y-m-d',time()+$interval*86400)];
    $xp=0;if($op==='learn'&&!in_array($id,$p['learned'],true)){$p['learned'][]=$id;$p['xp']+=10;$xp=10;}
    $all=true;foreach($t['words'] as $j=>$w)if(!in_array($topicId.'-'.$j,$p['learned'],true))$all=false;
    if($all){$bonus=game_award($p,'vocabulary-'.$topicId,50);if($bonus){$p['diamonds']++;$p['history']=array_slice([['title'=>'Từ vựng · '.$t['title'],'score'=>count($t['words']),'total'=>count($t['words']),'date'=>date('c')],...$p['history']],0,100);}$xp+=$bonus;}$result=['xp'=>$xp];
   }elseif($op==='bookmark'){
    if(!$t)fail(422,'Không tìm thấy chủ đề.');
    $i=$input['index']??-1;if(!is_int($i)||!isset($t['words'][$i])||!is_bool($input['saved']??null))fail(422,'Từ đã lưu chưa hợp lệ.');
    $id=$topicId.'-'.$i;
    $p['bookmarks']=array_values(array_diff($p['bookmarks'],[$id]));
    if($input['saved']){if(count($p['bookmarks'])>=500)fail(422,'Sổ từ đã đủ 500 từ.');$p['bookmarks'][]=$id;}
   }elseif($op==='goal'){
    $goal=$input['goal']??null;if(!is_int($goal)||!in_array($goal,[5,10,20,30],true))fail(422,'Mục tiêu chưa hợp lệ.');$p['dailyGoal']=$goal;
   }elseif($op==='resume'){
    $section=field($input,'section');if(!in_array($section,['vocabulary','grammar','listening','test'],true))fail(422,'Mục học chưa hợp lệ.');$p['resume']=['section'=>$section,'topic'=>$topicId];
   }elseif($op==='settings'){
    foreach(['autoNext','sound'] as $setting)if(isset($input[$setting])&&is_bool($input[$setting]))$p['settings'][$setting]=$input[$setting];
   }elseif($op==='tick'){
    $p['seconds']+=min(15,max(0,time()-$p['lastTick']));$p['lastTick']=time();
   }else fail(422,'Thao tác không được hỗ trợ.');
   if(in_array($op,['answer','learn','unknown'],true)){
    $today=date('Y-m-d');if(!in_array($today,$p['days'],true))$p['days'][]=$today;
    $p['daily'][$today]=($p['daily'][$today]??0)+1;
    $p['daily']=array_slice($p['daily'],-90,null,true);
   }
   $q=$pdo->prepare('INSERT INTO eq_game_events(user_id,event_id,result) VALUES (?,?,?)');$q->execute([$uid,$event,json_encode($result)]);
  }
  $q=$pdo->prepare('UPDATE eq_progress SET state=? WHERE user_id=?');$q->execute([json_encode($p,JSON_UNESCAPED_UNICODE),$uid]);require_once __DIR__.'/wardrobe.php';eq5_wallet_sync($pdo,$uid,$p);$pdo->commit();
  reply(200,['ok'=>true,'progress'=>game_public($p),'result'=>$result]);
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

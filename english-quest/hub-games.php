<?php
declare(strict_types=1);
require_once __DIR__.'/catalog.php';require_once __DIR__.'/game-guides.php';require_once __DIR__.'/game.php';
function hub_run(PDO $p,int $uid,string $id,bool $lock=false): array {$r=hub_q($p,'SELECT * FROM eqx_runs WHERE id=? AND user_id=?'.($lock&&$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id,$uid])->fetch();if(!$r)fail(404,'Không tìm thấy phiên chơi của bạn.');$r['state']=json_decode($r['state'],true,64,JSON_THROW_ON_ERROR);return $r;}
function hub_public_run(array $r): array {
 $s=$r['state'];$public=['id'=>$r['id'],'game'=>$r['game'],'index'=>$s['index'],'correct'=>$s['correct'],'attempts'=>$s['attempts'],'total'=>5,'finished'=>(bool)$r['finished'],'reward'=>$s['reward']??null,'started_at'=>(int)$r['started_at']];
 if($r['game']==='memory'){$public['deck']=array_map(fn($c)=>$c['text'],$s['deck']);$public['matched']=$s['matched'];}
 elseif(!$r['finished']){$q=$s['questions'][$s['index']];$public['question']=['prompt'=>$q['prompt'],'options'=>$q['options'],'dialogue'=>$q['dialogue']??null];}
 $public['last']=$s['last']??null;return $public;
}
function hub_game_reward(PDO $p,int $uid,string $id,int $correct): array {
 $old=hub_q($p,'SELECT xp,coins FROM eqx_rewards WHERE run_id=?',[$id])->fetch();if($old)return $old;
 // Lock the shared progress row so learning and minigame writes cannot lose XP.
 try{hub_q($p,'INSERT INTO eq_progress(user_id,state) VALUES (?,?)',[$uid,json_encode(game_initial())]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}
 $state=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?'.($p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$uid])->fetchColumn();$progress=json_decode($state,true,512,JSON_THROW_ON_ERROR);
 $used=(int)hub_q($p,'SELECT COALESCE(SUM(xp),0) FROM eqx_rewards WHERE user_id=? AND day=?',[$uid,date('Y-m-d')])->fetchColumn();$xp=min(max(0,100-$used),$correct*5);$coins=$xp;
 hub_q($p,'INSERT INTO eqx_rewards(run_id,user_id,day,xp,coins) VALUES (?,?,?,?,?)',[$id,$uid,date('Y-m-d'),$xp,$coins]);
 $progress['xp']+=$xp;$today=date('Y-m-d');if(!in_array($today,$progress['days'],true))$progress['days'][]=$today;
 $progress['daily']??=[];$progress['daily'][$today]=($progress['daily'][$today]??0)+5;$progress['daily']=array_slice($progress['daily'],-90,null,true);
 $progress['history']=array_slice([['title'=>'Minigame','score'=>$correct,'total'=>5,'date'=>date('c')],...$progress['history']],0,100);
 hub_q($p,'UPDATE eq_progress SET state=? WHERE user_id=?',[json_encode($progress,JSON_UNESCAPED_UNICODE),$uid]);
 try{hub_q($p,'INSERT INTO eqx_wallets(user_id,coins) VALUES (?,0)',[$uid]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}
 hub_q($p,'UPDATE eqx_wallets SET coins=coins+? WHERE user_id=?',[$coins,$uid]);return ['xp'=>$xp,'coins'=>$coins];
}
function hub_verified_context(PDO $p,int $uid,array $in): array {
 $game=field($in,'game')?:'learn';$guides=hub_guides();if(!isset($guides[$game]))fail(422,'Game chưa được nhận diện.');$guide=$guides[$game];$ctx=['game'=>$game,'name'=>$guide['name'],'installed'=>$guide['enabled'],'rules'=>$guide,'verified'=>true,'active_question'=>false];
 $profile=hub_profile($p,$uid);$ctx['learner_level']=$profile['level'];$ctx['learning_goal']=$profile['goal'];
 $ctx['available_games']=array_keys(array_filter($guides,fn($g)=>$g['enabled']));
 $ctx['reward_policy']=$game==='learn'?['new_word_xp'=>10,'new_correct_answer_xp'=>10,'completion_bonus_xp'=>50,'repeat_rewards'=>false]:['xp_per_correct'=>5,'max_minigame_xp_per_day'=>100,'coins_per_xp'=>1,'awarded_on_completion'=>true];
 if($game!=='learn')$ctx['remaining_daily_xp']=max(0,100-(int)hub_q($p,'SELECT COALESCE(SUM(xp),0) FROM eqx_rewards WHERE user_id=? AND day=?',[$uid,date('Y-m-d')])->fetchColumn());
 $id=field($in,'run');
 if($game==='learn'){
  $raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$uid])->fetchColumn();$pr=$raw?json_decode($raw,true):[];
  $ctx['xp']=(int)($pr['xp']??0);$ctx['hearts']=(int)($pr['hearts']??5);$ctx['weak_questions']=count($pr['wrong']??[]);$ctx['due_cards']=count(array_filter($pr['reviews']??[],fn($r)=>$r['due']<=date('Y-m-d')));$ctx['recent_history']=array_slice($pr['history']??[],0,3);
  if($id!==''){if(!isset($pr['runs'][$id])){$ctx['next_action']='Phiên học đã kết thúc; xem lịch sử hoặc chọn bài mới.';}else{$r=$pr['runs'][$id];$ctx['active_question']=true;$ctx['section']=$r['section'];$ctx['index']=$r['index'];$ctx['challenge']=$r['challenge'];$ctx['next_action']='Đọc/nghe câu hiện tại và tự chọn đáp án. Chỉ giải thích đáp án sau khi đã nộp.';}}
  else $ctx['next_action']='Chọn chủ đề, hoặc mở Sổ tay để ôn thẻ đến hạn.';
 }elseif($guide['enabled']&&$id!==''){
  $r=hub_run($p,$uid,$id);if($r['game']!==$game)fail(422,'Phiên chơi không khớp game.');$s=$r['state'];$ctx['finished']=(bool)$r['finished'];$ctx['score']=$s['correct'];$ctx['attempts']=$s['attempts'];$ctx['index']=$s['index'];$ctx['last_result']=$s['last']??null;$ctx['active_question']=!(bool)$r['finished'];$ctx['reward']=$s['reward']??null;$ctx['next_action']=$r['finished']?'Xem kết quả hoặc bắt đầu phiên mới.':($game==='memory'?'Lật hai thẻ để tìm cặp Anh–Việt.':'Tự chọn một đáp án cho câu hỏi hiện tại.');
 }else $ctx['next_action']=$guide['enabled']?'Nhấn Bắt đầu để tạo phiên chơi.':'Game chưa tích hợp; chọn một game đang hoạt động.';
 $ctx['next_action_en']=!$guide['enabled']?'This game is not integrated. Choose an available game.':($ctx['active_question']?($game==='memory'?'Flip two cards and check the English–Vietnamese pair.':'Read or listen to the task and choose your own answer.'):(!empty($ctx['finished'])?'Review your result or start another session.':'Start a session, choose a lesson, or review due vocabulary.'));
 return $ctx;
}
function hub_games(PDO $p,bool $mysql,int $uid,array $config,string $op,array $in): never {
 if($op==='game_list')reply(200,['ok'=>true,'games'=>hub_guides(),'daily_xp_cap'=>100,'coins'=>(int)hub_q($p,'SELECT coins FROM eqx_wallets WHERE user_id=?',[$uid])->fetchColumn()]);
 if($op==='game_leaderboard')reply(200,['ok'=>true,'leaders'=>hub_q($p,'SELECT u.id,u.display_name,SUM(r.xp) AS xp FROM eqx_rewards r JOIN eq_users u ON u.id=r.user_id JOIN eqx_profiles x ON x.user_id=u.id WHERE r.day=? AND x.suspended=0 AND x.discover=1 GROUP BY u.id,u.display_name ORDER BY xp DESC LIMIT 20',[date('Y-m-d')])->fetchAll()]);
 if($op==='game_start'){
  $game=field($in,'game');if(!in_array($game,['vocabulary','grammar','listening','reaction','memory'],true))fail(422,'Game này chưa tích hợp.');limit_attempts($p,'gamestart:'.$uid,40,3600);$catalog=eq_catalog();$pool=[];
  if($game==='memory'){
   foreach($catalog as $t)foreach($t['words'] as $w)$pool[]=$w;shuffle($pool);$pool=array_slice($pool,0,5);$deck=[];foreach($pool as $i=>$w){$deck[]=['pair'=>$i,'text'=>$w['en']];$deck[]=['pair'=>$i,'text'=>$w['vi']];}shuffle($deck);$state=['deck'=>$deck,'matched'=>[],'index'=>0,'correct'=>0,'attempts'=>0];
  }else{
   foreach($catalog as $t){$qs=$game==='grammar'?$t['grammarPractice']:($game==='listening'?[$t['questions'][2]]:$t['practice']);foreach($qs as $q){if(isset($q['kind']))continue;if($game==='listening')$q['dialogue']=array_column($t['dialogue'],1);$pool[]=$q;}}
   shuffle($pool);$state=['questions'=>array_slice($pool,0,5),'index'=>0,'correct'=>0,'attempts'=>0];
  }
  $id=bin2hex(random_bytes(16));hub_q($p,'INSERT INTO eqx_runs(id,user_id,game,state,started_at,updated_at) VALUES (?,?,?,?,?,?)',[$id,$uid,$game,json_encode($state,JSON_UNESCAPED_UNICODE),time(),time()]);reply(200,['ok'=>true,'run'=>hub_public_run(hub_run($p,$uid,$id))]);
 }
 if($op==='game_state')reply(200,['ok'=>true,'run'=>hub_public_run(hub_run($p,$uid,hub_text($in,'run',40)))]);
 if($op==='game_answer'){
  $id=hub_text($in,'run',40);$p->beginTransaction();try{
   $r=hub_run($p,$uid,$id,true);$s=$r['state'];if($r['finished']){$p->commit();reply(200,['ok'=>true,'run'=>hub_public_run($r)]);}
   $challenge=hub_q($p,"SELECT i.id FROM eqd_challenges c JOIN eqd_invitations i ON i.id=c.invite_id WHERE (c.sender_run=? OR c.recipient_run=?) AND i.status='accepted'",[$id,$id])->fetchColumn();if(time()-(int)$r['started_at']>($challenge?86400:900)){$p->rollBack();fail(409,'Phiên chơi đã hết hạn (15 phút thường / 24 giờ thách đấu).');}
   if(($in['attempt']??null)!==$s['attempts']){$p->rollBack();fail(409,'Lượt đã thay đổi; tải lại phiên chơi.');}
   if($r['game']==='memory'){
    $a=$in['a']??null;$b=$in['b']??null;if(!is_int($a)||!is_int($b)||$a===$b||!isset($s['deck'][$a],$s['deck'][$b])||in_array($a,$s['matched'],true)||in_array($b,$s['matched'],true)){$p->rollBack();fail(422,'Cặp thẻ chưa hợp lệ.');}
    $correct=$s['deck'][$a]['pair']===$s['deck'][$b]['pair'];if($correct){$s['matched'][]=$a;$s['matched'][]=$b;$s['correct']++;$s['index']++;}$s['last']=['correct'=>$correct,'explanation'=>$correct?'Bạn đã ghép đúng một cặp Anh–Việt.':'Hai thẻ này không cùng nghĩa. Ghi nhớ vị trí và thử lại.'];
   }else{
    $q=$s['questions'][$s['index']];$answer=$in['answer']??null;if(!is_int($answer)||!isset($q['options'][$answer])){$p->rollBack();fail(422,'Lựa chọn chưa hợp lệ.');}$correct=$q['correct']===$answer;$s['correct']+=(int)$correct;$s['index']++;$s['last']=['correct'=>$correct,'explanation'=>$q['explanation'],'answer'=>$q['options'][$q['correct']]];
   }
   $s['attempts']++;$finished=$s['index']>=5;if($finished)$s['reward']=hub_game_reward($p,$uid,$id,$s['correct']);
   hub_q($p,'UPDATE eqx_runs SET state=?,updated_at=?,finished=? WHERE id=?',[json_encode($s,JSON_UNESCAPED_UNICODE),time(),$finished?1:0,$id]);$p->commit();reply(200,['ok'=>true,'run'=>hub_public_run(hub_run($p,$uid,$id))]);
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 }
 if($op==='assistant_context')reply(200,['ok'=>true,'context'=>hub_verified_context($p,$uid,$in),'preferences'=>hub_q($p,'SELECT language,mode FROM eqx_assistant_preferences WHERE user_id=?',[$uid])->fetch()?:['language'=>'vi','mode'=>'normal']]);
 if($op==='assistant_preferences'){
  $lang=field($in,'language');$mode=field($in,'mode');if(!in_array($lang,['vi','en'],true)||!in_array($mode,['novice','normal','advanced'],true))fail(422,'Tùy chọn chưa hợp lệ.');
  $p->beginTransaction();try{hub_q($p,'DELETE FROM eqx_assistant_preferences WHERE user_id=?',[$uid]);hub_q($p,'INSERT INTO eqx_assistant_preferences(user_id,language,mode) VALUES (?,?,?)',[$uid,$lang,$mode]);$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}reply(200,['ok'=>true]);
 }
 if($op==='assistant_history')reply(200,['ok'=>true,'history'=>hub_q($p,'SELECT game,question,answer,source,created_at FROM eqx_support WHERE user_id=? ORDER BY id DESC LIMIT 30',[$uid])->fetchAll()]);
 if($op==='assistant'){
  limit_attempts($p,'assistant:'.$uid,12,60);limit_attempts($p,'assistant-hour:'.$uid,120,3600);$ctx=hub_verified_context($p,$uid,$in);$question=hub_text($in,'question',1600);$pref=hub_q($p,'SELECT language,mode FROM eqx_assistant_preferences WHERE user_id=?',[$uid])->fetch()?:['language'=>'vi','mode'=>'normal'];$ctx['language']=$pref['language'];$ctx['guidance_mode']=$pref['mode'];$guide=$ctx['rules'];
  $english=$pref['language']==='en';
  $basic=($english?$guide['en']:$guide['vi'])."\n\n".($english?'Next action: ':'Tiếp theo: ').($english?$ctx['next_action_en']:$ctx['next_action']);
  if(isset($ctx['last_result']['explanation']))$basic.="\n\n".$ctx['last_result']['explanation'];
  $steps=$english?$guide['steps_en']:$guide['steps'];
  if($pref['mode']==='novice')$basic.="\n\n".implode("\n",array_map(fn($i,$s)=>($i+1).'. '.$s,array_keys($steps),$steps));
  $intent=field($in,'intent');if($intent==='tips')$basic=($english?$guide['tips_en']:$guide['tips'])."\n\n".$basic;
  $res=['source'=>'guide','answer'=>$basic];
  // Active questions never reach the model, so prompt injection cannot reveal an answer.
  if(!$ctx['active_question']&&$guide['enabled']&&$intent==='chat'){
   require_once __DIR__.'/hub-ai.php';$ai=hub_ai($p,$uid,$config,'game_assistant',$question,$ctx);$res=$ai+['answer'=>$basic];
  }elseif($ctx['active_question'])$res['reason']=$pref['language']==='en'?'During an active question, only rule-based guidance is available.':'Đang trong lượt chơi: chỉ hướng dẫn luật và thao tác, không tiết lộ đáp án.';
  hub_q($p,'INSERT INTO eqx_support(user_id,game,run_id,question,answer,source,created_at) VALUES (?,?,?,?,?,?,?)',[$uid,$ctx['game'],field($in,'run')?:null,$question,$res['answer'],$res['source'],time()]);reply(200,['ok'=>true]+$res);
 }
 if($op==='tutor'){
  require_once __DIR__.'/hub-ai.php';$task=field($in,'task');if(!in_array($task,['tutor','correct','translate','plan','exercise'],true))fail(422,'Chọn tác vụ AI.');limit_attempts($p,'tutor:'.$uid,8,60);$profile=hub_profile($p,$uid);$ctx=['level'=>$profile['level'],'goal'=>$profile['goal'],'minutes'=>(int)$profile['minutes'],'language'=>'vi'];
  if($task==='plan'){$raw=hub_q($p,'SELECT state FROM eq_progress WHERE user_id=?',[$uid])->fetchColumn();$pr=$raw?json_decode($raw,true):[];$ctx['recent_history']=array_slice($pr['history']??[],0,5);$ctx['weak_count']=count($pr['wrong']??[]);}
  $question=hub_text($in,'question',5000);
  if($task==='tutor'){
   $previous=hub_q($p,"SELECT question,answer FROM eqx_support WHERE user_id=? AND game='tutor' ORDER BY id DESC LIMIT 3",[$uid])->fetchAll();$ctx['recent_dialogue']=[];
   foreach(array_reverse($previous) as $turn){preg_match('/^.{0,600}/us',$turn['question'],$q);preg_match('/^.{0,1000}/us',$turn['answer'],$a);$ctx['recent_dialogue'][]=['user'=>$q[0]??'','assistant'=>$a[0]??''];}
  }
  $res=hub_ai($p,$uid,$config,$task,$question,$ctx);if($res['source']!=='gemini')fail(503,$res['reason']);
  hub_q($p,'INSERT INTO eqx_support(user_id,game,question,answer,source,created_at) VALUES (?,?,?,?,?,?)',[$uid,'tutor',$question,$res['answer'],'gemini',time()]);reply(200,['ok'=>true]+$res);
 }
 if($op==='tutor_history')reply(200,['ok'=>true,'turns'=>array_reverse(hub_q($p,"SELECT question,answer,created_at FROM eqx_support WHERE user_id=? AND game='tutor' ORDER BY id DESC LIMIT 10",[$uid])->fetchAll())]);
 if($op==='speaking_save'){limit_attempts($p,'speaking:'.$uid,60,3600);hub_q($p,'INSERT INTO eqx_speaking(user_id,sample,transcript,created_at) VALUES (?,?,?,?)',[$uid,hub_text($in,'sample',2000),hub_text($in,'transcript',2000),time()]);reply(200,['ok'=>true]);}
 if($op==='speaking_history')reply(200,['ok'=>true,'history'=>hub_q($p,'SELECT sample,transcript,created_at FROM eqx_speaking WHERE user_id=? ORDER BY id DESC LIMIT 30',[$uid])->fetchAll()]);
 fail(404,'Chức năng học tập chưa được hỗ trợ.');
}

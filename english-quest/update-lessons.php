<?php
declare(strict_types=1);
// Server CLI only. Never expose an HTTP endpoint that spends Gemini quota.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__.'/gemini.php';
$mode=$argv[1]??'--update';
$known=['--update','--status','--check','--publish'];
if(!in_array($mode,$known,true)){fwrite(STDERR,"Usage: php update-lessons.php [--update|--status|--check|--publish ai-ID]\n");exit(2);}
$dir=__DIR__.'/data';if(!is_dir($dir))mkdir($dir,0700,true);
$lock=fopen($dir.'/update.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo "Another update is running.\n";exit;}
$stateFile=$dir.'/update-state.json';
$state=is_file($stateFile)?json_decode(file_get_contents($stateFile),true,32,JSON_THROW_ON_ERROR):[];
try {
    if($mode==='--status'){echo json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit;}
    $path=getenv('EQ_CONFIG_FILE')?:__DIR__.'/config.php';$config=require $path;
    if($mode==='--check'){
        $g=$config['gemini']??[];
        echo 'PHP '.PHP_VERSION.'; curl='.(extension_loaded('curl')?'yes':'no').'; key='.((getenv('GEMINI_API_KEY')?:($g['api_key']??''))!==''?'configured':'missing').'; data_writable='.(is_writable($dir)?'yes':'no')."\n";exit;
    }
    if($mode==='--publish'){
        $id=$argv[2]??'';
        if(!preg_match('/^ai-[a-f0-9]{20}$/D',$id))throw new RuntimeException('INVALID_DRAFT_ID');
        $draft=$dir.'/drafts/'.$id.'.json';if(!is_file($draft))throw new RuntimeException('DRAFT_NOT_FOUND');
        $topic=json_decode(file_get_contents($draft),true,64,JSON_THROW_ON_ERROR);
        eq_atomic_json($dir.'/published/'.$id.'.json',$topic);unlink($draft);echo "Published $id\n";exit;
    }
    $g=$config['gemini']??[];
    if(empty($g['enabled'])){echo "Gemini updates are disabled in config.\n";exit;}
    if(time()<($state['next_attempt_at']??0)){echo "Skipped: update interval or retry cooldown.\n";exit;}
    $catalog=eq_catalog();
    if(count($catalog)>=1000)throw new RuntimeException('CATALOG_LIMIT_REACHED');
    // Persist reservation before calling API: crash/repeated cron cannot exhaust quota.
    $hours=max(6,min(168,(int)($g['interval_hours']??24)));
    $state['last_attempt']=date('c');$state['next_attempt_at']=time()+$hours*3600;
    $state['status']='running';eq_atomic_json($stateFile,$state);
    $topic=eq_gemini_generate($config,$catalog);
    foreach($catalog as $t)if(strtolower(trim($t['title']))===strtolower(trim($topic['title']))||$t['id']===$topic['id'])throw new RuntimeException('DUPLICATE_TOPIC');
    $dest=($g['auto_publish']??true)?'published':'drafts';
    eq_atomic_json($dir.'/'.$dest.'/'.$topic['id'].'.json',$topic);
    $state['status']=$dest==='published'?'published':'draft';$state['last_success']=date('c');$state['last_topic']=$topic['id'];unset($state['last_error']);
    eq_atomic_json($stateFile,$state);echo "Saved one lesson to $dest: ".$topic['id']."\n";
}catch(Throwable $e){
    $code=$e->getMessage();if(!preg_match('/^[A-Z0-9_]+$/D',$code))$code='INVALID_CONTENT_OR_LOCAL_ERROR';
    $state['status']='error';$state['last_error']=$code;$state['next_attempt_at']=max($state['next_attempt_at']??0,time()+3600);
    eq_atomic_json($stateFile,$state);fwrite(STDERR,"Update failed: $code. Existing lessons preserved.\n");exit(1);
}finally{flock($lock,LOCK_UN);fclose($lock);}

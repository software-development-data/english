<?php
declare(strict_types=1);
function eq_catalog(): array {
    $all=[];
    $files=[__DIR__.'/lessons.json',__DIR__.'/extra-lessons.json'];
    $generated=glob(__DIR__.'/data/published/*.json') ?: []; sort($generated);
    foreach(array_merge($files,$generated) as $file){
        if(!is_file($file)) continue;
        try {
            $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            if(isset($data['id'])) $data=[$data];
            foreach($data as $topic){
                if(!is_array($topic)||!isset($topic['id'],$topic['words'],$topic['questions'][2],$topic['grammarPractice'],$topic['test'],$topic['practice'])) throw new RuntimeException('Invalid catalog');
                foreach(['practice','questions','test','grammarPractice'] as $list){
                    if(!is_array($topic[$list]))throw new RuntimeException('Invalid question list');
                    foreach($topic[$list] as $q)if(!is_array($q)||!is_array($q['options']??null)||!is_int($q['correct']??null)||!isset($q['options'][$q['correct']]))throw new RuntimeException('Invalid question');
                }
                if(isset($all[$topic['id']])) continue;
                $all[$topic['id']]=$topic;
            }
        } catch(Throwable $e){
            // A damaged generated file must not take the original catalog offline.
            if(in_array($file,$files,true)) throw $e;
            error_log('ENGLISH QUEST: skipped invalid generated catalog file '.basename($file));
        }
    }
    return array_values($all);
}
function eq_atomic_json(string $file,array $data): void {
    $dir=dirname($file);
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)) throw new RuntimeException('Cannot create private data directory');
    $tmp=tempnam($dir,'.eq-');
    if($tmp===false) throw new RuntimeException('Cannot create private data file');
    try {
        if(file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Cannot write private data');
        chmod($tmp,0600);
        if(!rename($tmp,$file)) throw new RuntimeException('Cannot replace private data');
    }finally{if(is_file($tmp))unlink($tmp);}
}
function eq_text(mixed $value,int $max=1200): string {
    if(!is_string($value)||trim($value)===''||strlen($value)>$max||!preg_match('//u',$value)||preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/u',$value)) throw new RuntimeException('Invalid lesson text');
    return trim($value);
}
function eq_question(mixed $q): array {
    if(!is_array($q)||!isset($q['options'])||!is_array($q['options'])||!array_is_list($q['options'])||count($q['options'])!==4||!is_int($q['correct']??null)||$q['correct']<0||$q['correct']>3) throw new RuntimeException('Invalid answer choices');
    $options=array_map(fn($o)=>eq_text($o,500),$q['options']);
    if(count(array_unique(array_map(fn($o)=>strtolower($o),$options)))!==4) throw new RuntimeException('Duplicate answer choices');
    return ['prompt'=>eq_text($q['prompt']??null),'options'=>$options,'correct'=>$q['correct'],'explanation'=>eq_text($q['explanation']??null,1800)];
}
function eq_validate_generated(array $raw): array {
    $t=[];
    foreach(['title','subtitle'] as $k)$t[$k]=eq_text($raw[$k]??null,180);
    $t['emoji']='✨';
    $t['level']=$raw['level']??'';
    if(!in_array($t['level'],['A1','A2','B1'],true))throw new RuntimeException('Invalid CEFR level');
    if(!is_array($raw['words']??null)||count($raw['words'])!==10)throw new RuntimeException('Expected ten words');
    $t['words']=[];
    foreach($raw['words'] as $w){$word=[];foreach(['en','vi','example','translation'] as $k)$word[$k]=eq_text($w[$k]??null,700);$word['ipa']='';$t['words'][]=$word;}
    if(count(array_unique(array_map(fn($w)=>strtolower($w['en']),$t['words'])))!==10||count(array_unique(array_column($t['words'],'vi')))!==10)throw new RuntimeException('Repeated vocabulary');
    if(!is_array($raw['dialogue']??null)||count($raw['dialogue'])!==4)throw new RuntimeException('Expected four dialogue turns');
    $t['dialogue']=[];
    foreach($raw['dialogue'] as $d){$t['dialogue'][]=[eq_text($d['speaker']??null,50),eq_text($d['en']??null,700),eq_text($d['vi']??null,700)];}
    $t['grammar']=[];foreach(['title','rule','example','note'] as $k)$t['grammar'][$k]=eq_text($raw['grammar'][$k]??null,1000);
    if(!is_array($raw['grammarPractice']??null)||count($raw['grammarPractice'])!==5)throw new RuntimeException('Expected five grammar questions');
    $t['grammarPractice']=array_map('eq_question',$raw['grammarPractice']);
    $t['questions']=[$t['grammarPractice'][0],$t['grammarPractice'][1],eq_question($raw['listeningQuestion']??null)];
    $vocab=[];$reverse=[];
    foreach($t['words'] as $i=>$w){
        $opts=[$w['vi'],$t['words'][($i+1)%10]['vi'],$t['words'][($i+2)%10]['vi'],$t['words'][($i+3)%10]['vi']];
        $n=$i%4;$opts=array_merge(array_slice($opts,$n),array_slice($opts,0,$n));
        $vocab[]=['prompt'=>'“'.$w['en'].'” có nghĩa là gì?','options'=>$opts,'correct'=>array_search($w['vi'],$opts,true),'explanation'=>$w['example'].' — '.$w['translation']];
        $reverse[]=['prompt'=>'Chọn tiếng Anh cho “'.$w['vi'].'”.','options'=>[$t['words'][($i+2)%10]['en'],$w['en'],$t['words'][($i+3)%10]['en'],$t['words'][($i+4)%10]['en']],'correct'=>1,'explanation'=>$w['vi'].' = '.$w['en']];
    }
    $t['practice']=array_merge($vocab,array_slice($reverse,0,5));
    $t['test']=array_merge(array_slice($vocab,0,5),$t['grammarPractice'],array_slice($reverse,0,5));
    $t['id']='ai-'.substr(hash('sha256',json_encode([$t['title'],$t['words']],JSON_UNESCAPED_UNICODE)),0,20);
    $t['source']='gemini';$t['created_at']=date('c');
    return $t;
}

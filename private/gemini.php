<?php
declare(strict_types=1);
require_once __DIR__.'/catalog.php';
function eq_gemini_schema(): array {
    $s=['type'=>'string'];
    $obj=fn($p)=>['type'=>'object','properties'=>$p,'required'=>array_keys($p),'additionalProperties'=>false];
    $arr=fn($i,$n)=>['type'=>'array','items'=>$i,'minItems'=>$n,'maxItems'=>$n];
    $q=$obj(['prompt'=>$s,'options'=>$arr($s,4),'correct'=>['type'=>'integer','minimum'=>0,'maximum'=>3],'explanation'=>$s]);
    return $obj(['title'=>$s,'subtitle'=>$s,'level'=>['type'=>'string','enum'=>['A1','A2','B1']],
        'words'=>$arr($obj(['en'=>$s,'vi'=>$s,'example'=>$s,'translation'=>$s]),10),
        'dialogue'=>$arr($obj(['speaker'=>$s,'en'=>$s,'vi'=>$s]),4),
        'grammar'=>$obj(['title'=>$s,'rule'=>$s,'example'=>$s,'note'=>$s]),
        'grammarPractice'=>$arr($q,5),'listeningQuestion'=>$q]);
}
function eq_gemini_generate(array $config,array $catalog): array {
    $g=$config['gemini']??[];$key=getenv('GEMINI_API_KEY')?:($g['api_key']??'');
    if($key===''||str_starts_with($key,'THAY_'))throw new RuntimeException('GEMINI_KEY_MISSING');
    if(!extension_loaded('curl'))throw new RuntimeException('PHP_CURL_MISSING');
    $model=getenv('GEMINI_MODEL')?:($g['model']??'gemini-3.5-flash-lite');
    if(!preg_match('/^gemini-[a-zA-Z0-9._-]+$/D',$model))throw new RuntimeException('GEMINI_MODEL_INVALID');
    $exclude=array_map(fn($t)=>$t['title'],$catalog);
    $prompt="You are a careful English curriculum author for Vietnamese learners. Produce ONE new practical lesson, randomly vary CEFR A1/A2/B1. All explanations, titles, notes and translations must be natural Vietnamese; English examples must be grammatical and idiomatic. Ten distinct useful words/phrases, each with an accurate Vietnamese meaning, English example and Vietnamese example translation. Exactly four short dialogue turns. Five different multiple-choice grammar exercises teaching the stated rule, with exactly one unambiguous correct choice. A listening question whose answer is explicitly supported by the dialogue. All multiple-choice questions have four distinct plausible options and a zero-based correct index. No HTML, no Markdown, no URLs, no unsafe instructions. Do not invent IPA. Internally check every answer and translation. Avoid previously covered topics: ".json_encode($exclude,JSON_UNESCAPED_UNICODE).". Pick a new subtopic such as renting an apartment, job interviews, banking, appointments, travel problems, polite complaints or studying abroad. Return only the requested JSON object.";
    $prompt.=' For grammar questions, include a Vietnamese instruction or intended meaning whenever more than one option could complete the English sentence grammatically. Avoid ambiguous modal-verb choices such as can versus could. Do not include factual financial or medical advice; examples are fictional language practice.';
    $payload=['contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.65,'maxOutputTokens'=>10000,'responseMimeType'=>'application/json','responseJsonSchema'=>eq_gemini_schema()]];
    $ch=curl_init('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent');
    $body='';
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$key],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_WRITEFUNCTION=>function($ch,$part)use(&$body){if(strlen($body)+strlen($part)>524288)return 0;$body.=$part;return strlen($part);}]);
    $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($ok===false)throw new RuntimeException('GEMINI_NETWORK_ERROR');
    // Never log upstream bodies, which can contain account identifiers or credentials.
    if($status!==200)throw new RuntimeException('GEMINI_HTTP_'.$status);
    $data=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    $candidate=$data['candidates'][0]??[];
    if(($candidate['finishReason']??'')!=='STOP')throw new RuntimeException('GEMINI_INCOMPLETE_OR_BLOCKED');
    $text='';foreach($candidate['content']['parts']??[] as $part)if(empty($part['thought']))$text.=$part['text']??'';
    return eq_validate_generated(json_decode($text,true,64,JSON_THROW_ON_ERROR));
}

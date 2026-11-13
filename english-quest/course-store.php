<?php
declare(strict_types=1);
require_once __DIR__.'/hub-common.php';
function eqc_slug(string $s): string {if(!preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/D',$s))throw new InvalidArgumentException('Invalid course identifier');return $s;}
function eqc_exercises(array $qs): array {
 if(count($qs)<3||count($qs)>30)throw new InvalidArgumentException('A test needs 3–30 exercises');
 foreach($qs as $q){
  if(!is_array($q)||!in_array($q['type']??'', ['choice','fill','reorder','match','listen'],true)||!is_string($q['prompt']??null)||!is_string($q['explanation']??null))throw new InvalidArgumentException('Invalid exercise');
  if(in_array($q['type'],['choice','listen'],true)&&(!is_array($q['options']??null)||count($q['options'])<2||!is_int($q['answer']??null)||!isset($q['options'][$q['answer']])||count(array_unique($q['options']))!==count($q['options'])))throw new InvalidArgumentException('Invalid choices');
  if($q['type']==='listen'&&empty($q['audio_text']))throw new InvalidArgumentException('Listening text missing');
  if($q['type']==='fill'&&(!is_array($q['answers']??null)||!count($q['answers'])))throw new InvalidArgumentException('Expected answer missing');
  if($q['type']==='reorder'&&(!is_array($q['tokens']??null)||empty($q['answer'])))throw new InvalidArgumentException('Invalid sentence ordering');
  if($q['type']==='match'){$n=count($q['left']??[]);$a=$q['answer']??[];$b=$a;sort($b);if($n<2||$n>8||count($q['right']??[])!==$n||$b!==range(0,$n-1))throw new InvalidArgumentException('Invalid matching exercise');}
 }
 return $qs;
}
function eqc_upsert(PDO $p,string $table,string $id,array $data): void {
 $exists=hub_q($p,"SELECT id FROM $table WHERE id=?",[$id])->fetchColumn();$keys=array_keys($data);
 if($exists)hub_q($p,"UPDATE $table SET ".implode(',',array_map(fn($k)=>"$k=?",$keys)).' WHERE id=?',[...array_values($data),$id]);
 else hub_q($p,"INSERT INTO $table(id,".implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($data)+1,'?')).')',[$id,...array_values($data)]);
}
function eqc_import(PDO $p,array $course,bool $onlyIfAbsent=false): void {
 $id=eqc_slug($course['id']);if($onlyIfAbsent&&hub_q($p,'SELECT 1 FROM eqc_courses WHERE id=?',[$id])->fetchColumn())return;
 $p->beginTransaction();try{
  $language=(string)($course['language_code']??(str_starts_with($id,'chinese-')?'zh-CN':'en-US'));if(!in_array($language,['en-US','en','zh-CN','vi-VN','math'],true))throw new InvalidArgumentException('Unsupported course language');
  eqc_upsert($p,'eqc_courses',$id,['title'=>$course['title'],'description'=>$course['description'],'level'=>$course['level'],'language_code'=>$language,'skill'=>$course['skill'],'published'=>1,'position'=>max(1,min(999,(int)($course['position']??1))),'version'=>1,'final_json'=>json_encode(eqc_exercises($course['final']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
  foreach($course['units'] as $i=>$unit){eqc_upsert($p,'eqc_units',eqc_slug($unit['id']),['course_id'=>$id,'title'=>$unit['title'],'position'=>$i+1,'published'=>1,'exam_json'=>json_encode(eqc_exercises($unit['exam']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   foreach($unit['lessons'] as $j=>$l)eqc_upsert($p,'eqc_lessons',eqc_slug($l['id']),['unit_id'=>$unit['id'],'title'=>$l['title'],'position'=>$j+1,'published'=>1,'content_json'=>json_encode($l['content'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'exercises_json'=>json_encode(eqc_exercises($l['exercises']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
  }$p->commit();
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

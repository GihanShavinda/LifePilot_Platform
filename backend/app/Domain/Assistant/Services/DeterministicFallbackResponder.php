<?php
namespace App\Domain\Assistant\Services;
use App\Domain\Assistant\Enums\AssistantIntent;
use Carbon\Carbon;
class DeterministicFallbackResponder
{
    public function respond(string $question,AssistantIntent $intent,array $evidence,string $reason='fallback'):array
    {
        if(!$evidence)return ['answer'=>'I could not find household-authorized evidence that supports an answer to that question.','claims'=>[],'citations'=>[],'draft'=>null,'grounding_status'=>'no_evidence','fallback_reason'=>$reason];
        if($intent===AssistantIntent::Calculate)return $this->calculate($evidence,$reason);
        if($intent===AssistantIntent::DraftTask)return $this->draftTask($question,$evidence,$reason);
        if($intent===AssistantIntent::DraftEvent)return $this->draftEvent($question,$evidence,$reason);
        $rows=array_slice($evidence,0,8);$lines=[];$claims=[];
        foreach($rows as $e){$facts=array_filter($e['facts']??[],fn($v)=>$v!==null&&$v!=='');$parts=[];foreach($facts as $k=>$v){if(is_scalar($v))$parts[]=$k.'='.$v;}$text=$e['title'].($parts?' — '.implode(', ',array_slice($parts,0,4)):'');$lines[]='- '.$text.' ['.$e['key'].']';$claims[]=['text'=>$text,'citations'=>[$e['key']],'factual_values'=>array_values(array_map('strval',array_filter($facts,'is_scalar')))];}
        $prefix=match($intent){AssistantIntent::UpcomingObligations=>'I found these upcoming items:',AssistantIntent::RecommendNextAction=>'Based only on the retrieved records, these are the items to review next:',default=>'I found these grounded records:'};
        return ['answer'=>$prefix."\n".implode("\n",$lines),'claims'=>$claims,'citations'=>array_column($rows,'key'),'draft'=>null,'grounding_status'=>'deterministic_fallback','fallback_reason'=>$reason];
    }
    private function calculate(array $evidence,string $reason):array
    {
        $groups=[];$citations=[];
        foreach($evidence as $e){if(($e['type']??'')!=='expense')continue;$amount=$e['facts']['amount']??null;$currency=$e['facts']['currency']??null;$date=$e['facts']['expense_date']??null;if(!is_numeric($amount)||!$currency||!$date)continue;try{$month=Carbon::parse($date)->format('Y-m');}catch(\Throwable){continue;}$groups[$month][$currency]=($groups[$month][$currency]??0)+(float)$amount;$citations[]=$e['key'];}
        if(!$groups)return $this->respond('',$this->searchIntent(),array_slice($evidence,0,8),$reason);
        krsort($groups);$lines=[];foreach($groups as $month=>$currencies){foreach($currencies as $currency=>$total)$lines[]=$month.': '.$currency.' '.number_format($total,2,'.','');}
        return ['answer'=>"Deterministic totals from the retrieved expense records:\n- ".implode("\n- ",$lines).'\nDifferent currencies are not combined.','claims'=>[['text'=>'Expense totals were calculated from the cited expense records without currency conversion.','citations'=>array_values(array_unique($citations)),'factual_values'=>[]]],'citations'=>array_values(array_unique($citations)),'draft'=>null,'grounding_status'=>'deterministic_fallback','fallback_reason'=>$reason];
    }
    private function draftTask(string $question,array $evidence,string $reason):array
    {
        $e=$evidence[0];$due=$e['facts']['due_at']??$e['facts']['end_date']??$e['facts']['next_billing_date']??null;$draft=['type'=>'task','title'=>'Review: '.$e['title'],'description'=>'Draft generated from cited LifePilot evidence. Review before creating.','due_at'=>$due,'citations'=>[$e['key']]];
        return ['answer'=>'I prepared a task draft from the retrieved evidence. Review it before creating any task.','claims'=>[['text'=>'The draft is based on '.$e['title'].'.','citations'=>[$e['key']],'factual_values'=>[$e['title']]]],'citations'=>[$e['key']],'draft'=>$draft,'grounding_status'=>'deterministic_fallback','fallback_reason'=>$reason];
    }
    private function draftEvent(string $question,array $evidence,string $reason):array
    {
        $e=$evidence[0];$start=$e['facts']['starts_at']??$e['facts']['due_at']??$e['facts']['end_date']??null;$draft=['type'=>'event','title'=>'Review: '.$e['title'],'starts_at'=>$start,'ends_at'=>$e['facts']['ends_at']??null,'timezone'=>$e['facts']['timezone']??null,'citations'=>[$e['key']]];
        return ['answer'=>'I prepared an event draft from the retrieved evidence. Review it before creating any calendar event.','claims'=>[['text'=>'The draft is based on '.$e['title'].'.','citations'=>[$e['key']],'factual_values'=>[$e['title']]]],'citations'=>[$e['key']],'draft'=>$draft,'grounding_status'=>'deterministic_fallback','fallback_reason'=>$reason];
    }
    private function searchIntent():AssistantIntent{return AssistantIntent::Search;}
}

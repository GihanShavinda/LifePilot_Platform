<?php
namespace App\Domain\Assistant\Services;
use App\Domain\Assistant\Enums\AssistantIntent;
class IntentClassifier
{
    public function classify(string $question):AssistantIntent
    {
        $q=mb_strtolower(trim($question));
        if(preg_match('/\b(create|draft|prepare|make)\b.*\b(task|todo)\b/u',$q))return AssistantIntent::DraftTask;
        if(preg_match('/\b(create|draft|schedule|prepare)\b.*\b(event|appointment|calendar)\b/u',$q))return AssistantIntent::DraftEvent;
        if(str_contains($q,'what should i')||str_contains($q,'next action')||str_contains($q,'handle before'))return AssistantIntent::RecommendNextAction;
        if((str_contains($q,'due')||str_contains($q,'pay'))&&(str_contains($q,'week')||str_contains($q,'month')||str_contains($q,'upcoming')))return AssistantIntent::UpcomingObligations;
        if(str_contains($q,'compare')||str_contains($q,'difference'))return AssistantIntent::Compare;
        if(str_contains($q,'why')||str_contains($q,'explain'))return AssistantIntent::Explain;
        if(str_contains($q,'total')||str_contains($q,'how much')||str_contains($q,'calculate')||str_contains($q,'spending'))return AssistantIntent::Calculate;
        if(str_contains($q,'summarize')||str_contains($q,'summary'))return AssistantIntent::Summarize;
        return AssistantIntent::Search;
    }
}

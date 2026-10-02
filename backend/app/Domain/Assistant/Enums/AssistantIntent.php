<?php
namespace App\Domain\Assistant\Enums;
enum AssistantIntent:string
{
    case Search='search';
    case Summarize='summarize';
    case Compare='compare';
    case Explain='explain';
    case Calculate='calculate';
    case UpcomingObligations='find_upcoming_obligations';
    case RecommendNextAction='recommend_next_action';
    case DraftTask='generate_draft_task';
    case DraftEvent='generate_draft_event';
}

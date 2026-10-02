<?php
namespace App\Domain\Graph\Enums;

enum LifeRelationType: string
{
    case IssuedBy='ISSUED_BY'; case RefersTo='REFERS_TO'; case CreatesObligation='CREATES_OBLIGATION';
    case GeneratesTask='GENERATES_TASK'; case DueOn='DUE_ON'; case PaidTo='PAID_TO'; case Owns='OWNS';
    case HasWarranty='HAS_WARRANTY'; case RenewsOn='RENEWS_ON'; case ScheduledAs='SCHEDULED_AS';
    case RelatedTo='RELATED_TO'; case EvidencedBy='EVIDENCED_BY'; case CompletedBy='COMPLETED_BY';
}

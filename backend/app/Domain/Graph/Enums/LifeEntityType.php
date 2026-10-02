<?php
namespace App\Domain\Graph\Enums;

enum LifeEntityType: string
{
    case User='user'; case Person='person'; case Organization='organization'; case Document='document';
    case Obligation='obligation'; case Task='task'; case Event='event'; case Expense='expense';
    case Subscription='subscription'; case Asset='asset'; case Warranty='warranty'; case Location='location';
}

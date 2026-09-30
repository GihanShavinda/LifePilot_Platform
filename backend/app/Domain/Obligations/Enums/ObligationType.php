<?php
namespace App\Domain\Obligations\Enums;

enum ObligationType: string
{
    case Payment = 'payment';
    case Renewal = 'renewal';
    case Appointment = 'appointment';
    case Submission = 'submission';
    case Collection = 'collection';
    case Maintenance = 'maintenance';
    case Cancellation = 'cancellation';
    case Registration = 'registration';
    case FollowUp = 'follow-up';
    case Custom = 'custom';
}

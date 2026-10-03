<?php

namespace App\Domain\Actions\Enums;

enum ActionType: string
{
    case CreateTask = 'create_task';
    case UpdateTask = 'update_task';
    case CreateReminder = 'create_reminder';
    case CreateCalendarEvent = 'create_calendar_event';
    case PrepareEmailDraft = 'prepare_email_draft';
    case CategorizeExpense = 'categorize_expense';
    case CreateExpense = 'create_expense';
    case CreateAsset = 'create_asset';
    case CreateSubscription = 'create_subscription';
    case ArchiveDocument = 'archive_document';
    case RequestIntegrationAction = 'request_integration_action';
}

<?php
namespace App\Domain\Documents\Enums;
enum ProcessingStatus:string { case Pending='pending'; case Queued='queued'; case Processing='processing'; case Ready='ready'; case Failed='failed'; case Rejected='rejected'; }

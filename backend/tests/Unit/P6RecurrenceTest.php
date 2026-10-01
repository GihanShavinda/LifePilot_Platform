<?php
namespace Tests\Unit;
use App\Domain\Scheduling\Services\CalendarRecurrence;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
class P6RecurrenceTest extends TestCase {
 public function test_month_end_and_timezone_recurrence():void{
  $service=new CalendarRecurrence();$start=CarbonImmutable::parse('2026-01-31 09:00','Asia/Colombo');
  $items=$service->occurrences($start,'monthly',1,CarbonImmutable::parse('2026-04-15','Asia/Colombo'));
  $this->assertSame(['2026-01-31','2026-02-28','2026-03-31'],array_map(fn($d)=>$d->format('Y-m-d'),$items));
  $this->assertSame('Asia/Colombo',$items[1]->timezoneName);
 }
}

<?php
namespace Tests\Unit;
use App\Domain\Obligations\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
class RecurrenceScheduleTest extends TestCase {
 public function test_monthly_recurrence_does_not_overflow_to_march():void {
  $next=(new RecurrenceService())->next(CarbonImmutable::parse('2027-01-31T10:00:00Z'),'monthly',1);
  $this->assertSame('2027-02-28',$next->toDateString());
 }
 public function test_weekly_interval():void {
  $next=(new RecurrenceService())->next(CarbonImmutable::parse('2027-01-01T10:00:00Z'),'weekly',2);
  $this->assertSame('2027-01-15',$next->toDateString());
 }
}

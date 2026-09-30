<?php

namespace Tests\Unit;

use App\Domain\Finance\Services\FinanceCalculations;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LifeFinanceCalculationsTest extends TestCase
{
    public function test_month_end_recurrence_is_bounded(): void
    {
        $f = new FinanceCalculations();
        $this->assertSame('2026-02-28', $f->nextDate('2026-01-31', 'monthly'));
    }
    public function test_recurring_cost_conversion(): void
    {
        $f = new FinanceCalculations();
        $this->assertSame('10.00', $f->monthlyEquivalent('120.00', 'yearly'));
    }
    public function test_warranty_expiry(): void
    {
        $f = new FinanceCalculations();
        $today = CarbonImmutable::parse('2026-09-30');
        $this->assertSame('expired', $f->warrantyState('2026-09-29', $today));
        $this->assertSame('expiring', $f->warrantyState('2026-10-20', $today));
        $this->assertSame('active', $f->warrantyState('2026-12-20', $today));
    }
}

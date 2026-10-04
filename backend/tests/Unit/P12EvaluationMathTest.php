<?php

namespace Tests\Unit;

use App\Domain\Completion\Services\EvaluationMath;
use PHPUnit\Framework\TestCase;

class P12EvaluationMathTest extends TestCase
{
    public function test_rates_precision_recall_and_mean_are_deterministic(): void
    {
        $math = new EvaluationMath();

        $this->assertSame(75.0, $math->rate(3, 4));
        $this->assertSame(80.0, $math->precision(8, 2));
        $this->assertSame(66.67, $math->recall(8, 4));
        $this->assertSame(20.0, $math->mean([10, 20, 30]));
    }

    public function test_missing_denominators_return_null_instead_of_fabricated_scores(): void
    {
        $math = new EvaluationMath();

        $this->assertNull($math->rate(0, 0));
        $this->assertNull($math->precision(0, 0));
        $this->assertNull($math->recall(0, 0));
        $this->assertNull($math->mean([]));
    }
}

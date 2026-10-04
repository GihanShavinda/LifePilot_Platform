<?php

namespace Tests\Unit;

use App\Domain\Analytics\Services\ForecastMath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class P11ForecastMathTest extends TestCase
{
    #[Test]
    public function moving_average_is_deterministic(): void
    {
        $math = new ForecastMath();

        $this->assertSame(20.0, $math->movingAverage([10, 20, 30], 3));
        $this->assertSame(30.0, $math->movingAverage([10, 20, 30, 40], 3));
        $this->assertNull($math->movingAverage([10, 20], 3));
    }

    #[Test]
    public function exponential_smoothing_is_reproducible(): void
    {
        $math = new ForecastMath();
        $first = $math->exponentialSmoothing([100, 120, 90, 130], 0.35);
        $second = $math->exponentialSmoothing([100, 120, 90, 130], 0.35);

        $this->assertSame($first, $second);
        $this->assertSame(111.18, $first);
    }

    #[Test]
    public function logistic_probability_increases_with_risk_score(): void
    {
        $math = new ForecastMath();

        $this->assertLessThan($math->sigmoid(1.5), $math->sigmoid(-1.0));
        $this->assertSame(0.5, $math->sigmoid(0));
    }
}

<?php

namespace App\Domain\Analytics\Services;

class ForecastMath
{
    /** @param array<int,float|int> $values */
    public function movingAverage(array $values, int $window = 3): ?float
    {
        if ($window < 1 || count($values) < $window) {
            return null;
        }

        $slice = array_slice(array_values($values), -$window);
        return round(array_sum($slice) / count($slice), 2);
    }

    /** @param array<int,float|int> $values */
    public function exponentialSmoothing(array $values, float $alpha = 0.35): ?float
    {
        if ($values === []) {
            return null;
        }

        $alpha = max(0.01, min(0.99, $alpha));
        $values = array_map('floatval', array_values($values));
        $level = $values[0];

        for ($i = 1; $i < count($values); $i++) {
            $level = ($alpha * $values[$i]) + ((1 - $alpha) * $level);
        }

        return round($level, 2);
    }

    public function sigmoid(float $score): float
    {
        return round(1 / (1 + exp(-$score)), 4);
    }

    public function monthlyEquivalent(float $amount, string $cycle): float
    {
        $factor = match (strtolower($cycle)) {
            'weekly' => 52 / 12,
            'biweekly', 'fortnightly' => 26 / 12,
            'monthly' => 1,
            'quarterly' => 1 / 3,
            'semiannual', 'half_yearly' => 1 / 6,
            'yearly', 'annual' => 1 / 12,
            default => 1,
        };

        return round($amount * $factor, 2);
    }
}

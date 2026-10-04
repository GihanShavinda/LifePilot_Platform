<?php

namespace App\Domain\Completion\Services;

class EvaluationMath
{
    public function rate(int $successes, int $total): ?float
    {
        if ($total <= 0) {
            return null;
        }

        return round(($successes / $total) * 100, 2);
    }

    public function precision(int $tp, int $fp): ?float
    {
        return ($tp + $fp) > 0 ? round(($tp / ($tp + $fp)) * 100, 2) : null;
    }

    public function recall(int $tp, int $fn): ?float
    {
        return ($tp + $fn) > 0 ? round(($tp / ($tp + $fn)) * 100, 2) : null;
    }

    public function mean(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($value) => is_numeric($value)));

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }
}

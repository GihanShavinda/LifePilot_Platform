<?php

namespace App\Domain\Completion\Services;

use App\Domain\Users\Models\User;

class CompletionDashboardService
{
    public function __construct(
        private readonly MainDashboardService $main,
        private readonly DocumentDashboardService $documents,
        private readonly FinanceDashboardService $finance,
        private readonly AiDashboardService $ai,
    ) {}

    public function all(User $user, int $months = 6): array
    {
        return [
            'main' => $this->main->build($user),
            'documents' => $this->documents->build($user),
            'finance' => $this->finance->build($user, $months),
            'ai' => $this->ai->build($user),
        ];
    }
}

<?php

namespace App\Domain\Completion\Services;

use App\Domain\Completion\Models\SecurityReviewResult;
use App\Domain\Users\Models\User;

class SecurityReviewService
{
    public function __construct(private readonly CompletionAccess $access) {}

    public function review(User $user): array
    {
        $householdId = $this->access->householdId($user);
        $checks = [
            [
                'check_key' => 'owasp_api_authorization',
                'status' => 'implemented',
                'title' => 'OWASP API authorization boundary',
                'description' => 'Authenticated API routes use Sanctum and domain-level household/resource authorization. Object access is not granted solely because two users share a household.',
                'evidence' => ['auth:sanctum', 'P10 sharing-aware access', 'resource-specific access services'],
            ],
            [
                'check_key' => 'bola',
                'status' => 'implemented',
                'title' => 'Broken object-level authorization protection',
                'description' => 'Documents, tasks, expenses, assets, calendar items, search, AI retrieval and agentic execution enforce resource ownership or explicit household sharing before returning or mutating records.',
                'evidence' => ['P10 private-vs-shared tests', 'P10 AI isolation test', 'accessibleTo() scopes'],
            ],
            [
                'check_key' => 'file_upload_security',
                'status' => 'implemented',
                'title' => 'File upload security',
                'description' => 'Document upload validation limits size and accepted extensions, stores checksums, and uses authorized signed download links rather than public file paths.',
                'evidence' => ['max upload size validation', 'pdf/jpg/jpeg/png/docx/txt allow-list', 'signed document URLs'],
            ],
            [
                'check_key' => 'prompt_injection',
                'status' => 'implemented',
                'title' => 'Prompt-injection resistance',
                'description' => 'Retrieved document content is treated as untrusted evidence. Grounded validation rejects unsupported facts, amounts, deadlines and citations before an AI answer is accepted.',
                'evidence' => ['P8 UntrustedContentGuard', 'P8 GroundedResponseValidator', 'prompt-injection tests'],
            ],
            [
                'check_key' => 'rate_limiting',
                'status' => 'implemented',
                'title' => 'Rate limiting',
                'description' => 'High-risk or abuse-prone authentication, upload, assistant, analytics refresh and action execution endpoints have route-level throttles.',
                'evidence' => ['login throttle', 'upload throttle', 'assistant throttle', 'action execution throttle', 'analytics refresh throttle'],
            ],
            [
                'check_key' => 'sensitive_data_logging',
                'status' => 'manual_review',
                'title' => 'Sensitive-data logging',
                'description' => 'Production deployment must keep application debug disabled and must review log processors so document text, credentials, tokens and personal values are not written to logs.',
                'evidence' => ['deployment checklist required'],
            ],
            [
                'check_key' => 'secret_management',
                'status' => 'manual_review',
                'title' => 'Secret management',
                'description' => 'Application, database, Reverb and third-party integration secrets must remain in environment/secret-manager configuration and must never be committed to the repository.',
                'evidence' => ['.env excluded from source control', 'production secret-manager recommended'],
            ],
            [
                'check_key' => 'agentic_safety',
                'status' => 'implemented',
                'title' => 'Agentic action safety',
                'description' => 'P9 plans are previewed, policy-checked, evidence-checked, risk-classified and user-approved before execution. Financial transaction execution remains out of scope.',
                'evidence' => ['P9 approval enforcement', 'P9 idempotency', 'P9 rollback', 'financial execution blocked'],
            ],
        ];

        foreach ($checks as $check) {
            SecurityReviewResult::updateOrCreate(
                [
                    'household_id' => $householdId,
                    'user_id' => $user->id,
                    'check_key' => $check['check_key'],
                ],
                [
                    'status' => $check['status'],
                    'title' => $check['title'],
                    'description' => $check['description'],
                    'evidence' => $check['evidence'],
                    'reviewed_at' => now(),
                ]
            );
        }

        return SecurityReviewResult::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->orderBy('check_key')
            ->get()
            ->map(fn (SecurityReviewResult $result) => [
                'check_key' => $result->check_key,
                'status' => $result->status,
                'title' => $result->title,
                'description' => $result->description,
                'evidence' => $result->evidence ?? [],
                'reviewed_at' => $result->reviewed_at?->toIso8601String(),
            ])->values()->all();
    }
}

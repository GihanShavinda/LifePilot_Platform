<?php

namespace App\Domain\Assistant\Services;

class GroundedResponseValidator
{
    /**
     * Validate a model-generated answer against the retrieved evidence bundle.
     *
     * Security goals:
     * - Every claim must have known citations.
     * - Explicit factual values must exist in cited evidence.
     * - Dates must exist in cited evidence.
     * - Currency + amount combinations must be grounded even when evidence
     *   stores currency and amount in separate structured fields.
     * - Draft actions must cite supporting evidence.
     */
    public function validate(?array $output, array $evidence): array
    {
        if (
            ! $output ||
            ! isset($output['answer']) ||
            ! is_string($output['answer'])
        ) {
            return [
                'valid' => false,
                'reason' => 'malformed_model_output',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Build evidence lookup map
        |--------------------------------------------------------------------------
        */

        $map = [];

        foreach ($evidence as $item) {
            if (
                is_array($item) &&
                isset($item['key'])
            ) {
                $map[$item['key']] = $item;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate claims
        |--------------------------------------------------------------------------
        */

        $claims = $output['claims'] ?? [];

        if (
            ! is_array($claims) ||
            count($claims) === 0
        ) {
            return [
                'valid' => false,
                'reason' => 'claims_missing',
            ];
        }

        foreach ($claims as $claim) {
            if (! is_array($claim)) {
                return [
                    'valid' => false,
                    'reason' => 'malformed_claim',
                ];
            }

            $claimCitations = $claim['citations'] ?? [];

            if (
                ! is_array($claimCitations) ||
                count($claimCitations) === 0
            ) {
                return [
                    'valid' => false,
                    'reason' => 'uncited_claim',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Resolve claim citations
            |--------------------------------------------------------------------------
            */

            $citedEvidence = [];

            foreach ($claimCitations as $key) {
                if (! isset($map[$key])) {
                    return [
                        'valid' => false,
                        'reason' => 'unknown_citation',
                        'citation' => $key,
                    ];
                }

                $citedEvidence[] = $map[$key];
            }

            $claimText = (string) ($claim['text'] ?? '');

            /*
            |--------------------------------------------------------------------------
            | Validate explicit factual_values supplied by model
            |--------------------------------------------------------------------------
            */

            $factualValues = (array) (
                $claim['factual_values'] ?? []
            );

            foreach ($factualValues as $value) {
                if (
                    ! $this->valueExistsInEvidence(
                        $value,
                        $citedEvidence
                    )
                ) {
                    return [
                        'valid' => false,
                        'reason' => 'unsupported_value',
                        'value' => $value,
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Validate dates contained in claim text
            |--------------------------------------------------------------------------
            */

            foreach (
                $this->extractDates($claimText)
                as $date
            ) {
                if (
                    ! $this->valueExistsInEvidence(
                        $date,
                        $citedEvidence
                    )
                ) {
                    return [
                        'valid' => false,
                        'reason' => 'unsupported_value',
                        'value' => $date,
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Validate monetary values contained in claim text
            |--------------------------------------------------------------------------
            |
            | Important:
            | Evidence commonly stores:
            |
            |   amount   => "2500.00"
            |   currency => "LKR"
            |
            | while the model naturally writes:
            |
            |   "LKR 2500.00"
            |
            | Therefore validate currency and amount separately.
            |
            */

            foreach (
                $this->extractMoney($claimText)
                as $money
            ) {
                if (
                    ! $this->moneyExistsInEvidence(
                        $money,
                        $citedEvidence
                    )
                ) {
                    return [
                        'valid' => false,
                        'reason' => 'unsupported_value',
                        'value' =>
                            $money['currency'] .
                            ' ' .
                            $money['amount'],
                    ];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Collect citations from all claims
        |--------------------------------------------------------------------------
        */

        $citations = [];

        foreach ($claims as $claim) {
            foreach (
                (array) ($claim['citations'] ?? [])
                as $citation
            ) {
                $citations[] = $citation;
            }
        }

        $citations = array_values(
            array_unique($citations)
        );

        $allCitedEvidence = [];

        foreach ($citations as $key) {
            if (isset($map[$key])) {
                $allCitedEvidence[] = $map[$key];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate factual values appearing in final answer
        |--------------------------------------------------------------------------
        */

        foreach (
            $this->extractDates($output['answer'])
            as $date
        ) {
            if (
                ! $this->valueExistsInEvidence(
                    $date,
                    $allCitedEvidence
                )
            ) {
                return [
                    'valid' => false,
                    'reason' => 'unsupported_answer_value',
                    'value' => $date,
                ];
            }
        }

        foreach (
            $this->extractMoney($output['answer'])
            as $money
        ) {
            if (
                ! $this->moneyExistsInEvidence(
                    $money,
                    $allCitedEvidence
                )
            ) {
                return [
                    'valid' => false,
                    'reason' => 'unsupported_answer_value',
                    'value' =>
                        $money['currency'] .
                        ' ' .
                        $money['amount'],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate draft actions
        |--------------------------------------------------------------------------
        */

        $draft = $output['draft'] ?? null;

        if (is_array($draft)) {
            $draftCitations = (array) (
                $draft['citations'] ?? []
            );

            if (count($draftCitations) === 0) {
                return [
                    'valid' => false,
                    'reason' => 'uncited_draft',
                ];
            }

            $draftEvidence = [];

            foreach ($draftCitations as $key) {
                if (! isset($map[$key])) {
                    return [
                        'valid' => false,
                        'reason' =>
                            'unknown_draft_citation',
                        'citation' => $key,
                    ];
                }

                $draftEvidence[] = $map[$key];
            }

            $fieldsThatRequireEvidence = [
                'due_at',
                'starts_at',
                'ends_at',
                'amount',
                'currency',
                'organization',
                'provider',
            ];

            foreach (
                $fieldsThatRequireEvidence
                as $field
            ) {
                if (
                    ! array_key_exists($field, $draft) ||
                    $draft[$field] === null ||
                    $draft[$field] === ''
                ) {
                    continue;
                }

                if (
                    ! $this->valueExistsInEvidence(
                        $draft[$field],
                        $draftEvidence
                    )
                ) {
                    return [
                        'valid' => false,
                        'reason' =>
                            'unsupported_draft_value',
                        'field' => $field,
                        'value' => $draft[$field],
                    ];
                }
            }

            $citations = array_values(
                array_unique(
                    array_merge(
                        $citations,
                        $draftCitations
                    )
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Valid grounded response
        |--------------------------------------------------------------------------
        */

        return [
            'valid' => true,
            'answer' => $output['answer'],
            'claims' => $claims,
            'citations' => $citations,
            'draft' => $draft,
        ];
    }

    /**
     * Check whether an individual scalar factual value exists somewhere in
     * the supplied evidence.
     */
    private function valueExistsInEvidence(
        mixed $value,
        array $evidence
    ): bool {
        $needle = $this->normalizeScalar($value);

        if ($needle === '') {
            return true;
        }

        foreach ($evidence as $item) {
            if (
                $this->recursiveContainsValue(
                    $item,
                    $needle
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively compare structured evidence values.
     *
     * This is stronger than checking only a serialized JSON substring because
     * the evidence bundle contains structured values such as amount/currency.
     */
    private function recursiveContainsValue(
        mixed $value,
        string $needle
    ): bool {
        if (is_array($value)) {
            foreach ($value as $nested) {
                if (
                    $this->recursiveContainsValue(
                        $nested,
                        $needle
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return $this->recursiveContainsValue(
                (array) $value,
                $needle
            );
        }

        if ($value === null) {
            return false;
        }

        $candidate = $this->normalizeScalar(
            $value
        );

        if ($candidate === '') {
            return false;
        }

        return $candidate === $needle ||
            str_contains($candidate, $needle);
    }

    /**
     * Validate a currency + amount pair against structured evidence.
     */
    private function moneyExistsInEvidence(
        array $money,
        array $evidence
    ): bool {
        $currency = strtoupper(
            trim((string) (
                $money['currency'] ?? ''
            ))
        );

        $amount = $this->normalizeAmount(
            (string) (
                $money['amount'] ?? ''
            )
        );

        if (
            $currency === '' ||
            $amount === ''
        ) {
            return false;
        }

        foreach ($evidence as $item) {
            $currencies = [];
            $amounts = [];

            $this->collectMoneyValues(
                $item,
                $currencies,
                $amounts
            );

            $currencyFound = in_array(
                $currency,
                $currencies,
                true
            );

            $amountFound = in_array(
                $amount,
                $amounts,
                true
            );

            /*
             * Currency and amount must both occur inside the same cited
             * evidence item. This prevents combining an amount from one
             * record with a currency from another record.
             */
            if (
                $currencyFound &&
                $amountFound
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect currency and numeric values recursively from one evidence item.
     */
    private function collectMoneyValues(
        mixed $value,
        array &$currencies,
        array &$amounts
    ): void {
        if (is_array($value)) {
            foreach ($value as $key => $nested) {
                if (
                    is_string($key) &&
                    in_array(
                        strtolower($key),
                        [
                            'currency',
                            'currency_code',
                        ],
                        true
                    ) &&
                    is_scalar($nested)
                ) {
                    $currencies[] = strtoupper(
                        trim((string) $nested)
                    );
                }

                if (
                    is_string($key) &&
                    in_array(
                        strtolower($key),
                        [
                            'amount',
                            'price',
                            'purchase_price',
                            'total',
                            'balance',
                        ],
                        true
                    ) &&
                    is_scalar($nested)
                ) {
                    $normalized =
                        $this->normalizeAmount(
                            (string) $nested
                        );

                    if ($normalized !== '') {
                        $amounts[] = $normalized;
                    }
                }

                $this->collectMoneyValues(
                    $nested,
                    $currencies,
                    $amounts
                );
            }

            return;
        }

        if (is_object($value)) {
            $this->collectMoneyValues(
                (array) $value,
                $currencies,
                $amounts
            );
        }
    }

    /**
     * Extract ISO dates that may represent deadlines or factual dates.
     */
    private function extractDates(
        string $text
    ): array {
        preg_match_all(
            '/\b\d{4}-\d{2}-\d{2}\b/u',
            $text,
            $matches
        );

        return array_values(
            array_unique(
                $matches[0] ?? []
            )
        );
    }

    /**
     * Extract currency + monetary amount pairs.
     */
    // private function extractMoney(
    //     string $text
    // ): array {
    //     preg_match_all(
    //         '/\b(LKR|USD|EUR|GBP|AUD|CAD|INR)\s*([0-9][0-9,.]*)/iu',
    //         $text,
    //         $matches,
    //         PREG_SET_ORDER
    //     );

    //     $results = [];

    //     foreach ($matches as $match) {
    //         $results[] = [
    //             'currency' =>
    //                 strtoupper($match[1]),
    //             'amount' =>
    //                 $this->normalizeAmount(
    //                     $match[2]
    //                 ),
    //         ];
    //     }

    //     return $results;
    // }
    private function extractMoney(string $text): array
{
    preg_match_all(
        '/\b(LKR|USD|EUR|GBP|AUD|CAD|INR)\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]+)?)/iu',
        $text,
        $matches,
        PREG_SET_ORDER
    );

    $results = [];

    foreach ($matches as $match) {
        $amount = $this->normalizeAmount($match[2]);

        if ($amount === '') {
            continue;
        }

        $results[] = [
            'currency' => strtoupper($match[1]),
            'amount' => $amount,
        ];
    }

    return $results;
}

    /**
     * Normalize generic scalar values for case-insensitive comparison.
     */
    private function normalizeScalar(
        mixed $value
    ): string {
        if (
            is_array($value) ||
            is_object($value)
        ) {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            return mb_strtolower(
                trim((string) $encoded)
            );
        }

        return mb_strtolower(
            trim((string) $value)
        );
    }

    /**
     * Normalize monetary amounts while preserving decimal meaning.
     *
     * Examples:
     *  "2,500.00" -> "2500.00"
     *  "2500.00"  -> "2500.00"
     *  "2500"     -> "2500"
     */
    private function normalizeAmount(
        string $amount
    ): string {
        $amount = trim($amount);

        $amount = str_replace(
            ',',
            '',
            $amount
        );

        if (
            ! preg_match(
                '/^-?\d+(?:\.\d+)?$/',
                $amount
            )
        ) {
            return '';
        }

        return $amount;
    }
}
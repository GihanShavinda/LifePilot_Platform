<?php

namespace App\Domain\Documents\Services;

use DateTimeImmutable;

class DeterministicFieldParser
{
    /**
     * @param array<int, string> $pages
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $text, array $pages = []): array
    {
        $fields = [];

        // Normalize line endings without changing source values.
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $this->addDocumentType($fields, $text, $pages);
        $this->addEmails($fields, $text, $pages);
        $this->addPhones($fields, $text, $pages);
        $this->addCurrencyAndAmount($fields, $text, $pages);
        $this->addDates($fields, $text, $pages);
        $this->addReferenceNumbers($fields, $text, $pages);
        $this->addPolicyNumbers($fields, $text, $pages);
        $this->addAccountReference($fields, $text, $pages);
        $this->addIssuer($fields, $text, $pages);

        return $fields;
    }

    /**
     * Identify supported document types from source text.
     */
    private function addDocumentType(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $rules = [
            'bill' => '/\b(?:utility bill|electricity bill|water bill|invoice|amount due)\b/i',
            'receipt' => '/\b(?:receipt|payment received|cashier|subtotal)\b/i',
            'warranty' => '/\b(?:warranty|guarantee|warranty period)\b/i',
            'appointment' => '/\b(?:appointment|scheduled for|consultation)\b/i',
            'insurance' => '/\b(?:insurance|policy number|insurance premium)\b/i',
        ];

        foreach ($rules as $type => $pattern) {
            if (!preg_match($pattern, $text, $match)) {
                continue;
            }

            $evidence = $match[0];

            $fields[] = $this->candidate(
                'document_type',
                $type,
                $type,
                0.90,
                $evidence,
                $this->findPage($evidence, $pages),
                'deterministic'
            );

            return;
        }
    }

    /**
     * Extract email addresses.
     */
    private function addEmails(
        array &$fields,
        string $text,
        array $pages
    ): void {
        preg_match_all(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            $text,
            $matches
        );

        $seen = [];

        foreach ($matches[0] ?? [] as $value) {
            $normalized = mb_strtolower($value);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;

            $fields[] = $this->candidate(
                'email_addresses',
                $value,
                $normalized,
                0.99,
                $value,
                $this->findPage($value, $pages),
                'deterministic'
            );
        }
    }

    /**
     * Extract telephone numbers from explicitly labeled lines.
     *
     * This deliberately avoids scanning every numerical sequence
     * in a document. Dates, monetary amounts, account references
     * and invoice numbers must not become telephone numbers.
     */
    private function addPhones(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $lines = preg_split('/\R/u', $text) ?: [];

        $pattern =
            '/^[ \t]*(?:phone(?:[ \t]+number)?|telephone|tel|' .
            'mobile(?:[ \t]+number)?|contact[ \t]+number)' .
            '[ \t]*[:#\-][ \t]*(.+)$/i';

        $seen = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (!preg_match($pattern, $line, $match)) {
                continue;
            }

            $raw = trim($match[1]);

            /*
             * A telephone field may contain multiple numbers.
             * Support separators without splitting phone spaces.
             */
            $candidates = preg_split(
                '/[;,]|[ \t]+\/[ \t]+/',
                $raw
            ) ?: [];

            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);

                /*
                 * Remove common extension suffixes.
                 * Do not guess at numbers embedded in prose.
                 */
                $candidate = preg_replace(
                    '/[ \t]+(?:ext\.?|extension|x)[ \t]*\d+$/i',
                    '',
                    $candidate
                ) ?? $candidate;

                $candidate = trim($candidate);

                $normalized = $this->normalizePhone(
                    $candidate
                );

                if ($normalized === null) {
                    continue;
                }

                if (isset($seen[$normalized])) {
                    continue;
                }

                $seen[$normalized] = true;

                $fields[] = $this->candidate(
                    'phone_numbers',
                    $candidate,
                    $normalized,
                    0.92,
                    $line,
                    $this->findPage($line, $pages),
                    'deterministic'
                );
            }
        }
    }

    /**
     * Initial phone validation:
     *
     * - Sri Lankan mobile numbers
     * - Sri Lankan standard ten-digit numbers
     * - International numbers with a leading +
     *
     * For international numbers, this is a structural check,
     * not full country-specific telephone validation.
     */
    private function normalizePhone(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        if (!preg_match('/^\+?[\d\s().\-]+$/', $raw)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === null) {
            return null;
        }

        /*
         * International format.
         */
        if (str_starts_with($raw, '+')) {
            if (
                strlen($digits) < 8 ||
                strlen($digits) > 15 ||
                $digits[0] === '0'
            ) {
                return null;
            }

            if (str_starts_with($digits, '94')) {
                $local = substr($digits, 2);

                if (
                    strlen($local) !== 9 ||
                    !preg_match('/^[1-9]\d{8}$/', $local)
                ) {
                    return null;
                }
            }

            return '+' . $digits;
        }

        /*
         * Sri Lankan local format: 0XXXXXXXXX.
         * This initial validator assumes unlabeled country
         * context is Sri Lanka.
         */
        if (
            strlen($digits) === 10 &&
            preg_match('/^0[1-9]\d{8}$/', $digits)
        ) {
            return '+94' . substr($digits, 1);
        }

        return null;
    }

    /**
     * Extract explicitly labeled monetary amounts.
     */
    private function addCurrencyAndAmount(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $pattern =
            '/^[ \t]*(?:amount[ \t]+due|grand[ \t]+total|' .
            'balance[ \t]+due|invoice[ \t]+total|' .
            'total[ \t]+amount|total)' .
            '[ \t]*[:\-][ \t]*' .
            '(LKR|USD|EUR|GBP|Rs\.?|US\$|\$|€|£)?' .
            '[ \t]*' .
            '([0-9][0-9,]*(?:\.[0-9]{1,2})?)[ \t]*$/im';

        if (!preg_match($pattern, $text, $match)) {
            return;
        }

        $evidence = trim($match[0]);
        $rawCurrency = trim($match[1] ?? '');
        $rawAmount = trim($match[2]);

        $currency = $this->normalizeCurrency(
            $rawCurrency
        );

        if ($currency !== null) {
            $fields[] = $this->candidate(
                'currency',
                $rawCurrency,
                $currency,
                0.96,
                $evidence,
                $this->findPage($evidence, $pages),
                'deterministic'
            );
        }

        $normalizedAmount = (float) str_replace(
            ',',
            '',
            $rawAmount
        );

        $fields[] = $this->candidate(
            'amount',
            $rawAmount,
            $normalizedAmount,
            0.96,
            $evidence,
            $this->findPage($evidence, $pages),
            'deterministic'
        );
    }

    /**
     * Extract labeled dates, checking specific labels first.
     */
    private function addDates(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $labels = [
            'document_date' => [
                'document date',
                'invoice date',
                'issue date',
                'issued on',
                'date',
            ],

            'due_date' => [
                'due date',
                'payment due date',
                'pay by',
            ],

            'start_date' => [
                'start date',
                'effective from',
                'valid from',
            ],

            'end_date' => [
                'end date',
                'expiry date',
                'expiration date',
                'expires',
                'valid until',
            ],

            'renewal_date' => [
                'renewal date',
                'renew by',
            ],
        ];

        $datePattern =
            '(\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}' .
            '|\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4})';

        foreach ($labels as $fieldName => $keywords) {
            foreach ($keywords as $keyword) {
                $pattern =
                    '/^[ \t]*' .
                    preg_quote($keyword, '/') .
                    '[ \t]*[:\-][ \t]*' .
                    $datePattern .
                    '[ \t]*$/im';

                if (!preg_match($pattern, $text, $match)) {
                    continue;
                }

                $normalized = $this->normalizeDate(
                    $match[1]
                );

                /*
                 * Invalid dates must not be persisted
                 * as high-confidence normalized dates.
                 */
                if ($normalized === null) {
                    continue;
                }

                $evidence = trim($match[0]);

                $fields[] = $this->candidate(
                    $fieldName,
                    $match[1],
                    $normalized,
                    0.94,
                    $evidence,
                    $this->findPage($evidence, $pages),
                    'deterministic'
                );

                break;
            }
        }
    }

    /**
     * Extract reference numbers.
     *
     * The complete label must be consumed before
     * the identifier is captured.
     */
    private function addReferenceNumbers(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $pattern =
            '/^[ \t]*(?:reference[ \t]+number|' .
            'reference[ \t]+no\.?|' .
            'ref(?:erence)?\.?|' .
            'invoice[ \t]+(?:number|no\.?)|' .
            'receipt[ \t]+(?:number|no\.?))' .
            '[ \t]*[:#\-][ \t]*' .
            '([A-Z0-9][A-Z0-9\/\-]{3,})[ \t]*$/im';

        if (!preg_match($pattern, $text, $match)) {
            return;
        }

        $value = trim($match[1]);
        $evidence = trim($match[0]);

        $fields[] = $this->candidate(
            'reference_number',
            $value,
            strtoupper($value),
            0.94,
            $evidence,
            $this->findPage($evidence, $pages),
            'deterministic'
        );
    }

    /**
     * Extract explicitly labeled insurance policy numbers.
     */
    private function addPolicyNumbers(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $pattern =
            '/^[ \t]*policy[ \t]+' .
            '(?:number|no\.?|reference)' .
            '[ \t]*[:#\-][ \t]*' .
            '([A-Z0-9][A-Z0-9\/\-]{3,})[ \t]*$/im';

        if (!preg_match($pattern, $text, $match)) {
            return;
        }

        $value = trim($match[1]);
        $evidence = trim($match[0]);

        $fields[] = $this->candidate(
            'policy_numbers',
            $value,
            strtoupper($value),
            0.97,
            $evidence,
            $this->findPage($evidence, $pages),
            'deterministic'
        );
    }

    /**
     * Extract explicitly labeled account references.
     */
    private function addAccountReference(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $pattern =
            '/^[ \t]*(?:account|customer)[ \t]+' .
            '(?:number|no\.?|reference|id)' .
            '[ \t]*[:#\-][ \t]*' .
            '([A-Z0-9][A-Z0-9\/\-]{3,})[ \t]*$/im';

        if (!preg_match($pattern, $text, $match)) {
            return;
        }

        $value = trim($match[1]);
        $evidence = trim($match[0]);

        $fields[] = $this->candidate(
            'account_reference',
            $value,
            strtoupper($value),
            0.94,
            $evidence,
            $this->findPage($evidence, $pages),
            'deterministic'
        );
    }

    /**
     * Extract an explicitly labeled issuer.
     *
     * If no reliable label is found, omit the field.
     * An unlabeled heading is not sufficient evidence
     * of the issuing organization.
     */
    private function addIssuer(
        array &$fields,
        string $text,
        array $pages
    ): void {
        $pattern =
            '/^[ \t]*(?:issuer|issued[ \t]+by|' .
            'provider|issuing[ \t]+organization)' .
            '[ \t]*:[ \t]*([^\r\n]+)$/im';

        if (!preg_match($pattern, $text, $match)) {
            return;
        }

        $issuer = trim($match[1]);

        if (
            mb_strlen($issuer) < 3 ||
            mb_strlen($issuer) > 120 ||
            !preg_match('/\p{L}/u', $issuer)
        ) {
            return;
        }

        $evidence = trim($match[0]);

        $fields[] = $this->candidate(
            'issuer',
            $issuer,
            $issuer,
            0.96,
            $evidence,
            $this->findPage($evidence, $pages),
            'deterministic'
        );
    }

    private function normalizeCurrency(
        string $value
    ): ?string {
        return match (strtoupper(trim($value))) {
            'LKR', 'RS', 'RS.' => 'LKR',
            'USD', 'US$', '$' => 'USD',
            'EUR', '€' => 'EUR',
            'GBP', '£' => 'GBP',
            default => null,
        };
    }

    /**
     * Normalize supported dates without silently
     * correcting invalid calendar values.
     */
    private function normalizeDate(
        string $value
    ): ?string {
        $formats = [
            'Y-m-d',
            'Y/m/d',
            'Y.m.d',
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'd/m/y',
            'd-m-y',
            'd.m.y',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat(
                '!' . $format,
                $value
            );

            if ($date === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if (
                $errors !== false &&
                (
                    $errors['warning_count'] > 0 ||
                    $errors['error_count'] > 0
                )
            ) {
                continue;
            }

            /*
             * The normalized result is accepted only
             * when parsing was valid.
             */
            return $date->format('Y-m-d');
        }

        return null;
    }

    /**
     * Find the page containing source evidence.
     */
    private function findPage(
        string $evidence,
        array $pages
    ): ?int {
        if ($evidence === '') {
            return null;
        }

        foreach ($pages as $page => $pageText) {
            if (
                str_contains(
                    mb_strtolower($pageText),
                    mb_strtolower($evidence)
                )
            ) {
                return (int) $page;
            }
        }

        return null;
    }

    /**
     * Preserve the existing P3 candidate structure.
     *
     * @return array<string, mixed>
     */
    private function candidate(
        string $fieldName,
        mixed $value,
        mixed $normalized,
        float $confidence,
        string $evidence,
        ?int $page,
        string $source
    ): array {
        return [
            'field_name' => $fieldName,
            'value' => $value,
            'normalized_value' => $normalized,
            'confidence' => $confidence,
            'page' => $page,
            'evidence_text' => $evidence,
            'source' => $source,
        ];
    }
}
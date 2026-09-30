<?php

namespace App\Domain\Documents\Services;

class EvidenceGroundingValidator
{
    /**
     * @param array<int,array<string,mixed>> $fields
     * @return array{accepted:array<int,array<string,mixed>>,rejected:array<int,array<string,mixed>>}
     */
    public function filterGrounded(string $sourceText, array $fields): array
    {
        $haystack = $this->normalize($sourceText);
        $accepted = [];
        $rejected = [];

        foreach ($fields as $field) {
            $evidence = isset($field['evidence_text'])
                ? $this->normalize((string) $field['evidence_text'])
                : '';

            if ($evidence === '' || !str_contains($haystack, $evidence)) {
                $field['_rejection_reason'] = 'Evidence text was not found in the source document.';
                $rejected[] = $field;
                continue;
            }

            $accepted[] = $field;
        }

        return compact('accepted', 'rejected');
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtolower($value);
    }
}

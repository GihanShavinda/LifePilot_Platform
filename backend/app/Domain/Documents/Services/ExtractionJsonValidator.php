<?php

namespace App\Domain\Documents\Services;

class ExtractionJsonValidator
{
    /**
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    public function validate(array $payload): array
    {
        $errors = [];

        if (!isset($payload['fields']) || !is_array($payload['fields'])) {
            return ['The structured extraction payload must contain a fields array.'];
        }

        foreach ($payload['fields'] as $index => $field) {
            if (!is_array($field)) {
                $errors[] = "fields.{$index} must be an object.";
                continue;
            }

            foreach (['field_name', 'value', 'confidence', 'evidence_text'] as $required) {
                if (!array_key_exists($required, $field)) {
                    $errors[] = "fields.{$index}.{$required} is required.";
                }
            }

            if (
                isset($field['field_name'])
                && !in_array($field['field_name'], ExtractionSchema::FIELD_NAMES, true)
            ) {
                $errors[] = "fields.{$index}.field_name is not supported.";
            }

            if (
                isset($field['confidence'])
                && (!is_numeric($field['confidence'])
                    || (float) $field['confidence'] < 0
                    || (float) $field['confidence'] > 1)
            ) {
                $errors[] = "fields.{$index}.confidence must be between 0 and 1.";
            }

            if (
                isset($field['page'])
                && $field['page'] !== null
                && (!is_int($field['page']) || $field['page'] < 1)
            ) {
                $errors[] = "fields.{$index}.page must be null or a positive integer.";
            }

            if (
                isset($field['evidence_text'])
                && (!is_string($field['evidence_text']) || trim($field['evidence_text']) === '')
            ) {
                $errors[] = "fields.{$index}.evidence_text cannot be empty.";
            }
        }

        return $errors;
    }
}

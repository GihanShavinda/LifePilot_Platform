<?php

namespace App\Domain\Documents\Services;

class ExtractionConflictDetector
{
    /**
     * @param array<int,array<string,mixed>> $fields
     * @return array<int,string>
     */
    public function conflictingFieldNames(array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $name = (string) ($field['field_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $values[$name][] = json_encode(
                $field['normalized_value']
                    ?? $field['value']
                    ?? null
            );
        }

        return collect($values)
            ->filter(
                fn (array $items) =>
                    count(array_unique($items)) > 1
            )
            ->keys()
            ->values()
            ->all();
    }
}

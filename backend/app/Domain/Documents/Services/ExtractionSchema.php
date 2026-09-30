<?php

namespace App\Domain\Documents\Services;

class ExtractionSchema
{
    public const FIELD_NAMES = [
        'issuer',
        'document_type',
        'document_date',
        'reference_number',
        'currency',
        'amount',
        'due_date',
        'start_date',
        'end_date',
        'renewal_date',
        'person_names',
        'organization_names',
        'addresses',
        'phone_numbers',
        'email_addresses',
        'asset_names',
        'policy_numbers',
        'account_reference',
        'line_items',
        'important_notes',
    ];

    /** @return array<string,mixed> */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['fields'],
            'properties' => [
                'fields' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'field_name',
                            'value',
                            'normalized_value',
                            'confidence',
                            'page',
                            'evidence_text',
                        ],
                        'properties' => [
                            'field_name' => [
                                'type' => 'string',
                                'enum' => self::FIELD_NAMES,
                            ],
                            'value' => [],
                            'normalized_value' => [],
                            'confidence' => [
                                'type' => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                            ],
                            'page' => [
                                'type' => ['integer', 'null'],
                                'minimum' => 1,
                            ],
                            'evidence_text' => [
                                'type' => 'string',
                                'minLength' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

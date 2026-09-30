<?php

namespace App\Domain\Documents\Contracts;

interface StructuredExtractionClient
{
    /**
     * @param array<string,mixed> $schema
     * @return array{fields:array<int,array<string,mixed>>,model_version:?string,raw:?array}
     */
    public function extract(string $documentText, array $schema): array;
}

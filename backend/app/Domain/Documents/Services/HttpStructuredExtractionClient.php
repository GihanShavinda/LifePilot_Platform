<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Contracts\StructuredExtractionClient;
use Illuminate\Support\Facades\Http;

class HttpStructuredExtractionClient implements StructuredExtractionClient
{
    public function __construct(
        private PromptInjectionGuard $guard
    ) {
    }

    /**
     * Calls a configurable internal/external LLM extraction gateway.
     *
     * Expected response:
     * {
     *   "fields": [...],
     *   "model_version": "provider/model",
     *   "raw": {...}
     * }
     *
     * When no endpoint is configured, the pipeline safely continues with
     * deterministic extraction only.
     *
     * @param array<string,mixed> $schema
     * @return array{fields:array<int,array<string,mixed>>,model_version:?string,raw:?array}
     */
    public function extract(string $documentText, array $schema): array
    {
        $endpoint = trim((string) config('documents.intelligence.llm.endpoint', ''));

        if ($endpoint === '') {
            return [
                'fields' => [],
                'model_version' => null,
                'raw' => ['skipped' => true, 'reason' => 'No LLM endpoint configured.'],
            ];
        }

        $headers = [];
        $token = trim((string) config('documents.intelligence.llm.token', ''));

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $response = Http::timeout((int) config('documents.intelligence.llm.timeout', 90))
            ->acceptJson()
            ->withHeaders($headers)
            ->post($endpoint, [
                'system_instruction' => $this->guard->systemInstruction(),
                'document_text' => $documentText,
                'json_schema' => $schema,
                'temperature' => 0,
            ]);

        $response->throw();

        $payload = $response->json();

        return [
            'fields' => is_array($payload['fields'] ?? null) ? $payload['fields'] : [],
            'model_version' => isset($payload['model_version'])
                ? (string) $payload['model_version']
                : null,
            'raw' => is_array($payload) ? $payload : null,
        ];
    }
}

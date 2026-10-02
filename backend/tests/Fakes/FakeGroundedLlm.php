<?php

namespace Tests\Fakes;

use App\Domain\Assistant\Contracts\GroundedLlm;

class FakeGroundedLlm implements GroundedLlm
{
    public function __construct(private ?array $response) {}
    public function generate(array $payload): ?array
    {
        return $this->response;
    }
    public function provider(): string
    {
        return 'fake';
    }
    public function model(): string
    {
        return 'fake-grounded';
    }
    public function version(): string
    {
        return 'test';
    }
}

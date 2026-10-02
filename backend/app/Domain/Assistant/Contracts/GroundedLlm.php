<?php
namespace App\Domain\Assistant\Contracts;
interface GroundedLlm
{
    /** @return array<string,mixed>|null */
    public function generate(array $payload): ?array;
    public function provider(): string;
    public function model(): string;
    public function version(): string;
}

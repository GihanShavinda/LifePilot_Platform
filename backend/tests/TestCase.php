<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prepare the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Simulate requests from the React SPA
        |--------------------------------------------------------------------------
        |
        | Sanctum checks Origin / Referer to decide whether an API request
        | should receive stateful SPA middleware including sessions.
        |
        */

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }
}
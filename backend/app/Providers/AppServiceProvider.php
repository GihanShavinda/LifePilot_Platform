<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\MalwareScanner;
use App\Domain\Documents\Contracts\StructuredExtractionClient;
use App\Domain\Documents\Services\HttpStructuredExtractionClient;
use App\Domain\Documents\Services\NullMalwareScanner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MalwareScanner::class,
            NullMalwareScanner::class
        );

        $this->app->bind(
            StructuredExtractionClient::class,
            HttpStructuredExtractionClient::class
        );
    }

    public function boot(): void
    {
    }
}

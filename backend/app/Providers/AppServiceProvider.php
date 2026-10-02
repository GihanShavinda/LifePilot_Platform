<?php

namespace App\Providers;

use App\Domain\Documents\Contracts\MalwareScanner;
use App\Domain\Documents\Contracts\StructuredExtractionClient;
use App\Domain\Documents\Services\HttpStructuredExtractionClient;
use App\Domain\Documents\Services\NullMalwareScanner;
use App\Domain\Graph\Contracts\EmbeddingProvider;
use App\Domain\Graph\Services\DeterministicHashEmbeddingProvider;
use App\Domain\Scheduling\Contracts\PushAdapter;
use App\Domain\Scheduling\Services\DisabledPushAdapter;
use App\Domain\Assistant\Contracts\GroundedLlm;
use App\Domain\Assistant\Services\HttpGroundedLlm;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PushAdapter::class,
            DisabledPushAdapter::class
        );

        $this->app->bind(
            MalwareScanner::class,
            NullMalwareScanner::class
        );

        $this->app->bind(
            StructuredExtractionClient::class,
            HttpStructuredExtractionClient::class
        );

        $this->app->singleton(
            EmbeddingProvider::class,
            fn () => new DeterministicHashEmbeddingProvider(
                (int) config('lifepilot_graph.embedding_dimensions', 384)
            )
        );

        $this->app->singleton(
            GroundedLlm::class,
            HttpGroundedLlm::class
        );
    }

    public function boot(): void
    {
    }
}

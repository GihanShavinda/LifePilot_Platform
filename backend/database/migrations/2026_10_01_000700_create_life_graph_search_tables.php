<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transactional migration wrapping.
     *
     * PostgreSQL marks the current transaction as aborted after a failed
     * statement. Since pgvector is optional in P7, failed extension/vector
     * setup must not roll back the core graph/search tables.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Life entities
        |--------------------------------------------------------------------------
        */

        Schema::create('life_entities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('entity_type', 40);

            $table->string('source_type', 80)
                ->nullable();

            $table->string('source_id', 120)
                ->nullable();

            $table->string('canonical_key', 500);

            $table->string('label');

            $table->text('search_text')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->json('source_reference')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'household_id',
                'canonical_key',
            ]);

            $table->index([
                'household_id',
                'entity_type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Life graph relations
        |--------------------------------------------------------------------------
        */

        Schema::create('life_relations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('from_entity_id')
                ->constrained('life_entities')
                ->cascadeOnDelete();

            $table->foreignId('to_entity_id')
                ->constrained('life_entities')
                ->cascadeOnDelete();

            $table->string('relation_type', 60);

            $table->string('dedupe_key', 64)
                ->unique();

            $table->json('metadata')
                ->nullable();

            $table->json('source_reference')
                ->nullable();

            $table->timestamps();

            $table->index([
                'household_id',
                'relation_type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Document chunks
        |--------------------------------------------------------------------------
        */

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('document_extraction_id')
                ->nullable()
                ->constrained('document_extractions')
                ->nullOnDelete();

            $table->unsignedInteger('chunk_index');

            $table->text('content');

            $table->string('content_hash', 64);

            $table->json('metadata')
                ->nullable();

            $table->json('source_reference')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'document_id',
                'document_extraction_id',
                'chunk_index',
            ]);

            $table->index([
                'household_id',
                'document_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Embedding records
        |--------------------------------------------------------------------------
        |
        | embedding_json is always available.
        |
        | If pgvector is installed, an additional vector(384) column is added.
        | If pgvector is unavailable, P7 continues using JSON embeddings and
        | PHP cosine similarity.
        |
        */

        Schema::create('embedding_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('document_chunk_id')
                ->unique()
                ->constrained('document_chunks')
                ->cascadeOnDelete();

            $table->string('provider', 40);

            $table->string('model', 120);

            $table->unsignedSmallInteger('dimensions');

            $table->longText('embedding_json');

            $table->string('content_hash', 64);

            $table->timestamps();

            $table->index('household_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Optional pgvector support
        |--------------------------------------------------------------------------
        */

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $vectorAvailable = false;

        try {
            DB::statement(
                'CREATE EXTENSION IF NOT EXISTS vector'
            );

            $vectorAvailable = true;
        } catch (\Throwable $exception) {
            /*
             * pgvector is optional.
             *
             * Do not fail P7 migration if:
             * - vector is not installed on PostgreSQL
             * - current DB user cannot create extensions
             * - server package is unavailable
             *
             * JSON embedding fallback remains usable.
             */
            $vectorAvailable = false;
        }

        if (! $vectorAvailable) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Add native vector column
        |--------------------------------------------------------------------------
        */

        try {
            DB::statement(
                'ALTER TABLE embedding_records
                 ADD COLUMN IF NOT EXISTS embedding vector(384)'
            );
        } catch (\Throwable $exception) {
            /*
             * Keep JSON fallback if native vector column cannot be created.
             */
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Optional HNSW index
        |--------------------------------------------------------------------------
        |
        | Older pgvector versions may not support HNSW.
        | Search can still work without this ANN index.
        |
        */

        try {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS embedding_records_embedding_hnsw
                 ON embedding_records
                 USING hnsw (embedding vector_cosine_ops)'
            );
        } catch (\Throwable $exception) {
            /*
             * Ignore index creation failure.
             * Native vector search may still be available without HNSW.
             */
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('embedding_records');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('life_relations');
        Schema::dropIfExists('life_entities');
    }
};
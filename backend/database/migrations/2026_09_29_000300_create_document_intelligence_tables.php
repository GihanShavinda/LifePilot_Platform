<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40)->default('queued');
            $table->char('source_text_hash', 64)->nullable();
            $table->longText('source_text')->nullable();
            $table->string('document_type', 80)->nullable();
            $table->decimal('overall_confidence', 5, 4)->nullable();
            $table->string('extractor_version', 80);
            $table->string('model_version', 120)->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_version_id', 'source_text_hash']);
            $table->index(['document_id', 'status']);
        });

        Schema::create('extracted_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_extraction_id')->constrained('document_extractions')->cascadeOnDelete();
            $table->string('field_name', 100);
            $table->json('value');
            $table->json('normalized_value')->nullable();
            $table->decimal('confidence', 5, 4);
            $table->unsignedInteger('page')->nullable();
            $table->text('evidence_text');
            $table->string('extractor_version', 80);
            $table->string('model_version', 120)->nullable();
            $table->string('review_status', 40)->default('pending');
            $table->string('source', 40)->default('deterministic');
            $table->char('fingerprint', 64);
            $table->timestamps();

            $table->unique(['document_extraction_id', 'fingerprint']);
            $table->index(['document_extraction_id', 'field_name']);
            $table->index(['review_status', 'confidence']);
        });

        Schema::create('extraction_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extracted_field_id')->constrained('extracted_fields')->cascadeOnDelete();
            $table->unsignedInteger('page')->nullable();
            $table->text('evidence_text');
            $table->unsignedInteger('char_start')->nullable();
            $table->unsignedInteger('char_end')->nullable();
            $table->string('source', 40)->default('document');
            $table->timestamps();
        });

        Schema::create('extraction_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_extraction_id')->constrained('document_extractions')->cascadeOnDelete();
            $table->foreignId('extracted_field_id')->nullable()->constrained('extracted_fields')->nullOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 20);
            $table->json('previous_value')->nullable();
            $table->json('reviewed_value')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['document_extraction_id', 'reviewed_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_reviews');
        Schema::dropIfExists('extraction_evidence');
        Schema::dropIfExists('extracted_fields');
        Schema::dropIfExists('document_extractions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('document_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->foreignId('document_source_id')->nullable()->constrained('document_sources')->nullOnDelete();
            $table->string('title', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('storage_disk', 40)->default('documents');
            $table->string('storage_path', 1024);
            $table->char('checksum', 64)->index();
            $table->date('document_date')->nullable()->index();
            $table->string('issuer', 255)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->string('processing_status', 30)->default('pending')->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('storage_disk', 40)->default('documents');
            $table->string('storage_path', 1024);
            $table->char('checksum', 64)->index();
            $table->timestamps();
            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('document_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->timestamps();
            $table->unique(['household_id', 'slug']);
        });

        Schema::create('document_document_tag', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_id', 'document_tag_id']);
        });

        Schema::create('document_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_type', 80)->default('baseline');
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('message')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_processing_jobs');
        Schema::dropIfExists('document_document_tag');
        Schema::dropIfExists('document_tags');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_sources');
        Schema::dropIfExists('document_categories');
    }
};

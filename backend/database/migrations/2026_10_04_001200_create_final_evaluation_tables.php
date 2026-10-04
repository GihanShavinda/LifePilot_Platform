<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('P12 final evaluation');
            $table->string('status', 30)->default('completed');
            $table->json('summary')->nullable();
            $table->timestampTz('generated_at');
            $table->timestamps();
            $table->index(['household_id', 'user_id', 'generated_at']);
        });

        Schema::create('evaluation_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('variant', 40);
            $table->string('metric_key', 100);
            $table->string('case_key')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('evidence_refs')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'variant', 'metric_key']);
        });

        Schema::create('evaluation_metric_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_run_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 40);
            $table->string('metric_key', 100);
            $table->string('label');
            $table->decimal('value', 12, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('status', 40);
            $table->unsignedInteger('sample_size')->default(0);
            $table->text('explanation');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_run_id', 'variant', 'metric_key'], 'evaluation_metric_unique');
        });

        Schema::create('security_review_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 100);
            $table->string('status', 30);
            $table->string('title');
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->timestampTz('reviewed_at');
            $table->timestamps();
            $table->unique(['household_id', 'user_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_review_results');
        Schema::dropIfExists('evaluation_metric_results');
        Schema::dropIfExists('evaluation_cases');
        Schema::dropIfExists('evaluation_runs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prediction_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prediction_type', 80);
            $table->string('method', 80);
            $table->string('model_version', 80);
            $table->string('feature_version', 80);
            $table->char('input_fingerprint', 64);
            $table->json('prediction');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->text('explanation');
            $table->json('evidence_refs')->nullable();
            $table->timestampTz('generated_at');
            $table->timestamps();

            $table->unique([
                'household_id', 'user_id', 'prediction_type', 'model_version',
                'feature_version', 'input_fingerprint'
            ], 'prediction_records_reproducible_unique');
            $table->index(['household_id', 'user_id', 'prediction_type', 'generated_at'], 'prediction_records_lookup_idx');
        });

        Schema::create('analytics_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('insight_key', 120);
            $table->string('type', 80);
            $table->string('severity', 20)->default('info');
            $table->string('title');
            $table->text('message');
            $table->json('metrics')->nullable();
            $table->json('evidence_refs');
            $table->string('model_version', 80);
            $table->string('feature_version', 80);
            $table->timestampTz('generated_at');
            $table->timestamps();

            $table->unique(['household_id', 'user_id', 'insight_key'], 'analytics_insights_user_key_unique');
            $table->index(['household_id', 'user_id', 'generated_at']);
        });

        Schema::create('data_quality_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 120);
            $table->string('status', 20); // ok, warning, info
            $table->string('title');
            $table->text('message');
            $table->json('metrics')->nullable();
            $table->json('evidence_refs')->nullable();
            $table->timestampTz('generated_at');
            $table->timestamps();

            $table->unique(['household_id', 'user_id', 'check_key'], 'data_quality_checks_user_key_unique');
            $table->index(['household_id', 'user_id', 'status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_checks');
        Schema::dropIfExists('analytics_insights');
        Schema::dropIfExists('prediction_records');
    }
};

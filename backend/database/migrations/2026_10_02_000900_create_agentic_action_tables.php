<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_policies', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 80)->unique();
            $table->string('risk_level', 20);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_explicit_high_risk_ack')->default(false);
            $table->boolean('enabled')->default(true);
            $table->json('conditions')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_conversation_message_id')
                ->nullable()
                ->constrained('conversation_messages')
                ->nullOnDelete();
            $table->text('request');
            $table->string('origin', 30)->default('user');
            $table->string('status', 30)->default('preview');
            $table->string('overall_risk', 20)->default('low');
            $table->string('idempotency_key', 64);
            $table->json('evidence_refs')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'idempotency_key']);
            $table->index(['household_id', 'status', 'created_at']);
        });

        Schema::create('action_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('action_type', 80);
            $table->string('risk_level', 20);
            $table->string('status', 30)->default('proposed');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('payload');
            $table->json('evidence_refs')->nullable();
            $table->json('preview')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('external_effect')->default(false);
            $table->timestamps();
            $table->unique(['action_plan_id', 'sequence']);
            $table->index(['action_plan_id', 'status']);
        });

        Schema::create('action_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('action_step_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 20);
            $table->boolean('explicit_high_risk_ack')->default(false);
            $table->text('comment')->nullable();
            $table->timestampTz('decided_at');
            $table->timestamps();
            $table->unique(['action_plan_id', 'action_step_id', 'user_id']);
        });

        Schema::create('action_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('action_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('idempotency_key', 64)->unique();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['action_plan_id', 'status']);
        });

        Schema::create('action_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_execution_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('action_step_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('result')->nullable();
            $table->json('verification')->nullable();
            $table->string('rollback_status', 30)->nullable();
            $table->json('rollback_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_results');
        Schema::dropIfExists('action_executions');
        Schema::dropIfExists('action_approvals');
        Schema::dropIfExists('action_steps');
        Schema::dropIfExists('action_plans');
        Schema::dropIfExists('action_policies');
    }
};

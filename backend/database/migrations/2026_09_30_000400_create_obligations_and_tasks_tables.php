<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('obligations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('document_extraction_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 40);
            $t->string('title');
            $t->text('description')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->char('currency', 3)->nullable();
            $t->dateTimeTz('due_at')->nullable();
            $t->string('status', 25)->default('suggested');
            $t->json('source_evidence')->nullable();
            $t->char('dedupe_key', 64);
            $t->timestampTz('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['household_id', 'dedupe_key']);
            $t->index(['household_id', 'status', 'due_at']);
        });
        Schema::create('recurring_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->string('frequency', 15); // daily, weekly, monthly, yearly
            $t->unsignedSmallInteger('interval')->default(1);
            $t->dateTimeTz('starts_at');
            $t->dateTimeTz('until_at')->nullable();
            $t->unsignedInteger('max_occurrences')->nullable();
            $t->unsignedInteger('generated_occurrences')->default(0);
            $t->dateTimeTz('next_at')->nullable();
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('obligation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('recurring_rule_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('priority', 12)->default('medium');
            $t->string('status', 25)->default('pending');
            $t->json('labels')->nullable();
            $t->dateTimeTz('due_at')->nullable();
            $t->dateTimeTz('completed_at')->nullable();
            $t->text('completion_evidence')->nullable();
            $t->unsignedInteger('occurrence_number')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['recurring_rule_id', 'occurrence_number']);
            $t->index(['household_id', 'status', 'due_at']);
        });
        Schema::create('task_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_completed')->default(false);
            $t->timestampTz('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('task_dependencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['task_id', 'depends_on_task_id']);
        });
        Schema::create('reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->dateTimeTz('remind_at');
            $t->dateTimeTz('snoozed_until')->nullable();
            $t->dateTimeTz('sent_at')->nullable();
            $t->string('channel', 15)->default('in_app');
            $t->string('status', 20)->default('scheduled');
            $t->boolean('recommended')->default(false);
            $t->timestamps();
            $t->index(['status', 'remind_at']);
        });
        Schema::create('task_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 60);
            $t->json('details')->nullable();
            $t->timestamps();
            $t->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['task_activities', 'reminders', 'task_dependencies', 'task_checklist_items', 'tasks', 'recurring_rules', 'obligations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

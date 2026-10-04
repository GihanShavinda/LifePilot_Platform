<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 30);
            $table->char('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'email', 'status']);
        });

        Schema::create('shared_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id');
            $table->string('scope', 20)->default('private');
            $table->timestampTz('shared_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'resource_type', 'resource_id']);
            $table->index(['household_id', 'scope', 'resource_type']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('assignable_type', 40);
            $table->unsignedBigInteger('assignable_id');
            $table->foreignId('assignee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('assigned');
            $table->text('note')->nullable();
            $table->timestampTz('assigned_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'assignable_type', 'assignable_id']);
            $table->index(['household_id', 'assignee_user_id', 'status']);
        });

        Schema::create('household_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 80)->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_activities');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('shared_resources');
        Schema::dropIfExists('household_invitations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversation_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title')->nullable();
            $t->string('status', 30)->default('active');
            $t->timestampTz('last_message_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['household_id', 'user_id']);
        });
        Schema::create('conversation_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20);
            $t->string('intent', 50)->nullable();
            $t->text('content');
            $t->json('citations')->nullable();
            $t->json('claims')->nullable();
            $t->string('model_provider', 80)->nullable();
            $t->string('model_name', 160)->nullable();
            $t->string('model_version', 80)->nullable();
            $t->string('grounding_status', 40)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['conversation_session_id', 'id']);
        });
        Schema::create('retrieval_traces', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('conversation_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->text('query');
            $t->string('intent', 50);
            $t->string('retriever', 80);
            $t->json('filters')->nullable();
            $t->json('evidence')->nullable();
            $t->unsignedInteger('result_count')->default(0);
            $t->unsignedInteger('latency_ms')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['household_id', 'created_at']);
        });
        Schema::create('assistant_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->smallInteger('rating');
            $t->string('reason', 120)->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->unique(['conversation_message_id', 'user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('assistant_feedback');
        Schema::dropIfExists('retrieval_traces');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_sessions');
    }
};

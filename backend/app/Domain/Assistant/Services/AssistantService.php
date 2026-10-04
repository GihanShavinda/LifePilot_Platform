<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Contracts\GroundedLlm;
use App\Domain\Assistant\Models\{ConversationMessage, ConversationSession};
use App\Domain\Users\Models\User;

class AssistantService
{
    public function __construct(
        private AssistantAccess $access,
        private IntentClassifier $classifier,
        private EvidenceBundleBuilder $retrieval,
        private AssistantPromptBuilder $promptBuilder,
        private GroundedLlm $llm,
        private GroundedResponseValidator $validator,
        private DeterministicFallbackResponder $fallback,
    ) {
    }

    public function ask(User $user, ConversationSession $session, string $question): ConversationMessage
    {
        $totalStarted = microtime(true);
        abort_unless($session->user_id === $user->id, 404);
        $householdId = $this->access->householdId($user);
        abort_unless($session->household_id === $householdId, 404);

        $intent = $this->classifier->classify($question);
        $userMessage = $session->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'intent' => $intent->value,
            'content' => $question,
            'grounding_status' => 'n/a',
        ]);

        $started = microtime(true);
        $evidence = $this->retrieval->build($user, $householdId, $question, $intent);
        $latency = (int) round((microtime(true) - $started) * 1000);

        $trace = $session->traces()->create([
            'conversation_message_id' => $userMessage->id,
            'household_id' => $householdId,
            'query' => $question,
            'intent' => $intent->value,
            'retriever' => 'p10-sharing-aware-evidence-bundle',
            'filters' => ['privacy_scope' => 'owner_private_plus_explicit_household_shares'],
            'evidence' => $evidence,
            'result_count' => count($evidence),
            'latency_ms' => $latency,
        ]);

        $validated = null;
        $fallbackReason = 'model_unavailable';
        if ($evidence) {
            $raw = $this->llm->generate($this->promptBuilder->build($question, $intent, $evidence));
            $check = $this->validator->validate($raw, $evidence);
            if ($check['valid'] ?? false) {
                $validated = $check;
            } else {
                $fallbackReason = $check['reason'] ?? 'validation_failed';
            }
        }

        $result = $validated
            ? [
                'answer' => $validated['answer'],
                'claims' => $validated['claims'],
                'citations' => $validated['citations'],
                'draft' => $validated['draft'],
                'grounding_status' => 'validated',
                'fallback_reason' => null,
            ]
            : $this->fallback->respond($question, $intent, $evidence, $fallbackReason);

        $evidenceMap = [];
        foreach ($evidence as $item) {
            $evidenceMap[$item['key']] = $item;
        }

        $citationDetails = [];
        foreach ($result['citations'] as $key) {
            if (isset($evidenceMap[$key])) {
                $citationDetails[] = [
                    'key' => $key,
                    'type' => $evidenceMap[$key]['type'],
                    'id' => $evidenceMap[$key]['id'],
                    'title' => $evidenceMap[$key]['title'],
                    'source' => $evidenceMap[$key]['source'],
                ];
            }
        }

        $assistant = $session->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'intent' => $intent->value,
            'content' => $result['answer'],
            'citations' => $result['citations'],
            'claims' => $result['claims'],
            'model_provider' => $this->llm->provider(),
            'model_name' => $this->llm->model(),
            'model_version' => $this->llm->version(),
            'grounding_status' => $result['grounding_status'],
            'metadata' => [
                'draft' => $result['draft'],
                'fallback_reason' => $result['fallback_reason'],
                'retrieval_trace_id' => $trace->id,
                'citation_details' => $citationDetails,
                'privacy_scope' => 'sharing_aware',
                'retrieval_latency_ms' => $latency,
                'total_response_latency_ms' => (int) round((microtime(true) - $totalStarted) * 1000),
            ],
        ]);

        $session->update([
            'last_message_at' => now(),
            'title' => $session->title ?: mb_substr($question, 0, 80),
        ]);

        return $assistant->fresh();
    }
}

<?php

namespace App\Domain\Assistant\Controllers;

use App\Domain\Assistant\Models\{AssistantFeedback, ConversationMessage, ConversationSession};
use App\Domain\Assistant\Services\{AssistantAccess, AssistantService};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AssistantController extends Controller
{
    public function sessions(Request $r, AssistantAccess $a)
    {
        $hid = $a->householdId($r->user());
        return response()->json(['success' => true, 'data' => ConversationSession::where('user_id', $r->user()->id)->where('household_id', $hid)->latest('last_message_at')->paginate(20)]);
    }
    public function createSession(Request $r, AssistantAccess $a)
    {
        $hid = $a->householdId($r->user());
        $s = ConversationSession::create(['household_id' => $hid, 'user_id' => $r->user()->id, 'title' => $r->string('title')->toString() ?: null, 'status' => 'active', 'last_message_at' => now()]);
        return response()->json(['success' => true, 'data' => ['session' => $s]], 201);
    }
    public function show(Request $r, int $id, AssistantAccess $a)
    {
        $hid = $a->householdId($r->user());
        $s = ConversationSession::where('id', $id)->where('household_id', $hid)->where('user_id', $r->user()->id)->with('messages')->firstOrFail();
        return response()->json(['success' => true, 'data' => ['session' => $s]]);
    }
    public function ask(Request $r, int $id, AssistantService $assistant, AssistantAccess $a)
    {
        $v = $r->validate(['question' => 'required|string|min:2|max:4000']);
        $hid = $a->householdId($r->user());
        $s = ConversationSession::where('id', $id)->where('household_id', $hid)->where('user_id', $r->user()->id)->firstOrFail();
        $m = $assistant->ask($r->user(), $s, $v['question']);
        return response()->json(['success' => true, 'data' => ['message' => $m]], 201);
    }
    public function feedback(Request $r, int $messageId)
    {
        $v = $r->validate(['rating' => 'required|integer|in:-1,1', 'reason' => 'nullable|string|max:120', 'comment' => 'nullable|string|max:2000']);
        $m = ConversationMessage::where('id', $messageId)->where('user_id', $r->user()->id)->where('role', 'assistant')->firstOrFail();
        $f = AssistantFeedback::updateOrCreate(['conversation_message_id' => $m->id, 'user_id' => $r->user()->id], $v);
        return response()->json(['success' => true, 'data' => ['feedback' => $f]]);
    }
}

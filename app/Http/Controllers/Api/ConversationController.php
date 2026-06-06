<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'asc')
            ->take(80)
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Check message limit for free users
        if (!$user->canSendMessage()) {
            return response()->json([
                'error'   => 'daily_limit_reached',
                'message' => 'You have reached your 20 daily message limit. Upgrade to Pro for unlimited messages.',
                'upgrade_url' => config('app.frontend_url') . '/upgrade',
            ], 429);
        }

        $request->validate([
            'role'    => 'required|in:user,assistant',
            'content' => 'required|string',
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'role'    => $request->role,
            'content' => $request->content,
        ]);

        // Only count user messages
        if ($request->role === 'user') {
            $user->incrementMessageCount();
        }

        // Keep only last 80 messages per user
        $count = Conversation::where('user_id', $user->id)->count();
        if ($count > 80) {
            Conversation::where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->take($count - 80)
                ->delete();
        }

        return response()->json([
            'conversation'      => $conversation,
            'messages_remaining' => $user->messagesRemaining(),
        ], 201);
    }

    public function clear(Request $request)
    {
        Conversation::where('user_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Conversation cleared']);
    }
}

<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Subscription;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // Dashboard stats
    public function stats()
    {
        $today     = now()->toDateString();
        $thisMonth = now()->startOfMonth();

        return response()->json([
            'users' => [
                'total'      => User::count(),
                'pro'        => User::where('plan', 'pro')->count(),
                'free'       => User::where('plan', 'free')->count(),
                'new_today'  => User::whereDate('created_at', $today)->count(),
                'new_month'  => User::where('created_at', '>=', $thisMonth)->count(),
                'onboarded'  => User::where('onboarded', true)->count(),
            ],
            'activity' => [
                'dau'                  => User::whereDate('updated_at', $today)->count(),
                'messages_today'       => Conversation::whereDate('created_at', $today)->count(),
                'messages_this_month'  => Conversation::where('created_at', '>=', $thisMonth)->count(),
            ],
            'revenue' => [
                'total_ngn'       => Subscription::where('status', 'active')->sum('amount'),
                'monthly_ngn'     => Subscription::where('status', 'active')->where('created_at', '>=', $thisMonth)->sum('amount'),
                'active_subs'     => Subscription::where('status', 'active')->count(),
                'cancelled_subs'  => Subscription::where('status', 'cancelled')->count(),
            ],
        ]);
    }

    // List all users
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
        }

        if ($request->plan) {
            $query->where('plan', $request->plan);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn($u) => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'email'               => $u->email,
                'plan'                => $u->plan,
                'is_pro'              => $u->isPro(),
                'occupation'          => $u->occupation,
                'whatsapp_number'     => $u->whatsapp_number,
                'messages_today'      => $u->daily_message_count,
                'onboarded'           => $u->onboarded,
                'created_at'          => $u->created_at->toDateString(),
                'pro_expires_at'      => $u->pro_expires_at,
            ]);

        return response()->json($users);
    }

    // Single user detail
    public function user(User $user)
    {
        return response()->json([
            'user'          => $user,
            'memory'        => $user->memory,
            'subscriptions' => $user->subscriptions()->latest()->get(),
            'message_count' => Conversation::where('user_id', $user->id)->count(),
        ]);
    }

    // Grant/revoke pro manually
    public function grantPro(Request $request, User $user)
    {
        $request->validate(['months' => 'required|integer|min:1|max:24']);
        $user->update([
            'plan'           => 'pro',
            'pro_expires_at' => now()->addMonths($request->months),
        ]);
        return response()->json(['message' => "Pro granted for {$request->months} month(s)"]);
    }

    public function revokePro(User $user)
    {
        $user->update(['plan' => 'free', 'pro_expires_at' => null]);
        return response()->json(['message' => 'Pro revoked']);
    }

    // Delete user
    public function deleteUser(User $user)
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Cannot delete admin users'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    // Subscription list
    public function subscriptions(Request $request)
    {
        $subs = Subscription::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json($subs);
    }

    // Recent activity
    public function activity()
    {
        $recentUsers = User::latest()->take(10)->get(['id', 'name', 'email', 'plan', 'created_at']);
        $recentSubs  = Subscription::with('user:id,name,email')->latest()->take(10)->get();

        return response()->json([
            'recent_users'         => $recentUsers,
            'recent_subscriptions' => $recentSubs,
        ]);
    }
}

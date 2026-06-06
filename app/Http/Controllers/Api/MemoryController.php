<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memory;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function get(Request $request)
    {
        $memory = Memory::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['goals' => [], 'habits' => [], 'reminders' => [], 'notes' => [], 'habit_logs' => [], 'whatsapp_logs' => []]
        );

        return response()->json(['memory' => $memory]);
    }

    public function update(Request $request)
    {
        $memory = Memory::firstOrCreate(['user_id' => $request->user()->id]);

        $allowed = ['goals', 'habits', 'reminders', 'notes', 'habit_logs', 'health', 'whatsapp_logs'];
        $data = $request->only($allowed);

        $memory->update($data);

        return response()->json(['memory' => $memory]);
    }

    public function clear(Request $request)
    {
        $memory = Memory::where('user_id', $request->user()->id)->first();
        if ($memory) {
            $memory->update([
                'goals' => [], 'habits' => [], 'reminders' => [],
                'notes' => [], 'habit_logs' => [], 'health' => null, 'whatsapp_logs' => [],
            ]);
        }

        // Also clear onboarding
        $request->user()->update(['onboarded' => false, 'age' => null, 'marital_status' => null, 'occupation' => null, 'whatsapp_number' => null]);

        return response()->json(['message' => 'Memory cleared']);
    }

    public function export(Request $request)
    {
        $user   = $request->user();
        $memory = Memory::where('user_id', $user->id)->first();

        return response()->json([
            'user'   => ['name' => $user->name, 'email' => $user->email, 'age' => $user->age, 'occupation' => $user->occupation],
            'memory' => $memory,
        ]);
    }
}

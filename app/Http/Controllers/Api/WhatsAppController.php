<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private function sendViaTwilio(string $to, string $message): bool
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from  = config('services.twilio.whatsapp_from');

        if (!$sid || !$token) {
            Log::warning('Twilio credentials not configured');
            return false;
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => "whatsapp:{$from}",
                'To'   => "whatsapp:{$to}",
                'Body' => $message,
            ]);

        return $response->successful();
    }

    public function send(Request $request)
    {
        $request->validate([
            'phone'   => 'required|string',
            'message' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user->isPro()) {
            return response()->json(['message' => 'WhatsApp reminders require Pro plan'], 403);
        }

        $sent = $this->sendViaTwilio($request->phone, $request->message);

        return response()->json([
            'sent'    => $sent,
            'message' => $sent ? 'Message sent' : 'Failed to send message',
        ]);
    }

    public function scheduleReminder(Request $request)
    {
        $request->validate([
            'phone'          => 'required|string',
            'message'        => 'required|string',
            'reminder_title' => 'required|string',
            'send_at'        => 'required|date',
            'recurring'      => 'boolean',
            'recurrence_rule'=> 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user->isPro()) {
            return response()->json(['message' => 'WhatsApp reminders require Pro plan'], 403);
        }

        \DB::table('whatsapp_queue')->insert([
            'user_id'         => $user->id,
            'phone'           => $request->phone,
            'message'         => $request->message,
            'reminder_title'  => $request->reminder_title,
            'send_at'         => $request->send_at,
            'recurring'       => $request->recurring ?? false,
            'recurrence_rule' => $request->recurrence_rule,
            'sent'            => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => 'Reminder scheduled']);
    }

    // Called by Laravel Scheduler every minute
    public static function processQueue(): void
    {
        $due = \DB::table('whatsapp_queue')
            ->where('sent', false)
            ->where('send_at', '<=', now())
            ->get();

        foreach ($due as $item) {
            $instance = new self();
            $sent = $instance->sendViaTwilio($item->phone, $item->message);

            if ($sent) {
                \DB::table('whatsapp_queue')
                    ->where('id', $item->id)
                    ->update(['sent' => true, 'sent_at' => now()]);

                Log::info("WhatsApp sent: {$item->reminder_title} to {$item->phone}");
            }
        }
    }
}

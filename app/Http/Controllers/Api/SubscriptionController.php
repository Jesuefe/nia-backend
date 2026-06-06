<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private string $paystackSecret;
    private int $proMonthlyAmount = 299900; // ₦2,999/month in kobo
    private int $proYearlyAmount  = 2999900; // ₦29,999/year in kobo

    public function __construct()
    {
        $this->paystackSecret = config('services.paystack.secret_key');
    }

    // Initialize Paystack payment
    public function initialize(Request $request)
    {
        $request->validate(['interval' => 'required|in:monthly,annually']);

        $user   = $request->user();
        $amount = $request->interval === 'monthly' ? $this->proMonthlyAmount : $this->proYearlyAmount;

        $response = Http::withToken($this->paystackSecret)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email'     => $user->email,
                'amount'    => $amount,
                'currency'  => 'NGN',
                'reference' => 'nia_' . $user->id . '_' . time(),
                'callback_url' => config('app.frontend_url') . '/subscription/verify',
                'metadata'  => [
                    'user_id'  => $user->id,
                    'interval' => $request->interval,
                    'plan'     => 'pro',
                ],
                'channels' => ['card', 'bank', 'ussd', 'mobile_money'],
            ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Failed to initialize payment'], 500);
        }

        return response()->json($response->json()['data']);
    }

    // Verify payment after redirect
    public function verify(Request $request)
    {
        $request->validate(['reference' => 'required|string']);

        $response = Http::withToken($this->paystackSecret)
            ->get("https://api.paystack.co/transaction/verify/{$request->reference}");

        if (!$response->successful()) {
            return response()->json(['message' => 'Verification failed'], 400);
        }

        $data = $response->json()['data'];

        if ($data['status'] !== 'success') {
            return response()->json(['message' => 'Payment was not successful'], 400);
        }

        $user     = $request->user();
        $interval = $data['metadata']['interval'] ?? 'monthly';
        $months   = $interval === 'annually' ? 12 : 1;
        $expires  = now()->addMonths($months);

        // Update user plan
        $user->update([
            'plan'                       => 'pro',
            'pro_expires_at'             => $expires,
            'paystack_customer_id'       => $data['customer']['id'] ?? null,
            'paystack_subscription_code' => $data['subscription_code'] ?? null,
        ]);

        // Record subscription
        Subscription::create([
            'user_id'                    => $user->id,
            'paystack_reference'         => $data['reference'],
            'paystack_subscription_code' => $data['subscription_code'] ?? null,
            'plan'                       => 'pro',
            'status'                     => 'active',
            'amount'                     => $data['amount'] / 100,
            'currency'                   => 'NGN',
            'interval'                   => $interval,
            'starts_at'                  => now(),
            'expires_at'                 => $expires,
        ]);

        return response()->json([
            'message'    => 'Subscription activated successfully',
            'plan'       => 'pro',
            'expires_at' => $expires,
        ]);
    }

    // Paystack webhook
    public function webhook(Request $request)
    {
        $paystackSignature = $request->header('x-paystack-signature');
        $payload           = $request->getContent();
        $computedSignature = hash_hmac('sha512', $payload, $this->paystackSecret);

        if ($paystackSignature !== $computedSignature) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->json('event');
        $data  = $request->json('data');

        match($event) {
            'subscription.disable' => $this->handleSubscriptionDisable($data),
            'charge.success'       => $this->handleChargeSuccess($data),
            'invoice.payment_failed' => $this->handlePaymentFailed($data),
            default                => null,
        };

        return response()->json(['message' => 'Webhook received']);
    }

    private function handleSubscriptionDisable(array $data): void
    {
        $sub = Subscription::where('paystack_subscription_code', $data['subscription_code'] ?? '')->first();
        if ($sub) {
            $sub->update(['status' => 'cancelled']);
            $sub->user->update(['plan' => 'free', 'pro_expires_at' => null]);
        }
    }

    private function handleChargeSuccess(array $data): void
    {
        // Renewal payment - extend subscription
        $userId = $data['metadata']['user_id'] ?? null;
        if (!$userId) return;

        $user = \App\Models\User::find($userId);
        if (!$user) return;

        $interval = $data['metadata']['interval'] ?? 'monthly';
        $months   = $interval === 'annually' ? 12 : 1;
        $expires  = now()->addMonths($months);

        $user->update(['plan' => 'pro', 'pro_expires_at' => $expires]);
    }

    private function handlePaymentFailed(array $data): void
    {
        Log::warning('Paystack payment failed', $data);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'plan'               => $user->plan,
            'is_pro'             => $user->isPro(),
            'pro_expires_at'     => $user->pro_expires_at,
            'messages_remaining' => $user->messagesRemaining(),
            'subscriptions'      => $user->subscriptions()->latest()->take(3)->get(),
        ]);
    }

    public function cancel(Request $request)
    {
        $user = $request->user();
        $sub  = Subscription::where('user_id', $user->id)->where('status', 'active')->latest()->first();

        if ($sub && $sub->paystack_subscription_code) {
            Http::withToken($this->paystackSecret)
                ->post("https://api.paystack.co/subscription/disable", [
                    'code'  => $sub->paystack_subscription_code,
                    'token' => $sub->paystack_subscription_code,
                ]);
        }

        if ($sub) $sub->update(['status' => 'cancelled']);
        $user->update(['plan' => 'free', 'pro_expires_at' => null]);

        return response()->json(['message' => 'Subscription cancelled']);
    }

    public function plans()
    {
        return response()->json([
            'plans' => [
                'free' => [
                    'name'     => 'Free',
                    'price'    => 0,
                    'currency' => 'NGN',
                    'features' => [
                        '20 messages per day',
                        'Goals & habit tracking',
                        'Basic reminders',
                        'Memory storage',
                    ],
                    'limits' => ['daily_messages' => 20],
                ],
                'pro' => [
                    'name'          => 'Pro',
                    'monthly_price' => 2999,
                    'yearly_price'  => 29999,
                    'currency'      => 'NGN',
                    'features'      => [
                        'Unlimited messages',
                        'WhatsApp reminders',
                        'Health & self-care tracking',
                        'Morning briefings',
                        'Brainstorm & research engine',
                        'Voice input & output',
                        'Priority support',
                        'Data export',
                    ],
                    'limits' => ['daily_messages' => -1],
                ],
            ],
        ]);
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'age', 'marital_status',
        'occupation', 'whatsapp_number', 'plan', 'paystack_customer_id',
        'paystack_subscription_code', 'pro_expires_at', 'is_admin',
        'onboarded', 'daily_message_count', 'message_count_date',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'pro_expires_at'    => 'datetime',
        'is_admin'          => 'boolean',
        'onboarded'         => 'boolean',
        'password'          => 'hashed',
    ];

    public function memory() {
        return $this->hasOne(Memory::class);
    }

    public function conversations() {
        return $this->hasMany(Conversation::class)->latest()->limit(80);
    }

    public function subscriptions() {
        return $this->hasMany(Subscription::class);
    }

    public function isPro(): bool {
        if ($this->plan === 'pro' && $this->pro_expires_at && $this->pro_expires_at->isFuture()) {
            return true;
        }
        return false;
    }

    public function canSendMessage(): bool {
        // Pro users: unlimited
        if ($this->isPro()) return true;

        // Free users: 20 messages per day
        $today = now()->toDateString();
        if ($this->message_count_date !== $today) return true; // new day resets
        return $this->daily_message_count < 20;
    }

    public function incrementMessageCount(): void {
        $today = now()->toDateString();
        if ($this->message_count_date !== $today) {
            $this->update(['daily_message_count' => 1, 'message_count_date' => $today]);
        } else {
            $this->increment('daily_message_count');
        }
    }

    public function messagesRemaining(): int {
        if ($this->isPro()) return 999999;
        $today = now()->toDateString();
        if ($this->message_count_date !== $today) return 20;
        return max(0, 20 - $this->daily_message_count);
    }
}

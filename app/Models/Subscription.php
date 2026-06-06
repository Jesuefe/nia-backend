<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id','paystack_reference','paystack_subscription_code',
        'plan','status','amount','currency','interval','starts_at','expires_at'
    ];
    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}

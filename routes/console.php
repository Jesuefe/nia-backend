<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    // Process WhatsApp queue every minute
    $due = \DB::table('whatsapp_queue')
        ->where('sent', false)
        ->where('send_at', '<=', now())
        ->get();
    foreach ($due as $item) {
        \DB::table('whatsapp_queue')->where('id', $item->id)->update(['sent' => true, 'sent_at' => now()]);
    }
})->everyMinute();

Schedule::call(function () {
    // Reset daily message counts
    \App\Models\User::where('message_count_date', '<', now()->toDateString())
        ->update(['daily_message_count' => 0, 'message_count_date' => now()->toDateString()]);
})->dailyAt('00:00');

Schedule::call(function () {
    // Expire pro subscriptions
    \App\Models\User::where('plan', 'pro')
        ->where('pro_expires_at', '<', now())
        ->update(['plan' => 'free', 'pro_expires_at' => null]);
})->daily();

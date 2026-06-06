<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    protected $fillable = ['user_id','goals','habits','reminders','notes','habit_logs','health','whatsapp_logs'];
    protected $casts = [
        'goals'         => 'array',
        'habits'        => 'array',
        'reminders'     => 'array',
        'notes'         => 'array',
        'habit_logs'    => 'array',
        'health'        => 'array',
        'whatsapp_logs' => 'array',
    ];
    public function user() { return $this->belongsTo(User::class); }
}

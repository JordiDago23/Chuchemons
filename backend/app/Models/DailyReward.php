<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReward extends Model
{
    protected $fillable = ['user_id', 'reward_type', 'item_id', 'chuchemon_id', 'quantity', 'claimed_at', 'next_available_at'];
    protected $casts = [
        'claimed_at' => 'datetime',
        'next_available_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function chuchemon()
    {
        return $this->belongsTo(Chuchemon::class);
    }
}

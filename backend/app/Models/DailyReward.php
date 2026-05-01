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
    
    /**
     * Prepare dates for serialization.
     * Ensure dates are serialized to ISO 8601 with timezone (RFC 3339).
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format(\DateTime::RFC3339);
    }

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

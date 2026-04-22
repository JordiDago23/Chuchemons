<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BattleRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenger_id',
        'challenged_id',
        'status',
        'accepted_at',
        'battle_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function challenger()
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function challenged()
    {
        return $this->belongsTo(User::class, 'challenged_id');
    }

    public function battle()
    {
        return $this->belongsTo(Battle::class);
    }
}

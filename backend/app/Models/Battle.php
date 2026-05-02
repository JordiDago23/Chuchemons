<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Battle extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenger_id',
        'challenged_id',
        'status',
        'winner_id',
        'loser_id',
        'winner_chuchemon_id',
        'loser_chuchemon_id',
        'resolved_at',
        'result_payload',
        'challenger_current_hp',
        'challenged_current_hp',
        'current_turn_id',
        'last_roll',
        'combat_log',
    ];

    protected $casts = [
        'resolved_at'  => 'datetime',
        'result_payload' => 'array',
        'last_roll'    => 'array',
        'combat_log'   => 'array',
    ];

    public function challenger()
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function challenged()
    {
        return $this->belongsTo(User::class, 'challenged_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function loser()
    {
        return $this->belongsTo(User::class, 'loser_id');
    }

    public function selections()
    {
        return $this->hasMany(BattleSelection::class);
    }
}

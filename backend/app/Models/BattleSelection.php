<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BattleSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'battle_id',
        'user_id',
        'chuchemon_id',
    ];

    public function battle()
    {
        return $this->belongsTo(Battle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chuchemon()
    {
        return $this->belongsTo(Chuchemon::class);
    }
}

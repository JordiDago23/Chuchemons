<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'apellidos',
        'email',
        'password',
        'player_id',
        'is_admin',
        'bio',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_admin'   => 'boolean',
        'last_seen_at' => 'datetime',
        'level'      => 'integer',
        'experience' => 'integer',
    ];

    protected $appends = ['experience_for_next_level'];

    // XP necesario para pasar del nivel N al N+1 (índice = nivel actual)
    public const XP_THRESHOLDS = [100, 200, 350, 550, 800, 1100, 1500, 2000, 2600, 3300];

    public function getExperienceForNextLevelAttribute(): int
    {
        return self::XP_THRESHOLDS[$this->level] ?? 0; // 0 = nivel máximo alcanzado
    }

    /**
     * Añade XP al usuario y sube de nivel si procede.
     */
    public function addExperience(int $amount): void
    {
        if ($this->level >= 10) return;

        $this->experience += $amount;

        while ($this->level < 10) {
            $needed = self::XP_THRESHOLDS[$this->level] ?? PHP_INT_MAX;
            if ($this->experience < $needed) break;
            $this->experience -= $needed;
            $this->level++;
        }

        $this->save();
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Relaciones
    public function capturedChuchemons()
    {
        return $this->belongsToMany(Chuchemon::class, 'user_chuchemons')
                    ->withPivot('count', 'level', 'experience', 'experience_for_next_level')
                    ->withTimestamps();
    }

    // Relación con datos de evolución (si la migración fue ejecutada)
    public function capturedChuchemsWithEvolution()
    {
        return $this->belongsToMany(Chuchemon::class, 'user_chuchemons')
                    ->withPivot('count', 'current_mida', 'evolution_count', 'level', 'experience', 'experience_for_next_level')
                    ->withTimestamps();
    }

    public function team()
    {
        return $this->hasOne(UserTeam::class);
    }

    public function items()
    {
        return $this->hasMany(MochilaXux::class);
    }

    public function infections()
    {
        return $this->hasMany(UserInfection::class);
    }

    public function dailyRewards()
    {
        return $this->hasMany(DailyReward::class);
    }

    public function sentFriendships()
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }

    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'receiver_id');
    }

    public function sentBattleRequests()
    {
        return $this->hasMany(BattleRequest::class, 'challenger_id');
    }

    public function receivedBattleRequests()
    {
        return $this->hasMany(BattleRequest::class, 'challenged_id');
    }

    public function challengedBattles()
    {
        return $this->hasMany(Battle::class, 'challenger_id');
    }

    public function opponentBattles()
    {
        return $this->hasMany(Battle::class, 'challenged_id');
    }
}
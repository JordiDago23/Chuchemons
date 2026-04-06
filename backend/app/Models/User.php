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
        'is_admin' => 'boolean',  //Rol de administrador
        'last_seen_at' => 'datetime',
    ];

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
}
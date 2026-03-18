<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'nombre',
        'apellidos',
        'email',
        'password',
        'player_id',
        'is_admin',
        'bio',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_admin' => 'boolean',  //Rol de administrador
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
                    ->withPivot('count')
                    ->withTimestamps();
    }

    // Relación con datos de evolución (si la migración fue ejecutada)
    public function capturedChuchemsWithEvolution()
    {
        return $this->belongsToMany(Chuchemon::class, 'user_chuchemons')
                    ->withPivot('count', 'current_mida', 'evolution_count')
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
}
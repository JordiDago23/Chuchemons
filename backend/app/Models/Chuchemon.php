<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chuchemon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'element',
        'mida',
        'image',
        'attack',
        'defense',
        'speed',
    ];

    protected $casts = [
        'attack' => 'integer',
        'defense' => 'integer',
        'speed' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function capturedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_chuchemons')
                    ->withPivot('count')
                    ->withTimestamps();
    }
}


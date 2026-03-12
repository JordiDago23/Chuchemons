<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chuchemon_1_id',
        'chuchemon_2_id',
        'chuchemon_3_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chuchemon1()
    {
        return $this->belongsTo(Chuchemon::class, 'chuchemon_1_id');
    }

    public function chuchemon2()
    {
        return $this->belongsTo(Chuchemon::class, 'chuchemon_2_id');
    }

    public function chuchemon3()
    {
        return $this->belongsTo(Chuchemon::class, 'chuchemon_3_id');
    }
}

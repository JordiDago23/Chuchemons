<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInfection extends Model
{
    protected $table = 'user_infections';
    protected $fillable = ['user_id', 'chuchemon_id', 'malaltia_id', 'infection_percentage', 'is_active', 'infected_at', 'cured_at'];
    protected $casts = [
        'infected_at' => 'datetime',
        'cured_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chuchemon()
    {
        return $this->belongsTo(Chuchemon::class, 'chuchemon_id');
    }

    public function malaltia()
    {
        return $this->belongsTo(Malaltia::class, 'malaltia_id');
    }
}

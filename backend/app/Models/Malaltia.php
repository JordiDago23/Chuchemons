<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Malaltia extends Model
{
    protected $table = 'malalties';
    protected $fillable = ['name', 'description', 'type'];

    public function vaccines()
    {
        return $this->hasMany(Vaccine::class, 'malaltia_id');
    }

    public function userInfections()
    {
        return $this->hasMany(UserInfection::class, 'malaltia_id');
    }
}

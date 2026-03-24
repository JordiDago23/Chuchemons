<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vaccine extends Model
{
    protected $fillable = ['name', 'description', 'malaltia_id'];

    public function malaltia()
    {
        return $this->belongsTo(Malaltia::class, 'malaltia_id');
    }
}

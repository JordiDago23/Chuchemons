<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MochilaXux extends Model
{
    protected $fillable = [
        'user_id',
        'item_id',
        'chuchemon_id',
        'quantity',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chuchemon()
    {
        return $this->belongsTo(Chuchemon::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    public const NAME_XUX_MADUIXA = 'Xux de Maduixa';
    public const NAME_XUX_LLIMONA = 'Xux de Llimona';
    public const NAME_XUX_COLA = 'Xux de Cola';
    public const NAME_XUX_EXP = 'Xux Exp';

    protected $fillable = [
        'name',
        'description',
        'type', // 'apilable' o 'no_apilable'
        'image',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function mochilaXuxes()
    {
        return $this->hasMany(MochilaXux::class);
    }

    public static function idByName(string $name): ?int
    {
        return static::query()->where('name', $name)->value('id');
    }
}

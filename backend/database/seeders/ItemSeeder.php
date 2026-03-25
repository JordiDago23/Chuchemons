<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // Items apilables (Xuxes — 3 tipus de llaminadures apilables)
        Item::create([
            'name' => 'Xux de Maduixa',
            'description' => 'Llaminadura de maduixa. Recupera 20 punts de salut',
            'type' => 'apilable',
            'image' => 'xux-maduixa.png',
        ]);

        Item::create([
            'name' => 'Xux de Llimona',
            'description' => 'Llaminadura de llimona. Augmenta l\'atac temporalment',
            'type' => 'apilable',
            'image' => 'xux-llimona.png',
        ]);

        Item::create([
            'name' => 'Xux de Cola',
            'description' => 'Llaminadura de cola. Augmenta la defensa temporalment',
            'type' => 'apilable',
            'image' => 'xux-cola.png',
        ]);

        // Items no apilables (Vacunes)
        Item::create([
            'name' => 'Antídot',
            'description' => 'Cura l\'enverinament',
            'type' => 'no_apilable',
            'image' => 'antidot.png',
        ]);

        Item::create([
            'name' => 'Reviviscència',
            'description' => 'Reviu un Xuxemon desfet',
            'type' => 'no_apilable',
            'image' => 'reviviscencia.png',
        ]);
    }
}

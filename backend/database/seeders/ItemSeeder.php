<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // Items apilables (Xuxes)
        Item::create([
            'name' => 'Poció de Salut',
            'description' => 'Recupera 20 punts de salut',
            'type' => 'apilable',
            'image' => 'pocion-salud.png',
        ]);

        Item::create([
            'name' => 'Poció de Força',
            'description' => 'Augmenta l\'atac temporalment',
            'type' => 'apilable',
            'image' => 'pocion-fuerza.png',
        ]);

        Item::create([
            'name' => 'Poció de Defensa',
            'description' => 'Augmenta la defensa temporalment',
            'type' => 'apilable',
            'image' => 'pocion-defensa.png',
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

<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => Item::NAME_XUX_MADUIXA,
                'description' => 'Llaminadura de maduixa. Recupera 20 punts de salut',
                'type' => 'apilable',
                'image' => 'xux-maduixa.png',
            ],
            [
                'name' => Item::NAME_XUX_LLIMONA,
                'description' => 'Llaminadura de llimona. Augmenta l\'atac temporalment',
                'type' => 'apilable',
                'image' => 'xux-llimona.png',
            ],
            [
                'name' => Item::NAME_XUX_COLA,
                'description' => 'Llaminadura de cola. Augmenta la defensa temporalment',
                'type' => 'apilable',
                'image' => 'xux-cola.png',
            ],
            [
                'name' => Item::NAME_XUX_EXP,
                'description' => 'Xux especial que serveix per pujar de nivell els Xuxemons.',
                'type' => 'apilable',
                'image' => 'xux-exp.png',
            ],
        ];

        foreach ($items as $data) {
            Item::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}

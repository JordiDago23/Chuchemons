<?php

namespace Database\Seeders;

use App\Models\Chuchemon;
use Illuminate\Database\Seeder;

class ChuchemonSeeder extends Seeder
{
    public function run(): void
    {
        $chuchemons = [
            ['name' => 'Apleki',       'element' => 'Terra', 'mida' => 'Petit',  'image' => 'apleki.png'],
            ['name' => 'Avecrem',      'element' => 'Aire',  'mida' => 'Petit',  'image' => 'avecrem.png'],
            ['name' => 'Bambino',      'element' => 'Terra', 'mida' => 'Petit',  'image' => 'bambino.png'],
            ['name' => 'Beeboo',       'element' => 'Aire',  'mida' => 'Petit',  'image' => 'beeboo.png'],
            ['name' => 'Boo-hoot',     'element' => 'Aire',  'mida' => 'Petit',  'image' => 'boo-hoot.png'],
            ['name' => 'Cabrales',     'element' => 'Terra', 'mida' => 'Petit',  'image' => 'cabrales.png'],
            ['name' => 'Catua',        'element' => 'Aire',  'mida' => 'Petit',  'image' => 'catua.png'],
            ['name' => 'Catyuska',     'element' => 'Aire',  'mida' => 'Petit',  'image' => 'catyuska.png'],
            ['name' => 'Chapapá',      'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'chapapa.png'],
            ['name' => 'Horseluis',    'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'horseluis.png'],
            ['name' => 'Krokolisko',   'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'krokolisko.png'],
            ['name' => 'Kurama',       'element' => 'Terra', 'mida' => 'Petit',  'image' => 'kurama.png'],
            ['name' => 'Ladybug',      'element' => 'Aire',  'mida' => 'Petit',  'image' => 'ladybug.png'],
            ['name' => 'Lengualargui', 'element' => 'Terra', 'mida' => 'Petit',  'image' => 'lengualargui.png'],
            ['name' => 'Medusation',   'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'medusation.png'],
            ['name' => 'Meekmeek',     'element' => 'Terra', 'mida' => 'Petit',  'image' => 'meekmeek.png'],
            ['name' => 'Megalo',       'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'megalo.png'],
            ['name' => 'Mocha',        'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'mocha.png'],
            ['name' => 'Murcimurci',   'element' => 'Aire',  'mida' => 'Petit',  'image' => 'murcimurci.png'],
            ['name' => 'Nemo',         'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'nemo.png'],
            ['name' => 'Oinkcelot',    'element' => 'Terra', 'mida' => 'Petit',  'image' => 'oinkcelot.png'],
            ['name' => 'Oreo',         'element' => 'Terra', 'mida' => 'Petit',  'image' => 'oreo.png'],
            ['name' => 'Otto',         'element' => 'Terra', 'mida' => 'Petit',  'image' => 'otto.png'],
            ['name' => 'Pinchimott',   'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'pinchimott.png'],
            ['name' => 'Pollis',       'element' => 'Aire',  'mida' => 'Petit',  'image' => 'pollis.png'],
            ['name' => 'Posón',        'element' => 'Aire',  'mida' => 'Petit',  'image' => 'poson.png'],
            ['name' => 'Quakko',       'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'quakko.png'],
            ['name' => 'Rajoy',        'element' => 'Aire',  'mida' => 'Petit',  'image' => 'rajoy.png'],
            ['name' => 'Rawlion',      'element' => 'Terra', 'mida' => 'Petit',  'image' => 'rawlion.png'],
            ['name' => 'Rexxo',        'element' => 'Terra', 'mida' => 'Petit',  'image' => 'rexxo.png'],
            ['name' => 'Ron',          'element' => 'Terra', 'mida' => 'Petit',  'image' => 'ron.png'],
            ['name' => 'Sesssi',       'element' => 'Terra', 'mida' => 'Petit',  'image' => 'sesssi.png'],
            ['name' => 'Shelly',       'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'shelly.png'],
            ['name' => 'Sirucco',      'element' => 'Aire',  'mida' => 'Petit',  'image' => 'sirucco.png'],
            ['name' => 'Peereira',     'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'peereira.png'],
            ['name' => 'Trompeta',     'element' => 'Aire',  'mida' => 'Petit',  'image' => 'trompeta.png'],
            ['name' => 'Trompi',       'element' => 'Terra', 'mida' => 'Petit',  'image' => 'trompi.png'],
            ['name' => 'Tux',          'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'tux.png'],
            ['name' => 'Chopper',      'element' => 'Aire',  'mida' => 'Petit',  'image' => 'chopper.png'],
            ['name' => 'Cuellilargui', 'element' => 'Terra', 'mida' => 'Petit',  'image' => 'cuellilargui.png'],
            ['name' => 'Deskangoo',    'element' => 'Terra', 'mida' => 'Petit',  'image' => 'deskangoo.png'],
            ['name' => 'Doflamingo',   'element' => 'Aire',  'mida' => 'Petit',  'image' => 'doflamingo.png'],
            ['name' => 'Dolly',        'element' => 'Terra', 'mida' => 'Petit',  'image' => 'dolly.png'],
            ['name' => 'Elconchudo',   'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'elconchudo.png'],
            ['name' => 'Eldientes',    'element' => 'Terra', 'mida' => 'Petit',  'image' => 'eldientes.png'],
            ['name' => 'Elgominas',    'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'elgominas.png'],
            ['name' => 'Flipper',      'element' => 'Aigua', 'mida' => 'Petit',  'image' => 'flipper.png'],
            ['name' => 'Floppi',       'element' => 'Aire',  'mida' => 'Petit',  'image' => 'floppi.png'],
        ];

        foreach ($chuchemons as $data) {
            // si no hay stats predefinidos, generamos valores razonables
            $data['attack']  = $data['attack']  ?? rand(45, 95);
            $data['defense'] = $data['defense'] ?? rand(35, 90);
            $data['speed']   = $data['speed']   ?? rand(30, 100);

            Chuchemon::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}

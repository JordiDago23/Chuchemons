<?php

namespace Database\Seeders;

use App\Models\Chuchemon;
use Illuminate\Database\Seeder;

class ChuchemonSeeder extends Seeder
{
    public function run(): void
    {
        $chuchemons = [
            ['name' => 'Apleki',       'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'apleki.png'],
            ['name' => 'Avecrem',      'element' => 'Aire',   'mida' => 'Mitjà', 'image' => 'avecrem.png'],
            ['name' => 'Bambino',      'element' => 'Tierra', 'mida' => 'Gran',  'image' => 'bambino.png'],
            ['name' => 'Beeboo',       'element' => 'Aire',   'mida' => 'Petit', 'image' => 'beeboo.png'],
            ['name' => 'Boo-hoot',     'element' => 'Aire',   'mida' => 'Mitjà', 'image' => 'boo-hoot.png'],
            ['name' => 'Cabrales',     'element' => 'Tierra', 'mida' => 'Gran',  'image' => 'cabrales.png'],
            ['name' => 'Catua',        'element' => 'Aire',   'mida' => 'Petit', 'image' => 'catua.png'],
            ['name' => 'Catyuska',     'element' => 'Aire',   'mida' => 'Mitjà', 'image' => 'catyuska.png'],
            ['name' => 'Chapapá',      'element' => 'Agua',   'mida' => 'Gran',  'image' => 'chapapa.png'],
            ['name' => 'Horseluis',    'element' => 'Agua',   'mida' => 'Petit', 'image' => 'horseluis.png'],
            ['name' => 'Krokolisko',   'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'krokolisko.png'],
            ['name' => 'Kurama',       'element' => 'Tierra', 'mida' => 'Gran',  'image' => 'kurama.png'],
            ['name' => 'Ladybug',      'element' => 'Aire',   'mida' => 'Petit', 'image' => 'ladybug.png'],
            ['name' => 'Lengualargui', 'element' => 'Tierra', 'mida' => 'Mitjà', 'image' => 'lengualargui.png'],
            ['name' => 'Medusation',   'element' => 'Agua',   'mida' => 'Gran',  'image' => 'medusation.png'],
            ['name' => 'Meekmeek',     'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'meekmeek.png'],
            ['name' => 'Megalo',       'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'megalo.png'],
            ['name' => 'Mocha',        'element' => 'Agua',   'mida' => 'Gran',  'image' => 'mocha.png'],
            ['name' => 'Murcimurci',   'element' => 'Aire',   'mida' => 'Petit', 'image' => 'murcimurci.png'],
            ['name' => 'Nemo',         'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'nemo.png'],
            ['name' => 'Oinkcelot',    'element' => 'Tierra', 'mida' => 'Gran',  'image' => 'oinkcelot.png'],
            ['name' => 'Oreo',         'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'oreo.png'],
            ['name' => 'Otto',         'element' => 'Tierra', 'mida' => 'Mitjà', 'image' => 'otto.png'],
            ['name' => 'Pinchimott',   'element' => 'Agua',   'mida' => 'Gran',  'image' => 'pinchimott.png'],
            ['name' => 'Pollis',       'element' => 'Aire',   'mida' => 'Petit', 'image' => 'pollis.png'],
            ['name' => 'Posón',        'element' => 'Aire',   'mida' => 'Mitjà', 'image' => 'poson.png'],
            ['name' => 'Quakko',       'element' => 'Agua',   'mida' => 'Gran',  'image' => 'quakko.png'],
            ['name' => 'Rajoy',        'element' => 'Aire',   'mida' => 'Petit', 'image' => 'rajoy.png'],
            ['name' => 'Rawlion',      'element' => 'Tierra', 'mida' => 'Mitjà', 'image' => 'rawlion.png'],
            ['name' => 'Rexxo',        'element' => 'Tierra', 'mida' => 'Gran',  'image' => 'rexxo.png'],
            ['name' => 'Ron',          'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'ron.png'],
            ['name' => 'Sesssi',       'element' => 'Tierra', 'mida' => 'Mitjà', 'image' => 'sesssi.png'],
            ['name' => 'Shelly',       'element' => 'Agua',   'mida' => 'Gran',  'image' => 'shelly.png'],
            ['name' => 'Sirucco',      'element' => 'Aire',   'mida' => 'Petit', 'image' => 'sirucco.png'],
            ['name' => 'Peereira',     'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'peereira.png'],
            ['name' => 'Trompeta',     'element' => 'Aire',   'mida' => 'Gran',  'image' => 'trompeta.png'],
            ['name' => 'Trompi',       'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'trompi.png'],
            ['name' => 'Tux',          'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'tux.png'],
            ['name' => 'Chopper',      'element' => 'Aire',   'mida' => 'Gran',  'image' => 'chopper.png'],
            ['name' => 'Cuellilargui', 'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'cuellilargui.png'],
            ['name' => 'Deskangoo',    'element' => 'Tierra', 'mida' => 'Mitjà', 'image' => 'deskangoo.png'],
            ['name' => 'Doflamingo',   'element' => 'Aire',   'mida' => 'Gran',  'image' => 'doflamingo.png'],
            ['name' => 'Dolly',        'element' => 'Tierra', 'mida' => 'Petit', 'image' => 'dolly.png'],
            ['name' => 'Elconchudo',   'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'elconchudo.png'],
            ['name' => 'Eldientes',    'element' => 'Tierra', 'mida' => 'Gran',  'image' => 'eldientes.png'],
            ['name' => 'Elgominas',    'element' => 'Agua',   'mida' => 'Petit', 'image' => 'elgominas.png'],
            ['name' => 'Flipper',      'element' => 'Agua',   'mida' => 'Mitjà', 'image' => 'flipper.png'],
            ['name' => 'Floppi',       'element' => 'Aire',   'mida' => 'Gran',  'image' => 'floppi.png'],
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

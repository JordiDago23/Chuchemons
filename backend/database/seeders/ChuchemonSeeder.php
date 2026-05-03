<?php

namespace Database\Seeders;

use App\Models\Chuchemon;
use Illuminate\Database\Seeder;

class ChuchemonSeeder extends Seeder
{
    public function run(): void
    {
        // Rule: attack must always be > defense (guarantees min damage in battle even with worst roll + type penalty).
        // Battle formula: damage = max(0, attack + roll(1-6) + sizeMod(0/1/2) + typeMod(-1/0/+1) - defense)
        // Worst case Petit: damage = attack + 1 - 1 - defense = attack - defense → must be > 0.
        $chuchemons = [
            // ── TERRA ─────────────────────────────────────────────────────────────
            ['name' => 'Apleki',       'element' => 'Terra', 'mida' => 'Petit', 'attack' => 65, 'defense' => 39, 'speed' => 72,  'image' => 'apleki.png'],
            ['name' => 'Meekmeek',     'element' => 'Terra', 'mida' => 'Petit', 'attack' => 60, 'defense' => 56, 'speed' => 77,  'image' => 'meekmeek.png'],
            ['name' => 'Oreo',         'element' => 'Terra', 'mida' => 'Petit', 'attack' => 71, 'defense' => 62, 'speed' => 52,  'image' => 'oreo.png'],
            ['name' => 'Ron',          'element' => 'Terra', 'mida' => 'Petit', 'attack' => 67, 'defense' => 61, 'speed' => 54,  'image' => 'ron.png'],
            ['name' => 'Trompi',       'element' => 'Terra', 'mida' => 'Petit', 'attack' => 77, 'defense' => 69, 'speed' => 82,  'image' => 'trompi.png'],
            ['name' => 'Cuellilargui', 'element' => 'Terra', 'mida' => 'Petit', 'attack' => 84, 'defense' => 46, 'speed' => 86,  'image' => 'cuellilargui.png'],
            ['name' => 'Dolly',        'element' => 'Terra', 'mida' => 'Petit', 'attack' => 87, 'defense' => 36, 'speed' => 81,  'image' => 'dolly.png'],
            ['name' => 'Lengualargui', 'element' => 'Terra', 'mida' => 'Petit',  'attack' => 87, 'defense' => 60, 'speed' => 60,  'image' => 'lengualargui.png'],
            ['name' => 'Otto',         'element' => 'Terra', 'mida' => 'Petit',  'attack' => 66, 'defense' => 60, 'speed' => 92,  'image' => 'otto.png'],
            ['name' => 'Rawlion',      'element' => 'Terra', 'mida' => 'Petit',  'attack' => 90, 'defense' => 61, 'speed' => 96,  'image' => 'rawlion.png'],
            ['name' => 'Sesssi',       'element' => 'Terra', 'mida' => 'Petit',  'attack' => 57, 'defense' => 37, 'speed' => 30,  'image' => 'sesssi.png'],
            ['name' => 'Deskangoo',    'element' => 'Terra', 'mida' => 'Petit',  'attack' => 70, 'defense' => 56, 'speed' => 64,  'image' => 'deskangoo.png'],
            ['name' => 'Bambino',      'element' => 'Terra', 'mida' => 'Petit',  'attack' => 87, 'defense' => 78, 'speed' => 58,  'image' => 'bambino.png'],
            ['name' => 'Cabrales',     'element' => 'Terra', 'mida' => 'Petit',  'attack' => 80, 'defense' => 68, 'speed' => 71,  'image' => 'cabrales.png'],
            ['name' => 'Kurama',       'element' => 'Terra', 'mida' => 'Petit',  'attack' => 62, 'defense' => 52, 'speed' => 81,  'image' => 'kurama.png'],
            ['name' => 'Oinkcelot',    'element' => 'Terra', 'mida' => 'Petit',  'attack' => 62, 'defense' => 54, 'speed' => 83,  'image' => 'oinkcelot.png'],
            ['name' => 'Rexxo',        'element' => 'Terra', 'mida' => 'Petit',  'attack' => 92, 'defense' => 73, 'speed' => 81,  'image' => 'rexxo.png'],
            ['name' => 'Eldientes',    'element' => 'Terra', 'mida' => 'Petit',  'attack' => 58, 'defense' => 52, 'speed' => 45,  'image' => 'eldientes.png'],

            // ── AIRE ──────────────────────────────────────────────────────────────
            ['name' => 'Beeboo',       'element' => 'Aire',  'mida' => 'Petit', 'attack' => 93, 'defense' => 62, 'speed' => 35,  'image' => 'beeboo.png'],
            ['name' => 'Catua',        'element' => 'Aire',  'mida' => 'Petit', 'attack' => 82, 'defense' => 79, 'speed' => 54,  'image' => 'catua.png'],
            ['name' => 'Ladybug',      'element' => 'Aire',  'mida' => 'Petit', 'attack' => 86, 'defense' => 68, 'speed' => 41,  'image' => 'ladybug.png'],
            ['name' => 'Murcimurci',   'element' => 'Aire',  'mida' => 'Petit', 'attack' => 70, 'defense' => 63, 'speed' => 71,  'image' => 'murcimurci.png'],
            ['name' => 'Pollis',       'element' => 'Aire',  'mida' => 'Petit', 'attack' => 79, 'defense' => 71, 'speed' => 75,  'image' => 'pollis.png'],
            ['name' => 'Rajoy',        'element' => 'Aire',  'mida' => 'Petit', 'attack' => 79, 'defense' => 72, 'speed' => 48,  'image' => 'rajoy.png'],
            ['name' => 'Sirucco',      'element' => 'Aire',  'mida' => 'Petit', 'attack' => 81, 'defense' => 65, 'speed' => 38,  'image' => 'sirucco.png'],
            ['name' => 'Avecrem',      'element' => 'Aire',  'mida' => 'Petit',  'attack' => 92, 'defense' => 72, 'speed' => 91,  'image' => 'avecrem.png'],
            ['name' => 'Boo-hoot',     'element' => 'Aire',  'mida' => 'Petit',  'attack' => 67, 'defense' => 65, 'speed' => 92,  'image' => 'boo-hoot.png'],
            ['name' => 'Catyuska',     'element' => 'Aire',  'mida' => 'Petit',  'attack' => 92, 'defense' => 78, 'speed' => 32,  'image' => 'catyuska.png'],
            ['name' => 'Posón',        'element' => 'Aire',  'mida' => 'Petit',  'attack' => 63, 'defense' => 53, 'speed' => 79,  'image' => 'poson.png'],
            ['name' => 'Trompeta',     'element' => 'Aire',  'mida' => 'Petit',  'attack' => 78, 'defense' => 67, 'speed' => 68,  'image' => 'trompeta.png'],
            ['name' => 'Chopper',      'element' => 'Aire',  'mida' => 'Petit',  'attack' => 68, 'defense' => 58, 'speed' => 45,  'image' => 'chopper.png'],
            ['name' => 'Doflamingo',   'element' => 'Aire',  'mida' => 'Petit',  'attack' => 76, 'defense' => 64, 'speed' => 84,  'image' => 'doflamingo.png'],
            ['name' => 'Floppi',       'element' => 'Aire',  'mida' => 'Petit',  'attack' => 75, 'defense' => 38, 'speed' => 33,  'image' => 'floppi.png'],

            // ── AIGUA ─────────────────────────────────────────────────────────────
            ['name' => 'Horseluis',    'element' => 'Aigua', 'mida' => 'Petit', 'attack' => 65, 'defense' => 58, 'speed' => 100, 'image' => 'horseluis.png'],
            ['name' => 'Elgominas',    'element' => 'Aigua', 'mida' => 'Petit', 'attack' => 72, 'defense' => 63, 'speed' => 50,  'image' => 'elgominas.png'],
            ['name' => 'Krokolisko',   'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 74, 'defense' => 67, 'speed' => 62,  'image' => 'krokolisko.png'],
            ['name' => 'Megalo',       'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 84, 'defense' => 64, 'speed' => 95,  'image' => 'megalo.png'],
            ['name' => 'Nemo',         'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 85, 'defense' => 78, 'speed' => 66,  'image' => 'nemo.png'],
            ['name' => 'Peereira',     'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 86, 'defense' => 83, 'speed' => 31,  'image' => 'peereira.png'],
            ['name' => 'Tux',          'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 84, 'defense' => 76, 'speed' => 94,  'image' => 'tux.png'],
            ['name' => 'Elconchudo',   'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 70, 'defense' => 62, 'speed' => 44,  'image' => 'elconchudo.png'],
            ['name' => 'Flipper',      'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 68, 'defense' => 55, 'speed' => 94,  'image' => 'flipper.png'],
            ['name' => 'Chapapà',      'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 70, 'defense' => 58, 'speed' => 97,  'image' => 'chapapa.png'],
            ['name' => 'Medusation',   'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 61, 'defense' => 51, 'speed' => 35,  'image' => 'medusation.png'],
            ['name' => 'Mocha',        'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 52, 'defense' => 40, 'speed' => 62,  'image' => 'mocha.png'],
            ['name' => 'Pinchimott',   'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 87, 'defense' => 55, 'speed' => 73,  'image' => 'pinchimott.png'],
            ['name' => 'Quakko',       'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 87, 'defense' => 82, 'speed' => 35,  'image' => 'quakko.png'],
            ['name' => 'Shelly',       'element' => 'Aigua', 'mida' => 'Petit',  'attack' => 77, 'defense' => 69, 'speed' => 52,  'image' => 'shelly.png'],
        ];

        foreach ($chuchemons as $data) {
            Chuchemon::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}

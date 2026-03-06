<?php

namespace Database\Seeders;

use App\Models\Chuchemon;
use Illuminate\Database\Seeder;

class ChuchemonSeeder extends Seeder
{
    public function run(): void
    {
        $chuchemons = [
            ['name' => 'Apleki', 'element' => 'Tierra', 'image' => 'apleki.png'],
            ['name' => 'Avecrem', 'element' => 'Aire', 'image' => 'avecrem.png'],
            ['name' => 'Bambino', 'element' => 'Tierra', 'image' => 'bambino.png'],
            ['name' => 'Beeboo', 'element' => 'Aire', 'image' => 'beeboo.png'],
            ['name' => 'Boo-hoot', 'element' => 'Aire', 'image' => 'boo-hoot.png'],
            ['name' => 'Cabrales', 'element' => 'Tierra', 'image' => 'cabrales.png'],
            ['name' => 'Catua', 'element' => 'Aire', 'image' => 'catua.png'],
            ['name' => 'Catyuska', 'element' => 'Aire', 'image' => 'catyuska.png'],
            ['name' => 'Chapapá', 'element' => 'Agua', 'image' => 'chapapa.png'],
            ['name' => 'Horseluis', 'element' => 'Agua', 'image' => 'horseluis.png'],
            ['name' => 'Krokolisko', 'element' => 'Agua', 'image' => 'krokolisko.png'],
            ['name' => 'Kurama', 'element' => 'Tierra', 'image' => 'kurama.png'],
            ['name' => 'Ladybug', 'element' => 'Aire', 'image' => 'ladybug.png'],
            ['name' => 'Lengualargui', 'element' => 'Tierra', 'image' => 'lengualargui.png'],
            ['name' => 'Medusation', 'element' => 'Agua', 'image' => 'medusation.png'],
            ['name' => 'Meekmeek', 'element' => 'Tierra', 'image' => 'meekmeek.png'],
            ['name' => 'Megalo', 'element' => 'Agua', 'image' => 'megalo.png'],
            ['name' => 'Mocha', 'element' => 'Agua', 'image' => 'mocha.png'],
            ['name' => 'Murcimurci', 'element' => 'Aire', 'image' => 'murcimurci.png'],
            ['name' => 'Nemo', 'element' => 'Agua', 'image' => 'nemo.png'],
            ['name' => 'Oinkcelot', 'element' => 'Tierra', 'image' => 'oinkcelot.png'],
            ['name' => 'Oreo', 'element' => 'Tierra', 'image' => 'oreo.png'],
            ['name' => 'Otto', 'element' => 'Tierra', 'image' => 'otto.png'],
            ['name' => 'Pinchimott', 'element' => 'Agua', 'image' => 'pinchimott.png'],
            ['name' => 'Pollis', 'element' => 'Aire', 'image' => 'pollis.png'],
            ['name' => 'Posón', 'element' => 'Aire', 'image' => 'poson.png'],
            ['name' => 'Quakko', 'element' => 'Agua', 'image' => 'quakko.png'],
            ['name' => 'Rajoy', 'element' => 'Aire', 'image' => 'rajoy.png'],
            ['name' => 'Rawlion', 'element' => 'Tierra', 'image' => 'rawlion.png'],
            ['name' => 'Rexxo', 'element' => 'Tierra', 'image' => 'rexxo.png'],
            ['name' => 'Ron', 'element' => 'Tierra', 'image' => 'ron.png'],
            ['name' => 'Sesssi', 'element' => 'Tierra', 'image' => 'sesssi.png'],
            ['name' => 'Shelly', 'element' => 'Agua', 'image' => 'shelly.png'],
            ['name' => 'Sirucco', 'element' => 'Aire', 'image' => 'sirucco.png'],
            ['name' => 'Peereira', 'element' => 'Agua', 'image' => 'peereira.png'],
            ['name' => 'Trompeta', 'element' => 'Aire', 'image' => 'trompeta.png'],
            ['name' => 'Trompi', 'element' => 'Tierra', 'image' => 'trompi.png'],
            ['name' => 'Tux', 'element' => 'Agua', 'image' => 'tux.png'],
            ['name' => 'Chopper', 'element' => 'Aire', 'image' => 'chopper.png'],
            ['name' => 'Cuellilargui', 'element' => 'Tierra', 'image' => 'cuellilargui.png'],
            ['name' => 'Deskangoo', 'element' => 'Tierra', 'image' => 'deskangoo.png'],
            ['name' => 'Doflamingo', 'element' => 'Aire', 'image' => 'doflamingo.png'],
            ['name' => 'Dolly', 'element' => 'Tierra', 'image' => 'dolly.png'],
            ['name' => 'Elconchudo', 'element' => 'Agua', 'image' => 'elconchudo.png'],
            ['name' => 'Eldientes', 'element' => 'Tierra', 'image' => 'eldientes.png'],
            ['name' => 'Elgominas', 'element' => 'Agua', 'image' => 'elgominas.png'],
            ['name' => 'Flipper', 'element' => 'Agua', 'image' => 'flipper.png'],
            ['name' => 'Floppi', 'element' => 'Aire', 'image' => 'floppi.png'],
        ];

        foreach ($chuchemons as $chuchemon) {
            Chuchemon::create($chuchemon);
        }
    }
}

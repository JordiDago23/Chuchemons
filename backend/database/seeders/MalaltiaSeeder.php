<?php

namespace Database\Seeders;

use App\Models\Malaltia;
use Illuminate\Database\Seeder;

class MalaltiaSeeder extends Seeder
{
    public function run(): void
    {
        $malalties = [
            [
                'name' => 'Bajón de azúcar',
                'description' => 'El xuxemon no pot alimentar-se. Requereix +2 xuxes per nivell per a créixer',
                'type' => 'metabolic',
                'severity' => 5,
            ],
            [
                'name' => 'Atracón',
                'description' => 'El xuxemon ha menjat domasiat. No pot menjar més xuxes per a aquest cicle',
                'type' => 'digestive',
                'severity' => 3,
            ],
        ];

        foreach ($malalties as $data) {
            Malaltia::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}

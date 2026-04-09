<?php

namespace Database\Seeders;

use App\Models\Vaccine;
use App\Models\Malaltia;
use Illuminate\Database\Seeder;

class VaccineSeeder extends Seeder
{
    public function run(): void
    {
        $bajón = Malaltia::where('name', 'Bajón de azúcar')->first();
        $sobredosis = Malaltia::where('name', 'Sobredosis de sucre')->first();
        $atracón = Malaltia::where('name', 'Atracón')->first();

        $vaccines = [
            ['name' => 'Xocolatina', 'description' => 'Cura "Bajón de azúcar" del xuxemon.', 'malaltia_id' => $bajón?->id],
            ['name' => 'Xal de fruits', 'description' => 'Cura "Atracón" del xuxemon.', 'malaltia_id' => $atracón?->id],
            ['name' => 'Insulina', 'description' => 'Cura todas las enfermedades del xuxemon.', 'malaltia_id' => $bajón?->id],
            ['name' => 'Fruita fresca', 'description' => 'Cura "Sobredosis de sucre" del xuxemon.', 'malaltia_id' => $sobredosis?->id],
        ];

        foreach ($vaccines as $data) {
            Vaccine::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}

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
        $atracón = Malaltia::where('name', 'Atracón')->first();

        $vaccines = [
            ['name' => 'Xocolatina', 'description' => 'Objecte No Aplicable que s\'emmagatzema en la mobilia i en usar-ho en un xuxemon freu "Bajón de azúcar"', 'malaltia_id' => $bajón?->id],
            ['name' => 'Xal de fruits', 'description' => 'Objecte No Aplicable que s\'emmagatzema en la mobilia i en usar-ho en un xuxemon cure les malalalties d\'aquest Xuxemon', 'malaltia_id' => $atracón?->id],
            ['name' => 'Insulina', 'description' => 'Objecte No Aplicable que s\'emmagatzema en la mobilia i cure les malalalties d\'aquest xuxemon', 'malaltia_id' => $bajón?->id],
        ];

        foreach ($vaccines as $data) {
            Vaccine::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}

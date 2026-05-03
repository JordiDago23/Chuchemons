<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\GameSetting;

class UpdateExperienceForNextLevel extends Command
{
    protected $signature = 'chuchemons:update-experience';
    protected $description = 'Actualiza experience_for_next_level basándose en el tamaño actual de cada chuchemon';

    public function handle(): int
    {
        $xpPetitMitja = GameSetting::getInt('xp_petit_mitja', 150);
        $xpMitjaGran = GameSetting::getInt('xp_mitja_gran', 250);

        $this->info("Actualizando experience_for_next_level...");
        $this->info("Petit → Mitjà: {$xpPetitMitja} XP");
        $this->info("Mitjà → Gran: {$xpMitjaGran} XP");

        // Actualizar Petit
        $petitUpdated = DB::table('user_chuchemons')
            ->where('current_mida', 'Petit')
            ->update(['experience_for_next_level' => $xpPetitMitja]);

        $this->line("✓ Actualizados {$petitUpdated} chuchemons Petit");

        // Actualizar Mitjà
        $mitjaUpdated = DB::table('user_chuchemons')
            ->where('current_mida', 'Mitjà')
            ->update(['experience_for_next_level' => $xpMitjaGran]);

        $this->line("✓ Actualizados {$mitjaUpdated} chuchemons Mitjà");

        // Gran no necesita evolucionar, pero por consistencia podemos ponerlo en 0 o null
        $granUpdated = DB::table('user_chuchemons')
            ->where('current_mida', 'Gran')
            ->update(['experience_for_next_level' => 0]);

        $this->line("✓ Actualizados {$granUpdated} chuchemons Gran");

        $this->info("✅ Actualización completa!");

        return Command::SUCCESS;
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-infection scheduler: runs hourly, infects random user chuchemons
Schedule::call(function () {
    try {
        $rate = \App\Models\GameSetting::getInt('taxa_infeccio', 12);
        if ($rate <= 0) return;

        $users = \App\Models\User::all();
        foreach ($users as $user) {
            $chuchemonIds = \Illuminate\Support\Facades\DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->pluck('chuchemon_id');

            foreach ($chuchemonIds as $cid) {
                if (rand(1, 100) <= $rate) {
                    $activeMalaltiaIds = \App\Models\UserInfection::where('user_id', $user->id)
                        ->where('chuchemon_id', $cid)
                        ->where('is_active', true)
                        ->pluck('malaltia_id');

                    $malaltia = \App\Models\Malaltia::whereNotIn('id', $activeMalaltiaIds)->inRandomOrder()->first();
                    if ($malaltia) {
                        \App\Models\UserInfection::create([
                            'user_id'              => $user->id,
                            'chuchemon_id'         => $cid,
                            'malaltia_id'          => $malaltia->id,
                            'is_active'            => true,
                            'infection_percentage'  => rand(10, 80),
                        ]);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Scheduler infection error: ' . $e->getMessage());
    }
})->hourly();

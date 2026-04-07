<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('game_settings')->insert([
            ['key' => 'xux_petit_mitja', 'value' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'xux_mitja_gran', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'taxa_infeccio', 'value' => '12', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'daily_xux_hour', 'value' => '06:00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'daily_xux_quantity', 'value' => '10', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'daily_chuchemon_hour', 'value' => '08:00', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('game_settings');
    }
};
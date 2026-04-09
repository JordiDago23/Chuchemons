<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->integer('attack_boost')->default(0)->after('max_hp');
            $table->integer('defense_boost')->default(0)->after('attack_boost');
        });
    }

    public function down(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->dropColumn(['attack_boost', 'defense_boost']);
        });
    }
};

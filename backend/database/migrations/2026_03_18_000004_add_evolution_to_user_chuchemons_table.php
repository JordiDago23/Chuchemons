<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->enum('current_mida', ['Petit', 'Mitjà', 'Gran'])->default('Petit')->after('count');
            $table->integer('evolution_count')->default(0)->after('current_mida'); // Contador de evoluciones
        });
    }

    public function down(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->dropColumn(['current_mida', 'evolution_count']);
        });
    }
};

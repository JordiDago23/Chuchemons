<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_rewards', function (Blueprint $table) {
            // Campo JSON para guardar la distribución de items (para recompensas de tipo 'xux')
            // Formato: [{"item_id": 1, "quantity": 4}, {"item_id": 2, "quantity": 3}, ...]
            $table->json('items_data')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('daily_rewards', function (Blueprint $table) {
            $table->dropColumn('items_data');
        });
    }
};

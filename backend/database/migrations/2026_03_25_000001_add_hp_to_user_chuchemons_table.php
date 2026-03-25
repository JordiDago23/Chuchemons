<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_hp')->default(105)->after('evolution_count');
            $table->unsignedSmallInteger('current_hp')->default(105)->after('max_hp');
        });

        // Recalculate existing rows: max_hp = 50 + defense + (level * 5) + mida_bonus
        DB::statement("
            UPDATE user_chuchemons uc
            JOIN chuchemons c ON c.id = uc.chuchemon_id
            SET
                uc.max_hp = 50 + IFNULL(c.defense, 50) + (uc.level * 5) +
                    CASE uc.current_mida
                        WHEN 'Mitjà' THEN 25
                        WHEN 'Gran'  THEN 50
                        ELSE 0
                    END,
                uc.current_hp = 50 + IFNULL(c.defense, 50) + (uc.level * 5) +
                    CASE uc.current_mida
                        WHEN 'Mitjà' THEN 25
                        WHEN 'Gran'  THEN 50
                        ELSE 0
                    END
        ");
    }

    public function down(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->dropColumn(['max_hp', 'current_hp']);
        });
    }
};

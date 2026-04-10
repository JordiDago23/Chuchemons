<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE user_chuchemons
            SET experience_for_next_level = CASE current_mida
                WHEN 'Mitjà' THEN 350
                WHEN 'Gran' THEN 450
                ELSE 250
            END");

        DB::statement('ALTER TABLE user_chuchemons ALTER experience_for_next_level SET DEFAULT 250');
    }

    public function down(): void
    {
        DB::statement("UPDATE user_chuchemons
            SET experience_for_next_level = 100");

        DB::statement('ALTER TABLE user_chuchemons ALTER experience_for_next_level SET DEFAULT 100');
    }
};
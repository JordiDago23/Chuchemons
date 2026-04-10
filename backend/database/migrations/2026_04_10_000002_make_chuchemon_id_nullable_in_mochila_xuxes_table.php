<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mochila_xuxes', 'chuchemon_id')) {
            DB::statement('ALTER TABLE mochila_xuxes MODIFY chuchemon_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        DB::statement('UPDATE mochila_xuxes SET chuchemon_id = 1 WHERE chuchemon_id IS NULL');
        DB::statement('ALTER TABLE mochila_xuxes MODIFY chuchemon_id BIGINT UNSIGNED NOT NULL');
    }
};
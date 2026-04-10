<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('malalties', 'infection_rate')) {
            Schema::table('malalties', function (Blueprint $table) {
                $table->unsignedTinyInteger('infection_rate')->default(0)->after('severity');
            });
        }

        DB::table('malalties')->where('name', 'Bajón de azúcar')->update(['infection_rate' => 5]);
        DB::table('malalties')->where('name', 'Sobredosis de sucre')->update(['infection_rate' => 10]);
        DB::table('malalties')->where('name', 'Atracón')->update(['infection_rate' => 15]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('malalties', 'infection_rate')) {
            Schema::table('malalties', function (Blueprint $table) {
                $table->dropColumn('infection_rate');
            });
        }
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove infection_rate from malalties table if it exists
        if (Schema::hasColumn('malalties', 'infection_rate')) {
            Schema::table('malalties', function (Blueprint $table) {
                $table->dropColumn('infection_rate');
            });
        }
    }

    public function down(): void
    {
        // Restore infection_rate to malalties table
        Schema::table('malalties', function (Blueprint $table) {
            $table->unsignedTinyInteger('infection_rate')->default(0)->after('type');
        });
    }
};

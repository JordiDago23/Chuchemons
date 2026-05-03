<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove severity and infection_rate from malalties table
        Schema::table('malalties', function (Blueprint $table) {
            if (Schema::hasColumn('malalties', 'severity')) {
                $table->dropColumn('severity');
            }
            if (Schema::hasColumn('malalties', 'infection_rate')) {
                $table->dropColumn('infection_rate');
            }
        });

        // Remove infection_percentage from user_infections table
        Schema::table('user_infections', function (Blueprint $table) {
            if (Schema::hasColumn('user_infections', 'infection_percentage')) {
                $table->dropColumn('infection_percentage');
            }
        });
    }

    public function down(): void
    {
        // Restore severity and infection_rate to malalties table
        Schema::table('malalties', function (Blueprint $table) {
            $table->unsignedTinyInteger('severity')->default(5)->after('type');
            $table->unsignedTinyInteger('infection_rate')->default(0)->after('severity');
        });

        // Restore infection_percentage to user_infections table
        Schema::table('user_infections', function (Blueprint $table) {
            $table->unsignedTinyInteger('infection_percentage')->default(0)->after('malaltia_id');
        });
    }
};

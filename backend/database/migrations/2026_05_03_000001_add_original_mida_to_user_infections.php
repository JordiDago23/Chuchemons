<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_infections', function (Blueprint $table) {
            $table->string('original_mida', 20)->nullable()->after('infection_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('user_infections', function (Blueprint $table) {
            $table->dropColumn('original_mida');
        });
    }
};

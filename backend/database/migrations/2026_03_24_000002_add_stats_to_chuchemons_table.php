<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chuchemons', function (Blueprint $table) {
            $table->unsignedSmallInteger('attack')->default(50)->after('mida');
            $table->unsignedSmallInteger('defense')->default(50)->after('attack');
            $table->unsignedSmallInteger('speed')->default(50)->after('defense');
        });
    }

    public function down(): void
    {
        Schema::table('chuchemons', function (Blueprint $table) {
            $table->dropColumn(['attack', 'defense', 'speed']);
        });
    }
};

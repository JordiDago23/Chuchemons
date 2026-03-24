<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->default(1)->after('count');
            $table->unsignedInteger('experience')->default(0)->after('level');
            $table->unsignedInteger('experience_for_next_level')->default(100)->after('experience');
        });
    }

    public function down(): void
    {
        Schema::table('user_chuchemons', function (Blueprint $table) {
            $table->dropColumn(['level', 'experience', 'experience_for_next_level']);
        });
    }
};

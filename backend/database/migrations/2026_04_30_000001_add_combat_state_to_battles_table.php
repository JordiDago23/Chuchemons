<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->unsignedSmallInteger('challenger_current_hp')->nullable()->after('result_payload');
            $table->unsignedSmallInteger('challenged_current_hp')->nullable()->after('challenger_current_hp');
            $table->unsignedBigInteger('current_turn_id')->nullable()->after('challenged_current_hp');
            $table->json('last_roll')->nullable()->after('current_turn_id');
            $table->json('combat_log')->nullable()->after('last_roll');

            $table->foreign('current_turn_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropForeign(['current_turn_id']);
            $table->dropColumn(['challenger_current_hp', 'challenged_current_hp', 'current_turn_id', 'last_roll', 'combat_log']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mochila_xuxes', function (Blueprint $table) {
            $table->unsignedBigInteger('vaccine_id')->nullable()->after('item_id');
            $table->foreign('vaccine_id')->references('id')->on('vaccines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mochila_xuxes', function (Blueprint $table) {
            $table->dropForeign(['vaccine_id']);
            $table->dropColumn('vaccine_id');
        });
    }
};

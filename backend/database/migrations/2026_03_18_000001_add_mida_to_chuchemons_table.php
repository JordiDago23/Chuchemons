<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chuchemons', function (Blueprint $table) {
            $table->enum('mida', ['Petit', 'Mitjà', 'Gran'])->default('Petit')->after('element');
        });
    }

    public function down(): void
    {
        Schema::table('chuchemons', function (Blueprint $table) {
            $table->dropColumn('mida');
        });
    }
};

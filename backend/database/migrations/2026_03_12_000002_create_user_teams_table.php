<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->foreignId('chuchemon_1_id')->nullable()->constrained('chuchemons')->onDelete('set null');
            $table->foreignId('chuchemon_2_id')->nullable()->constrained('chuchemons')->onDelete('set null');
            $table->foreignId('chuchemon_3_id')->nullable()->constrained('chuchemons')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_teams');
    }
};

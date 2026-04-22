<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battle_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('chuchemon_id')->constrained('chuchemons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['battle_id', 'user_id']);
            $table->index(['battle_id', 'chuchemon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_selections');
    }
};

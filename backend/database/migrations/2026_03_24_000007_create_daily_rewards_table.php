<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('reward_type', ['xux', 'chuchemon']); // xux o chuchemon
            $table->unsignedBigInteger('item_id')->nullable(); // Si es xux
            $table->unsignedBigInteger('chuchemon_id')->nullable(); // Si es chuchemon
            $table->unsignedSmallInteger('quantity')->default(10); // Para xuxes
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('next_available_at'); // Próxima disponibilidad (08:00 del día siguiente)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('set null');
            $table->foreign('chuchemon_id')->references('id')->on('chuchemons')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_rewards');
    }
};

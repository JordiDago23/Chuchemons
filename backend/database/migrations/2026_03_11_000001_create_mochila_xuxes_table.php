<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mochila_xuxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('chuchemon_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'chuchemon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mochila_xuxes');
    }
};

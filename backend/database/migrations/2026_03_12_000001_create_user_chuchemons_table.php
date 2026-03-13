<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_chuchemons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('chuchemon_id')->constrained('chuchemons')->onDelete('cascade');
            $table->integer('count')->default(1); // Cuántas veces lo tiene capturado
            $table->timestamps();
            
            // Un usuario no puede tener el mismo chuchemon duplicado en esta tabla
            $table->unique(['user_id', 'chuchemon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chuchemons');
    }
};

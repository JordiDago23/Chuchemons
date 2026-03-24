<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_infections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('chuchemon_id');
            $table->unsignedBigInteger('malaltia_id');
            $table->unsignedTinyInteger('infection_percentage')->default(0); // 0-100
            $table->boolean('is_active')->default(true);
            $table->timestamp('infected_at')->nullable();
            $table->timestamp('cured_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('chuchemon_id')->references('id')->on('chuchemons')->onDelete('cascade');
            $table->foreign('malaltia_id')->references('id')->on('malalties')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_infections');
    }
};

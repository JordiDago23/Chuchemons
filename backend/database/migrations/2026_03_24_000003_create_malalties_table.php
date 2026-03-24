<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malalties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // 'bajón de azúcar', 'atracón', etc.
            $table->unsignedTinyInteger('severity'); // 1-10
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('malalties');
    }
};

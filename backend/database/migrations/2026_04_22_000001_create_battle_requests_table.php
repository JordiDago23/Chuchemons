<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battle_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenged_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('battle_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'challenger_id']);
            $table->index(['status', 'challenged_id']);
            $table->index(['battle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_requests');
    }
};

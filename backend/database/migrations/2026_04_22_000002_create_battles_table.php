<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenged_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending_selection');
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('loser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('winner_chuchemon_id')->nullable()->constrained('chuchemons')->nullOnDelete();
            $table->foreignId('loser_chuchemon_id')->nullable()->constrained('chuchemons')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'challenger_id']);
            $table->index(['status', 'challenged_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battles');
    }
};

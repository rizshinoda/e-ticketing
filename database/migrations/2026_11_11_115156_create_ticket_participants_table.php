<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_participants', function (Blueprint $table) {
            $table->id();

            // Tiket yang ditangani
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            // Staf yang ikut menangani
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // User yang menambahkan participant
            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Waktu mulai berkontribusi
            $table->timestamp('joined_at');

            // Waktu selesai berkontribusi
            // NULL berarti masih aktif
            $table->timestamp('left_at')->nullable();

            // Catatan tambahan
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'left_at']);
            $table->index(['user_id', 'joined_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_participants');
    }
};

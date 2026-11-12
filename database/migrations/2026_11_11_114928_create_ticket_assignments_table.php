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
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();

            // Relasi ke tiket
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            // Staf yang menjadi PIC
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Staf yang melakukan penugasan
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Waktu mulai penugasan
            $table->timestamp('assigned_at');

            // Waktu berakhirnya penugasan
            // NULL berarti masih menjadi PIC aktif
            $table->timestamp('ended_at')->nullable();

            // Staf yang mengakhiri penugasan
            $table->foreignId('ended_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Catatan alasan handover
            $table->text('handover_note')->nullable();

            $table->timestamps();

            // Index untuk pencarian PIC aktif
            $table->index(['ticket_id', 'ended_at']);

            // Index untuk riwayat penugasan staf
            $table->index(['user_id', 'assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};

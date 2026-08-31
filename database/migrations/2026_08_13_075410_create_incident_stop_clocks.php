<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_stop_clocks', function (Blueprint $table) {
            $table->id();

            /*
             * Incident yang mengalami Stop Clock.
             */
            $table->foreignId('ticket_incident_id')
                ->constrained('ticket_incidents')
                ->cascadeOnDelete();

            /*
             * Waktu Stop Clock dimulai.
             */
            $table->timestamp('started_at');

            /*
             * Waktu Stop Clock dihentikan.
             *
             * NULL = masih aktif.
             */
            $table->timestamp('ended_at')
                ->nullable();

            /*
             * Alasan sekaligus detail Stop Clock.
             */
            $table->text('reason');

            /*
             * User yang memulai Stop Clock.
             */
            $table->foreignId('started_by')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             * User yang mengakhiri Stop Clock.
             */
            $table->foreignId('ended_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'ticket_incident_id',
                'started_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_stop_clocks');
    }
};

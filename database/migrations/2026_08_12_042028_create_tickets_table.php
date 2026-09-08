<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            /*
     * Nomor ticket.
     */
            $table->string('ticket_number')->unique();

            /*
     * individual = satu customer/site
     * gamas     = dapat mencakup banyak customer/site
     */
            $table->enum('ticket_type', [
                'individual',
                'gamas',
            ])->default('individual');

            /*
     * Deskripsi umum ticket.
     */
            $table->text('description')->nullable();

            /*
     * Prioritas ticket.
     */
            $table->enum('priority', [
                'low',
                'medium',
                'high',
                'critical',
            ])->default('medium');

            /*
     * Status keseluruhan ticket.
     */
            $table->enum('status', [
                'open',
                'on_progress',
                'resolved',
                'closed',
            ])->default('open');

            /*
     * User yang membuat ticket
     * sekaligus menangani ticket.
     */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /*
     * User yang melakukan resolve.
     */
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
     * User yang melakukan close.
     */
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
     * Waktu customer melaporkan gangguan.
     *
     * Menjadi awal perhitungan downtime ticket.
     */
            $table->timestamp('reported_at');

            /*
     * Respons pertama terhadap ticket.
     */
            $table->timestamp('first_response_at')
                ->nullable();

            /*
     * Ticket secara keseluruhan selesai.
     */
            $table->timestamp('resolved_at')
                ->nullable();

            /*
     * Total downtime ticket dalam menit.
     *
     * Sudah dikurangi seluruh periode Stop Clock.
     */
            $table->unsignedInteger('downtime_minutes')
                ->nullable();

            /*
     * Ticket benar-benar ditutup.
     */
            $table->timestamp('closed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'priority',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

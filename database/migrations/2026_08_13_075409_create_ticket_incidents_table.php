<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_incidents', function (Blueprint $table) {
            $table->id();

            /*
             * Customer/site yang mengalami incident.
             */
            $table->foreignId('ticket_customer_id')
                ->constrained('ticket_customers')
                ->cascadeOnDelete();

            /*
             * Nomor urut incident dalam satu customer/site.
             *
             * 1 = gangguan pertama
             * 2 = setelah reopen
             * 3 = reopen berikutnya
             */
            $table->unsignedInteger('incident_number');

            /*
             * Kendala pada incident ini.
             */
            $table->foreignId('kendala_id')
                ->constrained('ticket_categories')
                ->restrictOnDelete();

            /*
             * Waktu customer melaporkan gangguan.
             *
             * Menjadi awal perhitungan waktu incident.
             */
            $table->timestamp('reported_at');

            /*
             * Waktu support pertama kali merespons
             * incident ini.
             */
            $table->timestamp('first_response_at')
                ->nullable();

            /*
             * Waktu incident dinyatakan selesai.
             */
            $table->timestamp('resolved_at')
                ->nullable();

            /*
             * Total durasi incident.
             *
             * Bukan MTTR.
             */
            $table->unsignedInteger('downtime_minutes')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'ticket_customer_id',
                'incident_number',
            ]);

            $table->index([
                'ticket_customer_id',
                'reported_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_incidents');
    }
};

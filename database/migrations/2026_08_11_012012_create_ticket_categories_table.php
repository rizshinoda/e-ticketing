<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();

            /*
             * Nama kendala.
             *
             * Contoh:
             * Down
             * Intermittent
             * Packet Loss
             * High Latency
             */
            $table->string('name')->unique();

            /*
             * Apakah kendala ini termasuk
             * gangguan/downtime.
             */
            $table->boolean('is_downtime')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfos', function (Blueprint $table) {
            $table->id();

            /*
     * Ticket yang menjadi sumber RFO.
     */
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            /*
     * Nomor RFO.
     */
            $table->string('rfo_number')->unique();

            /*
     * Isi RFO yang disimpan sebagai history.
     */
            $table->longText('content');

            /*
     * User yang membuat RFO.
     */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfos');
    }
};

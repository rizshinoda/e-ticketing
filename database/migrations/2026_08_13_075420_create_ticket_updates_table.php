<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_updates', function (Blueprint $table) {
            $table->id();

            /*
             * Ticket induk.
             */
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            /*
             * User yang melakukan update.
             */
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             * Catatan progress/activity.
             */
            $table->text('message');

            $table->timestamps();

            $table->index([
                'ticket_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_updates');
    }
};

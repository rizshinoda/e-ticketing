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

            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            $table->unsignedInteger('incident_number');

            $table->foreignId('kendala_id')
                ->constrained('ticket_categories')
                ->restrictOnDelete();

            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();


            $table->timestamps();

            $table->unique([
                'ticket_id',
                'incident_number',
            ]);

            $table->index([
                'ticket_id',
                'reported_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_incidents');
    }
};

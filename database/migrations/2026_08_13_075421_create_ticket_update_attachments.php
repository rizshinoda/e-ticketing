<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('ticket_update_attachments', function (Blueprint $table) {
            $table->id();

            /*
             * Update/progress yang memiliki attachment.
             */
            $table->foreignId('ticket_update_id')
                ->constrained('ticket_updates')
                ->cascadeOnDelete();

            /*
             * Lokasi file.
             */
            $table->string('file_path');

            /*
             * Nama file asli.
             */
            $table->string('file_name');

            /*
             * MIME type.
             *
             * Contoh:
             * image/jpeg
             * image/png
             * application/pdf
             */
            $table->string('mime_type')
                ->nullable();

            /*
             * Ukuran file dalam byte.
             */
            $table->unsignedBigInteger('file_size')
                ->nullable();

            $table->timestamps();

            $table->index('ticket_update_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_update_attachments');
    }
};

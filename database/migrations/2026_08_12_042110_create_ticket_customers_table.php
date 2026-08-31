<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_customers', function (Blueprint $table) {
            $table->id();

            /*
             * Ticket induk.
             */
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            /*
             * Referensi site/layanan pada Online Billing.
             *
             * Nullable jika site belum tersedia
             * di Online Billing.
             */
            $table->foreignId('online_billing_id')
                ->nullable()
                ->constrained('online_billings')
                ->nullOnDelete();

            /*
             * Snapshot data ketika ticket dibuat.
             */
            $table->string('customer_name')->nullable();

            $table->string('site_name')->nullable();

            $table->string('no_jaringan')->nullable();

            /*
             * Media/tempat customer melaporkan gangguan.
             *
             * Contoh:
             * WAG CSD/NSD.LA-PC24Telin
             * WFG LA Sulampua
             * Email
             * WA Personal
             */
            $table->string('reported_via')->nullable();

            $table->timestamps();

            $table->index([
                'ticket_id',
                'online_billing_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_customers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();

            /*
             * Pelanggan pemilik SLA.
             */
            $table->foreignId('pelanggan_id')
                ->constrained('pelanggans')
                ->restrictOnDelete();

            /*
             * NULL:
             * SLA default pelanggan.
             *
             * Terisi:
             * SLA khusus site/layanan Online Billing.
             */
            $table->foreignId('online_billing_id')
                ->nullable()
                ->constrained('online_billings')
                ->nullOnDelete();

            /*
             * Persentase SLA.
             *
             * Contoh:
             * 99
             * 99.5
             * 99.7
             * 99.9
             * 99.95
             *
             * NULL = belum ditentukan.
             */
            $table->decimal('sla_percentage', 7, 4)
                ->nullable();

            /*
             * Awal berlakunya aturan SLA.
             */
            $table->date('effective_from');

            /*
             * Akhir berlakunya aturan SLA.
             *
             * NULL = masih berlaku.
             */
            $table->date('effective_until')
                ->nullable();

            $table->timestamps();

            $table->index([
                'pelanggan_id',
                'online_billing_id',
            ]);

            $table->index([
                'effective_from',
                'effective_until',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_rules');
    }
};

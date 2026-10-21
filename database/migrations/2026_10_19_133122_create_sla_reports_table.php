<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sla_reports', function (Blueprint $table) {
            $table->id();

            // Mencegah laporan untuk cakupan dan periode yang sama
            // difinalisasi berulang kali.
            $table->string('report_key', 191)->unique();

            // Simpan ID dan nama pelanggan untuk kebutuhan historis.
            $table->foreignId('pelanggan_id')
                ->nullable()
                ->constrained('pelanggans')
                ->nullOnDelete();

            $table->string('customer_name');

            // global atau site
            $table->enum('scope', ['global', 'site']);

            // Null untuk Global, berisi ID site untuk SLA per Site.
            $table->foreignId('online_billing_id')
                ->nullable()
                ->constrained('online_billings')
                ->nullOnDelete();

            // Snapshot nama site ketika laporan difinalisasi.
            $table->string('site_name')->nullable();

            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');

            // Tanggal awal dan akhir laporan bersifat inklusif
            // untuk penyimpanan dan tampilan laporan.
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('target_sla_percentage', 7, 4);
            $table->decimal('availability_percentage', 7, 4);

            $table->unsignedBigInteger('downtime_seconds');
            $table->unsignedBigInteger('total_service_seconds');

            $table->boolean('is_sla_met');

            // Jumlah site yang dihitung pada SLA Global.
            $table->unsignedInteger('site_count')->nullable();

            // Menyimpan hasil service lengkap, termasuk rincian Site.
            $table->json('snapshot');

            $table->foreignId('finalized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('finalized_at');

            $table->timestamps();

            $table->index(['pelanggan_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_reports');
    }
};

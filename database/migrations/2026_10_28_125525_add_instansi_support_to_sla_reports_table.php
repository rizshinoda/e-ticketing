<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_reports', function (Blueprint $table) {
            // Identitas instansi pada laporan.
            $table->foreignId('instansi_id')
                ->nullable()
                ->after('site_name')
                ->constrained('instansis')
                ->nullOnDelete();

            // Simpan nama instansi ketika laporan difinalisasi.
            $table->string('instansi_name')
                ->nullable()
                ->after('instansi_id');

            // Mempercepat filter riwayat berdasarkan instansi dan periode.
            $table->index(
                ['instansi_id', 'year', 'month'],
                'sla_reports_instansi_period_index'
            );

            // Tambahkan cakupan instansi tanpa mengubah
            // nilai scope laporan lama.
            $table->enum('scope', [
                'global',
                'site',
                'instansi',
            ])->change();
        });
    }

    public function down(): void
    {
        /*
         * Jangan membuang kolom instansi ketika masih ada
         * snapshot instansi yang tersimpan.
         */
        if (
            DB::table('sla_reports')
            ->where('scope', 'instansi')
            ->exists()
        ) {
            throw new \RuntimeException(
                'Rollback dibatalkan karena masih ada laporan SLA '
                    . 'per instansi. Pertahankan data laporan tersebut.'
            );
        }

        Schema::table('sla_reports', function (Blueprint $table) {
            $table->dropIndex('sla_reports_instansi_period_index');
            $table->dropForeign(['instansi_id']);

            $table->dropColumn([
                'instansi_id',
                'instansi_name',
            ]);

            $table->enum('scope', [
                'global',
                'site',
            ])->change();
        });
    }
};

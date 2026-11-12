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
        Schema::create('kpi_evaluation_details', function (Blueprint $table) {
            $table->id();

            // Relasi ke evaluasi KPI
            $table->foreignId('kpi_evaluation_id')
                ->constrained('kpi_evaluations')
                ->cascadeOnDelete();

            // Kode indikator penilaian
            $table->string('indicator_code', 50);

            // Nilai aktual
            $table->decimal('actual_value', 12, 2)
                ->default(0);

            // Target indikator
            $table->decimal('target_value', 12, 2)
                ->default(0);

            // Bobot indikator
            $table->decimal('weight', 5, 2)
                ->default(0);

            // Skor indikator
            $table->decimal('score', 5, 2)
                ->default(0);

            // Keterangan perhitungan
            $table->text('notes')->nullable();

            $table->timestamps();

            // Satu indikator hanya boleh muncul sekali
            // dalam satu evaluasi.
            $table->unique([
                'kpi_evaluation_id',
                'indicator_code',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_evaluation_details');
    }
};

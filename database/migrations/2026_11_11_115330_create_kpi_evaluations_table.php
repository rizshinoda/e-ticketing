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
        Schema::create('kpi_evaluations', function (Blueprint $table) {
            $table->id();

            // Staf yang dievaluasi
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Periode evaluasi
            $table->date('period_start');
            $table->date('period_end');

            // Snapshot dasar target produktivitas
            $table->unsignedInteger('total_incoming_tickets')
                ->default(0);

            $table->unsignedInteger('active_staff_count')
                ->default(0);

            $table->decimal('productivity_target', 10, 2)
                ->default(0);

            // Nilai masing-masing aspek KPI
            $table->decimal('productivity_score', 5, 2)
                ->default(0);

            $table->decimal('speed_score', 5, 2)
                ->default(0);

            $table->decimal('quality_score', 5, 2)
                ->default(0);

            // Nilai akhir KPI
            $table->decimal('final_score', 5, 2)
                ->default(0);

            // Status evaluasi
            $table->enum('status', [
                'draft',
                'finalized',
            ])->default('draft');

            // Waktu finalisasi
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            // Satu staf hanya memiliki satu evaluasi
            // untuk satu periode.
            $table->unique([
                'user_id',
                'period_start',
                'period_end',
            ]);

            $table->index([
                'period_start',
                'period_end',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_evaluations');
    }
};

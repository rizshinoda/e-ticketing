<?php

namespace App\Http\Controllers;

use App\Exports\TicketDisruptionReportExport;
use App\Services\TicketDisruptionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class TicketDisruptionReportExportController extends Controller
{
    public function __construct(
        protected TicketDisruptionReportService $reportService
    ) {}

    public function download(Request $request)
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        $validated = $request->validate([
            'month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'year' => [
                'sometimes',
                'required',
                'integer',
                'between:2000,' . $now->year,
            ],
        ]);

        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);

        $periodStart = CarbonImmutable::create(
            $year,
            $month,
            1,
            0,
            0,
            0,
            config('app.timezone')
        );

        if ($periodStart->greaterThan($now->startOfMonth())) {
            throw ValidationException::withMessages([
                'month' => 'Tidak dapat mengekspor laporan untuk periode mendatang.',
            ]);
        }

        $periodEnd = $periodStart->addMonth();

        // Gunakan service laporan yang sama dengan halaman Vue.
        $report = $this->reportService->generate(
            $periodStart,
            $periodEnd
        );

        $filename = sprintf(
            'laporan-tiket-kendala-%04d-%02d.xlsx',
            $year,
            $month
        );

        return Excel::download(
            new TicketDisruptionReportExport(
                $report,
                $month,
                $year
            ),
            $filename
        );
    }

    public function downloadPdf(Request $request)
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        $validated = $request->validate([
            'month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'year' => [
                'sometimes',
                'required',
                'integer',
                'between:2000,' . $now->year,
            ],
        ]);

        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);

        $periodStart = CarbonImmutable::create(
            $year,
            $month,
            1,
            0,
            0,
            0,
            config('app.timezone')
        );

        if ($periodStart->greaterThan($now->startOfMonth())) {
            throw ValidationException::withMessages([
                'month' => 'Tidak dapat mengekspor laporan untuk periode mendatang.',
            ]);
        }

        $periodEnd = $periodStart->addMonth();

        // Menggunakan service yang sama dengan laporan Vue dan Excel.
        $report = $this->reportService->generate(
            $periodStart,
            $periodEnd
        );

        $filename = sprintf(
            'laporan-tiket-kendala-%04d-%02d.pdf',
            $year,
            $month
        );

        $pdf = Pdf::loadView('sla.reports.ticket-disruptions.pdf', [
            'report' => $report,
            'month' => $month,
            'year' => $year,
            'generatedAt' => $now,
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}

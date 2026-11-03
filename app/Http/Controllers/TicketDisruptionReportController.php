<?php

namespace App\Http\Controllers;

use App\Services\TicketDisruptionReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketDisruptionReportController extends Controller
{
    public function index(
        Request $request,
        TicketDisruptionReportService $reportService
    ): Response {
        /*
         * Default periode adalah bulan sebelumnya.
         */
        $now = CarbonImmutable::now(config('app.timezone'));

        $defaultPeriod = $now
            ->startOfMonth()
            ->subMonth();

        $validated = $request->validate([
            'month' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],

            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:' . $now->year,
            ],
        ]);

        $month = (int) (
            $validated['month'] ?? $defaultPeriod->month
        );

        $year = (int) (
            $validated['year'] ?? $defaultPeriod->year
        );

        /*
         * Awal dan akhir periode laporan.
         * Akhir periode tidak termasuk.
         */
        $periodStart = CarbonImmutable::create(
            $year,
            $month,
            1,
            0,
            0,
            0,
            config('app.timezone')
        );

        /*
         * Jangan izinkan memilih bulan yang belum berjalan.
         */
        if ($periodStart->greaterThan($now->startOfMonth())) {
            throw ValidationException::withMessages([
                'month' => 'Bulan laporan tidak boleh berada di masa depan.',
            ]);
        }

        $periodEnd = $periodStart->addMonth();

        /*
         * Jalankan service laporan.
         */
        $report = $reportService->generate(
            $periodStart,
            $periodEnd
        );

        /*
         * Pagination detail insiden.
         */
        $details = collect(
            $report['incident_details']
        );

        $perPage = 10;

        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $incidentPaginator = new LengthAwarePaginator(
            $details
                ->forPage($currentPage, $perPage)
                ->values(),

            $details->count(),

            $perPage,

            $currentPage,

            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render(
            'Sla/Reports/TicketDisruptions/Index',
            [
                'filters' => [
                    'month' => $month,
                    'year' => $year,
                ],

                'period' => [
                    'start' => $periodStart->toDateString(),

                    'end' => $periodEnd
                        ->subDay()
                        ->toDateString(),

                    'label' => $periodStart
                        ->locale('id')
                        ->translatedFormat('F Y'),
                ],

                'summary' => $report['summary'],

                'siteRanking' => array_slice(
                    $report['site_ranking'],
                    0,
                    10
                ),

                'instansiRanking' => array_slice(
                    $report['instansi_ranking'],
                    0,
                    10
                ),

                'categoryRanking' => $report['category_ranking'],

                'dailyTrend' => $report['daily_trend'],

                'incidents' => $incidentPaginator,
            ]
        );
    }
}

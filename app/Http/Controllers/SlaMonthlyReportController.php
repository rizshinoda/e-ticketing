<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
use App\Models\Pelanggan;
use App\Models\SlaReport;
use App\Services\SlaCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class SlaMonthlyReportController extends Controller
{
    public function index(
        Request $request,
        SlaCalculationService $slaService
    ) {
        $previousMonth = CarbonImmutable::now()
            ->startOfMonth()
            ->subMonth();

        $pelangganId = $request->integer('pelanggan_id') ?: null;

        $filters = [
            'scope' => $request->input('scope', 'global'),
            'pelanggan_id' => $pelangganId,
            'online_billing_id' => $request->input(
                'online_billing_id'
            ),
            'month' => (string) $request->input(
                'month',
                $previousMonth->month
            ),
            'year' => (string) $request->input(
                'year',
                $previousMonth->year
            ),
        ];

        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get(['id', 'nama_pelanggan']);

        $sites = OnlineBilling::query()
            ->when(
                $pelangganId,
                fn($query) => $query->where(
                    'pelanggan_id',
                    $pelangganId
                )
            )
            ->orderBy('nama_site')
            ->get([
                'id',
                'pelanggan_id',
                'nama_site',
                'no_jaringan',
            ]);

        /*
         * Jika laporan sudah difinalisasi, gunakan snapshot lama.
         * Jangan menghitung ulang dan menimpa hasil resmi.
         */
        $reportKey = $this->reportKeyFromRequest($request);

        $finalizedReport = $reportKey
            ? SlaReport::where('report_key', $reportKey)->first()
            : null;

        $result = $finalizedReport
            ? ($finalizedReport->snapshot['result'] ?? null)
            : null;

        $calculationError = null;

        if (
            !$finalizedReport
            && $request->boolean('calculate')
        ) {
            $validated = $request->validate([
                'scope' => [
                    'required',
                    Rule::in(['site', 'global']),
                ],

                'pelanggan_id' => [
                    'required',
                    'integer',
                    'exists:pelanggans,id',
                ],

                'online_billing_id' => [
                    Rule::requiredIf(
                        $request->input('scope') === 'site'
                    ),
                    'nullable',
                    'integer',
                    'exists:online_billings,id',
                ],

                'month' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:12',
                ],

                'year' => [
                    'required',
                    'integer',
                    'min:2000',
                    'max:' . now()->year,
                ],
            ]);

            try {
                [$monthStart, $monthEnd] = $this->makePeriod(
                    (int) $validated['year'],
                    (int) $validated['month']
                );

                if (
                    $monthEnd->greaterThan(
                        CarbonImmutable::now()->startOfMonth()
                    )
                ) {
                    $calculationError =
                        'Laporan SLA bulanan hanya dapat dihitung '
                        . 'untuk bulan yang sudah berakhir.';
                } elseif ($validated['scope'] === 'site') {
                    $site = OnlineBilling::query()
                        ->whereKey(
                            $validated['online_billing_id']
                        )
                        ->where(
                            'pelanggan_id',
                            $validated['pelanggan_id']
                        )
                        ->first();

                    if (!$site) {
                        $calculationError =
                            'Site tidak ditemukan untuk pelanggan yang dipilih.';
                    } else {
                        $result = $slaService->calculateSite(
                            (int) $validated['pelanggan_id'],
                            (int) $site->id,
                            $monthStart,
                            $monthEnd
                        );
                    }
                } else {
                    $result = $slaService->calculateGlobal(
                        (int) $validated['pelanggan_id'],
                        $monthStart,
                        $monthEnd
                    );
                }
            } catch (Throwable $e) {
                report($e);

                $calculationError = app()->environment('local')
                    ? $e->getMessage()
                    : 'Laporan SLA gagal dihitung. '
                    . 'Periksa aturan SLA dan data terkait.';
            }
        }

        return Inertia::render('Sla/Reports/Monthly', [
            'customers' => $customers,
            'sites' => $sites,
            'filters' => $filters,
            'result' => $result,
            'calculationError' => $calculationError,
            'currentYear' => now()->year,
            'finalizedReport' => $finalizedReport,
            'success' => session('success'),
        ]);
    }

    /**
     * Hitung ulang di server, validasi kelayakan, lalu simpan snapshot.
     */
    public function finalize(
        Request $request,
        SlaCalculationService $slaService
    ) {
        $validated = $request->validate([
            'scope' => [
                'required',
                Rule::in(['site', 'global']),
            ],

            'pelanggan_id' => [
                'required',
                'integer',
                'exists:pelanggans,id',
            ],

            'online_billing_id' => [
                Rule::requiredIf(
                    $request->input('scope') === 'site'
                ),
                'nullable',
                'integer',
                'exists:online_billings,id',
            ],

            'month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
            ],

            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:' . now()->year,
            ],
        ]);

        $pelangganId = (int) $validated['pelanggan_id'];
        $scope = $validated['scope'];
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $onlineBillingId = $scope === 'site'
            ? (int) $validated['online_billing_id']
            : null;

        [$monthStart, $monthEnd] = $this->makePeriod(
            $year,
            $month
        );

        // Finalisasi hanya untuk bulan yang sudah berakhir.
        if (
            $monthEnd->greaterThan(
                CarbonImmutable::now()->startOfMonth()
            )
        ) {
            throw ValidationException::withMessages([
                'finalize' =>
                'Bulan laporan belum berakhir sehingga belum bisa difinalisasi.',
            ]);
        }

        $reportKey = $this->makeReportKey(
            $pelangganId,
            $scope,
            $onlineBillingId,
            $year,
            $month
        );

        if (SlaReport::where('report_key', $reportKey)->exists()) {
            throw ValidationException::withMessages([
                'finalize' =>
                'Laporan untuk cakupan dan periode ini sudah difinalisasi.',
            ]);
        }

        $customer = Pelanggan::findOrFail($pelangganId);

        $site = null;

        if ($scope === 'site') {
            $site = OnlineBilling::query()
                ->whereKey($onlineBillingId)
                ->where('pelanggan_id', $pelangganId)
                ->first();

            if (!$site) {
                throw ValidationException::withMessages([
                    'online_billing_id' =>
                    'Site tidak ditemukan untuk pelanggan yang dipilih.',
                ]);
            }
        }

        try {
            /*
             * Jangan mempercayai hasil yang dikirim browser.
             * Hitung ulang menggunakan data server saat finalisasi.
             */
            if ($scope === 'site') {
                $result = $slaService->calculateSite(
                    $pelangganId,
                    (int) $site->id,
                    $monthStart,
                    $monthEnd
                );
            } else {
                $result = $slaService->calculateGlobal(
                    $pelangganId,
                    $monthStart,
                    $monthEnd
                );
            }
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'finalize' => app()->environment('local')
                    ? $e->getMessage()
                    : 'Perhitungan gagal. Laporan belum difinalisasi.',
            ]);
        }

        $pendingCount = $scope === 'global'
            ? (int) ($result['pending_site_incidents_count'] ?? 0)
            : (int) ($result['pending_incidents_count'] ?? 0);

        if ($pendingCount > 0) {
            throw ValidationException::withMessages([
                'finalize' =>
                "Masih ada {$pendingCount} insiden-site downtime "
                    . 'yang belum selesai. Laporan belum dapat difinalisasi.',
            ]);
        }

        $target = $result['target_sla_percentage'] ?? null;
        $availability = $result['availability_percentage'] ?? null;
        $totalServiceSeconds = (int) (
            $result['total_service_seconds'] ?? 0
        );

        if (
            $target === null
            || $availability === null
            || $totalServiceSeconds <= 0
            || !is_bool($result['is_sla_met'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'finalize' =>
                'Target SLA atau hasil perhitungan belum lengkap. '
                    . 'Periksa aturan SLA dan site yang dihitung.',
            ]);
        }

        $downtimeSeconds = (int) (
            $result['downtime_seconds']
            ?? $result['total_downtime_site_seconds']
            ?? 0
        );

        /*
         * Snapshot menyimpan hasil lengkap dari service, termasuk
         * seluruh rincian site dalam laporan Global.
         */
        $snapshot = [
            'generated_at' => now()->toIso8601String(),

            'customer' => [
                'id' => $customer->id,
                'name' => $customer->nama_pelanggan,
            ],

            'period' => [
                'start' => $monthStart->toDateString(),
                'end' => $monthEnd->subDay()->toDateString(),
            ],

            'result' => $result,
        ];

        try {
            SlaReport::create([
                'report_key' => $reportKey,

                'pelanggan_id' => $pelangganId,
                'customer_name' => $customer->nama_pelanggan,

                'scope' => $scope,
                'online_billing_id' => $onlineBillingId,
                'site_name' => $site?->nama_site,

                'month' => $month,
                'year' => $year,

                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->subDay()->toDateString(),

                'target_sla_percentage' => $target,
                'availability_percentage' => $availability,

                'downtime_seconds' => $downtimeSeconds,
                'total_service_seconds' => $totalServiceSeconds,

                'is_sla_met' => $result['is_sla_met'],

                'site_count' => $scope === 'global'
                    ? (int) ($result['site_count'] ?? 0)
                    : 1,

                'snapshot' => $snapshot,

                'finalized_by' => $request->user()?->id,
                'finalized_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'finalize' => app()->environment('local')
                    ? $e->getMessage()
                    : 'Snapshot laporan gagal disimpan.',
            ]);
        }

        return redirect()
            ->route('sla-reports.index', [
                'scope' => $scope,
                'pelanggan_id' => $pelangganId,
                'online_billing_id' => $onlineBillingId,
                'month' => $month,
                'year' => $year,
            ])
            ->with(
                'success',
                'Laporan SLA berhasil difinalisasi dan snapshot telah disimpan.'
            );
    }

    private function makePeriod(
        int $year,
        int $month
    ): array {
        $start = CarbonImmutable::create(
            $year,
            $month,
            1
        )->startOfDay();

        return [$start, $start->addMonth()];
    }

    private function makeReportKey(
        int $pelangganId,
        string $scope,
        ?int $onlineBillingId,
        int $year,
        int $month
    ): string {
        $scopeKey = $scope === 'global'
            ? 'global'
            : 'site-' . $onlineBillingId;

        return $pelangganId
            . ':'
            . $scopeKey
            . ':'
            . sprintf('%04d-%02d', $year, $month);
    }


    private function reportKeyFromRequest(
        Request $request
    ): ?string {
        $previousMonth = CarbonImmutable::now()
            ->startOfMonth()
            ->subMonth();

        $pelangganId = $request->integer('pelanggan_id');
        $scope = (string) $request->input('scope', 'global');

        $month = filter_var(
            $request->input('month', $previousMonth->month),
            FILTER_VALIDATE_INT
        );

        $year = filter_var(
            $request->input('year', $previousMonth->year),
            FILTER_VALIDATE_INT
        );

        if (
            $pelangganId <= 0
            || !in_array($scope, ['site', 'global'], true)
            || $month === false
            || $year === false
            || $month < 1
            || $month > 12
            || $year < 2000
            || $year > now()->year
        ) {
            return null;
        }

        $onlineBillingId = null;

        if ($scope === 'site') {
            $onlineBillingId = $request->integer(
                'online_billing_id'
            );

            if ($onlineBillingId <= 0) {
                return null;
            }
        }

        return $this->makeReportKey(
            $pelangganId,
            $scope,
            $onlineBillingId,
            $year,
            $month
        );
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],

            'scope' => [
                'nullable',
                Rule::in(['all', 'global', 'site']),
            ],

            'pelanggan_id' => [
                'nullable',
                'integer',
                'exists:pelanggans,id',
            ],

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
                'max:' . now()->year,
            ],
        ]);

        $filters = [
            'search' => trim($validated['search'] ?? ''),
            'scope' => $validated['scope'] ?? 'all',
            'pelanggan_id' => $validated['pelanggan_id'] ?? '',
            'month' => $validated['month'] ?? '',
            'year' => $validated['year'] ?? '',
        ];

        $reports = SlaReport::query()
            // Ambil kolom yang diperlukan saja.
            // Snapshot JSON tidak perlu dimuat pada daftar riwayat.
            ->select([
                'id',
                'report_key',
                'pelanggan_id',
                'customer_name',
                'scope',
                'online_billing_id',
                'site_name',
                'month',
                'year',
                'period_start',
                'period_end',
                'target_sla_percentage',
                'availability_percentage',
                'downtime_seconds',
                'total_service_seconds',
                'is_sla_met',
                'site_count',
                'finalized_at',
            ])

            // Cari berdasarkan pelanggan atau nama site.
            ->when(
                $filters['search'] !== '',
                function ($query) use ($filters) {
                    $search = $filters['search'];

                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where(
                                'customer_name',
                                'like',
                                '%' . $search . '%'
                            )
                            ->orWhere(
                                'site_name',
                                'like',
                                '%' . $search . '%'
                            );
                    });
                }
            )

            // Filter cakupan SLA.
            ->when(
                $filters['scope'] !== 'all',
                fn($query) => $query->where(
                    'scope',
                    $filters['scope']
                )
            )

            // Filter pelanggan.
            ->when(
                $filters['pelanggan_id'] !== '',
                fn($query) => $query->where(
                    'pelanggan_id',
                    $filters['pelanggan_id']
                )
            )

            // Filter bulan.
            ->when(
                $filters['month'] !== '',
                fn($query) => $query->where(
                    'month',
                    $filters['month']
                )
            )

            // Filter tahun.
            ->when(
                $filters['year'] !== '',
                fn($query) => $query->where(
                    'year',
                    $filters['year']
                )
            )

            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('finalized_at')
            ->paginate(15)
            ->withQueryString();

        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get([
                'id',
                'nama_pelanggan',
            ]);

        return Inertia::render('Sla/Reports/History', [
            'reports' => $reports,
            'customers' => $customers,
            'filters' => $filters,
            'currentYear' => now()->year,
        ]);
    }

    public function downloadPdf(SlaReport $report)
    {
        // Gunakan snapshot resmi yang tersimpan.
        $snapshot = $report->snapshot;

        if (
            !is_array($snapshot) ||
            !isset($snapshot['result']) ||
            !is_array($snapshot['result'])
        ) {
            abort(404, 'Snapshot laporan SLA tidak ditemukan.');
        }

        $result = $snapshot['result'];

        // Nama file yang aman untuk diunduh.
        $name = $report->scope === 'site'
            ? ($report->site_name ?: $report->customer_name)
            : $report->customer_name;

        $filename = sprintf(
            'laporan-sla-%s-%04d-%02d-%s.pdf',
            Str::slug($name) ?: 'pelanggan',
            $report->year,
            $report->month,
            $report->scope
        );

        return Pdf::loadView('sla.reports.pdf', [
            'report' => $report,
            'result' => $result,
        ])
            ->setPaper(
                'a4',
                $report->scope === 'global' ? 'landscape' : 'portrait'
            )
            ->download($filename);
    }
}

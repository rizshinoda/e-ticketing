<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\OnlineBilling;
use App\Models\Pelanggan;
use App\Models\SlaReport;
use App\Services\SlaCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class SlaMonthlyReportController extends Controller
{
    /**
     * Halaman laporan SLA bulanan.
     *
     * Cakupan yang didukung:
     * - global
     * - site
     * - instansi
     */
    public function index(
        Request $request,
        SlaCalculationService $slaService
    ) {
        $previousMonth = CarbonImmutable::now()
            ->startOfMonth()
            ->subMonth();

        $pelangganId = $request->integer('pelanggan_id') ?: null;

        $scope = (string) $request->input('scope', 'global');

        if (!in_array($scope, ['global', 'site', 'instansi'], true)) {
            $scope = 'global';
        }

        $filters = [
            'scope' => $scope,
            'pelanggan_id' => $pelangganId,
            'online_billing_id' => $request->input(
                'online_billing_id'
            ),
            'instansi_id' => $request->input('instansi_id'),
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
            ->get([
                'id',
                'nama_pelanggan',
            ]);

        /*
         * Daftar instansi yang mempunyai site pada pelanggan terpilih.
         * Jika pelanggan belum dipilih, tampilkan instansi yang memiliki site.
         */
        $instansis = Instansi::query()
            ->whereIn(
                'id',
                OnlineBilling::query()
                    ->select('instansi_id')
                    ->whereNotNull('instansi_id')
                    ->when(
                        $pelangganId,
                        fn($query) => $query->where(
                            'pelanggan_id',
                            $pelangganId
                        )
                    )
            )
            ->orderBy('nama_instansi')
            ->get([
                'id',
                'nama_instansi',
            ]);

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
                'instansi_id',
                'nama_site',
                'no_jaringan',
            ]);

        /*
         * Jika laporan sudah difinalisasi, gunakan snapshot resmi.
         * Hasil snapshot tidak dihitung ulang pada halaman ini.
         */
        $reportKey = $this->reportKeyFromRequest($request);

        $finalizedReport = $reportKey
            ? SlaReport::where('report_key', $reportKey)->first()
            : null;

        $result = $finalizedReport
            ? ($finalizedReport->snapshot['result'] ?? null)
            : null;

        $calculationError = null;

        /*
         * Hitung laporan hanya jika:
         * - Belum ada snapshot final untuk cakupan dan periode tersebut.
         * - Pengguna meminta perhitungan.
         */
        if (
            !$finalizedReport
            && $request->boolean('calculate')
        ) {
            $validated = $request->validate([
                'scope' => [
                    'required',
                    Rule::in(['site', 'global', 'instansi']),
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

                'instansi_id' => [
                    Rule::requiredIf(
                        $request->input('scope') === 'instansi'
                    ),
                    'nullable',
                    'integer',
                    'exists:instansis,id',
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

                /*
                 * Laporan bulanan hanya dapat dihitung untuk bulan
                 * yang sudah berakhir.
                 */
                if (
                    $monthEnd->greaterThan(
                        CarbonImmutable::now()->startOfMonth()
                    )
                ) {
                    $calculationError =
                        'Laporan SLA bulanan hanya dapat dihitung '
                        . 'untuk bulan yang sudah berakhir.';
                } elseif ($validated['scope'] === 'site') {
                    /*
                     * Laporan satu site.
                     */
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
                } elseif ($validated['scope'] === 'instansi') {
                    /*
                     * Laporan semua site milik instansi tertentu
                     * di dalam pelanggan yang dipilih.
                     */
                    $hasSites = OnlineBilling::query()
                        ->where(
                            'pelanggan_id',
                            $validated['pelanggan_id']
                        )
                        ->where(
                            'instansi_id',
                            $validated['instansi_id']
                        )
                        ->exists();

                    if (!$hasSites) {
                        $calculationError =
                            'Tidak ditemukan site untuk instansi '
                            . 'dan pelanggan yang dipilih.';
                    } else {
                        $result = $slaService->calculateInstansi(
                            (int) $validated['pelanggan_id'],
                            (int) $validated['instansi_id'],
                            $monthStart,
                            $monthEnd
                        );
                    }
                } else {
                    /*
                     * Laporan global pelanggan tetap menggunakan
                     * perhitungan yang sudah ada.
                     */
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
            'instansis' => $instansis,
            'filters' => $filters,
            'result' => $result,
            'calculationError' => $calculationError,
            'currentYear' => now()->year,
            'finalizedReport' => $finalizedReport,
            'success' => session('success'),
        ]);
    }

    /**
     * Hitung ulang di server, validasi kelayakan,
     * lalu simpan snapshot laporan resmi.
     */
    public function finalize(
        Request $request,
        SlaCalculationService $slaService
    ) {
        $validated = $request->validate([
            'scope' => [
                'required',
                Rule::in(['site', 'global', 'instansi']),
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

            'instansi_id' => [
                Rule::requiredIf(
                    $request->input('scope') === 'instansi'
                ),
                'nullable',
                'integer',
                'exists:instansis,id',
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

        $instansiId = $scope === 'instansi'
            ? (int) $validated['instansi_id']
            : null;

        [$monthStart, $monthEnd] = $this->makePeriod(
            $year,
            $month
        );

        /*
         * Finalisasi hanya diizinkan untuk bulan yang sudah berakhir.
         */
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
            $instansiId,
            $year,
            $month
        );

        /*
         * Jangan membuat snapshot kedua untuk cakupan
         * dan periode laporan yang sama.
         */
        if (
            SlaReport::where('report_key', $reportKey)->exists()
        ) {
            throw ValidationException::withMessages([
                'finalize' =>
                'Laporan untuk cakupan dan periode ini sudah difinalisasi.',
            ]);
        }

        $customer = Pelanggan::findOrFail($pelangganId);

        $site = null;
        $instansi = null;

        /*
         * Validasi kepemilikan site terhadap pelanggan.
         */
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

        /*
         * Validasi instansi dan pastikan mempunyai site
         * pada pelanggan yang dipilih.
         */
        if ($scope === 'instansi') {
            $instansi = Instansi::query()
                ->whereKey($instansiId)
                ->first();

            $hasSites = OnlineBilling::query()
                ->where('pelanggan_id', $pelangganId)
                ->where('instansi_id', $instansiId)
                ->exists();

            if (!$instansi || !$hasSites) {
                throw ValidationException::withMessages([
                    'instansi_id' =>
                    'Instansi tidak ditemukan atau tidak memiliki site '
                        . 'pada pelanggan yang dipilih.',
                ]);
            }
        }

        try {
            /*
             * Jangan mempercayai hasil yang dikirim browser.
             * Seluruh perhitungan dilakukan kembali di server.
             */
            if ($scope === 'site') {
                $result = $slaService->calculateSite(
                    $pelangganId,
                    (int) $site->id,
                    $monthStart,
                    $monthEnd
                );
            } elseif ($scope === 'instansi') {
                $result = $slaService->calculateInstansi(
                    $pelangganId,
                    (int) $instansiId,
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

        /*
         * Untuk global dan instansi, layanan terdiri dari
         * beberapa site. Method calculateInstansi() harus
         * menghasilkan rincian pending incident yang konsisten.
         */
        $pendingCount = $scope === 'site'
            ? (int) ($result['pending_incidents_count'] ?? 0)
            : (int) (
                $result['pending_site_incidents_count']
                ?? $result['pending_incidents_count']
                ?? 0
            );

        if ($pendingCount > 0) {
            throw ValidationException::withMessages([
                'finalize' =>
                "Masih ada {$pendingCount} insiden downtime "
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
         * Snapshot menyimpan hasil lengkap agar laporan resmi
         * tidak berubah ketika data operasional diperbarui.
         */
        $snapshot = [
            'generated_at' => now()->toIso8601String(),

            'scope' => $scope,

            'customer' => [
                'id' => $customer->id,
                'name' => $customer->nama_pelanggan,
            ],

            'instansi' => $instansi
                ? [
                    'id' => $instansi->id,
                    'name' => $instansi->nama_instansi,
                ]
                : null,

            'site' => $site
                ? [
                    'id' => $site->id,
                    'name' => $site->nama_site,
                    'no_jaringan' => $site->no_jaringan,
                ]
                : null,

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

                'instansi_id' => $instansiId,
                'instansi_name' => $instansi?->nama_instansi,

                'month' => $month,
                'year' => $year,

                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->subDay()->toDateString(),

                'target_sla_percentage' => $target,
                'availability_percentage' => $availability,

                'downtime_seconds' => $downtimeSeconds,
                'total_service_seconds' => $totalServiceSeconds,

                'is_sla_met' => $result['is_sla_met'],

                'site_count' => in_array(
                    $scope,
                    ['global', 'instansi'],
                    true
                )
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
                'instansi_id' => $instansiId,
                'month' => $month,
                'year' => $year,
            ])
            ->with(
                'success',
                'Laporan SLA berhasil difinalisasi dan snapshot telah disimpan.'
            );
    }

    /**
     * Membuat rentang periode bulanan.
     *
     * $end adalah awal bulan berikutnya, sehingga sesuai
     * dengan rentang waktu [start, end).
     */
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

    /**
     * Membuat identitas snapshot berdasarkan pelanggan,
     * cakupan, dan periode laporan.
     *
     * Format global dan site dipertahankan agar snapshot lama
     * tetap bisa ditemukan menggunakan report key sebelumnya.
     */
    private function makeReportKey(
        int $pelangganId,
        string $scope,
        ?int $onlineBillingId,
        ?int $instansiId,
        int $year,
        int $month
    ): string {
        $scopeKey = match ($scope) {
            'global' => 'global',

            'site' => 'site-' . $onlineBillingId,

            'instansi' => 'instansi-' . $instansiId,

            default => throw new \InvalidArgumentException(
                'Cakupan laporan SLA tidak valid.'
            ),
        };

        return $pelangganId
            . ':'
            . $scopeKey
            . ':'
            . sprintf('%04d-%02d', $year, $month);
    }

    /**
     * Mencari report key yang sesuai dengan filter pada halaman laporan.
     */
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
            || !in_array(
                $scope,
                ['site', 'global', 'instansi'],
                true
            )
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
        $instansiId = null;

        if ($scope === 'site') {
            $onlineBillingId = $request->integer(
                'online_billing_id'
            );

            if ($onlineBillingId <= 0) {
                return null;
            }
        }

        if ($scope === 'instansi') {
            $instansiId = $request->integer('instansi_id');

            if ($instansiId <= 0) {
                return null;
            }
        }

        return $this->makeReportKey(
            $pelangganId,
            $scope,
            $onlineBillingId,
            $instansiId,
            (int) $year,
            (int) $month
        );
    }

    /**
     * Riwayat semua snapshot laporan SLA.
     */
    public function history(Request $request)
    {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],

            'scope' => [
                'nullable',
                Rule::in(['all', 'global', 'site', 'instansi']),
            ],

            'pelanggan_id' => [
                'nullable',
                'integer',
                'exists:pelanggans,id',
            ],

            'instansi_id' => [
                'nullable',
                'integer',
                'exists:instansis,id',
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
            'instansi_id' => $validated['instansi_id'] ?? '',
            'month' => $validated['month'] ?? '',
            'year' => $validated['year'] ?? '',
        ];

        $reports = SlaReport::query()
            /*
             * Snapshot JSON tidak dimuat pada daftar riwayat.
             * JSON hanya diperlukan saat membuka atau mengunduh detail.
             */
            ->select([
                'id',
                'report_key',
                'pelanggan_id',
                'customer_name',
                'scope',
                'online_billing_id',
                'site_name',
                'instansi_id',
                'instansi_name',
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

            /*
             * Pencarian pelanggan, site, atau instansi.
             */
            ->when(
                $filters['search'] !== '',
                function ($query) use ($filters) {
                    $search = $filters['search'];
                    $keyword = '%' . $search . '%';

                    $query->where(function ($subQuery) use ($keyword) {
                        $subQuery
                            ->where(
                                'customer_name',
                                'like',
                                $keyword
                            )
                            ->orWhere(
                                'site_name',
                                'like',
                                $keyword
                            )
                            ->orWhere(
                                'instansi_name',
                                'like',
                                $keyword
                            );
                    });
                }
            )

            /*
             * Filter cakupan laporan.
             */
            ->when(
                $filters['scope'] !== 'all',
                fn($query) => $query->where(
                    'scope',
                    $filters['scope']
                )
            )

            /*
             * Filter pelanggan.
             */
            ->when(
                $filters['pelanggan_id'] !== '',
                fn($query) => $query->where(
                    'pelanggan_id',
                    $filters['pelanggan_id']
                )
            )

            /*
             * Filter instansi.
             */
            ->when(
                $filters['instansi_id'] !== '',
                fn($query) => $query->where(
                    'instansi_id',
                    $filters['instansi_id']
                )
            )

            /*
             * Filter bulan.
             */
            ->when(
                $filters['month'] !== '',
                fn($query) => $query->where(
                    'month',
                    $filters['month']
                )
            )

            /*
             * Filter tahun.
             */
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
            ->paginate(10)
            ->withQueryString();

        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get([
                'id',
                'nama_pelanggan',
            ]);

        $instansis = Instansi::query()
            ->whereIn(
                'id',
                OnlineBilling::query()
                    ->select('instansi_id')
                    ->whereNotNull('instansi_id')
            )
            ->orderBy('nama_instansi')
            ->get([
                'id',
                'nama_instansi',
            ]);

        return Inertia::render('Sla/Reports/History', [
            'reports' => $reports,
            'customers' => $customers,
            'instansis' => $instansis,
            'filters' => $filters,
            'currentYear' => now()->year,
        ]);
    }

    /**
     * Mengunduh PDF berdasarkan snapshot resmi.
     */
    public function downloadPdf(SlaReport $report)
    {
        $snapshot = $report->snapshot;

        if (
            !is_array($snapshot)
            || !isset($snapshot['result'])
            || !is_array($snapshot['result'])
        ) {
            abort(404, 'Snapshot laporan SLA tidak ditemukan.');
        }

        $result = $snapshot['result'];

        /*
         * Nama instansi digunakan untuk laporan instansi.
         */
        $name = match ($report->scope) {
            'site' => $report->site_name ?: $report->customer_name,

            'instansi' => $report->instansi_name
                ?: $report->customer_name,

            default => $report->customer_name,
        };

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
                in_array(
                    $report->scope,
                    ['global', 'instansi'],
                    true
                )
                    ? 'landscape'
                    : 'portrait'
            )
            ->download($filename);
    }
}

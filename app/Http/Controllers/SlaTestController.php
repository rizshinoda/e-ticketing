<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
use App\Models\Pelanggan;
use App\Services\SlaCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class SlaTestController extends Controller
{
    public function index(
        Request $request,
        SlaCalculationService $slaService
    ) {
        /*
         * Filter halaman pengujian.
         * Target SLA tidak lagi diinput secara manual.
         */
        $pelangganId = $request->integer('pelanggan_id') ?: null;

        $filters = [
            'scope' => $request->input('scope', 'site'),
            'pelanggan_id' => $pelangganId,
            'online_billing_id' => $request->input(
                'online_billing_id'
            ),
            'period_start' => $request->input(
                'period_start',
                now()->startOfMonth()->toDateString()
            ),
            'period_end' => $request->input(
                'period_end',
                now()->toDateString()
            ),
        ];

        /*
         * Ambil daftar pelanggan untuk dropdown.
         */
        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get([
                'id',
                'nama_pelanggan',
            ]);

        /*
         * Ambil site aktif.
         * Jika pelanggan sudah dipilih, tampilkan site
         * milik pelanggan tersebut saja.
         */
        $sites = OnlineBilling::query()
            ->where('status', 'active')
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

        $result = null;
        $calculationError = null;

        /*
         * Perhitungan hanya dilakukan setelah tombol
         * Hitung SLA dikirim.
         */
        if ($request->boolean('calculate')) {
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

                'period_start' => [
                    'required',
                    'date',
                ],

                'period_end' => [
                    'required',
                    'date',
                    'after_or_equal:period_start',
                ],
            ]);

            try {
                /*
                 * Periode laporan menggunakan batas:
                 * [tanggal awal, tanggal akhir eksklusif).
                 *
                 * Karena tanggal akhir dari form inklusif,
                 * tambahkan satu hari untuk batas akhir service.
                 */
                $periodStart = CarbonImmutable::parse(
                    $validated['period_start']
                )->startOfDay();

                $periodEnd = CarbonImmutable::parse(
                    $validated['period_end']
                )->addDay()->startOfDay();

                /*
                 * SLA PER SITE
                 */
                if ($validated['scope'] === 'site') {
                    /*
                     * Pastikan site merupakan milik pelanggan
                     * yang dipilih dan masih aktif.
                     */
                    $site = OnlineBilling::query()
                        ->whereKey(
                            $validated['online_billing_id']
                        )
                        ->where(
                            'pelanggan_id',
                            $validated['pelanggan_id']
                        )
                        ->where('status', 'active')
                        ->firstOrFail();

                    /*
                     * Target SLA dan hasil perhitungan diambil
                     * dari SlaCalculationService.
                     */
                    $result = $slaService->calculateSite(
                        (int) $validated['pelanggan_id'],
                        (int) $site->id,
                        $periodStart,
                        $periodEnd
                    );
                }

                /*
                 * SLA GLOBAL
                 */ else {
                    /*
                     * Service menghitung site-site aktif milik
                     * pelanggan serta mengambil aturan Global.
                     */
                    $result = $slaService->calculateGlobal(
                        (int) $validated['pelanggan_id'],
                        $periodStart,
                        $periodEnd
                    );
                }
            } catch (Throwable $e) {
                report($e);

                $calculationError = app()->environment('local')
                    ? $e->getMessage()
                    : 'Perhitungan SLA gagal. Periksa data dan aturan SLA.';
            }
        }

        /*
         * Kirim data ke halaman Inertia/Vue.
         */
        return Inertia::render('Sla/Test', [
            'customers' => $customers,
            'sites' => $sites,
            'filters' => $filters,
            'result' => $result,
            'calculationError' => $calculationError,
        ]);
    }
}

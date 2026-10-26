<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
use App\Models\Pelanggan;
use App\Models\SlaRule;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SlaRuleController extends Controller
{
    /**
     * Halaman Pengaturan Target SLA.
     * Hanya menampilkan aturan yang dianggap aktif.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');

        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get([
                'id',
                'nama_pelanggan',
            ]);

        $sites = OnlineBilling::query()
            ->orderBy('nama_site')
            ->get([
                'id',
                'pelanggan_id',
                'nama_site',
                'no_jaringan',
            ]);

        $rules = SlaRule::query()
            ->with([
                'pelanggan:id,nama_pelanggan',
                'onlineBilling:id,pelanggan_id,nama_site,no_jaringan',
            ])
            ->whereNull('effective_until')
            ->when($search !== '', function ($query) use ($search) {
                $keyword = '%' . $search . '%';

                $query->where(function ($query) use ($keyword) {
                    $query
                        ->whereHas('pelanggan', function ($customer) use ($keyword) {
                            $customer->where(
                                'nama_pelanggan',
                                'like',
                                $keyword
                            );
                        })
                        ->orWhereHas('onlineBilling', function ($site) use ($keyword) {
                            $site
                                ->where('nama_site', 'like', $keyword)
                                ->orWhere('no_jaringan', 'like', $keyword);
                        })
                        ->orWhere('sla_percentage', 'like', $keyword)
                        ->orWhere('scope', 'like', $keyword);
                });
            })
            // Data terakhir dibuat atau diperbarui tampil paling atas.
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
        return Inertia::render('Sla/Rules/Index', [
            'customers' => $customers,
            'sites' => $sites,
            'rules' => $rules,
            'filters' => [
                'search' => $search,
            ],
            'success' => session('success'),
        ]);
    }
    /**
     * Simpan target SLA Global atau Site.
     * Jika target aktif sudah ada, perbarui target tersebut.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePermanentRule($request);

        DB::transaction(function () use ($data) {
            $this->savePermanentRule(
                pelangganId: $data['pelanggan_id'],
                scope: $data['scope'],
                onlineBillingId: $data['online_billing_id'],
                percentage: $data['sla_percentage'],
            );
        });

        return redirect()
            ->route('sla-rules.index')
            ->with(
                'success',
                'Target SLA berhasil disimpan dan berlaku sampai diperbarui.'
            );
    }

    /**
     * Perbarui aturan SLA yang dipilih.
     */
    public function update(
        Request $request,
        SlaRule $slaRule
    ): RedirectResponse {
        // Aturan lama yang sudah ditutup tidak boleh diedit
        // melalui halaman target aktif.
        if ($slaRule->effective_until !== null) {
            throw ValidationException::withMessages([
                'sla_percentage' =>
                'Aturan ini sudah tidak aktif. Pilih target aktif untuk melakukan perubahan.',
            ]);
        }

        $data = $this->validatePermanentRule($request);

        DB::transaction(function () use ($slaRule, $data) {
            // Cegah dua target aktif dengan kombinasi pelanggan,
            // scope, dan site yang sama.
            $this->ensureNoOtherActiveRule(
                data: $data,
                exceptId: $slaRule->id,
            );

            $slaRule->pelanggan_id = $data['pelanggan_id'];
            $slaRule->scope = $data['scope'];
            $slaRule->online_billing_id =
                $data['online_billing_id'];
            $slaRule->sla_percentage =
                $data['sla_percentage'];

            // Tanggal ini hanya nilai internal untuk skema lama.
            // Resolver SLA tidak lagi menggunakan tanggal ini.
            $slaRule->effective_from = now()->toDateString();
            $slaRule->effective_until = null;

            $slaRule->save();
        });

        return redirect()
            ->route('sla-rules.index')
            ->with(
                'success',
                'Target SLA berhasil diperbarui.'
            );
    }

    /**
     * Hapus target SLA aktif.
     */
    public function destroy(
        SlaRule $slaRule
    ): RedirectResponse {
        if ($slaRule->effective_until !== null) {
            throw ValidationException::withMessages([
                'sla_rule' =>
                'Aturan ini sudah tidak aktif.',
            ]);
        }

        $slaRule->delete();

        return redirect()
            ->route('sla-rules.index')
            ->with(
                'success',
                'Target SLA berhasil dihapus.'
            );
    }

    /**
     * Validasi data target SLA tanpa tanggal berlaku.
     */
    private function validatePermanentRule(
        Request $request
    ): array {
        $data = $request->validate([
            'pelanggan_id' => [
                'required',
                'integer',
                'exists:pelanggans,id',
            ],

            'scope' => [
                'required',
                'in:global,site',
            ],

            'online_billing_id' => [
                'nullable',
                'integer',
                'exists:online_billings,id',
                'required_if:scope,site',
            ],

            'sla_percentage' => [
                'required',
                'numeric',
                'gt:0',
                'lte:100',
            ],
        ]);

        $data['pelanggan_id'] = (int) $data['pelanggan_id'];
        $data['sla_percentage'] =
            (float) $data['sla_percentage'];

        if ($data['scope'] === 'site') {
            $onlineBillingId =
                (int) $data['online_billing_id'];

            $siteBelongsToCustomer = OnlineBilling::query()
                ->whereKey($onlineBillingId)
                ->where(
                    'pelanggan_id',
                    $data['pelanggan_id']
                )
                ->exists();

            if (!$siteBelongsToCustomer) {
                throw ValidationException::withMessages([
                    'online_billing_id' =>
                    'Site yang dipilih bukan milik pelanggan tersebut.',
                ]);
            }

            $data['online_billing_id'] = $onlineBillingId;
        } else {
            // Aturan Global tidak terkait dengan satu site.
            $data['online_billing_id'] = null;
        }

        return $data;
    }

    /**
     * Periksa apakah sudah ada target aktif lain untuk
     * kombinasi pelanggan, scope, dan site yang sama.
     */
    private function ensureNoOtherActiveRule(
        array $data,
        ?int $exceptId = null
    ): void {
        $query = SlaRule::query()
            ->where('pelanggan_id', $data['pelanggan_id'])
            ->where('scope', $data['scope'])
            ->whereNull('effective_until');

        if ($data['scope'] === 'global') {
            $query->whereNull('online_billing_id');
        } else {
            $query->where(
                'online_billing_id',
                $data['online_billing_id']
            );
        }

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            $field = $data['scope'] === 'site'
                ? 'online_billing_id'
                : 'scope';

            throw ValidationException::withMessages([
                $field =>
                'Target aktif untuk pelanggan dan cakupan ini sudah ada. ' .
                    'Silakan edit target yang sudah terdaftar.',
            ]);
        }
    }

    /**
     * Halaman penerapan target ke banyak site.
     */
    public function bulkSites(Request $request): Response
    {
        $validated = $request->validate([
            'pelanggan_id' => [
                'nullable',
                'integer',
                'exists:pelanggans,id',
            ],
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $pelangganId = !empty($validated['pelanggan_id'])
            ? (int) $validated['pelanggan_id']
            : null;

        $search = trim($validated['search'] ?? '');

        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get([
                'id',
                'nama_pelanggan',
            ]);

        $sitesQuery = OnlineBilling::query()
            ->where('status', 'active');

        // Pilih pelanggan terlebih dahulu sebelum memuat daftar site.
        if ($pelangganId !== null) {
            $sitesQuery->where('pelanggan_id', $pelangganId);
        } else {
            $sitesQuery->whereRaw('1 = 0');
        }

        // Pencarian dilakukan di database, bukan di seluruh data browser.
        if ($search !== '') {
            $keyword = '%' . $search . '%';

            $sitesQuery->where(function ($query) use ($keyword) {
                $query
                    ->where('nama_site', 'like', $keyword)
                    ->orWhere('no_jaringan', 'like', $keyword);
            });
        }

        // Target aktif untuk setiap site.
        $activeTargetQuery = SlaRule::query()
            ->select('sla_percentage')
            ->whereColumn(
                'sla_rules.pelanggan_id',
                'online_billings.pelanggan_id'
            )
            ->whereColumn(
                'sla_rules.online_billing_id',
                'online_billings.id'
            )
            ->where('sla_rules.scope', 'site')
            ->whereNull('sla_rules.effective_until')
            ->orderByDesc('sla_rules.id')
            ->limit(1);

        // Jumlah aturan aktif agar duplikasi bisa diketahui dari UI.
        $activeRuleCountQuery = SlaRule::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn(
                'sla_rules.pelanggan_id',
                'online_billings.pelanggan_id'
            )
            ->whereColumn(
                'sla_rules.online_billing_id',
                'online_billings.id'
            )
            ->where('sla_rules.scope', 'site')
            ->whereNull('sla_rules.effective_until');

        $sites = $sitesQuery
            ->select([
                'online_billings.id',
                'online_billings.pelanggan_id',
                'online_billings.nama_site',
                'online_billings.no_jaringan',
            ])
            ->addSelect([
                'active_sla_percentage' => $activeTargetQuery,
                'active_sla_rule_count' => $activeRuleCountQuery,
            ])
            ->orderBy('online_billings.nama_site')
            ->orderBy('online_billings.id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Sla/Rules/BulkSites', [
            'customers' => $customers,
            'sites' => $sites,
            'filters' => [
                'pelanggan_id' => $pelangganId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Terapkan target SLA ke banyak site sekaligus.
     */
    public function storeBulkSites(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'pelanggan_id' => [
                'required',
                'integer',
                'exists:pelanggans,id',
            ],

            'online_billing_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'online_billing_ids.*' => [
                'required',
                'integer',
                'distinct',
            ],

            'sla_percentage' => [
                'required',
                'numeric',
                'gt:0',
                'lte:100',
            ],
        ]);

        $pelangganId = (int) $validated['pelanggan_id'];

        $siteIds = array_map(
            'intval',
            $validated['online_billing_ids']
        );

        $percentage = (float) $validated['sla_percentage'];

        // Pastikan site yang dipilih aktif dan milik pelanggan tersebut.
        $sites = OnlineBilling::query()
            ->where('status', 'active')
            ->where('pelanggan_id', $pelangganId)
            ->whereIn('id', $siteIds)
            ->get(['id']);

        if ($sites->count() !== count($siteIds)) {
            throw ValidationException::withMessages([
                'online_billing_ids' =>
                'Ada site yang tidak aktif, tidak ditemukan, atau bukan milik pelanggan tersebut.',
            ]);
        }

        DB::transaction(function () use (
            $pelangganId,
            $sites,
            $percentage
        ) {
            foreach ($sites as $site) {
                $this->savePermanentRule(
                    pelangganId: $pelangganId,
                    scope: 'site',
                    onlineBillingId: (int) $site->id,
                    percentage: $percentage,
                );
            }
        });

        return redirect()
            ->route('sla-rules.index')
            ->with(
                'success',
                'Target SLA berhasil diterapkan ke ' .
                    $sites->count() . ' site.'
            );
    }

    /**
     * Membuat atau memperbarui satu target permanen.
     *
     * effective_until = null digunakan sebagai penanda internal
     * bahwa aturan tersebut adalah target aktif.
     */
    private function savePermanentRule(
        int $pelangganId,
        string $scope,
        ?int $onlineBillingId,
        float $percentage
    ): SlaRule {
        $query = SlaRule::query()
            ->where('pelanggan_id', $pelangganId)
            ->where('scope', $scope);

        if ($scope === 'global') {
            $query->whereNull('online_billing_id');
        } else {
            $query->where(
                'online_billing_id',
                $onlineBillingId
            );
        }

        // Ambil aturan aktif dan kunci selama transaksi.
        $activeRules = (clone $query)
            ->whereNull('effective_until')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        // Jangan memilih secara sembarang bila database
        // sudah memiliki lebih dari satu target aktif.
        if ($activeRules->count() > 1) {
            throw ValidationException::withMessages([
                'sla_percentage' =>
                'Ditemukan lebih dari satu target SLA aktif untuk ' .
                    'kombinasi pelanggan dan cakupan ini. Periksa dan ' .
                    'rapikan data aturan SLA yang duplikat terlebih dahulu.',
            ]);
        }

        $rule = $activeRules->first();

        if (!$rule) {
            $rule = new SlaRule();

            $rule->pelanggan_id = $pelangganId;
            $rule->scope = $scope;
            $rule->online_billing_id = $onlineBillingId;
        }

        $rule->sla_percentage = $percentage;

        // Nilai internal untuk kompatibilitas dengan skema database lama.
        // Nilai effective_from tidak digunakan untuk menentukan target laporan.
        $rule->effective_from = now()->toDateString();
        $rule->effective_until = null;

        $rule->save();

        return $rule;
    }
}

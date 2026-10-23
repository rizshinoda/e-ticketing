<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
use App\Models\Pelanggan;
use App\Models\SlaRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SlaRuleController extends Controller
{
    public function index(): Response
    {
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
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Sla/Rules/Index', [
            'customers' => $customers,
            'sites' => $sites,
            'rules' => $rules,
            'today' => now()->toDateString(),
            'success' => session('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRule($request);

        SlaRule::create($data);

        return redirect()
            ->route('sla-rules.index')
            ->with('success', 'Aturan SLA berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        SlaRule $slaRule
    ): RedirectResponse {
        $data = $this->validateRule(
            $request,
            $slaRule->id
        );

        $slaRule->update($data);

        return redirect()
            ->route('sla-rules.index')
            ->with('success', 'Aturan SLA berhasil diperbarui.');
    }

    public function destroy(SlaRule $slaRule): RedirectResponse
    {
        $slaRule->delete();

        return redirect()
            ->route('sla-rules.index')
            ->with('success', 'Aturan SLA berhasil dihapus.');
    }

    private function validateRule(
        Request $request,
        ?int $exceptId = null
    ): array {
        $data = $request->validate([
            'pelanggan_id' => [
                'required',
                'integer',
                'exists:pelanggans,id',
            ],

            'scope' => [
                'required',
                Rule::in(['global', 'site']),
            ],

            'online_billing_id' => [
                Rule::requiredIf(
                    $request->input('scope') === 'site'
                ),
                'nullable',
                'integer',
                'exists:online_billings,id',
            ],

            'sla_percentage' => [
                'required',
                'numeric',
                'gt:0',
                'lte:100',
            ],

            'effective_from' => [
                'required',
                'date',
            ],

            'effective_until' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],
        ]);

        if ($data['scope'] === 'site') {
            $siteBelongsToCustomer = OnlineBilling::query()
                ->whereKey($data['online_billing_id'])
                ->where('pelanggan_id', $data['pelanggan_id'])
                ->exists();

            if (!$siteBelongsToCustomer) {
                throw ValidationException::withMessages([
                    'online_billing_id' =>
                    'Site yang dipilih bukan milik pelanggan tersebut.',
                ]);
            }
        } else {
            // Aturan Global tidak terkait dengan satu site.
            $data['online_billing_id'] = null;
        }

        $this->ensureNoOverlappingRule($data, $exceptId);

        return $data;
    }

    private function ensureNoOverlappingRule(
        array $data,
        ?int $exceptId = null
    ): void {
        $query = SlaRule::query()
            ->where('pelanggan_id', $data['pelanggan_id'])
            ->where('scope', $data['scope']);

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

        // Jika tanggal akhir diisi, aturan lama harus dimulai
        // sebelum atau pada tanggal akhir aturan baru.
        if (!empty($data['effective_until'])) {
            $query->whereDate(
                'effective_from',
                '<=',
                $data['effective_until']
            );
        }

        // Aturan lama harus belum berakhir atau berakhir
        // pada atau setelah tanggal mulai aturan baru.
        $query->where(function ($q) use ($data) {
            $q->whereNull('effective_until')
                ->orWhereDate(
                    'effective_until',
                    '>=',
                    $data['effective_from']
                );
        });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_from' =>
                'Periode aturan SLA bertumpang tindih dengan aturan '
                    . 'lain untuk pelanggan dan cakupan yang sama.',
            ]);
        }
    }

    public function bulkSites()
    {
        $customers = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get(['id', 'nama_pelanggan']);

        $sites = OnlineBilling::query()
            ->where('status', 'active')
            ->orderBy('nama_site')
            ->get([
                'id',
                'pelanggan_id',
                'nama_site',
                'no_jaringan',
            ]);

        return Inertia::render('Sla/Rules/BulkSites', [
            'customers' => $customers,
            'sites' => $sites,
            'today' => now()->toDateString(),
        ]);
    }

    public function storeBulkSites(Request $request)
    {
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
                'exists:online_billings,id',
            ],

            'sla_percentage' => [
                'required',
                'numeric',
                'gt:0',
                'lte:100',
            ],

            'effective_from' => [
                'required',
                'date_format:Y-m-d',
            ],
        ]);

        $pelangganId = (int) $validated['pelanggan_id'];

        $siteIds = collect($validated['online_billing_ids'])
            ->map(fn($id) => (int) $id)
            ->values();

        // Pastikan seluruh site aktif dan milik pelanggan yang dipilih.
        $sites = OnlineBilling::query()
            ->where('pelanggan_id', $pelangganId)
            ->where('status', 'active')
            ->whereIn('id', $siteIds)
            ->orderBy('id')
            ->get(['id', 'nama_site']);

        if ($sites->count() !== $siteIds->count()) {
            throw ValidationException::withMessages([
                'online_billing_ids' =>
                'Pastikan semua site yang dipilih aktif dan milik pelanggan yang sama.',
            ]);
        }

        $effectiveFrom = CarbonImmutable::parse(
            $validated['effective_from']
        )->startOfDay();

        DB::transaction(function () use (
            $pelangganId,
            $sites,
            $siteIds,
            $effectiveFrom,
            $validated
        ) {
            // Periksa seluruh jadwal sebelum mengubah aturan mana pun.
            $existingRules = SlaRule::query()
                ->where('pelanggan_id', $pelangganId)
                ->where('scope', 'site')
                ->whereIn('online_billing_id', $siteIds)
                ->orderBy('effective_from')
                ->get();

            foreach ($sites as $site) {
                $siteRules = $existingRules->where(
                    'online_billing_id',
                    $site->id
                );

                // Jadwal yang dimulai pada atau setelah tanggal baru
                // tidak ditimpa otomatis.
                $futureRule = $siteRules->first(
                    fn($rule) => CarbonImmutable::parse(
                        $rule->effective_from
                    )->startOfDay()->greaterThanOrEqualTo($effectiveFrom)
                );

                if ($futureRule) {
                    throw ValidationException::withMessages([
                        'effective_from' =>
                        "Site {$site->nama_site} sudah memiliki aturan "
                            . 'yang mulai pada atau setelah tanggal tersebut. '
                            . 'Periksa jadwal aturan SLA site itu terlebih dahulu.',
                    ]);
                }

                // Aturan sebelum tanggal baru yang masih mencakup tanggal
                // baru akan diakhiri sehari sebelumnya.
                $overlappingRules = $siteRules->filter(
                    function ($rule) use ($effectiveFrom) {
                        $ruleStart = CarbonImmutable::parse(
                            $rule->effective_from
                        )->startOfDay();

                        $ruleEnd = $rule->effective_until !== null
                            ? CarbonImmutable::parse(
                                $rule->effective_until
                            )->endOfDay()
                            : null;

                        return $ruleStart->lessThan($effectiveFrom)
                            && (
                                $ruleEnd === null
                                || $ruleEnd->greaterThanOrEqualTo($effectiveFrom)
                            );
                    }
                );

                if ($overlappingRules->count() > 1) {
                    throw ValidationException::withMessages([
                        'effective_from' =>
                        "Jadwal aturan SLA untuk site {$site->nama_site} "
                            . 'saling tumpang tindih. Periksa aturan yang sudah ada.',
                    ]);
                }
            }

            foreach ($sites as $site) {
                $currentRule = SlaRule::query()
                    ->where('pelanggan_id', $pelangganId)
                    ->where('scope', 'site')
                    ->where('online_billing_id', $site->id)
                    ->whereDate(
                        'effective_from',
                        '<',
                        $effectiveFrom->toDateString()
                    )
                    ->where(function ($query) use ($effectiveFrom) {
                        $query->whereNull('effective_until')
                            ->orWhereDate(
                                'effective_until',
                                '>=',
                                $effectiveFrom->toDateString()
                            );
                    })
                    ->orderByDesc('effective_from')
                    ->first();

                if ($currentRule) {
                    $currentRule->update([
                        'effective_until' => $effectiveFrom
                            ->subDay()
                            ->toDateString(),
                    ]);
                }

                SlaRule::create([
                    'pelanggan_id' => $pelangganId,
                    'scope' => 'site',
                    'online_billing_id' => $site->id,
                    'sla_percentage' => $validated['sla_percentage'],
                    'effective_from' => $effectiveFrom->toDateString(),
                    'effective_until' => null,
                ]);
            }
        });

        return redirect()
            ->route('sla-rules.index')
            ->with(
                'success',
                "Target SLA {$validated['sla_percentage']}% berhasil diterapkan "
                    . "ke {$sites->count()} site."
            );
    }
}

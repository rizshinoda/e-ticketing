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
}

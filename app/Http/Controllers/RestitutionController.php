<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RestitutionController extends Controller
{
    public function index()
    {
        $pelanggans = Pelanggan::orderBy('nama_pelanggan')->get();

        return Inertia::render('Restitution/Index', [
            'pelanggans' => $pelanggans,
        ]);
    }
    public function sites(Pelanggan $pelanggan)
    {
        $sites = $pelanggan->onlineBillings()
            ->select([
                'id',
                'nama_site',
                'harga_sewa',
            ])
            ->get();

        return response()->json($sites);
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'pelanggan_id' => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer'],
        ]);

        dd($validated);
    }
}

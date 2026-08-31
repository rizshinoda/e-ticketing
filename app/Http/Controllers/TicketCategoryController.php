<?php

namespace App\Http\Controllers;

use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketCategoryController extends Controller
{
    /**
     * Menampilkan daftar kendala.
     */
    public function index()
    {
        $categories = TicketCategory::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('Master/Kendala/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Menyimpan kendala baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:ticket_categories,name',
            ],
            'is_downtime' => [
                'boolean',
            ],
        ]);

        TicketCategory::create([
            'name' => $validated['name'],
            'is_downtime' => $validated['is_downtime'] ?? false,
        ]);

        return redirect()
            ->route('master.kendala.index')
            ->with('success', 'Kendala berhasil ditambahkan.');
    }

    /**
     * Update kendala.
     */
    public function update(
        Request $request,
        TicketCategory $kendala
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:ticket_categories,name,' . $kendala->id,
            ],
            'is_downtime' => [
                'boolean',
            ],
        ]);

        $kendala->update([
            'name' => $validated['name'],
            'is_downtime' => $validated['is_downtime'] ?? false,
        ]);

        return redirect()
            ->route('master.kendala.index')
            ->with('success', 'Kendala berhasil diperbarui.');
    }

    /**
     * Hapus kendala.
     */
    public function destroy(TicketCategory $kendala)
    {
        $kendala->delete();

        return redirect()
            ->route('master.kendala.index')
            ->with('success', 'Kendala berhasil dihapus.');
    }
}

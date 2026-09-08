<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Daftar ticket.
     */
    public function index(): Response
    {
        $tickets = Ticket::query()
            ->with([
                'creator',
                'customers',
            ])
            ->latest()
            ->paginate(20);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
        ]);
    }
    public function searchOnlineBillings(Request $request)
    {
        $search = trim($request->input('search', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $onlineBillings = OnlineBilling::query()
            ->with('pelanggan')
            ->where('status', 'active')
            ->where(function ($query) use ($search) {
                $query
                    ->where('nama_site', 'like', "%{$search}%")
                    ->orWhere('no_jaringan', 'like', "%{$search}%")
                    ->orWhere('layanan', 'like', "%{$search}%")
                    ->orWhereHas('pelanggan', function ($q) use ($search) {
                        $q->where(
                            'nama_pelanggan',
                            'like',
                            "%{$search}%"
                        );
                    });
            })
            ->limit(20)
            ->get();

        return response()->json(
            $onlineBillings->map(function ($billing) {
                return [
                    'id' => $billing->id,
                    'customer_name' => $billing->pelanggan?->nama_pelanggan,
                    'site_name' => $billing->nama_site,
                    'no_jaringan' => $billing->no_jaringan,
                    'layanan' => $billing->layanan,
                    'bandwidth' => $billing->bandwidth,
                ];
            })
        );
    }
    /**
     * Form membuat ticket.
     */
    public function create(Request $request): Response
    {
        $categories = TicketCategory::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'is_downtime',
            ]);

        $search = trim($request->input('search', ''));

        $onlineBillings = collect();

        if ($search !== '') {
            $onlineBillings = OnlineBilling::query()
                ->with('pelanggan')
                ->where('status', 'active')
                ->where(function ($query) use ($search) {
                    $query->where(
                        'nama_site',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'no_jaringan',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'layanan',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'pelanggan',
                            function ($q) use ($search) {
                                $q->where(
                                    'nama_pelanggan',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                })
                ->limit(20)
                ->get([
                    'id',
                    'pelanggan_id',
                    'nama_site',
                    'no_jaringan',
                    'layanan',
                    'bandwidth',
                ]);
        }

        return Inertia::render('Tickets/Create', [
            'categories' => $categories,

            'onlineBillings' => $onlineBillings
                ->map(function ($billing) {
                    return [
                        'id' => $billing->id,

                        'customer_name' =>
                        $billing->pelanggan?->nama_pelanggan,

                        'site_name' =>
                        $billing->nama_site,

                        'no_jaringan' =>
                        $billing->no_jaringan,

                        'layanan' =>
                        $billing->layanan,

                        'bandwidth' =>
                        $billing->bandwidth,
                    ];
                })
                ->values(),

            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Simpan ticket baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticket_type' => [
                'required',
                'in:individual,gamas',
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

            'kendala_id' => [
                'required',
                'exists:ticket_categories,id',
            ],

            'reported_via' => [
                'nullable',
                'string',
                'max:255',
            ],

            'priority' => [
                'required',
                'in:low,medium,high,critical',
            ],

            'reported_at' => [
                'required',
                'date',
            ],

            'description' => [
                'required',
                'string',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Ambil Online Billing
    |--------------------------------------------------------------------------
    */

        $onlineBillings = OnlineBilling::query()
            ->with('pelanggan')
            ->where('status', 'active')
            ->whereIn(
                'id',
                $validated['online_billing_ids']
            )
            ->get();

        /*
    |--------------------------------------------------------------------------
    | Pastikan semua Online Billing valid dan aktif
    |--------------------------------------------------------------------------
    */

        if (
            $onlineBillings->count() !==
            count($validated['online_billing_ids'])
        ) {
            return back()
                ->withErrors([
                    'online_billing_ids' =>
                    'Salah satu Online Billing tidak ditemukan atau sudah tidak aktif.',
                ])
                ->withInput();
        }

        /*
    |--------------------------------------------------------------------------
    | Individual hanya boleh 1 site
    |--------------------------------------------------------------------------
    */

        if (
            $validated['ticket_type'] === 'individual' &&
            $onlineBillings->count() !== 1
        ) {
            return back()
                ->withErrors([
                    'online_billing_ids' =>
                    'Ticket individual hanya dapat memiliki satu site.',
                ])
                ->withInput();
        }

        /*
    |--------------------------------------------------------------------------
    | Buat Ticket
    |--------------------------------------------------------------------------
    */

        $ticket = DB::transaction(function () use (
            $validated,
            $onlineBillings
        ) {

            /*
        |--------------------------------------------------------------------------
        | Generate nomor ticket
        |--------------------------------------------------------------------------
        */

            $ticketNumber = 'TCK-' . now()->format('YmdHis');

            /*
        |--------------------------------------------------------------------------
        | 1. CREATE TICKET
        |--------------------------------------------------------------------------
        */

            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'ticket_type' => $validated['ticket_type'],
                'description' => $validated['description'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'created_by' => Auth::id(),
                'reported_at' => $validated['reported_at'],
            ]);

            /*
        |--------------------------------------------------------------------------
        | 2. CREATE CUSTOMER / SITE
        |--------------------------------------------------------------------------
        */

            foreach ($onlineBillings as $onlineBilling) {

                $ticketCustomer = $ticket->customers()->create([
                    /*
                 * Referensi Online Billing
                 */
                    'online_billing_id' => $onlineBilling->id,

                    /*
                 * Snapshot customer
                 */
                    'customer_name' =>
                    $onlineBilling->pelanggan?->nama_pelanggan,

                    /*
                 * Snapshot site
                 */
                    'site_name' =>
                    $onlineBilling->nama_site,

                    /*
                 * Snapshot nomor jaringan
                 */
                    'no_jaringan' =>
                    $onlineBilling->no_jaringan,

                    /*
                 * Cara laporan
                 */
                    'reported_via' =>
                    $validated['reported_via'] ?? null,
                ]);

                /*
            |--------------------------------------------------------------------------
            | 3. CREATE INCIDENT PERTAMA
            |--------------------------------------------------------------------------
            */

                $ticketCustomer->incidents()->create([
                    'incident_number' => 1,

                    'kendala_id' =>
                    $validated['kendala_id'],

                    'reported_at' =>
                    $validated['reported_at'],
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | 4. HISTORY AWAL TICKET
        |--------------------------------------------------------------------------
        */

            $ticket->updates()->create([
                'user_id' => Auth::id(),

                'message' => 'Ticket dibuat.',
            ]);

            /*
        |--------------------------------------------------------------------------
        | Return ticket dari transaction
        |--------------------------------------------------------------------------
        */

            return $ticket;
        });

        /*
    |--------------------------------------------------------------------------
    | Redirect ke detail ticket
    |--------------------------------------------------------------------------
    */

        return to_route(
            'tickets.show',
            $ticket
        )->with(
            'success',
            'Ticket berhasil dibuat.'
        );
    }

    /**
     * Detail ticket.
     */
    public function show(Ticket $ticket): Response
    {
        $ticket->load([
            'creator',
            'resolver',
            'closer',

            'customers.onlineBilling',
            'customers.incidents.category',

            'stopClocks',
            'rfos',

            'updates.user',
            'updates.attachments',
        ]);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }



    public function storeUpdate(
        Request $request,
        Ticket $ticket
    ) {
        $validated = $request->validate([
            'message' => [
                'required',
                'string',
            ],

            'attachments' => [
                'nullable',
                'array',
            ],

            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        DB::transaction(function () use (
            $validated,
            $request,
            $ticket
        ) {

            /*
        |--------------------------------------------------------------------------
        | Buat Update
        |--------------------------------------------------------------------------
        */

            $update = $ticket->updates()->create([
                'user_id' => Auth::id(),

                'message' =>
                $validated['message'],
            ]);


            /*
        |--------------------------------------------------------------------------
        | First Response
        |--------------------------------------------------------------------------
        |
        | Update pertama dianggap sebagai response
        | pertama Support.
        |
        */

            if (!$ticket->first_response_at) {

                $firstResponseAt = now();

                /*
            |--------------------------------------------------------------------------
            | Update Ticket
            |--------------------------------------------------------------------------
            */

                $ticket->update([
                    'first_response_at' =>
                    $firstResponseAt,

                    'status' =>
                    'on_progress',
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | Upload Attachment
        |--------------------------------------------------------------------------
        */

            if (
                $request->hasFile(
                    'attachments'
                )
            ) {

                foreach (
                    $request->file(
                        'attachments'
                    ) as $file
                ) {

                    $fileName =
                        time()
                        . '_'
                        . uniqid()
                        . '_'
                        . $file->getClientOriginalName();


                    $path = $file->storeAs(
                        'tickets/'
                            . $ticket->id
                            . '/updates',

                        $fileName,

                        'public'
                    );


                    $update
                        ->attachments()
                        ->create([
                            'file_path' =>
                            $path,

                            'file_name' =>
                            $file
                                ->getClientOriginalName(),

                            'mime_type' =>
                            $file
                                ->getClientMimeType(),

                            'file_size' =>
                            $file
                                ->getSize(),
                        ]);
                }
            }
        });


        return back()->with(
            'success',
            'Update berhasil ditambahkan.'
        );
    }

    public function startStopClock(
        Request $request,
        Ticket $ticket,
    ) {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);


        /*
    |--------------------------------------------------------------------------
    | Ticket harus sedang On Progress
    |--------------------------------------------------------------------------
    */

        if ($ticket->status !== 'on_progress') {
            return back()->withErrors([
                'stop_clock' =>
                'Stop Clock hanya dapat digunakan ketika ticket sedang On Progress.',
            ]);
        }



        /*
    |--------------------------------------------------------------------------
    | Cek apakah sudah ada Stop Clock aktif
    |--------------------------------------------------------------------------
    */

        $activeStopClock = $ticket
            ->stopClocks()
            ->whereNull('ended_at')
            ->exists();
        if ($activeStopClock) {
            return back()->withErrors([
                'stop_clock' =>
                'Ticket sedang dalam kondisi Stop Clock.',
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Buat Stop Clock
    |--------------------------------------------------------------------------
    */

        $ticket->stopClocks()->create([
            'started_at' => now(),
            'reason' => $validated['reason'],
            'started_by' => Auth::id(),
        ]);

        return back()->with(
            'success',
            'Stop Clock berhasil dimulai.'
        );
    }

    public function endStopClock(
        Ticket $ticket
    ) {
        /*
    |--------------------------------------------------------------------------
    | Cari Stop Clock aktif
    |--------------------------------------------------------------------------
    */

        $stopClock = $ticket
            ->stopClocks()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (!$stopClock) {
            return back()->withErrors([
                'stop_clock' =>
                'Tidak ada Stop Clock yang sedang aktif.',
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Akhiri Stop Clock
    |--------------------------------------------------------------------------
    */

        $stopClock->update([
            'ended_at' => now(),
            'ended_by' => Auth::id(),
        ]);

        return back()->with(
            'success',
            'Stop Clock berhasil dihentikan.'
        );
    }

    public function resolve(Ticket $ticket)
    {
        if ($ticket->status !== 'on_progress') {
            return back()->withErrors([
                'resolve' => 'Ticket hanya dapat di-resolve ketika status On Progress.',
            ]);
        }

        return back()->with(
            'success',
            'Validasi resolve berhasil.'
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
use App\Models\Pelanggan;
use App\Models\Rfo;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Daftar ticket.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $tickets = Ticket::query()
            ->with(['creator', 'customers'])
            ->when(
                $status,
                fn($query) => $query->where('status', $status)
            )
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where(
                        'ticket_number',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhereHas('customers', function ($query) use ($search) {
                            $query->where(
                                'customer_name',
                                'like',
                                "%{$search}%"
                            )
                                ->orWhere(
                                    'site_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'no_jaringan',
                                    'like',
                                    "%{$search}%"
                                );
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $ticketStats = [
            'total' => Ticket::count(),

            'open' => Ticket::where(
                'status',
                'open'
            )->count(),

            'on_progress' => Ticket::where(
                'status',
                'on_progress'
            )->count(),

            'resolved' => Ticket::where(
                'status',
                'resolved'
            )->count(),

            'closed' => Ticket::where(
                'status',
                'closed'
            )->count(),
        ];

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,

            'ticketStats' => $ticketStats,

            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
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
        $pelanggans = Pelanggan::query()
            ->orderBy('nama_pelanggan')
            ->get([
                'id',
                'nama_pelanggan',
            ]);
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
            'pelanggans' => $pelanggans,
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
        /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            /*
        |--------------------------------------------------------------------------
        | TICKET TYPE
        |--------------------------------------------------------------------------
        */

            'ticket_type' => [
                'required',
                'in:individual,gamas',
            ],

            /*
        |--------------------------------------------------------------------------
        | SUMBER CUSTOMER
        |--------------------------------------------------------------------------
        |
        | online_billing = customer/site sudah ada di Online Billing.
        | manual          = customer/site belum masuk Online Billing.
        |
        */

            'customer_source' => [
                'required',
                'in:online_billing,manual',
            ],

            /*
        |--------------------------------------------------------------------------
        | ONLINE BILLING
        |--------------------------------------------------------------------------
        |
        | Hanya digunakan jika customer_source = online_billing.
        |
        */

            'online_billing_ids' => [
                'exclude_unless:customer_source,online_billing',
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

            /*
        |--------------------------------------------------------------------------
        | CUSTOMER MANUAL
        |--------------------------------------------------------------------------
        |
        | Customer dipilih dari Master Pelanggan.
        |
        */

            'pelanggan_id' => [
                'required_if:customer_source,manual',
                'nullable',
                'integer',
                'exists:pelanggans,id',
            ],

            /*
        |--------------------------------------------------------------------------
        | CUSTOMER NAME
        |--------------------------------------------------------------------------
        |
        | Tidak lagi menjadi sumber utama customer.
        | Nama customer akan diambil dari Master Pelanggan
        | berdasarkan pelanggan_id.
        |
        */

            'customer_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
        |--------------------------------------------------------------------------
        | MANUAL SITES
        |--------------------------------------------------------------------------
        |
        | Individual:
        |   1 site.
        |
        | GAMAS:
        |   dapat memiliki banyak site.
        |
        */

            'manual_sites' => [
                'exclude_unless:customer_source,manual',
                'required',
                'array',
                'min:1',
            ],

            'manual_sites.*.site_name' => [
                'required',
                'string',
                'max:255',
            ],

            'manual_sites.*.no_jaringan' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
        |--------------------------------------------------------------------------
        | INCIDENT
        |--------------------------------------------------------------------------
        */

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

            'report_type' => [
                'required',
                'in:current,historical',
            ],

            'incident_reported_at' => [
                'required_if:report_type,historical',
                'nullable',
                'date',
            ],

            'incident_resolved_at' => [
                'required_if:report_type,historical',
                'nullable',
                'date',
                'after:incident_reported_at',
            ],

            'description' => [
                'required',
                'string',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | VALIDASI JUMLAH SITE MANUAL UNTUK INDIVIDUAL
    |--------------------------------------------------------------------------
    |
    | Individual hanya boleh mempunyai 1 site.
    |
    */

        if (
            $validated['customer_source'] === 'manual' &&
            $validated['ticket_type'] === 'individual' &&
            count($validated['manual_sites']) !== 1
        ) {
            return back()
                ->withErrors([
                    'manual_sites' =>
                    'Ticket individual hanya dapat memiliki satu site.',
                ])
                ->withInput();
        }

        /*
    |--------------------------------------------------------------------------
    | AMBIL CUSTOMER DARI MASTER PELANGGAN
    |--------------------------------------------------------------------------
    |
    | Hanya dilakukan jika customer_source = manual.
    |
    */

        $pelanggan = null;

        if ($validated['customer_source'] === 'manual') {
            $pelanggan = Pelanggan::findOrFail(
                $validated['pelanggan_id']
            );
        }

        /*
    |--------------------------------------------------------------------------
    | AMBIL ONLINE BILLING
    |--------------------------------------------------------------------------
    |
    | Hanya dijalankan jika customer berasal dari Online Billing.
    |
    */

        $onlineBillings = collect();

        if ($validated['customer_source'] === 'online_billing') {

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
        }

        /*
    |--------------------------------------------------------------------------
    | INDIVIDUAL HANYA BOLEH 1 SITE
    |--------------------------------------------------------------------------
    */

        if (
            $validated['ticket_type'] === 'individual' &&
            $validated['customer_source'] === 'online_billing' &&
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
    | GAMAS + ONLINE BILLING
    |--------------------------------------------------------------------------
    |
    | Satu GAMAS hanya boleh mempunyai satu customer,
    | tetapi dapat mempunyai banyak site.
    |
    */

        if (
            $validated['ticket_type'] === 'gamas' &&
            $validated['customer_source'] === 'online_billing'
        ) {

            $customerIds = $onlineBillings
                ->pluck('pelanggan_id')
                ->filter()
                ->unique();

            /*
        |--------------------------------------------------------------------------
        | Semua site GAMAS harus berasal dari customer yang sama
        |--------------------------------------------------------------------------
        */

            if ($customerIds->count() !== 1) {
                return back()
                    ->withErrors([
                        'online_billing_ids' =>
                        'Ticket GAMAS hanya dapat memiliki site dari customer yang sama.',
                    ])
                    ->withInput();
            }
        }

        /*
    |--------------------------------------------------------------------------
    | BUAT TICKET
    |--------------------------------------------------------------------------
    */

        $ticket = DB::transaction(function () use (
            $validated,
            $onlineBillings,
            $pelanggan
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
                'ticket_number' =>
                $ticketNumber,

                'ticket_type' =>
                $validated['ticket_type'],

                'description' =>
                $validated['description'],

                'priority' =>
                $validated['priority'],

                'status' =>
                'open',

                'created_by' =>
                Auth::id(),

                'reported_at' =>
                $validated['reported_at'],
            ]);

            /*
        |--------------------------------------------------------------------------
        | 2. CREATE CUSTOMER / SITE
        |--------------------------------------------------------------------------
        */

            if ($validated['customer_source'] === 'online_billing') {

                /*
            |--------------------------------------------------------------------------
            | CUSTOMER DARI ONLINE BILLING
            |--------------------------------------------------------------------------
            */

                foreach ($onlineBillings as $onlineBilling) {

                    $ticket->customers()->create([
                        /*
                     * Customer ID dari Online Billing.
                     */
                        'pelanggan_id' =>
                        $onlineBilling->pelanggan_id,

                        /*
                     * Referensi Online Billing.
                     */
                        'online_billing_id' =>
                        $onlineBilling->id,

                        /*
                     * Snapshot nama customer.
                     */
                        'customer_name' =>
                        $onlineBilling->pelanggan?->nama_pelanggan,

                        /*
                     * Snapshot site.
                     */
                        'site_name' =>
                        $onlineBilling->nama_site,

                        /*
                     * Snapshot no jaringan.
                     */
                        'no_jaringan' =>
                        $onlineBilling->no_jaringan,

                        /*
                     * Media/tempat customer melaporkan.
                     */
                        'reported_via' =>
                        $validated['reported_via'] ?? null,
                    ]);
                }
            } else {

                /*
            |--------------------------------------------------------------------------
            | CUSTOMER MANUAL
            |--------------------------------------------------------------------------
            |
            | Customer berasal dari Master Pelanggan.
            |
            */

                foreach ($validated['manual_sites'] as $site) {

                    $ticket->customers()->create([

                        /*
                     * ID customer sebenarnya.
                     */
                        'pelanggan_id' =>
                        $pelanggan->id,

                        /*
                     * Belum ada Online Billing.
                     */
                        'online_billing_id' =>
                        null,

                        /*
                     * Snapshot nama customer.
                     */
                        'customer_name' =>
                        $pelanggan->nama_pelanggan,

                        /*
                     * Nama site manual.
                     */
                        'site_name' =>
                        $site['site_name'],

                        /*
                     * No jaringan manual.
                     */
                        'no_jaringan' =>
                        $site['no_jaringan'] ?? null,

                        /*
                     * Media/tempat customer melaporkan.
                     */
                        'reported_via' =>
                        $validated['reported_via'] ?? null,
                    ]);
                }
            }

            /*
        |--------------------------------------------------------------------------
        | 3. CREATE INCIDENT PERTAMA
        |--------------------------------------------------------------------------
        */

            $ticket->incidents()->create([

                'incident_number' =>
                1,

                'kendala_id' =>
                $validated['kendala_id'],

                'reported_at' =>
                $validated['report_type'] === 'historical'
                    ? $validated['incident_reported_at']
                    : $validated['reported_at'],

                'resolved_at' =>
                $validated['report_type'] === 'historical'
                    ? $validated['incident_resolved_at']
                    : null,
            ]);

            /*
        |--------------------------------------------------------------------------
        | 4. HISTORY AWAL TICKET
        |--------------------------------------------------------------------------
        */

            $ticket->updates()->create([

                'user_id' =>
                Auth::id(),

                'message' =>
                'Ticket dibuat.',
            ]);

            /*
        |--------------------------------------------------------------------------
        | RETURN TICKET
        |--------------------------------------------------------------------------
        */

            return $ticket;
        });

        /*
    |--------------------------------------------------------------------------
    | REDIRECT KE DETAIL TICKET
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
        /*
    |--------------------------------------------------------------------------
    | LOAD DATA TICKET
    |--------------------------------------------------------------------------
    */

        $ticket->load([
            'creator',
            'resolver',
            'closer',

            /*
         * Customer/site ticket.
         *
         * onlineBilling tetap di-load karena
         * Show.vue masih membutuhkan data Online Billing
         * jika site berasal dari Online Billing.
         */
            'customers.pelanggan',
            'customers.onlineBilling',

            'incidents.category',
            'stopClocks',
            'rfos.creator',
            'updates.user',
            'updates.attachments',
        ]);

        /*
    |--------------------------------------------------------------------------
    | KATEGORI KENDALA
    |--------------------------------------------------------------------------
    */

        $categories = TicketCategory::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'is_downtime',
            ]);

        /*
    |--------------------------------------------------------------------------
    | SITE YANG TERSEDIA UNTUK GAMAS
    |--------------------------------------------------------------------------
    */

        $availableSites = collect();

        if ($ticket->ticket_type === 'gamas') {

            /*
        |--------------------------------------------------------------------------
        | AMBIL CUSTOMER UTAMA GAMAS
        |--------------------------------------------------------------------------
        |
        | Sekarang pelanggan_id berada langsung di
        | ticket_customers.
        |
        | Jadi tidak peduli site pertama:
        |
        | - Manual
        | - Online Billing
        |
        | customer tetap dapat ditemukan.
        |
        */

            $firstCustomer = $ticket->customers->first();

            $pelangganId = $firstCustomer?->pelanggan_id;

            /*
        |--------------------------------------------------------------------------
        | CUSTOMER HARUS DITEMUKAN
        |--------------------------------------------------------------------------
        */

            if ($pelangganId) {

                /*
            |--------------------------------------------------------------------------
            | AMBIL SEMUA ONLINE BILLING AKTIF
            |--------------------------------------------------------------------------
            |
            | Hanya site milik customer GAMAS yang ditampilkan.
            |
            */

                $availableSites = OnlineBilling::query()
                    ->with('pelanggan')
                    ->where('status', 'active')
                    ->where('pelanggan_id', $pelangganId)
                    ->orderBy('nama_site')
                    ->get([
                        'id',
                        'pelanggan_id',
                        'nama_site',
                        'no_jaringan',
                    ]);

                /*
            |--------------------------------------------------------------------------
            | AMBIL SITE ONLINE BILLING YANG SUDAH ADA
            |--------------------------------------------------------------------------
            */

                $existingOnlineBillingIds = $ticket->customers
                    ->pluck('online_billing_id')
                    ->filter()
                    ->values();

                /*
            |--------------------------------------------------------------------------
            | HANYA TAMPILKAN SITE YANG BELUM ADA
            |--------------------------------------------------------------------------
            */

                $availableSites = $availableSites
                    ->whereNotIn(
                        'id',
                        $existingOnlineBillingIds
                    )
                    ->values();
            }
        }

        /*
    |--------------------------------------------------------------------------
    | RETURN KE INERTIA
    |--------------------------------------------------------------------------
    */

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'categories' => $categories,
            'availableSites' => $availableSites,
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
        $ticket->updates()->create([
            'user_id' => Auth::id(),
            'message' => 'Stop Clock dimulai. Alasan: ' . $validated['reason'],
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
        $ticket->updates()->create([
            'user_id' => Auth::id(),
            'message' => 'Stop Clock dihentikan.',
        ]);
        return back()->with(
            'success',
            'Stop Clock berhasil dihentikan.'
        );
    }
    public function resolve(Request $request, Ticket $ticket)
    {
        // Validasi resolution
        $validated = $request->validate([
            'resolution' => [
                'required',
                'in:provider_issue,no_issue',
            ],
        ]);

        // Ticket hanya boleh di-resolve ketika On Progress
        if ($ticket->status !== 'on_progress') {
            return back()->withErrors([
                'resolve' => 'Ticket hanya dapat di-resolve ketika status On Progress.',
            ]);
        }

        // Pastikan tidak ada Stop Clock yang masih aktif
        $activeStopClock = $ticket->stopClocks()
            ->whereNull('ended_at')
            ->exists();

        if ($activeStopClock) {
            return back()->withErrors([
                'resolve' => 'Ticket tidak dapat di-resolve karena masih ada Stop Clock yang aktif.',
            ]);
        }

        // Ambil incident terakhir beserta kategori kendalanya
        $latestIncident = $ticket->incidents()
            ->with('category')
            ->latest('incident_number')
            ->first();

        if (!$latestIncident) {
            return back()->withErrors([
                'resolve' => 'Ticket tidak memiliki incident.',
            ]);
        }

        /*
     * Waktu Ticket di-Resolve
     */
        $resolvedAt = now();

        /*
     * Tentukan waktu selesai Incident.
     *
     * Current:
     * resolved_at masih NULL
     * → gunakan waktu sekarang.
     *
     * Historical:
     * resolved_at sudah ada
     * → gunakan waktu historical tersebut.
     */
        $incidentResolvedAt = $latestIncident->resolved_at ?? $resolvedAt;

        /*
     * ==========================================================
     * TENTUKAN APAKAH DOWNTIME DIHITUNG
     * ==========================================================
     *
     * Downtime hanya dihitung jika:
     *
     * 1. Resolution = provider_issue
     * 2. Category = is_downtime true
     *
     * Jika:
     *
     * - Resolution = no_issue
     * - Category = is_downtime false
     *
     * maka downtime tidak bertambah.
     */
        if (
            $validated['resolution'] === 'no_issue' ||
            !$latestIncident->category->is_downtime
        ) {
            DB::transaction(function () use (
                $ticket,
                $latestIncident,
                $incidentResolvedAt,
                $resolvedAt,
                $validated
            ) {
                /*
             * Untuk historical:
             * resolved_at yang sudah ada tetap dipertahankan.
             *
             * Untuk current:
             * resolved_at diisi dengan waktu sekarang.
             */
                $latestIncident->update([
                    'resolved_at' => $incidentResolvedAt,
                ]);

                // Update Ticket
                $ticket->update([
                    'status' => 'resolved',
                    'resolution' => $validated['resolution'],
                    'resolved_by' => Auth::id(),
                    'resolved_at' => $resolvedAt,
                ]);

                // Catat Activity
                $ticket->updates()->create([
                    'user_id' => Auth::id(),
                    'message' => 'Ticket berhasil di-Resolve.',
                ]);
            });

            return back()->with(
                'success',
                'Ticket berhasil di-resolve.'
            );
        }

        /*
     * ==========================================================
     * INCIDENT = PROVIDER ISSUE + DOWNTIME
     * ==========================================================
     */

        /*
     * Hitung Stop Clock dalam satuan DETIK.
     *
     * Kita tidak langsung menggunakan diffInMinutes()
     * agar detik tidak hilang pada setiap periode Stop Clock.
     */
        $totalStopClockSeconds = $ticket->stopClocks()
            ->whereNotNull('ended_at')
            ->where(
                'started_at',
                '>=',
                $latestIncident->reported_at
            )
            ->get()
            ->sum(function ($stopClock) {
                return $stopClock->started_at->diffInSeconds(
                    $stopClock->ended_at
                );
            });

        /*
     * Hitung total durasi Incident dalam DETIK.
     *
     * Current:
     * Incident mulai → waktu Resolve
     *
     * Historical:
     * Incident mulai → waktu Incident selesai
     */
        $totalIncidentSeconds = $latestIncident->reported_at->diffInSeconds(
            $incidentResolvedAt
        );

        /*
     * Kurangi waktu Stop Clock.
     */
        $currentDowntimeSeconds = max(
            0,
            $totalIncidentSeconds - $totalStopClockSeconds
        );

        /*
     * Konversi hasil akhir ke menit.
     *
     * Dibulatkan ke menit terdekat.
     */
        $currentDowntimeMinutes = intdiv(
            $currentDowntimeSeconds,
            60
        );
        /*
     * Tambahkan downtime periode ini
     * ke downtime sebelumnya.
     */
        $totalDowntimeMinutes =
            ($ticket->downtime_minutes ?? 0)
            + $currentDowntimeMinutes;

        DB::transaction(function () use (
            $ticket,
            $latestIncident,
            $incidentResolvedAt,
            $resolvedAt,
            $totalDowntimeMinutes,
            $validated
        ) {
            /*
         * Simpan resolved_at Incident.
         *
         * Historical:
         * tetap menggunakan waktu historical.
         *
         * Current:
         * menggunakan waktu Resolve.
         */
            $latestIncident->update([
                'resolved_at' => $incidentResolvedAt,
            ]);

            // Update Ticket
            $ticket->update([
                'status' => 'resolved',
                'resolution' => $validated['resolution'],
                'resolved_by' => Auth::id(),
                'resolved_at' => $resolvedAt,
                'downtime_minutes' => $totalDowntimeMinutes,
            ]);

            // Catat Activity
            $ticket->updates()->create([
                'user_id' => Auth::id(),
                'message' => 'Ticket berhasil di-Resolve.',
            ]);
        });

        return back()->with(
            'success',
            'Ticket berhasil di-resolve.'
        );
    }
    public function reopen(Request $request, Ticket $ticket)
    {
        // Re-Open hanya boleh dilakukan ketika ticket sudah Closed
        if ($ticket->status !== 'closed') {
            return back()->withErrors([
                'reopen' => 'Ticket hanya dapat di-Re-Open ketika status Closed.',
            ]);
        }

        // Validasi data Re-Open
        $validated = $request->validate([
            'kendala_id' => [
                'required',
                'exists:ticket_categories,id',
            ],
            'reported_at' => [
                'required',
                'date',
            ],
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use ($ticket, $validated) {

            // Ambil nomor incident terakhir pada ticket
            $lastIncidentNumber = $ticket->incidents()
                ->max('incident_number');

            $nextIncidentNumber = ($lastIncidentNumber ?? 0) + 1;

            // Buat incident baru
            $ticket->incidents()->create([
                'incident_number' => $nextIncidentNumber,
                'kendala_id' => $validated['kendala_id'],
                'reported_at' => $validated['reported_at'],
            ]);

            // Ticket kembali ke On Progress
            //
            // Data Resolved dan Closed dikosongkan
            // karena ticket sudah dibuka kembali.
            $ticket->update([
                'status' => 'on_progress',

                'resolved_at' => null,
                'resolved_by' => null,

                'closed_at' => null,
                'closed_by' => null,
            ]);

            // Catat aktivitas Re-Open
            $ticket->updates()->create([
                'user_id' => Auth::id(),
                'message' => 'Ticket di-Re-Open. Alasan: ' . $validated['reason'],
            ]);
        });

        return back()->with(
            'success',
            'Ticket berhasil di-Re-Open.'
        );
    }

    public function storeRfo(Request $request, Ticket $ticket)
    {
        if ($ticket->status !== 'resolved') {
            return back()->withErrors([
                'rfo' => 'RFO hanya dapat dibuat ketika ticket sudah Resolved.',
            ]);
        }

        $validated = $request->validate([
            'content' => [
                'required',
                'string',
            ],
        ]);

        DB::transaction(function () use ($ticket, $validated) {

            $rfoNumber = 'RFO-' . now()->format('YmdHis') . '-' . $ticket->id;

            $ticket->rfos()->create([
                'rfo_number' => $rfoNumber,
                'content' => $validated['content'],
                'created_by' => Auth::id(),
            ]);

            $ticket->updates()->create([
                'user_id' => Auth::id(),
                'message' => 'RFO dibuat dengan nomor ' . $rfoNumber . '.',
            ]);
        });

        return back()->with(
            'success',
            'RFO berhasil dibuat.'
        );
    }
    public function updateRfo(
        Request $request,
        Ticket $ticket,
        Rfo $rfo
    ) {
        if ($ticket->status !== 'resolved') {
            return back()->withErrors([
                'rfo' => 'RFO hanya dapat diedit ketika ticket masih Resolved.',
            ]);
        }

        if ($rfo->ticket_id !== $ticket->id) {
            abort(404);
        }

        $validated = $request->validate([
            'content' => [
                'required',
                'string',
            ],
        ]);

        DB::transaction(function () use ($ticket, $rfo, $validated) {

            $rfo->update([
                'content' => $validated['content'],
            ]);

            $ticket->updates()->create([
                'user_id' => Auth::id(),
                'message' => 'RFO ' . $rfo->rfo_number . ' diperbarui.',
            ]);
        });

        return back()->with(
            'success',
            'RFO berhasil diperbarui.'
        );
    }

    public function close(Ticket $ticket)
    {
        if ($ticket->status !== 'resolved') {
            return back()->withErrors([
                'close' => 'Ticket hanya dapat di-Close ketika status Resolved.',
            ]);
        }

        $activeStopClock = $ticket->stopClocks()
            ->whereNull('ended_at')
            ->exists();

        if ($activeStopClock) {
            return back()->withErrors([
                'close' => 'Ticket tidak dapat di-Close karena masih ada Stop Clock yang aktif.',
            ]);
        }

        if (!$ticket->rfos()->exists()) {
            return back()->withErrors([
                'close' => 'Ticket tidak dapat di-Close karena belum memiliki RFO.',
            ]);
        }

        $closedAt = now();

        $ticket->update([
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => $closedAt,
        ]);

        $ticket->updates()->create([
            'user_id' => Auth::id(),
            'message' => 'Ticket berhasil di-Close.',
        ]);

        return back()->with(
            'success',
            'Ticket berhasil di-Close.'
        );
    }

    public function addGamasSite(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'online_billing_id' => [
                'required',
                'integer',
                'exists:online_billings,id',
            ],
        ]);

        // Pastikan ticket adalah GAMAS
        if ($ticket->ticket_type !== 'gamas') {
            return back()->withErrors([
                'site' => 'Site tambahan hanya dapat ditambahkan ke ticket GAMAS.',
            ]);
        }

        // Ambil Online Billing yang dipilih
        $onlineBilling = OnlineBilling::query()
            ->with('pelanggan')
            ->where('status', 'active')
            ->find($validated['online_billing_id']);

        if (!$onlineBilling) {
            return back()->withErrors([
                'site' => 'Online Billing tidak ditemukan atau sudah tidak aktif.',
            ]);
        }

        // Pastikan GAMAS sudah memiliki customer/site
        $existingCustomer = $ticket->customers()->first();

        if (!$existingCustomer) {
            return back()->withErrors([
                'site' => 'Ticket GAMAS belum memiliki pelanggan.',
            ]);
        }

        /*
     * Pastikan pelanggan site baru
     * sama dengan pelanggan GAMAS.
     */
        if (
            $onlineBilling->pelanggan_id !==
            $existingCustomer->onlineBilling?->pelanggan_id
        ) {
            return back()->withErrors([
                'site' => 'Site yang ditambahkan harus berasal dari pelanggan yang sama dengan GAMAS.',
            ]);
        }

        // Pastikan site belum ada di ticket
        $alreadyExists = $ticket->customers()
            ->where('online_billing_id', $onlineBilling->id)
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors([
                'site' => 'Site tersebut sudah ada di ticket GAMAS.',
            ]);
        }

        DB::transaction(function () use ($ticket, $onlineBilling) {

            // Tambahkan site ke GAMAS
            $ticket->customers()->create([
                'online_billing_id' => $onlineBilling->id,
                'customer_name' => $onlineBilling->pelanggan?->nama_pelanggan,
                'site_name' => $onlineBilling->nama_site,
                'no_jaringan' => $onlineBilling->no_jaringan,
                'reported_via' => null,
            ]);

            // Catat Activity
            $ticket->updates()->create([
                'user_id' => Auth::id(),
                'message' => 'Site "' . $onlineBilling->nama_site . '" ditambahkan ke GAMAS.',
            ]);
        });

        return back()->with(
            'success',
            'Site berhasil ditambahkan ke GAMAS.'
        );
    }
    public function addSite(Request $request, Ticket $ticket)
    {
        /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'source' => [
                'required',
                'in:online_billing,manual',
            ],

            'online_billing_id' => [
                'nullable',
                'integer',
                'exists:online_billings,id',
            ],

            'site_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'no_jaringan' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reported_via' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | PASTIKAN TICKET GAMAS
    |--------------------------------------------------------------------------
    */

        if ($ticket->ticket_type !== 'gamas') {
            return back()->withErrors([
                'site' =>
                'Site hanya dapat ditambahkan ke ticket GAMAS.',
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | AMBIL CUSTOMER UTAMA GAMAS
    |--------------------------------------------------------------------------
    |
    | Sekarang customer utama ditentukan berdasarkan pelanggan_id,
    | bukan berdasarkan online_billing_id.
    |
    */

        $existingCustomer = $ticket->customers()
            ->first();

        if (!$existingCustomer) {
            return back()->withErrors([
                'site' =>
                'Customer utama ticket GAMAS tidak ditemukan.',
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | PASTIKAN GAMAS MEMILIKI PELANGGAN_ID
    |--------------------------------------------------------------------------
    */

        if (!$existingCustomer->pelanggan_id) {
            return back()->withErrors([
                'site' =>
                'Customer ticket GAMAS belum memiliki identitas pelanggan.',
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | SITE DARI ONLINE BILLING
    |--------------------------------------------------------------------------
    */

        if ($validated['source'] === 'online_billing') {

            /*
        |--------------------------------------------------------------------------
        | Online Billing ID wajib
        |--------------------------------------------------------------------------
        */

            if (!$validated['online_billing_id']) {
                return back()->withErrors([
                    'online_billing_id' =>
                    'Silakan pilih site dari Online Billing.',
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | AMBIL ONLINE BILLING
        |--------------------------------------------------------------------------
        */

            $onlineBilling = OnlineBilling::query()
                ->with('pelanggan')
                ->where('status', 'active')
                ->find($validated['online_billing_id']);

            if (!$onlineBilling) {
                return back()->withErrors([
                    'online_billing_id' =>
                    'Online Billing tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | PASTIKAN CUSTOMER SAMA
        |--------------------------------------------------------------------------
        |
        | Customer ticket:
        |   $existingCustomer->pelanggan_id
        |
        | Customer site Online Billing:
        |   $onlineBilling->pelanggan_id
        |
        | Keduanya harus sama.
        |
        */

            if (
                $onlineBilling->pelanggan_id !==
                $existingCustomer->pelanggan_id
            ) {
                return back()->withErrors([
                    'online_billing_id' =>
                    'Site harus berasal dari customer yang sama dengan ticket GAMAS.',
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | PASTIKAN SITE BELUM ADA DI TICKET
        |--------------------------------------------------------------------------
        */

            $alreadyExists = $ticket->customers()
                ->where(
                    'online_billing_id',
                    $onlineBilling->id
                )
                ->exists();

            if ($alreadyExists) {
                return back()->withErrors([
                    'online_billing_id' =>
                    'Site tersebut sudah ada di ticket.',
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | SIMPAN SITE ONLINE BILLING
        |--------------------------------------------------------------------------
        */

            $ticket->customers()->create([
                /*
             * Identitas customer.
             */
                'pelanggan_id' =>
                $onlineBilling->pelanggan_id,

                /*
             * Referensi Online Billing.
             */
                'online_billing_id' =>
                $onlineBilling->id,

                /*
             * Snapshot nama customer.
             */
                'customer_name' =>
                $onlineBilling->pelanggan?->nama_pelanggan,

                /*
             * Snapshot nama site.
             */
                'site_name' =>
                $onlineBilling->nama_site,

                /*
             * Snapshot nomor jaringan.
             */
                'no_jaringan' =>
                $onlineBilling->no_jaringan,

                /*
             * Media/tempat customer melaporkan.
             */
                'reported_via' =>
                $validated['reported_via'] ?? null,
            ]);

            /*
        |--------------------------------------------------------------------------
        | HISTORY
        |--------------------------------------------------------------------------
        */

            $ticket->updates()->create([
                'user_id' =>
                Auth::id(),

                'message' =>
                'Site dari Online Billing ditambahkan: ' .
                    ($onlineBilling->nama_site ?? '-'),
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | SITE MANUAL
    |--------------------------------------------------------------------------
    */ else {

            /*
        |--------------------------------------------------------------------------
        | NAMA SITE WAJIB
        |--------------------------------------------------------------------------
        */

            if (empty($validated['site_name'])) {
                return back()->withErrors([
                    'site_name' =>
                    'Nama site wajib diisi.',
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | PASTIKAN SITE MANUAL BELUM ADA
        |--------------------------------------------------------------------------
        */

            $alreadyExists = $ticket->customers()
                ->whereNull('online_billing_id')
                ->where(
                    'site_name',
                    $validated['site_name']
                )
                ->exists();

            if ($alreadyExists) {
                return back()->withErrors([
                    'site_name' =>
                    'Site tersebut sudah ada di ticket.',
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | SIMPAN SITE MANUAL
        |--------------------------------------------------------------------------
        */

            $ticket->customers()->create([
                /*
             * Gunakan customer utama GAMAS.
             */
                'pelanggan_id' =>
                $existingCustomer->pelanggan_id,

                /*
             * Belum ada di Online Billing.
             */
                'online_billing_id' =>
                null,

                /*
             * Snapshot nama customer.
             */
                'customer_name' =>
                $existingCustomer->customer_name,

                /*
             * Nama site manual.
             */
                'site_name' =>
                $validated['site_name'],

                /*
             * Nomor jaringan jika ada.
             */
                'no_jaringan' =>
                $validated['no_jaringan'] ?? null,

                /*
             * Media/tempat customer melaporkan.
             */
                'reported_via' =>
                $validated['reported_via'] ?? null,
            ]);

            /*
        |--------------------------------------------------------------------------
        | HISTORY
        |--------------------------------------------------------------------------
        */

            $ticket->updates()->create([
                'user_id' =>
                Auth::id(),

                'message' =>
                'Site manual ditambahkan: ' .
                    $validated['site_name'],
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return back()->with(
            'success',
            'Site berhasil ditambahkan ke ticket.'
        );
    }

    public function removeSite(
        Request $request,
        Ticket $ticket,
        $ticketCustomerId
    ) {
        if ($ticket->ticket_type !== 'gamas') {
            return back()->withErrors([
                'site' => 'Site hanya dapat dilepas dari ticket GAMAS.',
            ]);
        }

        $ticketCustomer = $ticket->customers()
            ->where('id', $ticketCustomerId)
            ->first();

        if (!$ticketCustomer) {
            return back()->withErrors([
                'site' => 'Site tidak ditemukan di ticket ini.',
            ]);
        }

        $siteCount = $ticket->customers()->count();

        if ($siteCount <= 1) {
            return back()->withErrors([
                'site' => 'Site terakhir tidak dapat dilepas dari ticket GAMAS.',
            ]);
        }

        $siteName = $ticketCustomer->site_name ?? '-';

        DB::transaction(function () use (
            $ticket,
            $ticketCustomer,
            $siteName
        ) {
            $ticketCustomer->delete();

            $ticket->updates()->create([
                'user_id' => Auth::id(),
                'message' => 'Site dilepas dari ticket: ' . $siteName,
            ]);
        });

        return back()->with(
            'success',
            'Site berhasil dilepas dari ticket.'
        );
    }
}

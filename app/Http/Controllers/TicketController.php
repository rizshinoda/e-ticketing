<?php

namespace App\Http\Controllers;

use App\Models\OnlineBilling;
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

                $ticket->customers()->create([
                    'online_billing_id' => $onlineBilling->id,

                    'customer_name' =>
                    $onlineBilling->pelanggan?->nama_pelanggan,

                    'site_name' =>
                    $onlineBilling->nama_site,

                    'no_jaringan' =>
                    $onlineBilling->no_jaringan,

                    'reported_via' =>
                    $validated['reported_via'] ?? null,
                ]);
            }

            /*
|--------------------------------------------------------------------------
| 3. CREATE INCIDENT PERTAMA
|--------------------------------------------------------------------------
*/

            $ticket->incidents()->create([
                'incident_number' => 1,
                'kendala_id' => $validated['kendala_id'],

                'reported_at' => $validated['report_type'] === 'historical'
                    ? $validated['incident_reported_at']
                    : $validated['reported_at'],

                'resolved_at' => $validated['report_type'] === 'historical'
                    ? $validated['incident_resolved_at']
                    : null,
            ]);

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
            'incidents.category',
            'stopClocks',
            'rfos.creator',
            'updates.user',
            'updates.attachments',
        ]);

        $categories = TicketCategory::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'is_downtime',
            ]);

        /*
    |--------------------------------------------------------------------------
    | Site yang tersedia untuk GAMAS
    |--------------------------------------------------------------------------
    */

        $availableSites = collect();

        if ($ticket->ticket_type === 'gamas') {

            // Ambil pelanggan dari site pertama GAMAS
            $firstCustomer = $ticket->customers->first();

            if ($firstCustomer?->onlineBilling?->pelanggan_id) {

                $pelangganId =
                    $firstCustomer->onlineBilling->pelanggan_id;

                /*
             * Ambil semua Online Billing aktif
             * milik pelanggan tersebut.
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
             * Jangan tampilkan site yang sudah
             * ada di ticket GAMAS.
             */
                $existingOnlineBillingIds =
                    $ticket->customers
                    ->pluck('online_billing_id')
                    ->filter()
                    ->values();

                $availableSites = $availableSites
                    ->whereNotIn('id', $existingOnlineBillingIds)
                    ->values();
            }
        }

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
                'online_billing_id' =>
                'Site hanya dapat ditambahkan ke ticket GAMAS.',
            ]);
        }

        // Ambil site yang dipilih
        $onlineBilling = OnlineBilling::query()
            ->with('pelanggan')
            ->where('status', 'active')
            ->findOrFail($validated['online_billing_id']);

        // Ambil customer dari site pertama yang sudah ada
        $existingCustomer = $ticket->customers()
            ->with('onlineBilling')
            ->first();

        if (!$existingCustomer?->onlineBilling) {
            return back()->withErrors([
                'online_billing_id' =>
                'Customer utama ticket GAMAS tidak ditemukan.',
            ]);
        }

        // Pastikan site berasal dari customer yang sama
        if (
            $onlineBilling->pelanggan_id !==
            $existingCustomer->onlineBilling->pelanggan_id
        ) {
            return back()->withErrors([
                'online_billing_id' =>
                'Site harus berasal dari customer yang sama dengan ticket GAMAS.',
            ]);
        }

        // Pastikan site belum ada di ticket
        $alreadyExists = $ticket->customers()
            ->where('online_billing_id', $onlineBilling->id)
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors([
                'online_billing_id' =>
                'Site tersebut sudah ada di ticket.',
            ]);
        }

        $ticket->customers()->create([
            'online_billing_id' => $onlineBilling->id,
            'customer_name' => $onlineBilling->pelanggan?->nama_pelanggan,
            'site_name' => $onlineBilling->nama_site,
            'no_jaringan' => $onlineBilling->no_jaringan,
            'reported_via' => null,
        ]);

        // Catat ke Activity
        $ticket->updates()->create([
            'user_id' => Auth::id(),
            'message' =>
            'Site ditambahkan ke ticket: ' .
                ($onlineBilling->nama_site ?? '-'),
        ]);

        return back()->with(
            'success',
            'Site berhasil ditambahkan ke ticket.'
        );
    }
}

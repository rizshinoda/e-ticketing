<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketDisruptionReportService
{
    public function generate(
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): array {
        $start = CarbonImmutable::instance($periodStart);
        $end = CarbonImmutable::instance($periodEnd);

        if (!$end->greaterThan($start)) {
            throw new InvalidArgumentException(
                'Periode laporan tidak valid.'
            );
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        /*
         * Untuk bulan yang sudah berlalu, gunakan akhir bulan.
         * Untuk bulan berjalan, gunakan waktu sekarang.
         */
        $durationCutoff = $end->lessThan($now)
            ? $end
            : $now;

        /*
         * Ambil SEMUA insiden pada periode laporan.
         *
         * Tidak ada filter is_downtime.
         * Semua kategori kendala akan masuk laporan.
         *
         * LEFT JOIN kategori digunakan agar insiden yang
         * belum memiliki kategori tetap bisa ditampilkan.
         */
        $rows = DB::table('ticket_customers as tc')
            ->join(
                'tickets as t',
                't.id',
                '=',
                'tc.ticket_id'
            )
            ->join(
                'ticket_incidents as ti',
                'ti.ticket_id',
                '=',
                'tc.ticket_id'
            )
            ->leftJoin(
                'ticket_categories as categories',
                'categories.id',
                '=',
                'ti.kendala_id'
            )
            ->leftJoin(
                'online_billings as ob',
                'ob.id',
                '=',
                'tc.online_billing_id'
            )
            ->leftJoin(
                'instansis as i',
                'i.id',
                '=',
                'ob.instansi_id'
            )
            ->where(
                'ti.reported_at',
                '>=',
                $start->toDateTimeString()
            )
            ->where(
                'ti.reported_at',
                '<',
                $end->toDateTimeString()
            )
            ->where(
                'ti.reported_at',
                '<=',
                $durationCutoff->toDateTimeString()
            )
            ->select([
                // Tiket
                't.id as ticket_id',
                't.ticket_number',
                't.status as ticket_status',

                // Site pada tiket
                'tc.id as ticket_customer_id',
                'tc.pelanggan_id',
                'tc.online_billing_id',
                'tc.customer_name',
                'tc.site_name as ticket_site_name',
                'tc.no_jaringan as ticket_no_jaringan',

                // Data Online Billing
                'ob.nama_site as billing_site_name',
                'ob.no_jaringan as billing_no_jaringan',
                'ob.instansi_id',
                'i.nama_instansi',

                // Kategori kendala
                'ti.kendala_id as category_id',
                'categories.name as category_name',
                'categories.is_downtime',

                // Insiden
                'ti.id as incident_id',
                'ti.incident_number',
                'ti.reported_at',
                'ti.resolved_at',
                'ti.resolution',
            ])
            ->orderBy('ti.reported_at')
            ->orderBy('ti.id')
            ->get();

        /*
         * Tempat pengelompokan data.
         */
        $siteGroups = [];
        $instansiGroups = [];
        $categoryGroups = [];
        $incidentGroups = [];

        foreach ($rows as $row) {
            $ticketId = (int) $row->ticket_id;
            $incidentId = (int) $row->incident_id;

            /*
             * Tentukan identitas site.
             */
            $siteKey = $this->makeSiteKey($row);

            $siteName = $row->billing_site_name
                ?: $row->ticket_site_name
                ?: 'Site tanpa nama';

            $noJaringan = $row->billing_no_jaringan
                ?: $row->ticket_no_jaringan;

            /*
             * Hitung durasi penanganan.
             *
             * Jika resolved_at kosong, hitung sampai waktu sekarang.
             * Jika resolved_at melewati akhir periode, batasi
             * sampai akhir periode laporan.
             *
             * Stop Clock tidak dikurangi karena ini bukan SLA.
             */
            $reportedAt = CarbonImmutable::parse(
                $row->reported_at
            );

            $resolvedAt = $row->resolved_at !== null
                ? CarbonImmutable::parse($row->resolved_at)
                : null;

            $durationEnd = $resolvedAt !== null
                && $resolvedAt->lessThan($durationCutoff)
                ? $resolvedAt
                : $durationCutoff;

            $durationSeconds = max(
                0,
                $durationEnd->getTimestamp()
                    - $reportedAt->getTimestamp()
            );

            /*
             * =====================================================
             * 1. PENGELOMPOKAN SITE
             * =====================================================
             */
            if (!isset($siteGroups[$siteKey])) {
                $siteGroups[$siteKey] = [
                    'site_key' => $siteKey,

                    'online_billing_id' => $row->online_billing_id,
                    'pelanggan_id' => $row->pelanggan_id,
                    'customer_name' => $row->customer_name,

                    'site_name' => $siteName,
                    'no_jaringan' => $noJaringan,

                    'instansi_id' => $row->instansi_id,
                    'instansi_name' => $row->nama_instansi,

                    'ticket_ids' => [],
                    'incident_durations' => [],
                ];
            }

            // Tiket unik pada site.
            $siteGroups[$siteKey]['ticket_ids'][$ticketId] = true;

            // Insiden unik pada site.
            $siteGroups[$siteKey]['incident_durations'][$incidentId]
                = $durationSeconds;

            /*
             * =====================================================
             * 2. PENGELOMPOKAN INSTANSI
             * =====================================================
             *
             * Site manual tanpa instansi tidak masuk ranking
             * instansi, tetapi tetap masuk ranking site.
             */
            if ($row->instansi_id !== null) {
                $instansiId = (int) $row->instansi_id;

                if (!isset($instansiGroups[$instansiId])) {
                    $instansiGroups[$instansiId] = [
                        'instansi_id' => $instansiId,
                        'instansi_name' => $row->nama_instansi,

                        'ticket_ids' => [],
                        'incident_durations' => [],
                        'site_keys' => [],
                    ];
                }

                $instansiGroups[$instansiId]['ticket_ids'][$ticketId]
                    = true;

                /*
                 * Satu insiden dihitung satu kali per instansi,
                 * meskipun mengenai beberapa site dalam instansi sama.
                 */
                $instansiGroups[$instansiId]['incident_durations'][$incidentId]
                    = $durationSeconds;

                $instansiGroups[$instansiId]['site_keys'][$siteKey]
                    = true;
            }

            /*
             * =====================================================
             * 3. PENGELOMPOKAN KATEGORI KENDALA
             * =====================================================
             */
            $categoryKey = $row->category_id !== null
                ? 'category-' . $row->category_id
                : 'uncategorized';

            if (!isset($categoryGroups[$categoryKey])) {
                $categoryGroups[$categoryKey] = [
                    'category_id' => $row->category_id,
                    'category_name' => $row->category_name
                        ?: 'Tanpa kategori',

                    // Hanya informasi, bukan filter.
                    'is_downtime' => (bool) $row->is_downtime,

                    'ticket_ids' => [],
                    'incident_durations' => [],
                    'site_keys' => [],
                    'instansi_ids' => [],
                ];
            }

            $categoryGroups[$categoryKey]['ticket_ids'][$ticketId]
                = true;

            $categoryGroups[$categoryKey]['incident_durations'][$incidentId]
                = $durationSeconds;

            $categoryGroups[$categoryKey]['site_keys'][$siteKey]
                = true;

            if ($row->instansi_id !== null) {
                $categoryGroups[$categoryKey]['instansi_ids'][(int) $row->instansi_id] = true;
            }

            /*
             * =====================================================
             * 4. DETAIL INSIDEN
             * =====================================================
             *
             * Satu insiden disimpan satu kali.
             * Semua site terdampak dimasukkan dalam array sites.
             */
            if (!isset($incidentGroups[$incidentId])) {
                $incidentGroups[$incidentId] = [
                    'incident_id' => $incidentId,
                    'incident_number' => $row->incident_number,

                    'ticket_id' => $ticketId,
                    'ticket_number' => $row->ticket_number,
                    'ticket_status' => $row->ticket_status,

                    'category_id' => $row->category_id,
                    'category_name' => $row->category_name
                        ?: 'Tanpa kategori',

                    'is_downtime' => (bool) $row->is_downtime,
                    'resolution' => $row->resolution,

                    'reported_at' => $reportedAt->toIso8601String(),
                    'resolved_at' => $resolvedAt?->toIso8601String(),

                    'duration_seconds' => $durationSeconds,

                    'sites' => [],
                ];
            }

            $incidentGroups[$incidentId]['sites'][$siteKey] = [
                'site_name' => $siteName,
                'no_jaringan' => $noJaringan,
                'customer_name' => $row->customer_name,

                'instansi_id' => $row->instansi_id,
                'instansi_name' => $row->nama_instansi,
            ];
        }

        /*
         * =====================================================
         * 5. BENTUK RANKING SITE
         * =====================================================
         */
        $siteRanking = [];

        foreach ($siteGroups as $group) {
            $durations = $group['incident_durations'];

            $siteRanking[] = [
                'online_billing_id' => $group['online_billing_id'],
                'pelanggan_id' => $group['pelanggan_id'],

                'customer_name' => $group['customer_name'],
                'site_name' => $group['site_name'],
                'no_jaringan' => $group['no_jaringan'],

                'instansi_id' => $group['instansi_id'],
                'instansi_name' => $group['instansi_name'],

                'ticket_count' => count($group['ticket_ids']),
                'incident_count' => count($durations),

                'duration_seconds' => (int) array_sum($durations),
            ];
        }

        /*
         * =====================================================
         * 6. BENTUK RANKING INSTANSI
         * =====================================================
         */
        $instansiRanking = [];

        foreach ($instansiGroups as $group) {
            $durations = $group['incident_durations'];

            $instansiRanking[] = [
                'instansi_id' => $group['instansi_id'],
                'instansi_name' => $group['instansi_name'],

                'ticket_count' => count($group['ticket_ids']),
                'incident_count' => count($durations),
                'site_count' => count($group['site_keys']),

                'duration_seconds' => (int) array_sum($durations),
            ];
        }

        /*
         * =====================================================
         * 7. BENTUK RANKING KATEGORI KENDALA
         * =====================================================
         */
        $categoryRanking = [];

        foreach ($categoryGroups as $group) {
            $durations = $group['incident_durations'];

            $categoryRanking[] = [
                'category_id' => $group['category_id'],
                'category_name' => $group['category_name'],

                'is_downtime' => $group['is_downtime'],

                'ticket_count' => count($group['ticket_ids']),
                'incident_count' => count($durations),

                'site_count' => count($group['site_keys']),
                'instansi_count' => count($group['instansi_ids']),

                'duration_seconds' => (int) array_sum($durations),
            ];
        }

        /*
         * Ranking utama:
         * 1. Jumlah insiden terbanyak.
         * 2. Jumlah tiket terbanyak.
         * 3. Durasi penanganan terlama.
         */
        $sortRanking = function (array &$items): void {
            usort($items, function ($a, $b) {
                return ($b['incident_count'] <=> $a['incident_count'])
                    ?: ($b['ticket_count'] <=> $a['ticket_count'])
                    ?: ($b['duration_seconds'] <=> $a['duration_seconds']);
            });
        };

        $sortRanking($siteRanking);
        $sortRanking($instansiRanking);
        $sortRanking($categoryRanking);

        /*
         * =====================================================
         * 8. DETAIL INSIDEN
         * =====================================================
         */
        $incidentDetails = array_values($incidentGroups);

        foreach ($incidentDetails as &$incident) {
            $incident['sites'] = array_values(
                $incident['sites']
            );
        }

        unset($incident);

        usort(
            $incidentDetails,
            fn($a, $b) => strcmp(
                $b['reported_at'],
                $a['reported_at']
            )
        );

        /*
         * =====================================================
         * 9. TREN GANGGUAN HARIAN
         * =====================================================
         *
         * Setiap insiden hanya dihitung satu kali per hari.
         */
        $dailyCounts = [];

        for (
            $day = $start->startOfDay();
            $day->lessThan($end)
                && !$day->greaterThan($durationCutoff->startOfDay());
            $day = $day->addDay()
        ) {
            $dailyCounts[$day->toDateString()] = 0;
        }

        foreach ($incidentDetails as $incident) {
            $date = CarbonImmutable::parse(
                $incident['reported_at']
            )->toDateString();

            if (array_key_exists($date, $dailyCounts)) {
                $dailyCounts[$date]++;
            }
        }

        $dailyTrend = [];

        foreach ($dailyCounts as $date => $count) {
            $dailyTrend[] = [
                'date' => $date,
                'incident_count' => $count,
            ];
        }

        /*
         * =====================================================
         * 10. RINGKASAN LAPORAN
         * =====================================================
         *
         * Durasi keseluruhan dihitung dari insiden unik,
         * bukan dijumlahkan dari ranking site.
         */
        $totalTickets = count(
            array_unique(
                array_column($incidentDetails, 'ticket_id')
            )
        );

        $totalDurationSeconds = (int) array_sum(
            array_column($incidentDetails, 'duration_seconds')
        );

        $sitesWithoutInstansi = 0;

        foreach ($siteGroups as $site) {
            if ($site['instansi_id'] === null) {
                $sitesWithoutInstansi++;
            }
        }

        return [
            'summary' => [
                'total_tickets' => $totalTickets,
                'total_incidents' => count($incidentDetails),
                // Site dan instansi terdampak
                'affected_sites' => count($siteGroups),
                'affected_instansi' => count($instansiGroups),
                'total_categories' => count($categoryGroups),

                'sites_without_instansi' => $sitesWithoutInstansi,

                'total_duration_seconds' => $totalDurationSeconds,
            ],

            'site_ranking' => $siteRanking,
            'instansi_ranking' => $instansiRanking,
            'category_ranking' => $categoryRanking,

            'daily_trend' => $dailyTrend,
            'incident_details' => $incidentDetails,
        ];
    }

    /**
     * Menentukan identitas site.
     *
     * Online Billing menggunakan ID.
     * Site manual menggunakan no jaringan atau nama site.
     */
    private function makeSiteKey(object $row): string
    {
        if ($row->online_billing_id !== null) {
            return 'online-billing-' . $row->online_billing_id;
        }

        $identity = trim(
            (string) (
                $row->ticket_no_jaringan
                ?: $row->ticket_site_name
            )
        );

        if ($identity === '') {
            return 'ticket-customer-' . $row->ticket_customer_id;
        }

        return 'manual-' . sha1(
            implode('|', [
                (string) $row->pelanggan_id,
                mb_strtolower($identity),
            ])
        );
    }
}

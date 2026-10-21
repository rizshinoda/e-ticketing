<?php

namespace App\Services;

use App\Models\OnlineBilling;
use App\Models\SlaRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class SlaCalculationService
{
    /**
     * Hitung SLA satu site dalam periode tertentu.
     *
     * Periode menggunakan format [start, end):
     * waktu mulai termasuk, waktu akhir tidak termasuk.
     */
    public function calculateSite(
        int $pelangganId,
        int $onlineBillingId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): array {
        [$start, $end, $periodSeconds] =
            $this->normalizePeriod($periodStart, $periodEnd);

        $site = OnlineBilling::query()
            ->where('pelanggan_id', $pelangganId)
            ->findOrFail($onlineBillingId);

        $rule = $this->resolveRuleForPeriod(
            pelangganId: $pelangganId,
            scope: 'site',
            onlineBillingId: $onlineBillingId,
            start: $start,
            end: $end,
        );

        $downtimeSeconds = $this->calculateSiteDowntimeSeconds(
            onlineBillingId: $onlineBillingId,
            start: $start,
            end: $end,
        );

        $availability = $this->calculateAvailability(
            $periodSeconds,
            $downtimeSeconds,
        );

        $target = $rule?->sla_percentage !== null
            ? (float) $rule->sla_percentage
            : null;

        $pendingIncidentsCount = $this->countUnresolvedDowntimeIncidents(
            $onlineBillingId,
            $start,
            $end
        );

        $reportStatus = $pendingIncidentsCount > 0
            ? 'provisional'
            : (
                $target === null
                ? 'missing_sla_rule'
                : 'ready_to_finalize'
            );
        return [
            'scope' => 'site',

            'pelanggan_id' => $pelangganId,

            'online_billing_id' => $site->id,

            'site_name' => $site->nama_site ?? null,

            'period_start' => $start->toIso8601String(),

            'period_end' => $end->toIso8601String(),

            'sla_rule_id' => $rule?->id,

            'target_sla_percentage' => $target,

            'availability_percentage' => $availability,

            'downtime_seconds' => $downtimeSeconds,

            'total_service_seconds' => $periodSeconds,

            'is_sla_met' => $this->isSlaMet(
                $availability,
                $target,
            ),
            'pending_incidents_count' => $pendingIncidentsCount,
            'report_status' => $reportStatus,
        ];
    }

    /**
     * Hitung SLA Global seluruh site aktif milik pelanggan.
     *
     * Asumsi:
     * Global dihitung berdasarkan total site-service time.
     */

    public function calculateGlobal(
        int $pelangganId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): array {
        [$start, $end] = $this->normalizePeriod(
            $periodStart,
            $periodEnd
        );

        /*
     * Target SLA Global harus mencakup seluruh periode laporan.
     */
        $rule = $this->resolveRuleForPeriod(
            pelangganId: $pelangganId,
            scope: 'global',
            onlineBillingId: null,
            start: $start,
            end: $end,
        );

        /*
     * Ambil hanya site yang:
     * 1. Milik pelanggan yang dipilih.
     * 2. Berstatus active saat laporan dibuat.
     * 3. Sudah mulai layanan sebelum periode laporan berakhir.
     *
     * tanggal_akhir sengaja tidak digunakan karena datanya
     * belum diperbarui secara konsisten.
     */
        $sites = OnlineBilling::query()
            ->where('pelanggan_id', $pelangganId)
            ->where('status', 'active')
            ->whereNotNull('tanggal_mulai')
            ->whereDate(
                'tanggal_mulai',
                '<',
                $end->toDateString()
            )
            ->orderBy('id')
            ->get([
                'id',
                'tanggal_mulai',
            ]);

        $siteResults = [];

        foreach ($sites as $site) {
            /*
         * Awal layanan yang dihitung adalah tanggal yang
         * paling akhir antara awal laporan dan tanggal mulai.
         */
            $siteStart = $start;

            $serviceStart = CarbonImmutable::parse(
                $site->tanggal_mulai
            )->startOfDay();

            if ($serviceStart->greaterThan($siteStart)) {
                $siteStart = $serviceStart;
            }

            // Akhir layanan mengikuti akhir periode laporan.
            // tanggal_akhir Online Billing tidak digunakan.
            $siteEnd = $end;

            if (!$siteEnd->greaterThan($siteStart)) {
                continue;
            }

            /*
         * Hitung SLA site menggunakan periode layanan aktual
         * yang berada di dalam periode laporan.
         */
            $siteResults[] = $this->calculateSite(
                pelangganId: $pelangganId,
                onlineBillingId: (int) $site->id,
                periodStart: $siteStart,
                periodEnd: $siteEnd,
            );
        }

        $siteCount = count($siteResults);

        /*
     * Total waktu layanan adalah jumlah waktu layanan
     * seluruh site yang berhasil dihitung.
     */
        $totalServiceSeconds = array_sum(
            array_column($siteResults, 'total_service_seconds')
        );

        /*
     * Akumulasi downtime dari masing-masing site.
     * Setiap site sudah menghitung durasi insidennya sendiri.
     */
        $totalDowntimeSeconds = array_sum(
            array_column($siteResults, 'downtime_seconds')
        );

        $availability = $totalServiceSeconds > 0
            ? $this->calculateAvailability(
                $totalServiceSeconds,
                $totalDowntimeSeconds,
            )
            : null;

        $target = $rule?->sla_percentage !== null
            ? (float) $rule->sla_percentage
            : null;

        /*
     * Hitung jumlah insiden-site yang belum selesai.
     */
        $pendingSiteIncidentsCount = array_sum(
            array_column(
                $siteResults,
                'pending_incidents_count'
            )
        );

        $reportStatus = $pendingSiteIncidentsCount > 0
            ? 'provisional'
            : (
                $target === null || $availability === null
                ? 'missing_sla_rule'
                : 'ready_to_finalize'
            );

        return [
            'scope' => 'global',
            'pelanggan_id' => $pelangganId,

            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),

            'sla_rule_id' => $rule?->id,
            'target_sla_percentage' => $target,
            'availability_percentage' => $availability,
            'is_sla_met' => $this->isSlaMet(
                $availability,
                $target,
            ),

            'site_count' => $siteCount,
            'total_downtime_site_seconds' => $totalDowntimeSeconds,
            'total_service_seconds' => $totalServiceSeconds,

            'pending_site_incidents_count' => $pendingSiteIncidentsCount,
            'report_status' => $reportStatus,

            'sites' => $siteResults,
        ];
    }


    /**
     * Hitung downtime efektif untuk satu site.
     *
     * Semua insiden kategori downtime dalam periode diperiksa,
     * bukan hanya latestIncident.
     *
     * Stop Clock dikurangi dari interval insiden.
     * Interval yang bertumpang tindih digabung agar
     * downtime tidak dihitung dua kali.
     */

    /**
     * Hitung akumulasi downtime insiden untuk satu site.
     *
     * Hanya menghitung insiden kategori downtime dari tiket
     * Resolved/Closed yang memiliki waktu penyelesaian insiden.
     *
     * Durasi setiap insiden dijumlahkan secara terpisah,
     * termasuk jika intervalnya bertumpang tindih dengan
     * insiden dari tiket lain.
     *
     * Stop Clock dikurangi dari masing-masing insiden.
     */
    private function calculateSiteDowntimeSeconds(
        int $onlineBillingId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        $startTimestamp = $start->getTimestamp();
        $endTimestamp = $end->getTimestamp();

        $startString = $start->toDateTimeString();
        $endString = $end->toDateTimeString();

        // Ambil insiden Down yang sudah selesai dari tiket
        // Resolved/Closed pada site yang dipilih.
        $incidents = DB::table('ticket_customers as tc')
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
            ->join(
                'ticket_categories as categories',
                'categories.id',
                '=',
                'ti.kendala_id'
            )
            ->where('tc.online_billing_id', $onlineBillingId)
            ->where('categories.is_downtime', true)

            // jika sla menghitung status resolved/closed, maka gunakan kondisi ini
            // ->whereIn('t.status', ['resolved', 'closed'])
            // ->whereNotNull('t.resolved_at')
            ->whereNotNull('ti.resolved_at')
            ->where('ti.resolution', 'provider_issue')
            ->where('ti.reported_at', '<', $endString)
            ->where('ti.resolved_at', '>', $startString)
            ->select([
                'ti.id as incident_id',
                'ti.ticket_id',
                'ti.reported_at',
                'ti.resolved_at',
            ])
            ->distinct()
            ->get();

        $totalSeconds = 0;

        // Cache Stop Clock agar tiket yang memiliki beberapa
        // insiden tidak perlu mengambil data Stop Clock berulang.
        $stopClockCache = [];

        foreach ($incidents as $incident) {
            $incidentStart = CarbonImmutable::parse(
                $incident->reported_at
            )->getTimestamp();

            // Query hanya mengambil insiden yang sudah selesai.
            $incidentEnd = CarbonImmutable::parse(
                $incident->resolved_at
            )->getTimestamp();

            // Batasi durasi ke periode laporan.
            $from = max(
                $startTimestamp,
                $incidentStart
            );

            $to = min(
                $endTimestamp,
                $incidentEnd
            );

            if ($to <= $from) {
                continue;
            }

            $ticketId = (int) $incident->ticket_id;

            if (!array_key_exists($ticketId, $stopClockCache)) {
                $stopClockCache[$ticketId] =
                    DB::table('ticket_stop_clocks')
                    ->where('ticket_id', $ticketId)
                    ->where('started_at', '<', $endString)
                    ->where(function ($query) use ($startString) {
                        $query->whereNull('ended_at')
                            ->orWhere(
                                'ended_at',
                                '>',
                                $startString
                            );
                    })
                    ->get([
                        'started_at',
                        'ended_at',
                    ]);
            }

            $stopIntervals = [];

            foreach ($stopClockCache[$ticketId] as $clock) {
                $clockStart = CarbonImmutable::parse(
                    $clock->started_at
                )->getTimestamp();

                $clockEnd = $clock->ended_at !== null
                    ? CarbonImmutable::parse(
                        $clock->ended_at
                    )->getTimestamp()
                    : $endTimestamp;

                // Potong Stop Clock ke rentang insiden ini.
                $clockFrom = max(
                    $from,
                    $clockStart
                );

                $clockTo = min(
                    $to,
                    $clockEnd
                );

                if ($clockTo > $clockFrom) {
                    $stopIntervals[] = [
                        $clockFrom,
                        $clockTo,
                    ];
                }
            }

            // Stop Clock yang saling tumpang tindih digabung
            // supaya waktu yang sama tidak dikurangi dua kali
            // dari satu insiden.
            $stopIntervals = $this->mergeIntervals(
                $stopIntervals
            );

            // Hitung downtime bersih untuk insiden ini saja.
            $incidentDowntimeSeconds = 0;
            $cursor = $from;

            foreach ($stopIntervals as [$stopFrom, $stopTo]) {
                if ($stopFrom > $cursor) {
                    $incidentDowntimeSeconds +=
                        $stopFrom - $cursor;
                }

                $cursor = max($cursor, $stopTo);

                if ($cursor >= $to) {
                    break;
                }
            }

            if ($cursor < $to) {
                $incidentDowntimeSeconds += $to - $cursor;
            }

            // Akumulasikan durasi insiden tanpa menggabungkan
            // interval dengan insiden lain.
            $totalSeconds += $incidentDowntimeSeconds;
        }

        // Jangan batasi hasil akumulasi ke total waktu periode.
        // Beberapa insiden yang beririsan boleh menghasilkan
        // akumulasi durasi lebih besar dari panjang periode.
        return max(0, $totalSeconds);
    }


    /**
     * Hitung insiden downtime yang belum selesai dan dapat
     * memengaruhi periode laporan.
     *
     * Insiden ini belum dihitung sebagai downtime karena
     * resolusi akhirnya belum diketahui.
     */
    private function countUnresolvedDowntimeIncidents(
        int $onlineBillingId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        $startString = $start->toDateTimeString();
        $endString = $end->toDateTimeString();

        return DB::table('ticket_customers as tc')
            ->join(
                'ticket_incidents as ti',
                'ti.ticket_id',
                '=',
                'tc.ticket_id'
            )
            ->join(
                'ticket_categories as categories',
                'categories.id',
                '=',
                'ti.kendala_id'
            )
            ->where('tc.online_billing_id', $onlineBillingId)
            ->where('categories.is_downtime', true)

            // Insiden harus sudah dimulai sebelum periode berakhir.
            ->where('ti.reported_at', '<', $endString)

            // Pending jika belum selesai atau belum memiliki resolution.
            ->where(function ($query) use ($startString) {
                $query->whereNull('ti.resolved_at')

                    ->orWhere(function ($historical) use ($startString) {
                        $historical
                            ->whereNotNull('ti.resolved_at')
                            ->whereNull('ti.resolution')

                            // Historical harus beririsan dengan periode.
                            ->where(
                                'ti.resolved_at',
                                '>',
                                $startString
                            );
                    });
            })

            ->distinct()
            ->count('ti.id');
    }

    /**
     * Gabungkan interval [start, end) yang bertumpang tindih.
     *
     * Setiap interval berbentuk [timestampMulai, timestampSelesai].
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort(
            $intervals,
            fn(array $a, array $b) => $a[0] <=> $b[0]
        );

        $merged = [];

        foreach ($intervals as [$from, $to]) {
            if ($to <= $from) {
                continue;
            }

            $lastIndex = count($merged) - 1;

            if (
                $lastIndex >= 0
                && $from <= $merged[$lastIndex][1]
            ) {
                $merged[$lastIndex][1] = max(
                    $merged[$lastIndex][1],
                    $to
                );
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }

    /**
     * Cari satu aturan yang mencakup seluruh periode laporan.
     *
     * Jika periode melintasi pergantian aturan,
     * laporan harus dibagi menjadi beberapa periode.
     */
    private function resolveRuleForPeriod(
        int $pelangganId,
        string $scope,
        ?int $onlineBillingId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): ?SlaRule {
        $lastMoment = $end->subSecond();

        $query = SlaRule::query()
            ->where('pelanggan_id', $pelangganId)
            ->where('scope', $scope)
            ->whereDate(
                'effective_from',
                '<=',
                $lastMoment->toDateString()
            )
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_until')
                    ->orWhereDate(
                        'effective_until',
                        '>=',
                        $start->toDateString()
                    );
            });

        if ($scope === 'global') {
            $query->whereNull('online_billing_id');
        } else {
            $query->where(
                'online_billing_id',
                $onlineBillingId
            );
        }

        $rules = $query
            ->orderBy('effective_from')
            ->limit(2)
            ->get();

        if ($rules->count() > 1) {
            throw new LogicException(
                'Terdapat beberapa aturan SLA yang '
                    . 'bersinggungan dengan periode laporan. '
                    . 'Periksa effective_from dan effective_until.'
            );
        }

        $rule = $rules->first();

        if (!$rule) {
            return null;
        }

        $ruleStart = CarbonImmutable::parse(
            $rule->effective_from
        )->startOfDay();

        $ruleEnd = $rule->effective_until !== null
            ? CarbonImmutable::parse(
                $rule->effective_until
            )->endOfDay()
            : null;

        if (
            $ruleStart->greaterThan($start)
            || (
                $ruleEnd !== null
                && $ruleEnd->lessThan($lastMoment)
            )
        ) {
            throw new LogicException(
                'Satu aturan SLA tidak mencakup seluruh '
                    . 'periode laporan. Pisahkan perhitungan '
                    . 'berdasarkan periode berlakunya aturan.'
            );
        }

        return $rule;
    }

    /**
     * Availability dihitung dalam detik agar
     * tidak kehilangan presisi sebelum pembulatan.
     */
    private function calculateAvailability(
        int $totalSeconds,
        int $downtimeSeconds,
    ): ?float {
        if ($totalSeconds <= 0) {
            return null;
        }

        $downtimeSeconds = min(
            max(0, $downtimeSeconds),
            $totalSeconds
        );

        return round(
            (
                ($totalSeconds - $downtimeSeconds)
                / $totalSeconds
            ) * 100,
            4
        );
    }

    private function isSlaMet(
        ?float $availability,
        ?float $target,
    ): ?bool {
        if ($availability === null || $target === null) {
            return null;
        }

        return $availability >= $target;
    }

    /**
     * Pastikan periode valid dan memiliki durasi positif.
     */
    private function normalizePeriod(
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): array {
        $start = CarbonImmutable::instance($periodStart);
        $end = CarbonImmutable::instance($periodEnd);

        if (!$end->greaterThan($start)) {
            throw new InvalidArgumentException(
                'Waktu akhir periode harus setelah waktu mulai.'
            );
        }

        $periodSeconds =
            $end->getTimestamp() - $start->getTimestamp();

        return [$start, $end, $periodSeconds];
    }
}

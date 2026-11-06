<?php

namespace App\Exports;

use App\Exports\Sheets\TicketDisruptionArraySheet;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TicketDisruptionReportExport implements WithMultipleSheets
{
    protected array $report;

    protected int $month;

    protected int $year;

    public function __construct(
        array $report,
        int $month,
        int $year
    ) {
        $this->report = $report;
        $this->month = $month;
        $this->year = $year;
    }

    public function sheets(): array
    {
        return [
            new TicketDisruptionArraySheet(
                'Summary',
                $this->summaryRows(),
                [1, 7],
                ['A' => 35, 'B' => 90],
                false,
                'A8'
            ),

            new TicketDisruptionArraySheet(
                'Tren Harian',
                $this->dailyRows(),
                [1],
                ['A' => 20, 'B' => 20, 'C' => 20]
            ),

            new TicketDisruptionArraySheet(
                'Rekap Kendala',
                $this->categoryRows(),
                [1],
                [
                    'A' => 32,
                    'B' => 16,
                    'C' => 16,
                    'D' => 16,
                    'E' => 16,
                    'F' => 20,
                    'G' => 30,
                ]
            ),

            new TicketDisruptionArraySheet(
                'Ranking Site',
                $this->siteRows(),
                [1],
                [
                    'A' => 32,
                    'B' => 32,
                    'C' => 25,
                    'D' => 30,
                    'E' => 16,
                    'F' => 16,
                    'G' => 30,
                ]
            ),

            new TicketDisruptionArraySheet(
                'Ranking Instansi',
                $this->instansiRows(),
                [1],
                [
                    'A' => 35,
                    'B' => 16,
                    'C' => 16,
                    'D' => 16,
                    'E' => 30,
                ]
            ),

            new TicketDisruptionArraySheet(
                'Detail Insiden',
                $this->incidentRows(),
                [1],
                [
                    'A' => 20,
                    'B' => 15,
                    'C' => 20,
                    'D' => 22,
                    'E' => 45,
                    'F' => 22,
                    'G' => 22,
                    'H' => 25,
                    'I' => 65,
                ],
                true,
                'A2',
                ['I'],
                true
            ),
        ];
    }

    private function summaryRows(): array
    {
        $summary = $this->report['summary'] ?? [];

        $monthName = CarbonImmutable::create(
            $this->year,
            $this->month,
            1
        )->locale('id')->translatedFormat('F');

        return [
            ['LAPORAN TIKET DAN KENDALA'],

            ['Periode Laporan', $monthName . ' ' . $this->year],

            [
                'Diekspor Pada',
                CarbonImmutable::now(config('app.timezone'))
                    ->format('d/m/Y H:i:s'),
            ],

            [
                'Dasar Periode',
                'Tanggal pelaporan insiden',
            ],

            [
                'Keterangan Durasi',
                'Durasi dihitung dari waktu pelaporan sampai waktu penyelesaian. '
                    . 'Insiden yang belum selesai dihitung sampai waktu export.',
            ],

            [],

            ['RINGKASAN', 'JUMLAH'],

            [
                'Total Tiket',
                $summary['total_tickets'] ?? 0,
            ],

            [
                'Total Insiden',
                $summary['total_incidents'] ?? 0,
            ],

            [
                'Site Terdampak',
                $summary['affected_sites']
                    ?? $summary['total_sites']
                    ?? 0,
            ],

            [
                'Instansi Terdampak',
                $summary['affected_instansi']
                    ?? $summary['total_instansi']
                    ?? 0,
            ],

            [
                'Total Kategori Kendala',
                $summary['total_categories'] ?? 0,
            ],

            [
                'Site Tanpa Instansi',
                $summary['sites_without_instansi'] ?? 0,
            ],

            [
                'Total Durasi Penanganan',
                $this->formatDuration(
                    $summary['total_duration_seconds'] ?? 0
                ),
            ],
        ];
    }

    private function dailyRows(): array
    {
        $rows = [
            ['Tanggal', 'Hari', 'Total Insiden'],
        ];

        foreach ($this->report['daily_trend'] ?? [] as $item) {
            $date = $item['date'] ?? '';

            $rows[] = [
                $date,
                $date ? CarbonImmutable::parse($date)->locale('id')->translatedFormat('l') : '-',
                (int) ($item['incident_count'] ?? 0),
            ];
        }

        return $rows;
    }

    private function categoryRows(): array
    {
        $rows = [
            [
                'Kategori Kendala',
                'Total Tiket',
                'Total Insiden',
                'Jumlah Site',
                'Jumlah Instansi',
                'Termasuk Downtime',
                'Total Durasi Penanganan',
            ],
        ];

        foreach ($this->report['category_ranking'] ?? [] as $item) {
            $rows[] = [
                $this->safeText($item['category_name'] ?? '-'),
                (int) ($item['ticket_count'] ?? 0),
                (int) ($item['incident_count'] ?? 0),
                (int) ($item['site_count'] ?? 0),
                (int) ($item['instansi_count'] ?? 0),
                !empty($item['is_downtime']) ? 'Ya' : 'Tidak',
                $this->formatDuration($item['duration_seconds'] ?? 0),
            ];
        }

        return $rows;
    }

    private function siteRows(): array
    {
        $rows = [
            [
                'Nama Site',
                'Pelanggan',
                'No. Jaringan',
                'Instansi',
                'Total Tiket',
                'Total Insiden',
                'Total Durasi Penanganan',
            ],
        ];

        foreach ($this->report['site_ranking'] ?? [] as $item) {
            $rows[] = [
                $this->safeText($item['site_name'] ?? '-'),
                $this->safeText($item['customer_name'] ?? '-'),
                $this->safeText($item['no_jaringan'] ?? '-'),
                $this->safeText($item['instansi_name'] ?? '-'),
                (int) ($item['ticket_count'] ?? 0),
                (int) ($item['incident_count'] ?? 0),
                $this->formatDuration($item['duration_seconds'] ?? 0),
            ];
        }

        return $rows;
    }

    private function instansiRows(): array
    {
        $rows = [
            [
                'Nama Instansi',
                'Jumlah Site',
                'Total Tiket',
                'Total Insiden',
                'Total Durasi Penanganan',
            ],
        ];

        foreach ($this->report['instansi_ranking'] ?? [] as $item) {
            $rows[] = [
                $this->safeText($item['instansi_name'] ?? '-'),
                (int) ($item['site_count'] ?? 0),
                (int) ($item['ticket_count'] ?? 0),
                (int) ($item['incident_count'] ?? 0),
                $this->formatDuration($item['duration_seconds'] ?? 0),
            ];
        }

        return $rows;
    }

    private function incidentRows(): array
    {
        $rows = [
            [
                'No. Tiket',
                'No. Insiden',
                'Status Tiket',
                'Kategori Kendala',
                'Site Terdampak',
                'Dilaporkan',
                'Diselesaikan',
                'Durasi Penanganan',
                'RFO / Penyebab Gangguan',
            ],
        ];

        foreach ($this->report['incident_details'] ?? [] as $item) {
            // Data site terdampak.
            $sites = [];

            foreach ($item['sites'] ?? [] as $site) {
                $siteName = $site['site_name'] ?? 'Site tanpa nama';

                $noJaringan = $site['no_jaringan'] ?? null;

                if ($noJaringan) {
                    $siteName .= ' (' . $noJaringan . ')';
                }

                $sites[] = $siteName;
            }

            // Data RFO.
            $rfoTexts = [];

            foreach ($item['rfos'] ?? [] as $rfo) {
                $rfoNumber = $rfo['rfo_number'] ?? 'RFO tanpa nomor';

                $content = trim($rfo['content'] ?? '');

                $createdAt = !empty($rfo['created_at'])
                    ? $this->formatDate($rfo['created_at'])
                    : '-';

                $rfoTexts[] = implode("\n", [
                    'Nomor: ' . $rfoNumber,
                    'Dibuat: ' . $createdAt,
                    'Isi RFO:',
                    $content ?: '-',
                ]);
            }

            // Gabungkan seluruh riwayat RFO dalam satu sel.
            $rfoText = !empty($rfoTexts)
                ? implode("\n\n------------------------------\n\n", $rfoTexts)
                : 'Belum ada RFO';

            $reportedAt = $item['reported_at'] ?? null;
            $resolvedAt = $item['resolved_at'] ?? null;

            $rows[] = [
                $this->safeText($item['ticket_number'] ?? '-'),

                isset($item['incident_number'])
                    ? '#' . $item['incident_number']
                    : '-',

                $this->safeText($item['ticket_status'] ?? '-'),

                $this->safeText(
                    $item['category_name'] ?? 'Tanpa kategori'
                ),

                $this->safeText(
                    implode('; ', $sites)
                ),

                $this->formatDate($reportedAt),

                $this->formatDate($resolvedAt),

                $this->formatDuration(
                    $item['duration_seconds'] ?? 0
                ),

                $this->safeText($rfoText),
            ];
        }

        return $rows;
    }
    private function formatDuration(int|float|string|null $seconds): string
    {
        $remaining = max(0, (int) ($seconds ?? 0));

        $days = intdiv($remaining, 86400);
        $remaining %= 86400;

        $hours = intdiv($remaining, 3600);
        $remaining %= 3600;

        $minutes = intdiv($remaining, 60);
        $secs = $remaining % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' hari';
        }

        if ($hours > 0) {
            $parts[] = $hours . ' jam';
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' menit';
        }

        if (empty($parts)) {
            $parts[] = $secs . ' detik';
        }

        return implode(' ', $parts);
    }

    private function formatDate(?string $date): string
    {
        if (!$date) {
            return 'Belum selesai';
        }

        return CarbonImmutable::parse($date)
            ->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');
    }

    /**
     * Mencegah teks dari data pengguna diperlakukan sebagai formula Excel.
     */
    private function safeText(mixed $value): string
    {
        $text = trim((string) ($value ?? '-'));

        if ($text === '') {
            return '-';
        }

        if (preg_match('/^[=+\-@\t\r]/u', $text)) {
            return "'" . $text;
        }

        return $text;
    }
}

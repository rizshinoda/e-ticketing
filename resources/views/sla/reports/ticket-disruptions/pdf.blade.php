<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <title>Laporan Tiket dan Kendala</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 22px 25px 25px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #252525;
            line-height: 1.45;
        }

        .header {
            width: 100%;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 19px;
            font-weight: bold;
            color: #172554;
        }

        .header-subtitle {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
        }

        .header-meta {
            text-align: right;
            font-size: 9px;
            color: #475569;
        }

        .section {
            margin-bottom: 17px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            padding: 7px 9px;
            background: #eff6ff;
            border-left: 3px solid #2563eb;
            margin-bottom: 8px;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-left: -6px;
            margin-right: -6px;
        }

        .summary-card {
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            padding: 10px;
            width: 16.66%;
            vertical-align: top;
        }

        .summary-label {
            color: #64748b;
            font-size: 8px;
        }

        .summary-value {
            color: #172554;
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
        }

        .summary-description {
            color: #64748b;
            font-size: 7px;
            margin-top: 3px;
        }

        .info {
            padding: 9px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-top: 5px;
        }

        .info-label {
            color: #64748b;
            font-size: 8px;
        }

        .info-value {
            color: #172554;
            font-size: 15px;
            font-weight: bold;
            margin-top: 3px;
        }

        .note {
            color: #64748b;
            font-size: 8px;
            margin-top: 6px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .table th {
            background: #1e293b;
            color: #ffffff;
            font-weight: bold;
            padding: 7px 6px;
            text-align: left;
            font-size: 8px;
            border: 1px solid #1e293b;
        }

        .table td {
            padding: 6px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 8px;
            overflow-wrap: break-word;
        }

        .table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-muted {
            color: #64748b;
        }

        .nowrap {
            white-space: nowrap;
        }

        .page-break {
            page-break-before: always;
        }

        .chart-container {
            border: 1px solid #e2e8f0;
            padding: 8px;
        }

        .chart {
            width: 100%;
            height: 165px;
        }

        .footer-note {
            margin-top: 10px;
            padding-top: 7px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 8px;
        }

        .empty {
            text-align: center;
            color: #64748b;
            padding: 15px !important;
        }

        .status-open {
            color: #b45309;
            font-weight: bold;
        }

        .status-closed {
            color: #15803d;
            font-weight: bold;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    @php
    use Carbon\CarbonImmutable;

    $summary = $report['summary'] ?? [];

    $categories = $report['category_ranking'] ?? [];
    $sites = $report['site_ranking'] ?? [];
    $instansis = $report['instansi_ranking'] ?? [];
    $dailyTrend = $report['daily_trend'] ?? [];
    $incidents = $report['incident_details'] ?? [];

    $periodLabel = CarbonImmutable::create(
    $year,
    $month,
    1
    )->locale('id')->translatedFormat('F Y');

    $formatDuration = function ($seconds) {
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
    };

    $formatDate = function ($value) {
    if (!$value) {
    return 'Belum selesai';
    }

    return CarbonImmutable::parse($value)
    ->timezone(config('app.timezone'))
    ->format('d/m/Y H:i');
    };

    // Data grafik tren harian.
    $chartWidth = 1000;
    $chartHeight = 180;
    $chartLeft = 42;
    $chartRight = 12;
    $chartTop = 12;
    $chartBottom = 145;

    $chartPlotWidth = $chartWidth - $chartLeft - $chartRight;
    $chartPlotHeight = $chartBottom - $chartTop;

    $chartValues = array_map(
    fn ($item) => max(0, (int) ($item['incident_count'] ?? 0)),
    $dailyTrend
    );

    $chartMax = max(0, ...$chartValues);
    $chartStep = max(1, (int) ceil(max(1, $chartMax) / 4));
    $chartAxisMax = $chartStep * 4;

    $chartPoints = [];
    $chartPointStrings = [];

    $dailyCount = count($dailyTrend);

    foreach ($dailyTrend as $index => $item) {
    $value = max(0, (int) ($item['incident_count'] ?? 0));

    $x = $dailyCount > 1
    ? $chartLeft + ($index / ($dailyCount - 1)) * $chartPlotWidth
    : $chartLeft + ($chartPlotWidth / 2);

    $y = $chartBottom - ($value / $chartAxisMax) * $chartPlotHeight;

    $chartPoints[] = [
    'x' => $x,
    'y' => $y,
    'value' => $value,
    'day' => (int) substr($item['date'], -2),
    ];

    $chartPointStrings[] = round($x, 2) . ',' . round($y, 2);
    }

    $chartLine = implode(' ', $chartPointStrings);

    // =====================================================
    // DATA GRAFIK TREN INSIDEN HARIAN
    // =====================================================

    $chartWidth = 1000;
    $chartHeight = 180;

    $chartLeft = 45;
    $chartRight = 15;
    $chartTop = 12;
    $chartBottom = 145;

    $chartPlotWidth = $chartWidth - $chartLeft - $chartRight;
    $chartPlotHeight = $chartBottom - $chartTop;

    $chartValues = array_map(
    fn ($item) => max(0, (int) ($item['incident_count'] ?? 0)),
    $dailyTrend
    );

    $chartMax = max(0, ...$chartValues);

    $chartStep = max(
    1,
    (int) ceil(max(1, $chartMax) / 4)
    );

    $chartAxisMax = $chartStep * 4;

    $dailyCount = count($dailyTrend);

    $chartPoints = [];

    // Membuat koordinat setiap tanggal.
    foreach ($dailyTrend as $index => $item) {
    $value = max(
    0,
    (int) ($item['incident_count'] ?? 0)
    );

    $day = (int) substr($item['date'], -2);

    $x = $dailyCount > 1
    ? $chartLeft + ($index / ($dailyCount - 1)) * $chartPlotWidth
    : $chartLeft + ($chartPlotWidth / 2);

    $y = $chartBottom
    - ($value / $chartAxisMax) * $chartPlotHeight;

    $chartPoints[] = [
    'x' => round($x, 2),
    'y' => round($y, 2),
    'value' => $value,
    'day' => $day,
    'show_label' => $day === 1
    || $day % 5 === 0
    || $index === $dailyCount - 1,
    ];
    }

    // Membuat garis grafik melengkung.
    $linePath = '';

    if (count($chartPoints) === 1) {
    $linePath = sprintf(
    'M %s %s',
    $chartPoints[0]['x'],
    $chartPoints[0]['y']
    );
    } elseif (count($chartPoints) > 1) {
    $linePath = sprintf(
    'M %s %s',
    $chartPoints[0]['x'],
    $chartPoints[0]['y']
    );

    for ($i = 0; $i < count($chartPoints) - 1; $i++) {
        $p0=$chartPoints[max(0, $i - 1)];
        $p1=$chartPoints[$i];
        $p2=$chartPoints[$i + 1];
        $p3=$chartPoints[
        min(count($chartPoints) - 1, $i + 2)
        ];

        $cp1x=$p1['x'] + ($p2['x'] - $p0['x']) / 6;
        $cp1y=$p1['y'] + ($p2['y'] - $p0['y']) / 6;

        $cp2x=$p2['x'] - ($p3['x'] - $p1['x']) / 6;
        $cp2y=$p2['y'] - ($p3['y'] - $p1['y']) / 6;

        $linePath .=sprintf( ' C %.2f %.2f, %.2f %.2f, %.2f %.2f' ,
        $cp1x,
        $cp1y,
        $cp2x,
        $cp2y,
        $p2['x'],
        $p2['y']
        );
        }
        }

        // Area di bawah garis.
        $areaPath='' ;

        if (count($chartPoints)> 0) {
        $firstPoint = $chartPoints[0];
        $lastPoint = $chartPoints[count($chartPoints) - 1];

        $areaPath = $linePath
        . sprintf(' L %s %s', $lastPoint['x'], $chartBottom)
        . sprintf(' L %s %s', $firstPoint['x'], $chartBottom)
        . ' Z';
        }

        // Membuat SVG sebagai string.
        // SVG akan dimasukkan sebagai gambar Data URI,
        // bukan SVG inline biasa.
        $svg = [];

        $svg[] = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 %s %s">',
            $chartWidth,
            $chartHeight,
            $chartWidth,
            $chartHeight
            );

            // Grid horizontal dan label sumbu Y.
            for ($i = 0; $i <= 4; $i++) {
                $tickValue=$chartAxisMax - ($i * $chartStep);

                $gridY=$chartTop + ($i / 4) * $chartPlotHeight;

                $svg[]=sprintf( '<line x1="%s" y1="%.2f" x2="%s" y2="%.2f" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="4 4"/>' ,
                $chartLeft,
                $gridY,
                $chartWidth - $chartRight,
                $gridY
                );

                $svg[]=sprintf( '<text x="%s" y="%.2f" text-anchor="end" font-family="DejaVu Sans" font-size="12" fill="#64748b">%s</text>' ,
                $chartLeft - 10,
                $gridY + 4,
                $tickValue
                );
                }

                // Area gradient sederhana.
                if ($areaPath !=='' ) {
                $svg[]=sprintf( '<path d="%s" fill="#3b82f6" fill-opacity="0.15"/>' ,
                $areaPath
                );
                }

                // Garis grafik.
                if ($linePath !=='' ) {
                $svg[]=sprintf( '<path d="%s" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' ,
                $linePath
                );
                }

                // Titik data dan angka insiden.
                foreach ($chartPoints as $point) {
                if ($point['value']> 0) {
                $svg[] = sprintf(
                '
                <circle cx="%s" cy="%s" r="4" fill="#2563eb" />',
                $point['x'],
                $point['y']
                );

                $labelY = max(
                $chartTop + 12,
                $point['y'] - 9
                );

                $svg[] = sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-family="DejaVu Sans" font-size="11" font-weight="bold" fill="#1e40af">%s</text>',
                $point['x'],
                $labelY,
                $point['value']
                );
                }

                // Label tanggal.
                if ($point['show_label']) {
                $svg[] = sprintf(
                '<text x="%s" y="168" text-anchor="middle" font-family="DejaVu Sans" font-size="11" fill="#64748b">%s</text>',
                $point['x'],
                $point['day']
                );
                }
                }

                $svg[] = '</svg>';

        $chartSvg = implode('', $svg);

        $chartDataUri = $chartSvg !== ''
        ? 'data:image/svg+xml;base64,' . base64_encode($chartSvg)
        : null;
        @endphp

        {{-- HEADER --}}
        <div class="header">
            <table class="header-table">
                <tr>
                    <td>
                        <div class="header-title">
                            LAPORAN TIKET DAN KENDALA
                        </div>

                        <div class="header-subtitle">
                            Rekapitulasi tiket, insiden, site, dan instansi terdampak
                        </div>
                    </td>

                    <td class="header-meta">
                        <strong>Periode: {{ $periodLabel }}</strong>
                        <br>
                        Dicetak:
                        {{ $generatedAt->format('d/m/Y H:i:s') }}
                    </td>
                </tr>
            </table>
        </div>

        {{-- RINGKASAN --}}
        <div class="section">
            <div class="section-title">
                Ringkasan Laporan
            </div>

            <table class="summary-table">
                <tr>
                    <td class="summary-card">
                        <div class="summary-label">Total Tiket</div>
                        <div class="summary-value">
                            {{ number_format($summary['total_tickets'] ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="summary-description">
                            Tiket unik
                        </div>
                    </td>

                    <td class="summary-card">
                        <div class="summary-label">Total Insiden</div>
                        <div class="summary-value">
                            {{ number_format($summary['total_incidents'] ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="summary-description">
                            Insiden unik
                        </div>
                    </td>

                    <td class="summary-card">
                        <div class="summary-label">Site Terdampak</div>
                        <div class="summary-value">
                            {{ number_format($summary['affected_sites'] ?? $summary['total_sites'] ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="summary-description">
                            Site unik
                        </div>
                    </td>

                    <td class="summary-card">
                        <div class="summary-label">Instansi Terdampak</div>
                        <div class="summary-value">
                            {{ number_format($summary['affected_instansi'] ?? $summary['total_instansi'] ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="summary-description">
                            Instansi unik
                        </div>
                    </td>

                    <td class="summary-card">
                        <div class="summary-label">Kategori Kendala</div>
                        <div class="summary-value">
                            {{ number_format($summary['total_categories'] ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="summary-description">
                            Seluruh kategori
                        </div>
                    </td>

                    <td class="summary-card">
                        <div class="summary-label">Site Tanpa Instansi</div>
                        <div class="summary-value">
                            {{ number_format($summary['sites_without_instansi'] ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="summary-description">
                            Belum terhubung
                        </div>
                    </td>
                </tr>
            </table>

            <div class="info">
                <div class="info-label">
                    Total Durasi Penanganan Seluruh Insiden
                </div>

                <div class="info-value">
                    {{ $formatDuration($summary['total_duration_seconds'] ?? 0) }}
                </div>
            </div>

            <div class="note">
                Periode laporan berdasarkan tanggal pelaporan insiden (reported_at).
                Durasi penanganan dihitung menggunakan hasil perhitungan service laporan.
                Durasi ini bukan perhitungan downtime SLA.
            </div>
        </div>

        {{-- TREN HARIAN --}}
        {{-- TREN HARIAN --}}
        <div class="section">
            <div class="section-title">
                Tren Insiden Harian
            </div>

            <div class="chart-container">
                @if($chartDataUri)
                <img
                    src="{{ $chartDataUri }}"
                    alt="Grafik Tren Insiden Harian"
                    style="
                    display: block;
                    width: 100%;
                    height: 165px;
                ">
                @else
                <p class="empty">
                    Belum ada data insiden pada periode ini.
                </p>
                @endif
            </div>

            <div class="note">
                Grafik menunjukkan jumlah insiden berdasarkan tanggal reported_at.
            </div>
        </div>

        {{-- REKAP KENDALA --}}
        <div class="section">
            <div class="section-title">
                Rekap Kendala
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Kategori Kendala</th>
                        <th class="text-right">Total Tiket</th>
                        <th class="text-right">Total Insiden</th>
                        <th class="text-right">Jumlah Site</th>
                        <th class="text-right">Jumlah Instansi</th>
                        <th>Jenis Downtime</th>
                        <th>Total Durasi Penanganan</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($categories as $item)
                    <tr>
                        <td>{{ $item['category_name'] ?? 'Tanpa kategori' }}</td>

                        <td class="text-right">
                            {{ number_format($item['ticket_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item['incident_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item['site_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item['instansi_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td>
                            {{ !empty($item['is_downtime']) ? 'Ya' : 'Tidak' }}
                        </td>

                        <td>
                            {{ $formatDuration($item['duration_seconds'] ?? 0) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="empty">
                            Belum ada data kategori kendala.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- RANKING SITE DAN INSTANSI --}}
        <div class="page-break"></div>

        <div class="header">
            <div class="header-title">
                ANALISIS SITE DAN INSTANSI
            </div>

            <div class="header-subtitle">
                Periode {{ $periodLabel }}
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                Site Paling Sering Mengalami Kendala
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Site</th>
                        <th>Pelanggan</th>
                        <th>No. Jaringan</th>
                        <th>Instansi</th>
                        <th class="text-right">Total Tiket</th>
                        <th class="text-right">Total Insiden</th>
                        <th>Total Durasi Penanganan</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($sites as $item)
                    <tr>
                        <td>{{ $item['site_name'] ?? '-' }}</td>
                        <td>{{ $item['customer_name'] ?? '-' }}</td>
                        <td>{{ $item['no_jaringan'] ?? '-' }}</td>
                        <td>{{ $item['instansi_name'] ?? '-' }}</td>

                        <td class="text-right">
                            {{ number_format($item['ticket_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item['incident_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td>
                            {{ $formatDuration($item['duration_seconds'] ?? 0) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="empty">
                            Belum ada data site terdampak.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">
                Instansi Paling Sering Mengalami Kendala
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Instansi</th>
                        <th class="text-right">Jumlah Site</th>
                        <th class="text-right">Total Tiket</th>
                        <th class="text-right">Total Insiden</th>
                        <th>Total Durasi Penanganan</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($instansis as $item)
                    <tr>
                        <td>{{ $item['instansi_name'] ?? '-' }}</td>

                        <td class="text-right">
                            {{ number_format($item['site_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item['ticket_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item['incident_count'] ?? 0, 0, ',', '.') }}
                        </td>

                        <td>
                            {{ $formatDuration($item['duration_seconds'] ?? 0) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="empty">
                            Belum ada data instansi terdampak.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- DETAIL INSIDEN --}}
        <div class="page-break"></div>

        <div class="header">
            <div class="header-title">
                DETAIL TIKET DAN INSIDEN
            </div>

            <div class="header-subtitle">
                Seluruh insiden yang dilaporkan pada {{ $periodLabel }}
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 11%;">No. Tiket</th>
                    <th style="width: 7%;">No. Insiden</th>
                    <th style="width: 10%;">Kategori Kendala</th>
                    <th style="width: 27%;">Site Terdampak</th>
                    <th style="width: 13%;">Dilaporkan</th>
                    <th style="width: 13%;">Diselesaikan</th>
                    <th style="width: 12%;">Durasi Penanganan</th>
                </tr>
            </thead>

            <tbody>
                @forelse($incidents as $item)
                <tr>
                    <td>
                        <strong>{{ $item['ticket_number'] ?? '-' }}</strong>
                    </td>

                    <td>
                        {{ isset($item['incident_number']) ? '#' . $item['incident_number'] : '-' }}
                    </td>

                    <td>
                        {{ $item['category_name'] ?? 'Tanpa kategori' }}
                    </td>

                    <td>
                        @foreach($item['sites'] ?? [] as $site)
                        <div>
                            {{ $site['site_name'] ?? 'Site tanpa nama' }}

                            @if(!empty($site['no_jaringan']))
                            <span class="text-muted">
                                ({{ $site['no_jaringan'] }})
                            </span>
                            @endif
                        </div>
                        @endforeach
                    </td>

                    <td class="nowrap">
                        {{ $formatDate($item['reported_at'] ?? null) }}
                    </td>

                    <td class="nowrap">
                        {{ $formatDate($item['resolved_at'] ?? null) }}
                    </td>

                    <td>
                        {{ $formatDuration($item['duration_seconds'] ?? 0) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="empty">
                        Belum ada insiden pada periode ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-note">
            Dokumen ini dihasilkan otomatis oleh sistem e-ticketing.
            Durasi penanganan bukan perhitungan downtime SLA.
        </div>

</body>

</html>
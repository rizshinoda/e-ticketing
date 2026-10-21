<!DOCTYPE html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Laporan SLA {{ $report->customer_name }}</title>
    <style>
        @page {
            margin: 28px 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.45;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 13px;
            margin: 20px 0 8px;
        }

        p {
            margin: 3px 0;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .meta td {
            width: 50%;
            padding: 5px 0;
            vertical-align: top;
        }

        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin: 10px -5px;
        }

        .cards td {
            width: 25%;
            padding: 9px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .value {
            font-size: 13px;
            font-weight: bold;
        }

        .status {
            font-weight: bold;
        }

        .success {
            color: #15803d;
        }

        .danger {
            color: #b91c1c;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.data th,
        table.data td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }

        table.data th {
            background: #f3f4f6;
            font-size: 8px;
            text-transform: uppercase;
        }

        table.data td.num,
        table.data th.num {
            text-align: right;
        }

        .note {
            border: 1px solid #d1d5db;
            padding: 9px;
            margin-top: 14px;
            font-size: 9px;
        }

        .footer {
            margin-top: 18px;
            border-top: 1px solid #d1d5db;
            padding-top: 7px;
            color: #6b7280;
            font-size: 8px;
        }

        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>

<body>
    @php
    $monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $monthName = $monthNames[(int) $report->month] ?? (string) $report->month;
    $startLabel = '1 ' . $monthName . ' ' . $report->year;
    $endDay = \Illuminate\Support\Carbon::create((int) $report->year, (int) $report->month, 1)->endOfMonth()->day;
    $endLabel = $endDay . ' ' . $monthName . ' ' . $report->year;

    $target = data_get($result, 'target_sla_percentage', $report->target_sla_percentage);
    $availability = data_get($result, 'availability_percentage', $report->availability_percentage);
    $downtime = (int) data_get($result, 'downtime_seconds', data_get($result, 'total_downtime_site_seconds', $report->downtime_seconds ?? 0));
    $serviceTime = (int) data_get($result, 'total_service_seconds', $report->total_service_seconds ?? 0);
    $isSlaMet = data_get($result, 'is_sla_met', $report->is_sla_met);
    $sites = data_get($result, 'sites', []);

    $formatDuration = function ($seconds) {
    $seconds = max(0, (int) $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;
    return number_format($hours, 0, ',', '.') . ' jam ' . $minutes . ' menit ' . $remainingSeconds . ' detik';
    };

    $formatPercent = function ($value, $decimals = 2) {
    if ($value === null || $value === '') return '-';
    return number_format((float) $value, $decimals, ',', '.') . '%';
    };
    @endphp

    <div class="header">
        <h1>Laporan SLA Bulanan</h1>
        <p class="muted">Laporan resmi berdasarkan snapshot yang telah difinalisasi.</p>
    </div>

    <table class="meta">
        <tr>
            <td>
                <p><strong>Pelanggan</strong></p>
                <p>{{ $report->customer_name }}</p>
            </td>
            <td>
                <p><strong>Cakupan SLA</strong></p>
                <p>{{ $report->scope === 'global' ? 'SLA Global' : 'SLA per Site' }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p><strong>Periode Laporan</strong></p>
                <p>{{ $startLabel }} – {{ $endLabel }}</p>
            </td>
            <td>
                <p><strong>Nomor Laporan</strong></p>
                <p>{{ $report->id }}</p>
            </td>
        </tr>
        @if ($report->scope === 'site')
        <tr>
            <td colspan="2">
                <p><strong>Site</strong></p>
                <p>{{ $report->site_name ?: data_get($result, 'site_name', 'Nama site tidak tersedia') }}</p>
            </td>
        </tr>
        @endif
    </table>

    <table class="cards">
        <tr>
            <td>
                <div class="label">Target SLA</div>
                <div class="value">{{ $formatPercent($target, 2) }}</div>
            </td>
            <td>
                <div class="label">Availability</div>
                <div class="value">{{ $formatPercent($availability, 4) }}</div>
            </td>
            <td>
                <div class="label">Total Downtime</div>
                <div class="value" style="font-size: 10px">{{ $formatDuration($downtime) }}</div>
            </td>
            <td>
                <div class="label">Total Waktu Layanan</div>
                <div class="value" style="font-size: 10px">{{ $formatDuration($serviceTime) }}</div>
            </td>
        </tr>
    </table>

    <h2>Status Pemenuhan Target SLA</h2>
    <p class="status {{ $isSlaMet ? 'success' : 'danger' }}">
        {{ $isSlaMet ? 'SLA Tercapai' : 'SLA Tidak Tercapai' }}
    </p>

    @if ($report->scope === 'global' && is_array($sites) && count($sites) > 0)
    <h2>Rincian SLA per Site</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Site</th>
                <th class="num">Target SLA</th>
                <th class="num">Waktu Layanan</th>
                <th class="num">Availability</th>
                <th class="num">Downtime</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sites as $site)
            @php
            $siteTarget = data_get($site, 'target_sla_percentage');
            $siteMet = data_get($site, 'is_sla_met');
            $siteReportStatus = data_get($site, 'report_status');
            @endphp
            <tr>
                <td>{{ data_get($site, 'site_name', 'Site tidak diketahui') }}</td>
                <td class="num">{{ $formatPercent($siteTarget, 2) }}</td>
                <td class="num nowrap">{{ $formatDuration(data_get($site, 'total_service_seconds', 0)) }}</td>
                <td class="num">{{ $formatPercent(data_get($site, 'availability_percentage'), 4) }}</td>
                <td class="num nowrap">{{ $formatDuration(data_get($site, 'downtime_seconds', 0)) }}</td>
                <td>
                    @if ($siteReportStatus === 'provisional')
                    Sementara
                    @elseif ($siteTarget === null || $siteReportStatus === 'missing_sla_rule')
                    Target belum tersedia
                    @elseif ($siteMet === true)
                    Tercapai
                    @else
                    Tidak tercapai
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="note">
        <strong>Catatan:</strong> Dokumen ini dibuat dari snapshot laporan SLA yang telah difinalisasi pada {{ $report->finalized_at ? \Illuminate\Support\Carbon::parse($report->finalized_at)->format('d-m-Y H:i') : '-' }}. Nilai dalam dokumen ini tidak dihitung ulang saat PDF diunduh.
    </div>

    <div class="footer">
        Dokumen laporan SLA resmi · ID laporan {{ $report->id }} · Dibuat pada {{ now()->format('d-m-Y H:i') }}
    </div>
</body>

</html>
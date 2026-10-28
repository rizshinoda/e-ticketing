<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

type ReportScope = 'global' | 'site' | 'instansi';

interface Customer {
    id: number;
    nama_pelanggan: string;
}

interface Instansi {
    id: number;
    nama_instansi: string;
}

interface SlaReport {
    id: number;
    report_key: string;
    pelanggan_id: number | null;
    customer_name: string;
    scope: ReportScope;
    online_billing_id: number | null;
    site_name: string | null;
    instansi_id: number | null;
    instansi_name: string | null;
    month: number;
    year: number;
    period_start: string;
    period_end: string;
    target_sla_percentage: number | string | null;
    availability_percentage: number | string | null;
    downtime_seconds: number;
    total_service_seconds: number;
    is_sla_met: boolean;
    site_count: number | null;
    finalized_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedReports {
    data: SlaReport[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Filters {
    search: string;
    scope: 'all' | ReportScope | string;
    pelanggan_id: number | string | null;
    instansi_id: number | string | null;
    month: number | string | null;
    year: number | string | null;
}

const props = defineProps<{
    reports: PaginatedReports;
    customers: Customer[];
    instansis: Instansi[];
    filters: Filters;
    currentYear: number;
}>();

const form = reactive({
    search: props.filters.search ?? '',
    scope: props.filters.scope ?? 'all',

    pelanggan_id: props.filters.pelanggan_id
        ? String(props.filters.pelanggan_id)
        : '',

    instansi_id: props.filters.instansi_id
        ? String(props.filters.instansi_id)
        : '',

    month: props.filters.month ? String(props.filters.month) : '',

    year: props.filters.year ? String(props.filters.year) : '',
});

const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

function applyFilters() {
    router.get(
        '/sla-reports/history',
        {
            search: form.search.trim() || undefined,
            scope: form.scope !== 'all' ? form.scope : undefined,
            pelanggan_id: form.pelanggan_id || undefined,
            instansi_id: form.instansi_id || undefined,
            month: form.month || undefined,
            year: form.year || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function resetFilters() {
    form.search = '';
    form.scope = 'all';
    form.pelanggan_id = '';
    form.instansi_id = '';
    form.month = '';
    form.year = '';

    applyFilters();
}

/*
|--------------------------------------------------------------------------
| Buka snapshot laporan
|--------------------------------------------------------------------------
*/

function openReport(report: SlaReport) {
    if (!report.pelanggan_id) {
        return;
    }

    router.get(
        '/sla-reports',
        {
            scope: report.scope,
            pelanggan_id: report.pelanggan_id,

            online_billing_id:
                report.scope === 'site'
                    ? (report.online_billing_id ?? undefined)
                    : undefined,

            instansi_id:
                report.scope === 'instansi'
                    ? (report.instansi_id ?? undefined)
                    : undefined,

            month: report.month,
            year: report.year,
        },
        {
            preserveScroll: true,
        },
    );
}

function goToPage(url: string | null) {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
}

/*
|--------------------------------------------------------------------------
| Formatter
|--------------------------------------------------------------------------
*/

function paginationLabel(label: string): string {
    const normalized = label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&')
        .trim();

    const lowerLabel = normalized.toLowerCase();

    if (
        lowerLabel.includes('pagination.previous') ||
        /\bprevious\b/i.test(normalized)
    ) {
        return 'Sebelumnya';
    }

    if (
        lowerLabel.includes('pagination.next') ||
        /\bnext\b/i.test(normalized)
    ) {
        return 'Berikutnya';
    }

    return normalized;
}

function formatPercent(value: number | string | null): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '-';
    }

    return `${number.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    })}%`;
}

function formatDuration(value: number | null): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const seconds = Number(value);

    if (!Number.isFinite(seconds) || seconds < 0) {
        return '-';
    }

    const totalSeconds = Math.floor(seconds);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const remainingSeconds = totalSeconds % 60;

    if (hours > 0) {
        return `${hours.toLocaleString('id-ID')} jam ${minutes} menit`;
    }

    if (minutes > 0) {
        return `${minutes} menit ${remainingSeconds} detik`;
    }

    return `${remainingSeconds} detik`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function monthLabel(month: number): string {
    return months.find((item) => item.value === month)?.label ?? String(month);
}

function periodLabel(report: SlaReport): string {
    const lastDay = new Date(report.year, report.month, 0).getDate();

    const month = monthLabel(report.month);

    return `1 ${month} ${report.year} – ${lastDay} ${month} ${report.year}`;
}

function scopeLabel(scope: ReportScope): string {
    switch (scope) {
        case 'global':
            return 'Global Pelanggan';

        case 'site':
            return 'Per Site';

        case 'instansi':
            return 'Per Instansi';

        default:
            return scope;
    }
}

function scopeClass(scope: ReportScope): string {
    switch (scope) {
        case 'global':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300';

        case 'site':
            return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-300';

        case 'instansi':
            return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300';

        default:
            return 'border-border bg-muted text-muted-foreground';
    }
}

function secondaryLabel(report: SlaReport): string {
    if (report.scope === 'site') {
        return report.site_name || 'Nama site tidak tersedia';
    }

    if (report.scope === 'instansi') {
        return report.instansi_name || 'Nama instansi tidak tersedia';
    }

    return `${report.site_count ?? 0} site dihitung`;
}
</script>

<template>
    <div class="space-y-6 p-4 text-foreground md:p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Riwayat Laporan SLA
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Daftar snapshot laporan SLA yang sudah difinalisasi. Data di
                halaman ini tidak dihitung ulang.
            </p>
        </div>

        <!-- Filter -->
        <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Filter Riwayat</h2>

            <form class="mt-5 space-y-4" @submit.prevent="applyFilters">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <!-- Pencarian -->
                    <div>
                        <label
                            for="search"
                            class="mb-2 block text-sm font-medium"
                        >
                            Cari laporan
                        </label>

                        <input
                            id="search"
                            v-model="form.search"
                            type="search"
                            placeholder="Pelanggan, instansi, atau site..."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground"
                        />
                    </div>

                    <!-- Cakupan -->
                    <div>
                        <label
                            for="scope"
                            class="mb-2 block text-sm font-medium"
                        >
                            Cakupan SLA
                        </label>

                        <select
                            id="scope"
                            v-model="form.scope"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="all">Semua cakupan</option>

                            <option value="global">Global Pelanggan</option>

                            <option value="instansi">Per Instansi</option>

                            <option value="site">Per Site</option>
                        </select>
                    </div>

                    <!-- Pelanggan -->
                    <div>
                        <label
                            for="customer"
                            class="mb-2 block text-sm font-medium"
                        >
                            Pelanggan
                        </label>

                        <select
                            id="customer"
                            v-model="form.pelanggan_id"
                            @change="form.instansi_id = ''"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="">Semua pelanggan</option>

                            <option
                                v-for="customer in props.customers"
                                :key="customer.id"
                                :value="String(customer.id)"
                            >
                                {{ customer.nama_pelanggan }}
                            </option>
                        </select>
                    </div>

                    <!-- Instansi -->
                    <div>
                        <label
                            for="instansi"
                            class="mb-2 block text-sm font-medium"
                        >
                            Instansi
                        </label>

                        <select
                            id="instansi"
                            v-model="form.instansi_id"
                            :disabled="!props.instansis.length"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Semua instansi</option>

                            <option
                                v-for="instansi in props.instansis"
                                :key="instansi.id"
                                :value="String(instansi.id)"
                            >
                                {{ instansi.nama_instansi }}
                            </option>
                        </select>
                    </div>

                    <!-- Bulan -->
                    <div>
                        <label
                            for="month"
                            class="mb-2 block text-sm font-medium"
                        >
                            Bulan
                        </label>

                        <select
                            id="month"
                            v-model="form.month"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="">Semua bulan</option>

                            <option
                                v-for="month in months"
                                :key="month.value"
                                :value="String(month.value)"
                            >
                                {{ month.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Tahun -->
                    <div>
                        <label
                            for="year"
                            class="mb-2 block text-sm font-medium"
                        >
                            Tahun
                        </label>

                        <input
                            id="year"
                            v-model="form.year"
                            type="number"
                            min="2000"
                            :max="props.currentYear"
                            placeholder="Semua tahun"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        Terapkan Filter
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium hover:bg-muted"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </section>

        <!-- Tabel riwayat -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
        >
            <div
                class="flex flex-col gap-1 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Laporan yang Difinalisasi
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Menampilkan {{ props.reports.from ?? 0 }}–{{
                            props.reports.to ?? 0
                        }}
                        dari {{ props.reports.total }} laporan.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[1250px] table-auto text-left text-sm"
                >
                    <thead
                        class="bg-muted/60 text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="w-[60px] px-4 py-3 font-semibold">
                                No.
                            </th>

                            <th
                                class="w-[320px] min-w-[320px] px-4 py-3 font-semibold"
                            >
                                Pelanggan / Site / Instansi
                            </th>

                            <th class="w-[160px] px-4 py-3 font-semibold">
                                Cakupan
                            </th>

                            <th class="w-[250px] px-4 py-3 font-semibold">
                                Periode
                            </th>

                            <th
                                class="w-[130px] px-4 py-3 text-right font-semibold"
                            >
                                Target SLA
                            </th>

                            <th
                                class="w-[140px] px-4 py-3 text-right font-semibold"
                            >
                                Availability
                            </th>

                            <th
                                class="w-[160px] px-4 py-3 text-right font-semibold"
                            >
                                Downtime
                            </th>

                            <th class="w-[150px] px-4 py-3 font-semibold">
                                Status SLA
                            </th>

                            <th class="w-[200px] px-4 py-3 font-semibold">
                                Difinalisasi
                            </th>

                            <th
                                class="w-[200px] px-4 py-3 text-right font-semibold"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <!-- Data kosong -->
                        <tr v-if="props.reports.data.length === 0">
                            <td colspan="10" class="px-4 py-12 text-center">
                                <p class="font-medium">Belum ada laporan SLA</p>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Laporan yang sudah difinalisasi akan muncul
                                    di sini.
                                </p>
                            </td>
                        </tr>

                        <!-- Baris laporan -->
                        <tr
                            v-for="(report, index) in props.reports.data"
                            :key="report.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <td
                                class="px-4 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    (props.reports.current_page - 1) * 15 +
                                    index +
                                    1
                                }}
                            </td>

                            <!-- Pelanggan dan identitas cakupan -->
                            <td class="w-[320px] min-w-[320px] px-4 py-4">
                                <p
                                    class="text-sm leading-5 font-semibold break-words text-foreground"
                                >
                                    {{ report.customer_name }}
                                </p>

                                <p
                                    class="mt-1.5 text-sm leading-5 break-words whitespace-normal text-muted-foreground"
                                >
                                    {{ secondaryLabel(report) }}
                                </p>

                                <p
                                    v-if="
                                        report.scope === 'site' &&
                                        report.online_billing_id
                                    "
                                    class="mt-1 text-xs text-muted-foreground/80"
                                >
                                    Site ID: {{ report.online_billing_id }}
                                </p>
                            </td>

                            <!-- Cakupan -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="scopeClass(report.scope)"
                                >
                                    {{ scopeLabel(report.scope) }}
                                </span>
                            </td>

                            <!-- Periode -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <p class="font-medium text-foreground">
                                    {{ monthLabel(report.month) }}
                                    {{ report.year }}
                                </p>

                                <p
                                    class="mt-1 text-sm leading-5 text-muted-foreground"
                                >
                                    {{ periodLabel(report) }}
                                </p>
                            </td>

                            <!-- Target -->
                            <td
                                class="px-4 py-4 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatPercent(report.target_sla_percentage)
                                }}
                            </td>

                            <!-- Availability -->
                            <td
                                class="px-4 py-4 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatPercent(
                                        report.availability_percentage,
                                    )
                                }}
                            </td>

                            <!-- Downtime -->
                            <td
                                class="px-4 py-4 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ formatDuration(report.downtime_seconds) }}
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        report.is_sla_met
                                            ? 'bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-300'
                                            : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300'
                                    "
                                >
                                    {{
                                        report.is_sla_met
                                            ? 'Tercapai'
                                            : 'Tidak tercapai'
                                    }}
                                </span>
                            </td>

                            <!-- Waktu finalisasi -->
                            <td
                                class="px-4 py-4 text-sm whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(report.finalized_at) }}
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        :disabled="!report.pelanggan_id"
                                        class="rounded-md border border-border px-3 py-1.5 text-xs font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="openReport(report)"
                                    >
                                        Lihat Detail
                                    </button>

                                    <a
                                        :href="`/sla-reports/${report.id}/pdf`"
                                        class="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:opacity-90"
                                    >
                                        Unduh PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="props.reports.last_page > 1"
                class="flex flex-col gap-3 border-t border-border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ props.reports.from ?? 0 }}–{{
                        props.reports.to ?? 0
                    }}
                    dari {{ props.reports.total }} laporan. Halaman
                    {{ props.reports.current_page }} dari
                    {{ props.reports.last_page }}.
                </p>

                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="(link, index) in props.reports.links"
                        :key="`${index}-${link.label}`"
                        type="button"
                        :disabled="!link.url || link.active"
                        class="min-w-9 rounded-md border border-border px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-40"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background text-foreground hover:bg-muted'
                        "
                        @click="goToPage(link.url)"
                    >
                        {{ paginationLabel(link.label) }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

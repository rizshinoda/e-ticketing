<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

interface Customer {
    id: number;
    nama_pelanggan: string;
}

interface SlaReport {
    id: number;
    report_key: string;
    pelanggan_id: number | null;
    customer_name: string;
    scope: 'global' | 'site';
    online_billing_id: number | null;
    site_name: string | null;
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
    scope: 'all' | 'global' | 'site' | string;
    pelanggan_id: number | string | null;
    month: number | string | null;
    year: number | string | null;
}

const props = defineProps<{
    reports: PaginatedReports;
    customers: Customer[];
    filters: Filters;
    currentYear: number;
}>();

const form = reactive({
    search: props.filters.search ?? '',
    scope: props.filters.scope ?? 'all',
    pelanggan_id: props.filters.pelanggan_id
        ? String(props.filters.pelanggan_id)
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

function applyFilters() {
    router.get(
        '/sla-reports/history',
        {
            search: form.search.trim() || undefined,
            scope: form.scope !== 'all' ? form.scope : undefined,
            pelanggan_id: form.pelanggan_id || undefined,
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
    form.month = '';
    form.year = '';
    applyFilters();
}

function openReport(report: SlaReport) {
    if (!report.pelanggan_id) return;

    router.get(
        '/sla-reports',
        {
            scope: report.scope,
            pelanggan_id: report.pelanggan_id,
            online_billing_id:
                report.scope === 'site'
                    ? (report.online_billing_id ?? undefined)
                    : undefined,
            month: report.month,
            year: report.year,
        },
        { preserveScroll: true },
    );
}

function goToPage(url: string | null) {
    if (!url) return;
    router.visit(url, { preserveScroll: true });
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelumnya')
        .replace('Next &raquo;', 'Berikutnya');
}

function formatPercent(value: number | string | null): string {
    if (value === null || value === undefined || value === '') return '-';

    const number = Number(value);
    if (!Number.isFinite(number)) return '-';

    return `${number.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    })}%`;
}

function formatDuration(value: number | null): string {
    const seconds = Number(value);
    if (!Number.isFinite(seconds) || seconds < 0) return '-';

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = Math.floor(seconds % 60);

    if (hours > 0) {
        return `${hours.toLocaleString('id-ID')} jam ${minutes} menit`;
    }

    if (minutes > 0) return `${minutes} menit ${remainingSeconds} detik`;
    return `${remainingSeconds} detik`;
}

function formatDate(value: string | null): string {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

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
</script>

<template>
    <div class="space-y-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Riwayat Laporan SLA
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Daftar snapshot laporan SLA yang sudah difinalisasi. Data di
                    halaman ini tidak dihitung ulang.
                </p>
            </div>
        </div>

        <section class="rounded-xl border bg-card p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Filter Riwayat</h2>

            <form class="mt-5 space-y-4" @submit.prevent="applyFilters">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label
                            for="search"
                            class="mb-2 block text-sm font-medium"
                        >
                            Cari pelanggan atau site
                        </label>
                        <input
                            id="search"
                            v-model="form.search"
                            type="search"
                            placeholder="Nama pelanggan atau site..."
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>

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
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="all">Semua cakupan</option>
                            <option value="global">SLA Global</option>
                            <option value="site">SLA per Site</option>
                        </select>
                    </div>

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
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Semua pelanggan</option>
                            <option
                                v-for="customer in customers"
                                :key="customer.id"
                                :value="String(customer.id)"
                            >
                                {{ customer.nama_pelanggan }}
                            </option>
                        </select>
                    </div>

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
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
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
                            :max="currentYear"
                            placeholder="Semua tahun"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
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
                        class="inline-flex items-center rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div
                class="flex flex-col gap-1 border-b p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Laporan yang Difinalisasi
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Menampilkan {{ reports.from ?? 0 }}–{{
                            reports.to ?? 0
                        }}
                        dari {{ reports.total }} laporan.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead
                        class="bg-muted/60 text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3 font-semibold">No.</th>
                            <th class="px-4 py-3 font-semibold">
                                Pelanggan / Site
                            </th>
                            <th class="px-4 py-3 font-semibold">Cakupan</th>
                            <th class="px-4 py-3 font-semibold">Periode</th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Target SLA
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Availability
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Downtime
                            </th>
                            <th class="px-4 py-3 font-semibold">Status SLA</th>
                            <th class="px-4 py-3 font-semibold">
                                Difinalisasi
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr v-if="reports.data.length === 0">
                            <td colspan="10" class="px-4 py-12 text-center">
                                <p class="font-medium">Belum ada laporan SLA</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Laporan yang sudah difinalisasi akan muncul
                                    di sini.
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="(report, index) in reports.data"
                            :key="report.id"
                            class="hover:bg-muted/30"
                        >
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    (reports.current_page - 1) * 15 + index + 1
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{ report.customer_name }}
                                </p>
                                <p
                                    v-if="report.scope === 'site'"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        report.site_name ||
                                        'Nama site tidak tersedia'
                                    }}
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ report.site_count ?? 0 }} site dihitung
                                </p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{
                                        report.scope === 'global'
                                            ? 'Global'
                                            : 'Per Site'
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ monthLabel(report.month) }} {{ report.year }}
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ periodLabel(report) }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatPercent(report.target_sla_percentage)
                                }}
                            </td>
                            <td
                                class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatPercent(
                                        report.availability_percentage,
                                    )
                                }}
                            </td>
                            <td
                                class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ formatDuration(report.downtime_seconds) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        report.is_sla_met
                                            ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                                    "
                                >
                                    {{
                                        report.is_sla_met
                                            ? 'Tercapai'
                                            : 'Tidak tercapai'
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ formatDate(report.finalized_at) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        :disabled="!report.pelanggan_id"
                                        class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
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

            <div
                v-if="reports.last_page > 1"
                class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Halaman {{ reports.current_page }} dari
                    {{ reports.last_page }}
                </p>
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="(link, index) in reports.links"
                        :key="`${index}-${link.label}`"
                        type="button"
                        :disabled="!link.url"
                        class="min-w-9 rounded-md border px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
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

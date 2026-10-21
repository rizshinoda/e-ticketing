<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Customer {
    id: number;
    nama_pelanggan: string;
}

interface Site {
    id: number;
    pelanggan_id: number;
    nama_site: string;
    no_jaringan: string | null;
}

interface SlaResult {
    scope?: 'site' | 'global';
    pelanggan_id?: number;
    online_billing_id?: number | null;
    site_name?: string | null;
    target_sla_percentage?: number | string | null;
    availability_percentage?: number | string | null;
    downtime_seconds?: number | null;
    total_downtime_site_seconds?: number | null;
    total_service_seconds?: number | null;
    site_count?: number;
    is_sla_met?: boolean | null;
    report_status?:
        'provisional' | 'missing_sla_rule' | 'ready_to_finalize' | string;
    pending_incidents_count?: number;
    pending_site_incidents_count?: number;
    sites?: SlaResult[];
    nama_site?: string;
    site_id?: number;
}

interface Filters {
    scope: 'site' | 'global';
    pelanggan_id: number | string | null;
    online_billing_id: number | string | null;
    month: number | string;
    year: number | string;
}

interface FinalizedReport {
    id: number;
    report_key: string;
    scope: 'global' | 'site';
    customer_name: string;
    site_name: string | null;
    month: number;
    year: number;
    period_start: string;
    period_end: string;
    target_sla_percentage: number | string;
    availability_percentage: number | string;
    downtime_seconds: number;
    total_service_seconds: number;
    is_sla_met: boolean;
    site_count: number | null;
    finalized_at: string;
}

const props = defineProps<{
    customers: Customer[];
    sites: Site[];
    filters: Filters;
    result: SlaResult | null;
    calculationError: string | null;
    currentYear: number;
    finalizedReport: FinalizedReport | null;
    success: string | null;
}>();

const page = usePage();

const form = reactive({
    scope: props.filters.scope ?? 'global',
    pelanggan_id: props.filters.pelanggan_id
        ? String(props.filters.pelanggan_id)
        : '',
    online_billing_id: props.filters.online_billing_id
        ? String(props.filters.online_billing_id)
        : '',
    month: String(props.filters.month),
    year: String(props.filters.year),
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

const filteredSites = computed(() =>
    props.sites.filter(
        (site) => String(site.pelanggan_id) === form.pelanggan_id,
    ),
);

const selectedMonthLabel = computed(
    () =>
        months.find((month) => month.value === Number(form.month))?.label ?? '',
);

const reportPeriodLabel = computed(
    () => `${selectedMonthLabel.value} ${form.year}`,
);

const siteResults = computed(() => {
    const rows = props.result?.sites ?? [];
    return Array.isArray(rows) ? rows : [];
});

const resultMatchesForm = computed(() => {
    if (!props.result) return false;

    return (
        form.scope === props.filters.scope &&
        form.pelanggan_id === String(props.filters.pelanggan_id ?? '') &&
        form.month === String(props.filters.month) &&
        form.year === String(props.filters.year) &&
        (form.scope === 'global' ||
            form.online_billing_id ===
                String(props.filters.online_billing_id ?? ''))
    );
});

const totalDowntime = computed(() => {
    if (!props.result) return null;

    return (
        props.result.downtime_seconds ??
        props.result.total_downtime_site_seconds ??
        null
    );
});

const reportStatus = computed(() => {
    if (!props.result) return null;

    if (props.result.report_status) {
        return props.result.report_status;
    }

    const pending =
        props.result.scope === 'global'
            ? (props.result.pending_site_incidents_count ?? 0)
            : (props.result.pending_incidents_count ?? 0);

    if (pending > 0) return 'provisional';

    if (
        props.result.target_sla_percentage === null ||
        props.result.target_sla_percentage === undefined
    ) {
        return 'missing_sla_rule';
    }

    return 'ready_to_finalize';
});

const finalizeError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;
    return errors?.finalize ?? null;
});

function reloadSites() {
    form.online_billing_id = '';

    router.get(
        '/sla-reports',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id || undefined,
            month: form.month,
            year: form.year,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function submitReport() {
    router.get(
        '/sla-reports',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id,
            online_billing_id:
                form.scope === 'site' ? form.online_billing_id : undefined,
            month: form.month,
            year: form.year,
            calculate: 1,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function finalizeReport() {
    const confirmed = window.confirm(
        `Finalisasi laporan SLA ${reportPeriodLabel.value}? ` +
            'Hasil saat ini akan disimpan sebagai snapshot resmi dan tidak ' +
            'akan diperbarui otomatis.',
    );

    if (!confirmed) return;

    router.post(
        '/sla-reports/finalize',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id,
            online_billing_id:
                form.scope === 'site' ? form.online_billing_id : null,
            month: form.month,
            year: form.year,
        },
        {
            preserveScroll: true,
        },
    );
}

function formatPercent(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const number = Number(value);
    if (!Number.isFinite(number)) return '-';

    return `${number.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    })}%`;
}

function formatDuration(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const totalSeconds = Number(value);
    if (!Number.isFinite(totalSeconds) || totalSeconds < 0) return '-';

    const seconds = Math.floor(totalSeconds);
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    return `${hours} jam ${minutes} menit ${remainingSeconds} detik`;
}

function statusLabel(value: boolean | null | undefined): string {
    if (value === true) return 'Tercapai';
    if (value === false) return 'Tidak tercapai';
    return 'Target belum tersedia';
}

function formatDateTime(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <div class="space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Laporan SLA Bulanan
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Hitung, tinjau, dan finalisasi pencapaian SLA berdasarkan
                periode bulanan.
            </p>
        </div>

        <div
            v-if="success"
            class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ success }}
        </div>

        <div
            v-if="finalizeError"
            class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {{ finalizeError }}
        </div>

        <section class="rounded-xl border bg-card p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Parameter Laporan</h2>

            <form class="mt-5 space-y-5" @submit.prevent="submitReport">
                <div class="grid gap-5 md:grid-cols-2">
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
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            @change="reloadSites"
                        >
                            <option value="" disabled>Pilih pelanggan</option>
                            <option
                                v-for="customer in customers"
                                :key="customer.id"
                                :value="String(customer.id)"
                            >
                                {{ customer.nama_pelanggan }}
                            </option>
                        </select>
                    </div>

                    <div v-if="form.scope === 'site'" class="md:col-span-2">
                        <label
                            for="site"
                            class="mb-2 block text-sm font-medium"
                        >
                            Site
                        </label>

                        <select
                            id="site"
                            v-model="form.online_billing_id"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>Pilih site</option>
                            <option
                                v-for="site in filteredSites"
                                :key="site.id"
                                :value="String(site.id)"
                            >
                                {{ site.nama_site }} —
                                {{ site.no_jaringan || 'Tanpa nomor jaringan' }}
                            </option>
                        </select>

                        <p
                            v-if="
                                form.pelanggan_id && filteredSites.length === 0
                            "
                            class="mt-2 text-sm text-amber-600"
                        >
                            Tidak ditemukan site untuk pelanggan ini.
                        </p>
                    </div>

                    <div>
                        <label
                            for="month"
                            class="mb-2 block text-sm font-medium"
                        >
                            Bulan laporan
                        </label>

                        <select
                            id="month"
                            v-model="form.month"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
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
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Periode dihitung otomatis dari hari pertama sampai hari
                    terakhir bulan yang dipilih. Laporan hanya dapat dihitung
                    setelah bulan tersebut berakhir.
                </p>

                <button
                    type="submit"
                    :disabled="
                        !form.pelanggan_id ||
                        !form.month ||
                        !form.year ||
                        Number(form.year) < 2000 ||
                        Number(form.year) > currentYear ||
                        (form.scope === 'site' && !form.online_billing_id)
                    "
                    class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Tampilkan Laporan
                </button>
            </form>
        </section>

        <section
            v-if="calculationError"
            class="rounded-xl border border-red-300 bg-red-50 p-5 text-red-800 dark:bg-red-950 dark:text-red-200"
        >
            <h2 class="font-semibold">Laporan belum dapat dihitung</h2>
            <p class="mt-2 text-sm break-words">{{ calculationError }}</p>
        </section>

        <template v-if="result && resultMatchesForm">
            <section class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">
                        Laporan {{ reportPeriodLabel }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            form.scope === 'global'
                                ? 'Rekap SLA seluruh site yang dihitung oleh service.'
                                : 'Hasil perhitungan SLA untuk site yang dipilih.'
                        }}
                    </p>
                </div>

                <div
                    v-if="finalizedReport"
                    class="rounded-xl border border-green-300 bg-green-50 p-5 text-green-800 dark:bg-green-950 dark:text-green-200"
                >
                    <h3 class="font-semibold">Laporan Sudah Difinalisasi</h3>
                    <p class="mt-2 text-sm">
                        Snapshot laporan resmi sudah tersimpan. Nomor laporan:
                        {{ finalizedReport.id }}.
                    </p>
                    <p class="mt-1 text-sm">
                        Difinalisasi pada:
                        {{ formatDateTime(finalizedReport.finalized_at) }}
                    </p>
                    <p class="mt-1 text-sm">
                        Hasil yang ditampilkan berasal dari snapshot resmi,
                        bukan perhitungan ulang.
                    </p>
                </div>
                <div
                    v-else-if="reportStatus === 'provisional'"
                    class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    <h3 class="font-semibold">Laporan SLA Sementara</h3>
                    <p class="mt-2 text-sm">
                        Masih terdapat
                        {{
                            result.scope === 'global'
                                ? (result.pending_site_incidents_count ?? 0)
                                : (result.pending_incidents_count ?? 0)
                        }}
                        insiden-site downtime yang belum selesai dan berpotensi
                        memengaruhi hasil periode ini. Laporan perlu ditinjau
                        kembali setelah insiden selesai.
                    </p>
                </div>
                <div
                    v-else-if="reportStatus === 'missing_sla_rule'"
                    class="rounded-xl border border-red-300 bg-red-50 p-5 text-red-800 dark:bg-red-950 dark:text-red-200"
                >
                    <h3 class="font-semibold">Aturan SLA Belum Tersedia</h3>
                    <p class="mt-2 text-sm">
                        Tidak ditemukan aturan SLA yang mencakup seluruh periode
                        laporan. Periksa Pengaturan Target SLA.
                    </p>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-green-300 bg-green-50 p-5 text-green-800 dark:bg-green-950 dark:text-green-200"
                >
                    <h3 class="font-semibold">
                        Siap Ditinjau untuk Finalisasi
                    </h3>
                    <p class="mt-2 text-sm">
                        Aturan SLA tersedia dan tidak ada insiden downtime yang
                        belum selesai dalam pemeriksaan ini. Tinjau hasil
                        sebelum menjadikannya laporan resmi.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border bg-card p-5">
                        <p class="text-sm text-muted-foreground">Target SLA</p>
                        <p class="mt-2 text-2xl font-bold">
                            {{ formatPercent(result.target_sla_percentage) }}
                        </p>
                    </div>

                    <div class="rounded-xl border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            Availability
                        </p>
                        <p class="mt-2 text-2xl font-bold">
                            {{ formatPercent(result.availability_percentage) }}
                        </p>
                    </div>

                    <div class="rounded-xl border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            Total downtime
                        </p>
                        <p class="mt-2 text-lg font-bold">
                            {{ formatDuration(totalDowntime) }}
                        </p>
                    </div>

                    <div class="rounded-xl border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            Total waktu layanan
                        </p>
                        <p class="mt-2 text-lg font-bold">
                            {{ formatDuration(result.total_service_seconds) }}
                        </p>
                    </div>
                </div>

                <div>
                    <p>Status pemenuhan target SLA</p>

                    <p
                        v-if="reportStatus === 'provisional'"
                        class="font-semibold text-amber-500"
                    >
                        Belum Final — Menunggu Penyelesaian Insiden
                    </p>

                    <p
                        v-else-if="result?.is_sla_met"
                        class="font-semibold text-green-500"
                    >
                        SLA Tercapai
                    </p>

                    <p v-else class="font-semibold text-red-500">
                        SLA Tidak Tercapai
                    </p>
                </div>
                <div
                    v-if="siteResults.length"
                    class="overflow-hidden rounded-xl border"
                >
                    <div class="border-b px-5 py-4">
                        <h3 class="font-semibold">Rincian Site</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Status setiap site mengikuti target SLA Site
                            masing-masing. Status Global dihitung terpisah
                            menggunakan aturan Global.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-4 py-3">Site</th>
                                    <th class="px-4 py-3">Target SLA Site</th>
                                    <th class="px-4 py-3">
                                        Total Waktu Layanan
                                    </th>
                                    <th class="px-4 py-3">Availability</th>
                                    <th class="px-4 py-3">Downtime</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(site, index) in siteResults"
                                    :key="
                                        site.online_billing_id ??
                                        site.site_id ??
                                        index
                                    "
                                    class="border-t"
                                >
                                    <td class="px-4 py-3">
                                        {{
                                            site.site_name ??
                                            site.nama_site ??
                                            `Site ${index + 1}`
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            formatPercent(
                                                site.target_sla_percentage,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{
                                            formatDuration(
                                                site.total_service_seconds,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            formatPercent(
                                                site.availability_percentage,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{
                                            formatDuration(
                                                site.downtime_seconds,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="
                                                site.is_sla_met === true
                                                    ? 'text-green-600'
                                                    : site.is_sla_met === false
                                                      ? 'text-red-600'
                                                      : 'text-muted-foreground'
                                            "
                                        >
                                            {{ statusLabel(site.is_sla_met) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="finalizedReport" class="flex justify-end">
                    <span
                        class="inline-flex items-center rounded-md border px-4 py-2 text-sm text-muted-foreground"
                    >
                        <span
                            class="mr-2 inline-block size-2 rounded-full bg-green-500"
                        ></span>
                        Laporan sudah dikunci
                    </span>
                </div>
                <div
                    v-else-if="
                        reportStatus === 'ready_to_finalize' &&
                        typeof result.is_sla_met === 'boolean' &&
                        result.availability_percentage !== null &&
                        result.availability_percentage !== undefined
                    "
                    class="flex justify-end"
                >
                    <button
                        type="button"
                        @click="finalizeReport"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        Finalisasi Laporan
                    </button>
                </div>

                <details class="rounded-xl border bg-card p-5">
                    <summary class="cursor-pointer font-semibold">
                        Detail data laporan
                    </summary>
                    <pre
                        class="mt-4 overflow-x-auto rounded-md bg-muted p-4 text-xs"
                        >{{ JSON.stringify(result, null, 2) }}</pre>
                </details>
            </section>
        </template>
    </div>
</template>

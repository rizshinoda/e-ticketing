<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    confirmAction,
    showSuccessToast,
    showErrorToast,
} from '@/lib/sla-notifications';

type ReportScope = 'global' | 'site' | 'instansi';

interface Customer {
    id: number;
    nama_pelanggan: string;
}

interface Instansi {
    id: number;
    nama_instansi: string;
}

interface Site {
    id: number;
    pelanggan_id: number;
    instansi_id: number | null;
    nama_site: string;
    no_jaringan: string | null;
}

interface SlaResult {
    scope?: ReportScope;
    pelanggan_id?: number;
    online_billing_id?: number | null;
    site_name?: string | null;
    instansi_id?: number | null;
    instansi_name?: string | null;
    no_jaringan?: string | null;

    target_sla_percentage?: number | string | null;
    availability_percentage?: number | string | null;

    downtime_seconds?: number | null;
    total_downtime_site_seconds?: number | null;
    total_service_seconds?: number | null;

    site_count?: number;
    is_sla_met?: boolean | null;

    report_status?:
        | 'provisional'
        | 'missing_sla_rule'
        | 'ready_to_finalize'
        | 'no_service_period'
        | string;

    pending_incidents_count?: number;
    pending_site_incidents_count?: number;

    sites?: SlaResult[];
    site_id?: number;
    nama_site?: string;
}

interface Filters {
    scope: ReportScope;
    pelanggan_id: number | string | null;
    online_billing_id: number | string | null;
    instansi_id: number | string | null;
    month: number | string;
    year: number | string;
}

interface FinalizedReport {
    id: number;
    report_key: string;
    scope: ReportScope;
    customer_name: string;
    site_name: string | null;
    instansi_id: number | null;
    instansi_name: string | null;
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
    instansis: Instansi[];
    filters: Filters;
    result: SlaResult | null;
    calculationError: string | null;
    currentYear: number;
    finalizedReport: FinalizedReport | null;
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

    instansi_id: props.filters.instansi_id
        ? String(props.filters.instansi_id)
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

/*
|--------------------------------------------------------------------------
| Filter dan informasi laporan
|--------------------------------------------------------------------------
*/

const filteredSites = computed(() =>
    props.sites.filter(
        (site) => String(site.pelanggan_id) === form.pelanggan_id,
    ),
);

const customerInstansiIds = computed(
    () =>
        new Set(
            filteredSites.value
                .map((site) => site.instansi_id)
                .filter((id): id is number => id !== null),
        ),
);

const filteredInstansis = computed(() =>
    props.instansis.filter((instansi) =>
        customerInstansiIds.value.has(instansi.id),
    ),
);

const selectedSite = computed(() =>
    filteredSites.value.find(
        (site) => String(site.id) === form.online_billing_id,
    ),
);

const selectedInstansi = computed(() =>
    filteredInstansis.value.find(
        (instansi) => String(instansi.id) === form.instansi_id,
    ),
);

const selectedMonthLabel = computed(
    () =>
        months.find((month) => month.value === Number(form.month))?.label ?? '',
);

const reportPeriodLabel = computed(
    () => `${selectedMonthLabel.value} ${form.year}`,
);

const reportScopeLabel = computed(() => {
    switch (form.scope) {
        case 'site':
            return 'SLA Per Site';

        case 'instansi':
            return 'SLA Per Instansi';

        default:
            return 'SLA Global Pelanggan';
    }
});

const reportTitleLabel = computed(() => {
    if (form.scope === 'global') {
        return props.result?.scope === 'global'
            ? 'Seluruh Site Pelanggan'
            : 'Seluruh Site Pelanggan';
    }

    if (form.scope === 'site') {
        return (
            props.finalizedReport?.site_name ??
            props.result?.site_name ??
            selectedSite.value?.nama_site ??
            'Site yang dipilih'
        );
    }

    return (
        props.finalizedReport?.instansi_name ??
        props.result?.instansi_name ??
        selectedInstansi.value?.nama_instansi ??
        'Instansi yang dipilih'
    );
});

const targetLabel = computed(() =>
    form.scope === 'site' ? 'Target SLA Site' : 'Target SLA Global Pelanggan',
);

const siteResults = computed((): SlaResult[] => {
    const rows = props.result?.sites ?? [];

    return Array.isArray(rows) ? rows : [];
});

/*
|--------------------------------------------------------------------------
| Pastikan hasil sesuai dengan filter aktif
|--------------------------------------------------------------------------
*/

const resultMatchesForm = computed(() => {
    if (!props.result) {
        return false;
    }

    const baseMatches =
        form.scope === props.filters.scope &&
        form.pelanggan_id === String(props.filters.pelanggan_id ?? '') &&
        form.month === String(props.filters.month) &&
        form.year === String(props.filters.year);

    if (!baseMatches) {
        return false;
    }

    if (form.scope === 'global') {
        return true;
    }

    if (form.scope === 'site') {
        return (
            form.online_billing_id ===
            String(props.filters.online_billing_id ?? '')
        );
    }

    return form.instansi_id === String(props.filters.instansi_id ?? '');
});

const totalDowntime = computed(() => {
    const result = props.result;

    if (!result) {
        return null;
    }

    return (
        result.downtime_seconds ?? result.total_downtime_site_seconds ?? null
    );
});

/*
|--------------------------------------------------------------------------
| Status laporan
|--------------------------------------------------------------------------
*/

const pendingIncidentCount = computed(() => {
    const result = props.result;

    if (!result) {
        return 0;
    }

    if (result.scope === 'global' || result.scope === 'instansi') {
        return (
            result.pending_site_incidents_count ??
            result.pending_incidents_count ??
            0
        );
    }

    return result.pending_incidents_count ?? 0;
});

const reportStatus = computed(() => {
    const result = props.result;

    if (!result) {
        return null;
    }

    if (result.report_status) {
        return result.report_status;
    }

    if (pendingIncidentCount.value > 0) {
        return 'provisional';
    }

    if (
        result.target_sla_percentage === null ||
        result.target_sla_percentage === undefined
    ) {
        return 'missing_sla_rule';
    }

    if (result.availability_percentage == null) {
        return 'no_service_period';
    }

    return 'ready_to_finalize';
});

const finalizeError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return errors?.finalize ?? null;
});

/*
|--------------------------------------------------------------------------
| Aksi form
|--------------------------------------------------------------------------
*/

function handleScopeChange() {
    form.online_billing_id = '';
    form.instansi_id = '';
}

function reloadSites() {
    form.online_billing_id = '';
    form.instansi_id = '';

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
    if (form.scope === 'site' && !form.online_billing_id) {
        return;
    }

    if (form.scope === 'instansi' && !form.instansi_id) {
        return;
    }

    router.get(
        '/sla-reports',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id,

            online_billing_id:
                form.scope === 'site' ? form.online_billing_id : undefined,

            instansi_id:
                form.scope === 'instansi' ? form.instansi_id : undefined,

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

/*
|--------------------------------------------------------------------------
| Finalisasi laporan
|--------------------------------------------------------------------------
*/

async function finalizeReport() {
    const confirmed = await confirmAction({
        title: 'Finalisasi Laporan SLA?',
        text:
            `Finalisasi ${reportScopeLabel.value} ` +
            `${reportTitleLabel.value} untuk ${reportPeriodLabel.value}? ` +
            'Hasil akan disimpan sebagai snapshot resmi dan tidak diperbarui otomatis.',
        confirmButtonText: 'Ya, finalisasi',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    router.post(
        '/sla-reports/finalize',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id,

            online_billing_id:
                form.scope === 'site' ? form.online_billing_id : null,

            instansi_id: form.scope === 'instansi' ? form.instansi_id : null,

            month: form.month,
            year: form.year,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                showSuccessToast(
                    `Laporan SLA ${reportTitleLabel.value} ` +
                        `${reportPeriodLabel.value} berhasil difinalisasi.`,
                );
            },

            onError: () => {
                showErrorToast(
                    'Finalisasi laporan gagal. Periksa kembali data laporan.',
                );
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| Formatter
|--------------------------------------------------------------------------
*/

function formatPercent(value: unknown): string {
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

function formatDuration(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const totalSeconds = Number(value);

    if (!Number.isFinite(totalSeconds) || totalSeconds < 0) {
        return '-';
    }

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

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <div class="space-y-6 p-4 text-foreground md:p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Laporan SLA Bulanan
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Hitung, tinjau, dan finalisasi laporan SLA berdasarkan
                pelanggan, instansi, atau site untuk periode bulanan. Target SLA
                per instansi tetap mengikuti target global pelanggan.
            </p>
        </div>

        <!-- Error finalisasi -->
        <div
            v-if="finalizeError"
            class="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
        >
            {{ finalizeError }}
        </div>

        <!-- Parameter laporan -->
        <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Parameter Laporan</h2>

            <form class="mt-5 space-y-5" @submit.prevent="submitReport">
                <div class="grid gap-5 md:grid-cols-2">
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
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            @change="handleScopeChange"
                        >
                            <option value="global">SLA Global Pelanggan</option>

                            <option value="instansi">SLA Per Instansi</option>

                            <option value="site">SLA Per Site</option>
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
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            @change="reloadSites"
                        >
                            <option value="" disabled>Pilih pelanggan</option>

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
                    <div v-if="form.scope === 'instansi'" class="md:col-span-2">
                        <label
                            for="instansi"
                            class="mb-2 block text-sm font-medium"
                        >
                            Instansi
                        </label>

                        <select
                            id="instansi"
                            v-model="form.instansi_id"
                            required
                            :disabled="!form.pelanggan_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" disabled>Pilih instansi</option>

                            <option
                                v-for="instansi in filteredInstansis"
                                :key="instansi.id"
                                :value="String(instansi.id)"
                            >
                                {{ instansi.nama_instansi }}
                            </option>
                        </select>

                        <p
                            v-if="
                                form.pelanggan_id &&
                                filteredInstansis.length === 0
                            "
                            class="mt-2 text-sm text-amber-600 dark:text-amber-300"
                        >
                            Tidak ditemukan instansi dengan site pada pelanggan
                            ini.
                        </p>

                        <p class="mt-2 text-xs text-muted-foreground">
                            Seluruh site milik instansi terpilih akan dihitung.
                            Target pembanding tetap menggunakan SLA global
                            pelanggan.
                        </p>
                    </div>

                    <!-- Site -->
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
                            :disabled="!form.pelanggan_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
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
                            class="mt-2 text-sm text-amber-600 dark:text-amber-300"
                        >
                            Tidak ditemukan site untuk pelanggan ini.
                        </p>
                    </div>

                    <!-- Bulan -->
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
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
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
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Periode laporan dihitung dari hari pertama sampai awal bulan
                    berikutnya. Laporan hanya dapat dihitung setelah bulan yang
                    dipilih berakhir.
                </p>

                <button
                    type="submit"
                    :disabled="
                        !form.pelanggan_id ||
                        !form.month ||
                        !form.year ||
                        Number(form.year) < 2000 ||
                        Number(form.year) > props.currentYear ||
                        (form.scope === 'site' && !form.online_billing_id) ||
                        (form.scope === 'instansi' && !form.instansi_id)
                    "
                    class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Tampilkan Laporan
                </button>
            </form>
        </section>

        <!-- Error perhitungan -->
        <section
            v-if="props.calculationError"
            class="rounded-xl border border-destructive/30 bg-destructive/10 p-5 text-destructive"
        >
            <h2 class="font-semibold">Laporan belum dapat dihitung</h2>

            <p class="mt-2 text-sm break-words">
                {{ props.calculationError }}
            </p>
        </section>

        <!-- Hasil -->
        <template v-if="props.result && resultMatchesForm">
            <section class="space-y-4">
                <!-- Judul hasil -->
                <div>
                    <h2 class="text-lg font-semibold">
                        Laporan {{ reportPeriodLabel }}
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        {{ reportScopeLabel }}:
                        <span class="font-medium text-foreground">
                            {{ reportTitleLabel }}
                        </span>
                    </p>

                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="form.scope === 'global'">
                            Rekap SLA seluruh site pelanggan.
                        </template>

                        <template v-else-if="form.scope === 'site'">
                            Hasil perhitungan SLA untuk site yang dipilih.
                        </template>

                        <template v-else>
                            Rekap SLA seluruh site milik instansi terpilih,
                            dibandingkan dengan target global pelanggan.
                        </template>
                    </p>
                </div>

                <!-- Status snapshot -->
                <div
                    v-if="props.finalizedReport"
                    class="rounded-xl border border-green-300 bg-green-50 p-5 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    <h3 class="font-semibold">Laporan Sudah Difinalisasi</h3>

                    <p class="mt-2 text-sm">
                        Snapshot resmi sudah tersimpan. Nomor laporan:
                        {{ props.finalizedReport.id }}.
                    </p>

                    <p
                        v-if="props.finalizedReport.instansi_name"
                        class="mt-1 text-sm"
                    >
                        Instansi:
                        {{ props.finalizedReport.instansi_name }}
                    </p>

                    <p class="mt-1 text-sm">
                        Difinalisasi pada:
                        {{ formatDateTime(props.finalizedReport.finalized_at) }}
                    </p>

                    <p class="mt-1 text-sm">
                        Hasil ini berasal dari snapshot resmi, bukan perhitungan
                        ulang.
                    </p>
                </div>

                <!-- Provisional -->
                <div
                    v-else-if="reportStatus === 'provisional'"
                    class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <h3 class="font-semibold">Laporan SLA Sementara</h3>

                    <p class="mt-2 text-sm">
                        Masih terdapat
                        <span class="font-semibold">
                            {{ pendingIncidentCount }}
                        </span>
                        insiden downtime yang belum selesai dan dapat
                        memengaruhi hasil periode ini. Tinjau kembali setelah
                        insiden selesai.
                    </p>
                </div>

                <!-- Tidak ada waktu layanan -->
                <div
                    v-else-if="reportStatus === 'no_service_period'"
                    class="rounded-xl border border-border bg-muted/30 p-5"
                >
                    <h3 class="font-semibold">
                        Tidak Ada Waktu Layanan pada Periode Ini
                    </h3>

                    <p class="mt-2 text-sm text-muted-foreground">
                        Tidak ada waktu layanan yang dapat dihitung untuk
                        cakupan dan periode yang dipilih. Laporan tidak dapat
                        difinalisasi.
                    </p>
                </div>

                <!-- Target tidak tersedia -->
                <div
                    v-else-if="reportStatus === 'missing_sla_rule'"
                    class="rounded-xl border border-destructive/30 bg-destructive/10 p-5 text-destructive"
                >
                    <h3 class="font-semibold">Aturan SLA Belum Tersedia</h3>

                    <p class="mt-2 text-sm">
                        <template v-if="form.scope === 'instansi'">
                            Target SLA global pelanggan tidak ditemukan. Atur
                            target global pada Pengaturan Target SLA.
                        </template>

                        <template v-else>
                            Aturan SLA yang diperlukan untuk laporan ini belum
                            tersedia. Periksa Pengaturan Target SLA.
                        </template>
                    </p>
                </div>

                <!-- Siap finalisasi -->
                <div
                    v-else-if="reportStatus === 'ready_to_finalize'"
                    class="rounded-xl border border-green-300 bg-green-50 p-5 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    <h3 class="font-semibold">
                        Siap Ditinjau untuk Finalisasi
                    </h3>

                    <p class="mt-2 text-sm">
                        Aturan SLA tersedia dan tidak ada insiden downtime yang
                        belum selesai dalam pemeriksaan ini. Tinjau hasil
                        sebelum menyimpannya sebagai laporan resmi.
                    </p>
                </div>

                <!-- Ringkasan SLA -->
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            {{ targetLabel }}
                        </p>

                        <p class="mt-2 text-2xl font-bold">
                            {{
                                formatPercent(
                                    props.result.target_sla_percentage,
                                )
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            Availability
                        </p>

                        <p class="mt-2 text-2xl font-bold">
                            {{
                                formatPercent(
                                    props.result.availability_percentage,
                                )
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            Total downtime
                        </p>

                        <p class="mt-2 text-lg font-bold">
                            {{ formatDuration(totalDowntime) }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-border bg-card p-5">
                        <p class="text-sm text-muted-foreground">
                            Total waktu layanan
                        </p>

                        <p class="mt-2 text-lg font-bold">
                            {{
                                formatDuration(
                                    props.result.total_service_seconds,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Status pencapaian -->
                <div>
                    <p class="text-sm text-muted-foreground">
                        Status pemenuhan target SLA
                    </p>

                    <p
                        v-if="reportStatus === 'provisional'"
                        class="font-semibold text-amber-600 dark:text-amber-400"
                    >
                        Belum Final — Menunggu Penyelesaian Insiden
                    </p>

                    <p
                        v-else-if="props.result.is_sla_met === true"
                        class="font-semibold text-green-600 dark:text-green-400"
                    >
                        SLA Tercapai
                    </p>

                    <p
                        v-else-if="props.result.is_sla_met === false"
                        class="font-semibold text-red-600 dark:text-red-400"
                    >
                        SLA Tidak Tercapai
                    </p>

                    <p v-else class="font-semibold text-muted-foreground">
                        Target SLA belum dapat dinilai
                    </p>
                </div>

                <!-- Rincian site -->
                <div
                    v-if="
                        siteResults.length > 0 &&
                        (form.scope === 'global' || form.scope === 'instansi')
                    "
                    class="overflow-hidden rounded-xl border border-border"
                >
                    <div class="border-b border-border px-5 py-4">
                        <h3 class="font-semibold">Rincian Site</h3>

                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="form.scope === 'instansi'">
                                Hanya site milik instansi terpilih yang
                                ditampilkan. Target pada ringkasan adalah target
                                global pelanggan; target pada tabel merupakan
                                informasi masing-masing site.
                            </template>

                            <template v-else>
                                Availability global dihitung dari total waktu
                                layanan dan downtime seluruh site. Target
                                masing-masing site ditampilkan sebagai rincian.
                            </template>
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-left text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-4 py-3">Site</th>
                                    <th class="px-4 py-3">Target SLA Site</th>
                                    <th class="px-4 py-3">
                                        Total Waktu Layanan
                                    </th>
                                    <th class="px-4 py-3">Availability</th>
                                    <th class="px-4 py-3">Downtime</th>
                                    <th class="px-4 py-3">Status Site</th>
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
                                    class="border-t border-border"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-medium">
                                            {{
                                                site.site_name ??
                                                site.nama_site ??
                                                `Site ${index + 1}`
                                            }}
                                        </div>

                                        <p
                                            v-if="site.no_jaringan"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ site.no_jaringan }}
                                        </p>
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
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : site.is_sla_met === false
                                                      ? 'text-red-600 dark:text-red-400'
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

                <!-- Indikator snapshot -->
                <div v-if="props.finalizedReport" class="flex justify-end">
                    <span
                        class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm text-muted-foreground"
                    >
                        <span
                            class="mr-2 inline-block size-2 rounded-full bg-green-500"
                        ></span>

                        Laporan sudah dikunci
                    </span>
                </div>

                <!-- Tombol finalisasi -->
                <div
                    v-else-if="
                        reportStatus === 'ready_to_finalize' &&
                        typeof props.result.is_sla_met === 'boolean' &&
                        props.result.availability_percentage !== null &&
                        props.result.availability_percentage !== undefined
                    "
                    class="flex justify-end"
                >
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                        @click="finalizeReport"
                    >
                        Finalisasi Laporan
                    </button>
                </div>

                <!-- Data laporan untuk pemeriksaan -->
                <details class="rounded-xl border border-border bg-card p-5">
                    <summary class="cursor-pointer font-semibold">
                        Detail data laporan
                    </summary>

                    <pre
                        class="mt-4 overflow-x-auto rounded-md bg-muted p-4 text-xs"
                        >{{ JSON.stringify(props.result, null, 2) }}</pre>
                </details>
            </section>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

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

interface Filters {
    scope: 'site' | 'global';
    pelanggan_id: number | string | null;
    online_billing_id: number | string | null;
    period_start: string;
    period_end: string;
}

interface SlaResult {
    scope?: 'site' | 'global';
    pelanggan_id?: number;
    online_billing_id?: number;
    site_name?: string | null;
    target_sla_percentage?: number | string | null;
    availability_percentage?: number | string | null;
    downtime_seconds?: number | null;
    total_downtime_site_seconds?: number | null;
    total_service_seconds?: number | null;
    is_sla_met?: boolean | null;
    sites?: SlaResult[];
    site_name_label?: string;
    nama_site?: string;
    site_id?: number;
    report_status?: 'provisional' | 'ready_to_finalize' | 'missing_sla_rule';

    pending_incidents_count?: number;
    pending_site_incidents_count?: number;
}

const props = defineProps<{
    customers: Customer[];
    sites: Site[];
    filters: Filters;
    result: SlaResult | null;
    calculationError: string | null;
}>();

const form = reactive({
    scope: props.filters.scope ?? 'site',
    pelanggan_id: props.filters.pelanggan_id
        ? String(props.filters.pelanggan_id)
        : '',
    online_billing_id: props.filters.online_billing_id
        ? String(props.filters.online_billing_id)
        : '',
    period_start: props.filters.period_start,
    period_end: props.filters.period_end,
});

const filteredSites = computed(() =>
    props.sites.filter(
        (site) => String(site.pelanggan_id) === form.pelanggan_id,
    ),
);

const siteResults = computed(() => {
    if (!props.result) return [];

    const rows = props.result.sites ?? [];

    return Array.isArray(rows) ? rows : [];
});

const totalDowntime = computed(() => {
    if (!props.result) return null;

    // Hasil Site menggunakan downtime_seconds.
    // Hasil Global menggunakan total_downtime_site_seconds.
    return (
        props.result.downtime_seconds ??
        props.result.total_downtime_site_seconds ??
        null
    );
});

const hasSlaStatus = computed(
    () => typeof props.result?.is_sla_met === 'boolean',
);

const hasTarget = computed(
    () =>
        props.result?.target_sla_percentage !== null &&
        props.result?.target_sla_percentage !== undefined &&
        props.result?.target_sla_percentage !== '',
);

const resultMatchesForm = computed(() => {
    if (!props.result) return false;

    return (
        form.scope === props.filters.scope &&
        form.pelanggan_id === String(props.filters.pelanggan_id ?? '') &&
        form.period_start === props.filters.period_start &&
        form.period_end === props.filters.period_end &&
        (form.scope === 'global' ||
            form.online_billing_id ===
                String(props.filters.online_billing_id ?? ''))
    );
});

function onCustomerChange() {
    form.online_billing_id = '';

    // Muat ulang daftar site untuk pelanggan yang baru dipilih.
    // Tanpa calculate, halaman hanya memperbarui parameter.
    router.get(
        '/admin/sla-test',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id,
            period_start: form.period_start,
            period_end: form.period_end,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function submitTest() {
    router.get(
        '/admin/sla-test',
        {
            scope: form.scope,
            pelanggan_id: form.pelanggan_id,
            online_billing_id:
                form.scope === 'site' ? form.online_billing_id : undefined,
            period_start: form.period_start,
            period_end: form.period_end,
            calculate: 1,
        },
        {
            preserveState: true,
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

    if (!Number.isFinite(totalSeconds) || totalSeconds < 0) {
        return '-';
    }

    const seconds = Math.floor(totalSeconds);
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    return `${hours} jam ${minutes} menit ${remainingSeconds} detik`;
}
</script>

<template>
    <div class="space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">SLA Test</h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Uji perhitungan SLA Site dan Global sebelum digunakan pada
                laporan operasional.
            </p>
        </div>

        <section class="rounded-xl border bg-card p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Parameter Pengujian</h2>

            <form class="mt-5 space-y-5" @submit.prevent="submitTest">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label
                            for="scope"
                            class="mb-2 block text-sm font-medium"
                        >
                            Jenis perhitungan
                        </label>

                        <select
                            id="scope"
                            v-model="form.scope"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="site">SLA per Site</option>

                            <option value="global">SLA Global</option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="pelanggan"
                            class="mb-2 block text-sm font-medium"
                        >
                            Pelanggan
                        </label>

                        <select
                            id="pelanggan"
                            v-model="form.pelanggan_id"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            @change="onCustomerChange"
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
                                {{ site.nama_site }}
                                —
                                {{ site.no_jaringan || 'Tanpa nomor jaringan' }}
                            </option>
                        </select>

                        <p
                            v-if="
                                form.pelanggan_id && filteredSites.length === 0
                            "
                            class="mt-2 text-sm text-amber-600"
                        >
                            Tidak ditemukan site aktif untuk pelanggan ini.
                        </p>
                    </div>

                    <div>
                        <label
                            for="period_start"
                            class="mb-2 block text-sm font-medium"
                        >
                            Tanggal awal
                        </label>

                        <input
                            id="period_start"
                            v-model="form.period_start"
                            type="date"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label
                            for="period_end"
                            class="mb-2 block text-sm font-medium"
                        >
                            Tanggal akhir
                        </label>

                        <input
                            id="period_end"
                            v-model="form.period_end"
                            type="date"
                            required
                            :min="form.period_start"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Kedua tanggal dihitung secara inklusif. Contoh: 1–31 Oktober
                    berarti 31 hari atau 744 jam. Target SLA diambil dari aturan
                    yang tersimpan.
                </p>

                <button
                    type="submit"
                    :disabled="
                        !form.pelanggan_id ||
                        !form.period_start ||
                        !form.period_end ||
                        form.period_end < form.period_start ||
                        (form.scope === 'site' && !form.online_billing_id)
                    "
                    class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Hitung SLA
                </button>
            </form>
        </section>

        <section
            v-if="calculationError"
            class="rounded-xl border border-red-300 bg-red-50 p-5 text-red-800 dark:bg-red-950 dark:text-red-200"
        >
            <h2 class="font-semibold">Perhitungan gagal</h2>

            <p class="mt-2 text-sm break-words">
                {{ calculationError }}
            </p>
        </section>

        <template v-if="result && resultMatchesForm">
            <div
                v-if="result.report_status === 'provisional'"
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
                    memengaruhi periode laporan. Hasil SLA dapat berubah setelah
                    insiden diselesaikan.
                </p>
            </div>

            <div
                v-else-if="result.report_status === 'missing_sla_rule'"
                class="rounded-xl border border-red-300 bg-red-50 p-5 text-red-800 dark:bg-red-950 dark:text-red-200"
            >
                <h3 class="font-semibold">Aturan SLA Belum Tersedia</h3>

                <p class="mt-2 text-sm">
                    Target SLA untuk cakupan laporan ini belum tersedia.
                    Lengkapi aturan SLA sebelum laporan difinalisasi.
                </p>
            </div>

            <div
                v-else-if="result.report_status === 'ready_to_finalize'"
                class="rounded-xl border border-green-300 bg-green-50 p-5 text-green-800 dark:bg-green-950 dark:text-green-200"
            >
                <h3 class="font-semibold">Laporan Siap Difinalisasi</h3>

                <p class="mt-2 text-sm">
                    Tidak ditemukan insiden downtime yang belum selesai dan
                    berpotensi memengaruhi periode ini. Target SLA tersedia dan
                    hasil perhitungan dapat ditinjau untuk proses finalisasi.
                </p>
            </div>

            <section class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Hasil Pengujian</h2>

                    <p class="text-sm text-muted-foreground">
                        Hasil dihitung menggunakan target SLA yang berlaku pada
                        periode laporan.
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

                <div v-if="hasSlaStatus" class="rounded-xl border p-5">
                    <p class="text-sm text-muted-foreground">
                        Status pemenuhan target SLA
                    </p>

                    <p
                        class="mt-2 text-xl font-bold"
                        :class="
                            result.is_sla_met
                                ? 'text-green-600'
                                : 'text-red-600'
                        "
                    >
                        {{
                            result.is_sla_met
                                ? 'SLA Tercapai'
                                : 'SLA Tidak Tercapai'
                        }}
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-amber-300 p-5 text-amber-700 dark:text-amber-300"
                >
                    <p class="font-semibold">Target SLA belum tersedia</p>

                    <p class="mt-1 text-sm">
                        Belum ditemukan aturan SLA yang mencakup seluruh periode
                        dan cakupan yang dipilih. Periksa Pengaturan Target SLA
                        sebelum menilai hasil ini.
                    </p>
                </div>

                <div
                    v-if="siteResults.length"
                    class="overflow-hidden rounded-xl border"
                >
                    <div class="border-b px-5 py-4">
                        <h3 class="font-semibold">Rincian Site</h3>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Target dan status di tabel ini mengikuti aturan
                            masing-masing site. Target Global berlaku untuk
                            hasil agregat Global, bukan otomatis untuk setiap
                            site.
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

                                    <td class="px-4 py-3">
                                        {{
                                            formatDuration(
                                                site.downtime_seconds,
                                            )
                                        }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            v-if="
                                                typeof site.is_sla_met ===
                                                'boolean'
                                            "
                                            :class="
                                                site.is_sla_met
                                                    ? 'text-green-600'
                                                    : 'text-red-600'
                                            "
                                        >
                                            {{
                                                site.is_sla_met
                                                    ? 'Tercapai'
                                                    : 'Tidak tercapai'
                                            }}
                                        </span>

                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                        >
                                            Target belum tersedia
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <details class="rounded-xl border bg-card p-5">
                    <summary class="cursor-pointer font-semibold">
                        Detail data hasil perhitungan
                    </summary>

                    <pre
                        class="mt-4 overflow-x-auto rounded-md bg-muted p-4 text-xs"
                        >{{ JSON.stringify(result, null, 2) }}</pre>
                </details>
            </section>
        </template>
    </div>
</template>

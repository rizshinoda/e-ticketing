<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    confirmAction,
    showSuccessToast,
    showErrorToast,
} from '@/lib/sla-notifications';
interface Customer {
    id: number;
    nama_pelanggan: string;
}

interface Site {
    id: number;
    pelanggan_id: number;
    nama_site: string;
    no_jaringan: string | null;
    active_sla_percentage?: number | string | null;
    active_sla_rule_count?: number | string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    customers: Customer[];
    sites: Paginated<Site>;
    filters: {
        pelanggan_id?: string | number | null;
        search?: string;
    };
    success?: string | null;
}>();

const search = ref(props.filters?.search ?? '');

const form = useForm({
    pelanggan_id: String(props.filters?.pelanggan_id ?? ''),
    online_billing_ids: [] as number[],
    sla_percentage: 99,
});

const presets = [95, 98, 99, 99.5, 99.9, 99.99];

const visibleSites = computed(() => props.sites.data ?? []);

const allVisibleSelected = computed(() => {
    if (visibleSites.value.length === 0) return false;

    return visibleSites.value.every((site) =>
        form.online_billing_ids.includes(site.id),
    );
});

const selectedVisibleCount = computed(
    () =>
        visibleSites.value.filter((site) =>
            form.online_billing_ids.includes(site.id),
        ).length,
);

const selectedCustomerName = computed(() => {
    const customer = props.customers.find(
        (item) => String(item.id) === form.pelanggan_id,
    );

    return customer?.nama_pelanggan ?? '';
});

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);

watch(
    () => props.filters?.pelanggan_id,
    (value) => {
        const nextValue = String(value ?? '');

        if (form.pelanggan_id !== nextValue) {
            form.pelanggan_id = nextValue;
            form.online_billing_ids = [];
        }
    },
);

function loadSites() {
    router.get(
        '/sla-rules/bulk-sites',
        {
            pelanggan_id: form.pelanggan_id || undefined,
            search: search.value.trim() || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function changeCustomer() {
    form.online_billing_ids = [];
    form.clearErrors();
    search.value = '';

    loadSites();
}

function applySearch() {
    loadSites();
}

function clearSearch() {
    search.value = '';
    loadSites();
}

function toggleSite(siteId: number) {
    const index = form.online_billing_ids.indexOf(siteId);

    if (index !== -1) {
        form.online_billing_ids.splice(index, 1);
        return;
    }

    form.online_billing_ids.push(siteId);
}

function toggleVisibleSites() {
    const visibleIds = visibleSites.value.map((site) => site.id);

    if (allVisibleSelected.value) {
        form.online_billing_ids = form.online_billing_ids.filter(
            (id) => !visibleIds.includes(id),
        );
        return;
    }

    const selectedIds = new Set(form.online_billing_ids);

    visibleIds.forEach((id) => selectedIds.add(id));

    form.online_billing_ids = Array.from(selectedIds);
}

function selectPreset(value: number) {
    form.sla_percentage = value;
}

async function submit() {
    form.clearErrors();

    if (!form.pelanggan_id) {
        form.setError('pelanggan_id', 'Pilih pelanggan terlebih dahulu.');
        return;
    }

    if (form.online_billing_ids.length === 0) {
        form.setError(
            'online_billing_ids',
            'Pilih minimal satu site yang akan diatur.',
        );
        return;
    }

    if (
        !Number.isFinite(Number(form.sla_percentage)) ||
        Number(form.sla_percentage) < 0 ||
        Number(form.sla_percentage) > 100
    ) {
        form.setError(
            'sla_percentage',
            'Target SLA harus berada antara 0 sampai 100.',
        );
        return;
    }

    const totalSites = form.online_billing_ids.length;

    // Konfirmasi menggunakan SweetAlert2.
    const confirmed = await confirmAction({
        title: 'Terapkan Target SLA?',
        text:
            `Target SLA ${form.sla_percentage}% akan diterapkan ke ` +
            `${totalSites} site milik ${selectedCustomerName.value}. ` +
            'Target aktif yang sudah ada akan diperbarui.',
        confirmButtonText: 'Ya, terapkan',
        cancelButtonText: 'Batal',
    });

    // Hentikan proses jika pengguna membatalkan.
    if (!confirmed) {
        return;
    }

    form.post('/sla-rules/bulk-sites', {
        preserveScroll: true,

        onSuccess: () => {
            form.online_billing_ids = [];

            showSuccessToast(
                `Target SLA berhasil diterapkan ke ${totalSites} site.`,
            );
        },

        onError: () => {
            showErrorToast(
                'Target SLA gagal disimpan. Periksa kembali data yang dimasukkan.',
            );
        },
    });
}

function goToPage(url: string | null) {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
}

function paginationLabel(label: string) {
    const decoded = label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&');

    if (/previous/i.test(decoded)) {
        return 'Sebelumnya';
    }

    if (/next/i.test(decoded)) {
        return 'Berikutnya';
    }

    return decoded;
}

function formatPercent(value: number | string | null | undefined) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const number = Number(value);

    if (!Number.isFinite(number)) return '—';

    return `${new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(number)}%`;
}

function activeRuleCount(site: Site) {
    return Number(site.active_sla_rule_count ?? 0);
}

function ruleStatusLabel(site: Site) {
    const count = activeRuleCount(site);

    if (count > 1) {
        return `Duplikat: ${count} target aktif`;
    }

    if (count === 1) {
        return `Target aktif: ${formatPercent(site.active_sla_percentage)}`;
    }

    return 'Belum ada target aktif';
}

function ruleStatusClass(site: Site) {
    const count = activeRuleCount(site);

    if (count > 1) {
        return 'border-destructive/30 bg-destructive/10 text-destructive';
    }

    if (count === 1) {
        return 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300';
    }

    return 'border-border bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Atur Target SLA Banyak Site" />

    <div class="space-y-6 p-4 text-foreground sm:p-6 lg:p-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Atur Target SLA Banyak Site
                </h1>

                <p
                    class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    Pilih pelanggan, tentukan target SLA, lalu pilih site yang
                    akan diperbarui. Daftar site dimuat per halaman agar tetap
                    ringan meskipun jumlah site banyak.
                </p>
            </div>

            <Link
                href="/sla-rules"
                class="inline-flex shrink-0 items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition hover:bg-accent hover:text-accent-foreground"
            >
                Kembali ke Target SLA
            </Link>
        </div>

        <!-- Pengaturan -->
        <section
            class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
        >
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-foreground">
                    Pengaturan Target
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Pilih pelanggan terlebih dahulu agar daftar site dapat
                    ditampilkan.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <!-- Pelanggan -->
                <div>
                    <label
                        for="pelanggan_id"
                        class="mb-1.5 block text-sm font-medium text-foreground"
                    >
                        Pelanggan
                    </label>

                    <select
                        id="pelanggan_id"
                        v-model="form.pelanggan_id"
                        required
                        class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
                        @change="changeCustomer"
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

                    <p
                        v-if="form.errors.pelanggan_id"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ form.errors.pelanggan_id }}
                    </p>
                </div>

                <!-- Target -->
                <div>
                    <label
                        for="sla_percentage"
                        class="mb-1.5 block text-sm font-medium text-foreground"
                    >
                        Target SLA (%)
                    </label>

                    <div class="relative">
                        <input
                            id="sla_percentage"
                            v-model.number="form.sla_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 pr-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/30"
                        />

                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                        >
                            %
                        </span>
                    </div>

                    <p
                        v-if="form.errors.sla_percentage"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ form.errors.sla_percentage }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="
                                Number(form.sla_percentage) === preset
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-background text-foreground hover:bg-accent'
                            "
                            @click="selectPreset(preset)"
                        >
                            {{ preset }}%
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="mt-5 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm leading-6 text-foreground"
            >
                <p class="font-medium">Cara kerja target SLA</p>

                <p class="mt-1 text-muted-foreground">
                    Site yang belum memiliki target aktif akan mendapatkan
                    target baru. Site yang sudah memiliki target aktif akan
                    diperbarui menggunakan nilai yang dipilih di atas.
                </p>
            </div>
        </section>

        <!-- Daftar site -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="flex flex-col gap-4 border-b border-border p-5">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            Pilih Site
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="form.pelanggan_id">
                                {{ props.sites.total }} site ditemukan untuk
                                pelanggan ini.
                            </template>

                            <template v-else>
                                Pilih pelanggan untuk menampilkan daftar site.
                            </template>
                        </p>
                    </div>

                    <div
                        v-if="form.pelanggan_id"
                        class="rounded-lg bg-primary/10 px-3 py-2 text-sm text-primary"
                    >
                        Dipilih:
                        <span class="font-semibold">
                            {{ form.online_billing_ids.length }}
                        </span>
                        site
                    </div>
                </div>

                <!-- Pencarian -->
                <form
                    class="flex flex-col gap-2 sm:flex-row"
                    @submit.prevent="applySearch"
                >
                    <input
                        v-model="search"
                        type="search"
                        :disabled="!form.pelanggan_id"
                        placeholder="Cari nama site atau nomor jaringan..."
                        class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-70"
                    />

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="!form.pelanggan_id"
                            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Cari Site
                        </button>

                        <button
                            type="button"
                            :disabled="!form.pelanggan_id"
                            class="rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium text-foreground hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                            @click="clearSearch"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <!-- Pilih semua pada halaman saat ini -->
                <div
                    v-if="form.pelanggan_id && visibleSites.length > 0"
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 text-sm text-foreground"
                    >
                        <input
                            type="checkbox"
                            :checked="allVisibleSelected"
                            class="h-4 w-4 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring/30"
                            @change="toggleVisibleSites"
                        />

                        Pilih semua site di halaman ini
                    </label>

                    <p class="text-xs text-muted-foreground">
                        {{ selectedVisibleCount }} dari
                        {{ visibleSites.length }} site di halaman ini dipilih.
                    </p>
                </div>
            </div>

            <!-- Belum pilih pelanggan -->
            <div v-if="!form.pelanggan_id" class="px-5 py-14 text-center">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01M14 9v.01M14 12v.01M14 15v.01M14 18v.01"
                        />
                    </svg>
                </div>

                <h3 class="mt-3 font-medium text-foreground">
                    Pelanggan belum dipilih
                </h3>

                <p class="mt-1 text-sm text-muted-foreground">
                    Pilih pelanggan di atas untuk melihat site yang tersedia.
                </p>
            </div>

            <!-- Tidak ada hasil -->
            <div
                v-else-if="visibleSites.length === 0"
                class="px-5 py-14 text-center"
            >
                <h3 class="font-medium text-foreground">
                    Site tidak ditemukan
                </h3>

                <p class="mt-1 text-sm text-muted-foreground">
                    Tidak ada site yang cocok dengan pencarian saat ini. Coba
                    kata kunci lain.
                </p>
            </div>

            <!-- Daftar site -->
            <div v-else class="divide-y divide-border">
                <label
                    v-for="site in visibleSites"
                    :key="site.id"
                    class="flex cursor-pointer gap-3 px-5 py-4 transition-colors hover:bg-muted/40"
                >
                    <div class="pt-1">
                        <input
                            type="checkbox"
                            :checked="form.online_billing_ids.includes(site.id)"
                            class="h-4 w-4 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring/30"
                            @change="toggleSite(site.id)"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-sm font-medium break-words text-foreground"
                                >
                                    {{ site.nama_site }}
                                </p>

                                <p class="mt-1 text-xs text-muted-foreground">
                                    No. Jaringan:
                                    <span class="font-medium text-foreground">
                                        {{
                                            site.no_jaringan || 'Belum tersedia'
                                        }}
                                    </span>
                                </p>

                                <p
                                    class="mt-1 text-xs text-muted-foreground/80"
                                >
                                    Site ID: {{ site.id }}
                                </p>
                            </div>

                            <span
                                class="inline-flex w-fit max-w-full items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="ruleStatusClass(site)"
                            >
                                {{ ruleStatusLabel(site) }}
                            </span>
                        </div>

                        <p
                            v-if="activeRuleCount(site) > 1"
                            class="mt-2 text-xs leading-5 text-destructive"
                        >
                            Site ini memiliki lebih dari satu target aktif.
                            Periksa data duplikat sebelum melanjutkan.
                        </p>
                    </div>
                </label>
            </div>

            <!-- Validasi -->
            <div
                v-if="form.errors.online_billing_ids"
                class="border-t border-destructive/20 bg-destructive/10 px-5 py-3 text-sm text-destructive"
            >
                {{ form.errors.online_billing_ids }}
            </div>

            <!-- Pagination -->
            <div
                v-if="form.pelanggan_id && props.sites.total > 0"
                class="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan
                    <span class="font-medium text-foreground">
                        {{ props.sites.from ?? 0 }}–{{ props.sites.to ?? 0 }}
                    </span>
                    dari
                    <span class="font-medium text-foreground">
                        {{ props.sites.total }}
                    </span>
                    site
                </p>

                <div class="flex flex-wrap items-center gap-1">
                    <button
                        v-for="(link, index) in props.sites.links"
                        :key="`${index}-${link.label}`"
                        type="button"
                        :disabled="!link.url || link.active"
                        class="min-w-9 rounded-lg border px-3 py-2 text-sm transition disabled:cursor-not-allowed"
                        :class="
                            link.active
                                ? 'border-primary bg-primary font-semibold text-primary-foreground'
                                : link.url
                                  ? 'border-border bg-background text-foreground hover:bg-accent'
                                  : 'border-border bg-muted text-muted-foreground opacity-60'
                        "
                        @click="goToPage(link.url)"
                    >
                        {{ paginationLabel(link.label) }}
                    </button>
                </div>
            </div>

            <!-- Terapkan target -->
            <div
                class="flex flex-col gap-3 border-t border-border bg-muted/30 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-sm text-muted-foreground">
                    <template v-if="form.online_billing_ids.length > 0">
                        Target
                        <span class="font-semibold text-foreground">
                            {{ formatPercent(form.sla_percentage) }}
                        </span>
                        akan diterapkan kepada
                        <span class="font-semibold text-foreground">
                            {{ form.online_billing_ids.length }} site
                        </span>
                        yang dipilih.
                    </template>

                    <template v-else> Belum ada site yang dipilih. </template>
                </div>

                <button
                    type="button"
                    :disabled="
                        form.processing ||
                        !form.pelanggan_id ||
                        form.online_billing_ids.length === 0
                    "
                    class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="submit"
                >
                    {{
                        form.processing
                            ? 'Menyimpan...'
                            : `Terapkan ke ${form.online_billing_ids.length} Site`
                    }}
                </button>
            </div>
        </section>
    </div>
</template>

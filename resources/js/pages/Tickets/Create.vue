<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

interface Category {
    id: number;
    name: string;
    is_downtime: boolean;
}

interface OnlineBilling {
    id: number;
    customer_name: string | null;
    site_name: string | null;
    no_jaringan: string | null;
    layanan: string | null;
    bandwidth: string | null;
}
interface Pelanggan {
    id: number;
    nama_pelanggan: string;
}
interface Filters {
    search: string;
}

const props = defineProps<{
    categories: Category[];
    pelanggans: Pelanggan[];
    onlineBillings: OnlineBilling[];
    filters: Filters;
}>();

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const search = ref('');

const showResults = ref(false);

const searchLoading = ref(false);

/*
|--------------------------------------------------------------------------
| Selected Online Billing
|--------------------------------------------------------------------------
*/

const selectedBillings = ref<OnlineBilling[]>([]);

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/
const form = useForm({
    ticket_type: 'individual',

    customer_source: 'online_billing',

    online_billing_ids: [] as number[],

    customer_name: '',
    pelanggan_id: null as number | null,
    manual_sites: [] as {
        site_name: string;
        no_jaringan: string;
    }[],

    kendala_id: null as number | null,

    reported_via: '',

    priority: 'medium',

    report_type: 'current',

    reported_at: new Date()
        .toLocaleString('sv-SE', {
            timeZone: 'Asia/Jakarta',
        })
        .replace(' ', 'T')
        .slice(0, 16),

    incident_reported_at: new Date()
        .toLocaleString('sv-SE', {
            timeZone: 'Asia/Jakarta',
        })
        .replace(' ', 'T')
        .slice(0, 16),

    incident_resolved_at: null as string | null,

    description: '',
});
const manualSites = ref([
    {
        site_name: '',
        no_jaringan: '',
    },
]);

const addManualSite = () => {
    manualSites.value.push({
        site_name: '',
        no_jaringan: '',
    });
};

const removeManualSite = (index: number) => {
    if (manualSites.value.length === 1) {
        return;
    }

    manualSites.value.splice(index, 1);
};

const incidentReportedAt = ref(
    new Date()
        .toLocaleString('sv-SE', {
            timeZone: 'Asia/Jakarta',
        })
        .replace(' ', 'T')
        .slice(0, 16),
);

const incidentResolvedAt = ref<string | null>(null);
/*
|--------------------------------------------------------------------------
| Debounce Search
|--------------------------------------------------------------------------
*/

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    /*
     * Jika search kosong,
     * sembunyikan hasil.
     */
    if (!value.trim()) {
        showResults.value = false;

        return;
    }

    /*
     * Tunggu 400ms setelah user berhenti mengetik.
     */
    searchTimer = setTimeout(() => {
        searchOnlineBilling();
    }, 400);
});

/*
|--------------------------------------------------------------------------
| Search Online Billing
|--------------------------------------------------------------------------
*/

const searchOnlineBilling = () => {
    const keyword = search.value.trim();

    if (!keyword) {
        showResults.value = false;

        return;
    }

    searchLoading.value = true;

    router.get(
        '/tickets/create',
        {
            search: keyword,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,

            onSuccess: () => {
                showResults.value = true;
            },

            onFinish: () => {
                searchLoading.value = false;
            },
        },
    );
};

/*
|--------------------------------------------------------------------------
| Check Selected
|--------------------------------------------------------------------------
*/

const isSelected = (billing: OnlineBilling) => {
    return selectedBillings.value.some((item) => item.id === billing.id);
};

/*
|--------------------------------------------------------------------------
| Select Online Billing
|--------------------------------------------------------------------------
*/

const selectBilling = (billing: OnlineBilling) => {
    /*
     * Jangan duplicate.
     */
    if (isSelected(billing)) {
        return;
    }

    /*
     * Individual:
     * hanya satu site.
     */
    if (form.ticket_type === 'individual') {
        selectedBillings.value = [billing];
    }

    /*
     * GAMAS:
     * boleh banyak site.
     */
    else {
        selectedBillings.value.push(billing);
    }

    /*
     * Update ID yang dikirim
     * ke backend.
     */
    form.online_billing_ids = selectedBillings.value.map((item) => item.id);

    /*
     * Bersihkan search.
     */
    search.value = '';

    showResults.value = false;
};

/*
|--------------------------------------------------------------------------
| Remove Selected Online Billing
|--------------------------------------------------------------------------
*/

const removeBilling = (billingId: number) => {
    selectedBillings.value = selectedBillings.value.filter(
        (item) => item.id !== billingId,
    );

    form.online_billing_ids = selectedBillings.value.map((item) => item.id);
};

/*
|--------------------------------------------------------------------------
| Change Ticket Type
|--------------------------------------------------------------------------
*/

watch(
    () => form.ticket_type,
    (type) => {
        /*
         * Jika berubah menjadi Individual
         * dan sebelumnya ada banyak site,
         * sisakan satu.
         */
        if (type === 'individual' && selectedBillings.value.length > 1) {
            selectedBillings.value = [selectedBillings.value[0]];

            form.online_billing_ids = selectedBillings.value.map(
                (item) => item.id,
            );
        }
    },
);

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.manual_sites = manualSites.value;

    Swal.fire({
        title: 'Buat Ticket?',
        text: 'Pastikan data customer, site, kendala, dan laporan sudah benar.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Ticket',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.post('/tickets', {
            onError: (errors) => {
                console.log('ERROR:', errors);
            },

            onSuccess: () => {
                console.log('SUCCESS');
            },
        });
    });
};
</script>
<template>
    <div class="min-h-screen bg-background px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <!-- ==========================================
                 HEADER
            =========================================== -->
            <div class="mb-8">
                <div
                    class="mb-3 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <span>Tickets</span>
                    <span>/</span>
                    <span class="text-foreground"> Buat Ticket </span>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            Buat Ticket Gangguan
                        </h1>

                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                        >
                            Buat laporan gangguan baru dan lengkapi informasi
                            customer, site, kendala, serta detail laporan.
                        </p>
                    </div>

                    <div
                        class="hidden rounded-lg border bg-background px-3 py-2 text-xs text-muted-foreground shadow-sm sm:block"
                    >
                        Ticket Baru
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 FORM
            =========================================== -->
            <form class="space-y-6" @submit.prevent="submit">
                <!-- ======================================
                     INFORMASI TICKET
                ======================================= -->
                <section
                    class="overflow-hidden rounded-xl border bg-background shadow-sm"
                >
                    <div class="border-b bg-muted/20 px-6 py-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                            >
                                01
                            </div>

                            <div>
                                <h2 class="font-semibold">Informasi Ticket</h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Tentukan jenis ticket dan jenis laporan
                                    gangguan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 p-6 md:grid-cols-2">
                        <!-- JENIS TICKET -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Jenis Ticket
                            </label>

                            <select
                                v-model="form.ticket_type"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="individual">Individual</option>

                                <option value="gamas">GAMAS</option>
                            </select>

                            <p
                                class="mt-2 text-xs leading-5 text-muted-foreground"
                            >
                                Individual untuk satu site. GAMAS dapat mencakup
                                beberapa site.
                            </p>
                        </div>

                        <!-- JENIS LAPORAN -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Jenis Laporan
                            </label>

                            <select
                                v-model="form.report_type"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="current">
                                    Gangguan Saat Ini
                                </option>

                                <option value="historical">
                                    Gangguan Sebelumnya
                                </option>
                            </select>

                            <p
                                class="mt-2 text-xs leading-5 text-muted-foreground"
                            >
                                Pilih apakah gangguan sedang berlangsung atau
                                merupakan gangguan sebelumnya.
                            </p>
                        </div>

                        <!-- HISTORICAL -->
                        <div
                            v-if="form.report_type === 'historical'"
                            class="rounded-xl border bg-muted/20 p-5 md:col-span-2"
                        >
                            <div class="mb-5">
                                <h3 class="text-sm font-semibold">
                                    Waktu Gangguan
                                </h3>

                                <p
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    Masukkan waktu mulai dan waktu selesai
                                    gangguan.
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <!-- GANGGUAN MULAI -->
                                <div>
                                    <label
                                        class="mb-2 block text-sm font-medium"
                                    >
                                        Gangguan Mulai
                                    </label>

                                    <input
                                        v-model="form.incident_reported_at"
                                        type="datetime-local"
                                        class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                </div>

                                <!-- GANGGUAN SELESAI -->
                                <div>
                                    <label
                                        class="mb-2 block text-sm font-medium"
                                    >
                                        Gangguan Selesai
                                    </label>

                                    <input
                                        v-model="form.incident_resolved_at"
                                        type="datetime-local"
                                        class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ======================================
                     CUSTOMER & SITE
                ======================================= -->
                <section
                    class="overflow-visible rounded-xl border bg-background shadow-sm"
                >
                    <div class="border-b bg-muted/20 px-6 py-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                            >
                                02
                            </div>

                            <div>
                                <h2 class="font-semibold">Customer & Site</h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Tentukan customer dan site yang mengalami
                                    gangguan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 p-6">
                        <!-- SUMBER CUSTOMER -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Sumber Customer / Site
                            </label>

                            <select
                                v-model="form.customer_source"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="online_billing">
                                    Sudah Aktif / Online Billing
                                </option>

                                <option value="manual">Belum Aktifasi</option>
                            </select>
                        </div>

                        <!-- ==================================
                             MANUAL CUSTOMER
                        =================================== -->
                        <div
                            v-if="form.customer_source === 'manual'"
                            class="rounded-xl border bg-muted/20 p-5"
                        >
                            <div class="mb-5">
                                <h3 class="font-semibold">
                                    Data Customer / Site
                                </h3>

                                <p
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    Pilih customer dari master data kemudian
                                    masukkan site yang belum aktif.
                                </p>
                            </div>

                            <!-- CUSTOMER -->
                            <div class="mb-6">
                                <label class="mb-2 block text-sm font-medium">
                                    Customer
                                </label>

                                <select
                                    v-model="form.pelanggan_id"
                                    class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option :value="null">
                                        Pilih Customer
                                    </option>

                                    <option
                                        v-for="pelanggan in props.pelanggans"
                                        :key="pelanggan.id"
                                        :value="pelanggan.id"
                                    >
                                        {{ pelanggan.nama_pelanggan }}
                                    </option>
                                </select>
                            </div>

                            <!-- SITE -->
                            <div>
                                <div
                                    class="mb-4 flex items-center justify-between gap-4"
                                >
                                    <div>
                                        <h3 class="text-sm font-semibold">
                                            Site
                                        </h3>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Masukkan site yang mengalami
                                            gangguan.
                                        </p>
                                    </div>

                                    <button
                                        v-if="form.ticket_type === 'gamas'"
                                        type="button"
                                        class="shrink-0 rounded-lg border px-3 py-2 text-xs font-medium text-primary transition hover:bg-primary/10"
                                        @click="addManualSite"
                                    >
                                        + Tambah Site
                                    </button>
                                </div>

                                <!-- MANUAL SITE LIST -->
                                <div class="space-y-3">
                                    <div
                                        v-for="(site, index) in manualSites"
                                        :key="index"
                                        class="rounded-xl border bg-background p-4 shadow-sm transition hover:border-primary/30"
                                    >
                                        <div
                                            class="mb-4 flex items-center justify-between"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                                >
                                                    {{ index + 1 }}
                                                </span>

                                                <span
                                                    class="text-sm font-semibold"
                                                >
                                                    Site {{ index + 1 }}
                                                </span>
                                            </div>

                                            <button
                                                v-if="
                                                    form.ticket_type ===
                                                        'gamas' &&
                                                    manualSites.length > 1
                                                "
                                                type="button"
                                                class="rounded-md px-2 py-1 text-xs text-destructive transition hover:bg-destructive/10"
                                                @click="removeManualSite(index)"
                                            >
                                                Hapus
                                            </button>
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-2">
                                            <!-- NAMA SITE -->
                                            <div>
                                                <label
                                                    class="mb-2 block text-xs font-medium"
                                                >
                                                    Nama Site
                                                </label>

                                                <input
                                                    v-model="site.site_name"
                                                    type="text"
                                                    placeholder="Masukkan nama site"
                                                    class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                />
                                            </div>

                                            <!-- NO JARINGAN -->
                                            <div>
                                                <label
                                                    class="mb-2 block text-xs font-medium"
                                                >
                                                    No Jaringan
                                                </label>

                                                <input
                                                    v-model="site.no_jaringan"
                                                    type="text"
                                                    placeholder="Masukkan no jaringan jika sudah ada"
                                                    class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================================
                             SEARCH ONLINE BILLING
                        =================================== -->
                        <div
                            v-if="form.customer_source === 'online_billing'"
                            class="relative"
                        >
                            <label class="mb-2 block text-sm font-medium">
                                Customer / Site
                            </label>

                            <div class="relative">
                                <input
                                    v-model="search"
                                    type="text"
                                    class="w-full rounded-lg border bg-background px-4 py-3 pr-10 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    placeholder="Cari customer, site, atau no jaringan..."
                                />

                                <div
                                    class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                                >
                                    🔍
                                </div>
                            </div>

                            <p class="mt-2 text-xs text-muted-foreground">
                                Pencarian otomatis akan berjalan setelah
                                berhenti mengetik.
                            </p>

                            <!-- LOADING -->
                            <div
                                v-if="searchLoading"
                                class="mt-2 flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <span
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary"
                                ></span>

                                Mencari...
                            </div>

                            <!-- SEARCH RESULTS -->
                            <div
                                v-if="
                                    showResults && props.onlineBillings.length
                                "
                                class="absolute right-0 left-0 z-50 mt-2 overflow-hidden rounded-xl border bg-background shadow-xl"
                            >
                                <div
                                    class="border-b bg-muted/30 px-4 py-3 text-xs font-medium text-muted-foreground"
                                >
                                    Hasil Pencarian
                                </div>

                                <button
                                    v-for="billing in props.onlineBillings"
                                    :key="billing.id"
                                    type="button"
                                    class="block w-full border-b p-4 text-left transition last:border-b-0 hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="isSelected(billing)"
                                    @click="selectBilling(billing)"
                                >
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold">
                                                {{
                                                    billing.customer_name || '-'
                                                }}
                                            </div>

                                            <div class="mt-1 truncate text-sm">
                                                {{ billing.site_name || '-' }}
                                            </div>

                                            <div
                                                class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground"
                                            >
                                                <span>
                                                    No Jaringan:
                                                    {{
                                                        billing.no_jaringan ||
                                                        '-'
                                                    }}
                                                </span>

                                                <span>•</span>

                                                <span>
                                                    {{ billing.layanan || '-' }}
                                                </span>

                                                <span>•</span>

                                                <span>
                                                    {{
                                                        billing.bandwidth || '-'
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            v-if="isSelected(billing)"
                                            class="shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                        >
                                            Sudah dipilih
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <!-- TIDAK DITEMUKAN -->
                            <div
                                v-if="
                                    showResults &&
                                    !props.onlineBillings.length &&
                                    !searchLoading
                                "
                                class="absolute right-0 left-0 z-50 mt-2 rounded-xl border bg-background p-5 text-center text-sm text-muted-foreground shadow-xl"
                            >
                                <div class="mb-2 text-lg">🔍</div>

                                Online Billing tidak ditemukan.
                            </div>
                        </div>

                        <!-- ==================================
                             SELECTED SITES
                        =================================== -->
                        <div v-if="selectedBillings.length" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold">
                                        Site Terpilih
                                    </h3>

                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Site yang akan dimasukkan ke ticket.
                                    </p>
                                </div>

                                <span
                                    class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                                >
                                    {{ selectedBillings.length }} Site
                                </span>
                            </div>

                            <!-- SITE CARD -->
                            <div
                                v-for="billing in selectedBillings"
                                :key="billing.id"
                                class="group rounded-xl border bg-muted/20 p-4 transition hover:border-primary/30 hover:bg-muted/40"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div class="min-w-0">
                                        <div class="font-semibold">
                                            {{ billing.customer_name || '-' }}
                                        </div>

                                        <div class="mt-1 text-sm">
                                            {{ billing.site_name || '-' }}
                                        </div>

                                        <div
                                            class="mt-3 flex flex-wrap gap-2 text-xs"
                                        >
                                            <span
                                                class="rounded-full bg-primary/10 px-2.5 py-1 font-medium text-primary"
                                            >
                                                {{
                                                    billing.no_jaringan ||
                                                    'No jaringan -'
                                                }}
                                            </span>

                                            <span
                                                class="rounded-full bg-muted px-2.5 py-1"
                                            >
                                                {{ billing.layanan || '-' }}
                                            </span>

                                            <span
                                                class="rounded-full bg-muted px-2.5 py-1"
                                            >
                                                {{ billing.bandwidth || '-' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- HAPUS SITE -->
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                                        title="Hapus site"
                                        @click="removeBilling(billing.id)"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ======================================
                     KENDALA & PRIORITY
                ======================================= -->
                <section class="rounded-xl border bg-background shadow-sm">
                    <div class="border-b bg-muted/20 px-6 py-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                            >
                                03
                            </div>

                            <div>
                                <h2 class="font-semibold">
                                    Kendala & Priority
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Tentukan jenis gangguan dan tingkat
                                    prioritas ticket.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 p-6 md:grid-cols-2">
                        <!-- KENDALA -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Kendala
                            </label>

                            <select
                                v-model="form.kendala_id"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option :value="null">Pilih Kendala</option>

                                <option
                                    v-for="category in props.categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.kendala_id"
                                class="mt-2 text-sm text-destructive"
                            >
                                {{ form.errors.kendala_id }}
                            </p>
                        </div>

                        <!-- PRIORITY -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Priority
                            </label>

                            <select
                                v-model="form.priority"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="low">Low</option>

                                <option value="medium">Medium</option>

                                <option value="high">High</option>

                                <option value="critical">Critical</option>
                            </select>

                            <p class="mt-2 text-xs text-muted-foreground">
                                Gunakan priority sesuai tingkat dampak gangguan.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ======================================
                     DETAIL LAPORAN
                ======================================= -->
                <section class="rounded-xl border bg-background shadow-sm">
                    <div class="border-b bg-muted/20 px-6 py-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                            >
                                04
                            </div>

                            <div>
                                <h2 class="font-semibold">Detail Laporan</h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Lengkapi informasi mengenai laporan
                                    gangguan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 p-6">
                        <!-- REPORTED VIA -->
                        <div>
                            <label
                                for="reported_via"
                                class="mb-2 block text-sm font-medium"
                            >
                                Dilaporkan Melalui
                            </label>

                            <input
                                id="reported_via"
                                v-model="form.reported_via"
                                type="text"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                placeholder="Contoh: WAG CSD/NSD.LA-PC24Telin"
                            />

                            <p class="mt-2 text-xs text-muted-foreground">
                                Contoh: WAG, Email, atau WA Personal.
                            </p>
                        </div>

                        <!-- WAKTU LAPORAN -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Waktu Laporan
                            </label>

                            <input
                                v-model="form.reported_at"
                                type="datetime-local"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.reported_at"
                                class="mt-2 text-sm text-destructive"
                            >
                                {{ form.errors.reported_at }}
                            </p>
                        </div>

                        <!-- DESCRIPTION -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label
                                    for="description"
                                    class="block text-sm font-medium"
                                >
                                    Deskripsi Gangguan
                                </label>

                                <span class="text-xs text-muted-foreground">
                                    Wajib diisi
                                </span>
                            </div>

                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="6"
                                class="w-full resize-y rounded-lg border bg-background px-3 py-3 text-sm leading-6 shadow-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                placeholder="Jelaskan gangguan yang dilaporkan..."
                            ></textarea>

                            <p
                                v-if="form.errors.description"
                                class="mt-2 text-sm text-destructive"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ======================================
                     ERROR ONLINE BILLING
                ======================================= -->
                <div
                    v-if="form.errors.online_billing_ids"
                    class="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
                >
                    <div class="font-semibold">
                        Gagal memilih Online Billing
                    </div>

                    <div class="mt-1">
                        {{ form.errors.online_billing_ids }}
                    </div>
                </div>

                <!-- ======================================
                     SUBMIT
                ======================================= -->
                <div
                    class="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-end"
                >
                    <!-- BATAL -->
                    <button
                        type="button"
                        class="rounded-lg border bg-background px-5 py-2.5 text-sm font-medium transition hover:bg-muted"
                        @click="$inertia.visit('/tickets')"
                    >
                        Batal
                    </button>

                    <!-- BUAT TICKET -->
                    <button
                        type="submit"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="
                            form.processing ||
                            (form.customer_source === 'online_billing' &&
                                selectedBillings.length === 0) ||
                            (form.customer_source === 'manual' &&
                                (!form.pelanggan_id ||
                                    manualSites.length === 0 ||
                                    manualSites.some(
                                        (site) => !site.site_name,
                                    )))
                        "
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Buat Ticket' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

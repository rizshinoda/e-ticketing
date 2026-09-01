<script setup lang="ts">
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

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

interface Filters {
    search: string;
}

const props = defineProps<{
    categories: Category[];
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

    online_billing_ids: [] as number[],

    kendala_id: null as number | null,

    reported_via: '',

    priority: 'medium',

    reported_at: new Date().toISOString().slice(0, 16),

    description: '',
});

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
    form.post('/tickets', {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="p-6">
        <!-- ==========================================
             HEADER
        =========================================== -->

        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Buat Ticket</h1>

            <p class="text-sm text-muted-foreground">
                Buat ticket gangguan baru,
            </p>
        </div>

        <!-- ==========================================
             FORM
        =========================================== -->

        <form class="max-w-4xl space-y-6" @submit.prevent="submit">
            <!-- ======================================
                 JENIS TICKET
            ======================================= -->

            <div>
                <label class="mb-2 block text-sm font-medium">
                    Jenis Ticket
                </label>

                <select
                    v-model="form.ticket_type"
                    class="w-full rounded-md border px-3 py-2"
                >
                    <option value="individual">Individual</option>

                    <option value="gamas">GAMAS</option>
                </select>

                <p class="mt-1 text-xs text-muted-foreground">
                    Individual untuk satu site. GAMAS dapat mencakup beberapa
                    site.
                </p>
            </div>

            <!-- ======================================
                 SEARCH ONLINE BILLING
            ======================================= -->

            <div class="relative">
                <label class="mb-2 block text-sm font-medium">
                    Customer / Site
                </label>

                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-md border px-3 py-2"
                    placeholder="Ketik customer, site, atau no jaringan..."
                />

                <p class="mt-1 text-xs text-muted-foreground">
                    Pencarian otomatis akan berjalan setelah berhenti mengetik.
                </p>

                <!-- Loading -->

                <div
                    v-if="searchLoading"
                    class="mt-2 text-sm text-muted-foreground"
                >
                    Mencari...
                </div>

                <!-- ==================================
                     SEARCH RESULTS
                =================================== -->

                <div
                    v-if="showResults && props.onlineBillings.length"
                    class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border bg-background shadow-lg"
                >
                    <button
                        v-for="billing in props.onlineBillings"
                        :key="billing.id"
                        type="button"
                        class="block w-full border-b p-3 text-left last:border-b-0 hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSelected(billing)"
                        @click="selectBilling(billing)"
                    >
                        <div class="font-medium">
                            {{ billing.customer_name || '-' }}
                        </div>

                        <div class="text-sm">
                            {{ billing.site_name || '-' }}
                        </div>

                        <div class="text-xs text-muted-foreground">
                            No Jaringan:
                            {{ billing.no_jaringan || '-' }}

                            ·

                            {{ billing.layanan || '-' }}

                            ·

                            {{ billing.bandwidth || '-' }}
                        </div>

                        <div
                            v-if="isSelected(billing)"
                            class="mt-1 text-xs text-green-600"
                        >
                            Sudah dipilih
                        </div>
                    </button>
                </div>

                <!-- Tidak ditemukan -->

                <div
                    v-if="
                        showResults &&
                        !props.onlineBillings.length &&
                        !searchLoading
                    "
                    class="absolute z-50 mt-1 w-full rounded-md border bg-background p-4 text-sm text-muted-foreground shadow-lg"
                >
                    Online Billing tidak ditemukan.
                </div>
            </div>

            <!-- ======================================
                 SELECTED SITES
            ======================================= -->

            <div v-if="selectedBillings.length" class="space-y-3">
                <div class="text-sm font-medium">Site Terpilih</div>

                <div
                    v-for="billing in selectedBillings"
                    :key="billing.id"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div>
                        <div class="font-medium">
                            {{ billing.customer_name || '-' }}
                        </div>

                        <div class="text-sm">
                            {{ billing.site_name || '-' }}
                        </div>

                        <div class="text-xs text-muted-foreground">
                            No Jaringan:
                            {{ billing.no_jaringan || '-' }}
                        </div>

                        <div class="text-xs text-muted-foreground">
                            {{ billing.layanan || '-' }}

                            ·

                            {{ billing.bandwidth || '-' }}
                        </div>
                    </div>

                    <!-- HAPUS -->

                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-sm text-destructive hover:bg-destructive/10"
                        @click="removeBilling(billing.id)"
                    >
                        Hapus
                    </button>
                </div>
            </div>

            <!-- ======================================
                 KENDALA
            ======================================= -->

            <div>
                <label class="mb-2 block text-sm font-medium"> Kendala </label>

                <select
                    v-model="form.kendala_id"
                    class="w-full rounded-md border px-3 py-2"
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
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.kendala_id }}
                </p>
            </div>

            <!-- ======================================
                 REPORTED VIA
            ======================================= -->

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
                    class="w-full rounded-md border px-3 py-2"
                    placeholder="Contoh: WAG CSD/NSD.LA-PC24Telin"
                />

                <p class="mt-1 text-xs text-muted-foreground">
                    Contoh: WAG, Email, atau WA Personal.
                </p>
            </div>

            <!-- ======================================
                 PRIORITY
            ======================================= -->

            <div>
                <label class="mb-2 block text-sm font-medium"> Priority </label>

                <select
                    v-model="form.priority"
                    class="w-full rounded-md border px-3 py-2"
                >
                    <option value="low">Low</option>

                    <option value="medium">Medium</option>

                    <option value="high">High</option>

                    <option value="critical">Critical</option>
                </select>
            </div>

            <!-- ======================================
                 REPORTED AT
            ======================================= -->

            <div>
                <label class="mb-2 block text-sm font-medium">
                    Waktu Laporan
                </label>

                <input
                    v-model="form.reported_at"
                    type="datetime-local"
                    class="w-full rounded-md border px-3 py-2"
                />

                <p
                    v-if="form.errors.reported_at"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.reported_at }}
                </p>
            </div>

            <!-- ======================================
                 DESCRIPTION
            ======================================= -->

            <div>
                <label for="description" class="mb-2 block text-sm font-medium">
                    Deskripsi Gangguan
                </label>

                <textarea
                    id="description"
                    v-model="form.description"
                    rows="5"
                    class="w-full rounded-md border px-3 py-2"
                    placeholder="Jelaskan gangguan yang dilaporkan..."
                />

                <p
                    v-if="form.errors.description"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.description }}
                </p>
            </div>

            <!-- ======================================
                 ERROR ONLINE BILLING
            ======================================= -->

            <div
                v-if="form.errors.online_billing_ids"
                class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ form.errors.online_billing_ids }}
            </div>

            <!-- ======================================
                 SUBMIT
            ======================================= -->

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                    :disabled="form.processing || selectedBillings.length === 0"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Buat Ticket' }}
                </button>
            </div>
        </form>
    </div>
</template>

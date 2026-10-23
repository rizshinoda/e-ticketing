<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

const props = defineProps<{
    customers: Customer[];
    sites: Site[];
    today: string;
}>();

const search = ref('');

const form = useForm({
    pelanggan_id: '',
    online_billing_ids: [] as number[],
    sla_percentage: 99,
    effective_from: props.today,
});

const presets = [
    { label: '95%', value: 95 },
    { label: '98%', value: 98 },
    { label: '99%', value: 99 },
    { label: '99,5%', value: 99.5 },
    { label: '99,9%', value: 99.9 },
    { label: '99,99%', value: 99.99 },
];

const customerSites = computed(() =>
    props.sites.filter(
        (site) => String(site.pelanggan_id) === form.pelanggan_id,
    ),
);

const visibleSites = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase();

    if (!keyword) return customerSites.value;

    return customerSites.value.filter((site) =>
        `${site.nama_site} ${site.no_jaringan ?? ''}`
            .toLocaleLowerCase()
            .includes(keyword),
    );
});

const allVisibleSelected = computed(
    () =>
        visibleSites.value.length > 0 &&
        visibleSites.value.every((site) =>
            form.online_billing_ids.includes(site.id),
        ),
);

function changeCustomer() {
    form.online_billing_ids = [];
    search.value = '';
    form.clearErrors('pelanggan_id', 'online_billing_ids');
}

function toggleVisibleSites() {
    const visibleIds = visibleSites.value.map((site) => site.id);

    if (allVisibleSelected.value) {
        form.online_billing_ids = form.online_billing_ids.filter(
            (id) => !visibleIds.includes(id),
        );
        return;
    }

    form.online_billing_ids = Array.from(
        new Set([...form.online_billing_ids, ...visibleIds]),
    );
}

function submit() {
    if (
        !window.confirm(
            `Terapkan target SLA ${String(form.sla_percentage).replace('.', ',')}% ` +
                `ke ${form.online_billing_ids.length} site mulai ${form.effective_from}?`,
        )
    ) {
        return;
    }

    form.post('/sla-rules/bulk-sites');
}
</script>

<template>
    <Head title="Terapkan Target SLA ke Banyak Site" />

    <div class="space-y-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Terapkan Target SLA ke Banyak Site
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Pilih satu pelanggan, tentukan target dan tanggal mulai
                    berlaku, lalu terapkan target yang sama ke beberapa site
                    sekaligus.
                </p>
            </div>

            <Link
                href="/sla-rules"
                class="inline-flex w-fit items-center rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                Kembali ke Pengaturan SLA
            </Link>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="space-y-4 rounded-xl border bg-card p-4 md:p-6">
                <div>
                    <h2 class="font-semibold">1. Pilih pelanggan dan site</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Site yang ditampilkan berasal dari pelanggan yang
                        dipilih.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="pelanggan_id" class="text-sm font-medium"
                            >Pelanggan</label
                        >
                        <select
                            id="pelanggan_id"
                            v-model="form.pelanggan_id"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            @change="changeCustomer"
                        >
                            <option value="">Pilih pelanggan</option>
                            <option
                                v-for="customer in customers"
                                :key="customer.id"
                                :value="String(customer.id)"
                            >
                                {{ customer.nama_pelanggan }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.pelanggan_id"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.pelanggan_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label for="site_search" class="text-sm font-medium"
                            >Cari site</label
                        >
                        <input
                            id="site_search"
                            v-model="search"
                            type="search"
                            :disabled="!form.pelanggan_id"
                            placeholder="Nama site atau nomor jaringan"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm disabled:opacity-50"
                        />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/30 px-4 py-3"
                    >
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm font-medium"
                        >
                            <input
                                type="checkbox"
                                :checked="allVisibleSelected"
                                :disabled="visibleSites.length === 0"
                                class="size-4 rounded border"
                                @change="toggleVisibleSites"
                            />
                            Pilih semua hasil yang tampil
                        </label>
                        <span class="text-sm text-muted-foreground">
                            {{ form.online_billing_ids.length }} site dipilih
                            <span v-if="form.pelanggan_id"
                                >· {{ visibleSites.length }} site tampil</span
                            >
                        </span>
                    </div>

                    <div
                        v-if="!form.pelanggan_id"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        Pilih pelanggan untuk melihat daftar site.
                    </div>
                    <div
                        v-else-if="visibleSites.length === 0"
                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada site yang cocok dengan pencarian.
                    </div>
                    <div v-else class="max-h-[420px] divide-y overflow-y-auto">
                        <label
                            v-for="site in visibleSites"
                            :key="site.id"
                            class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-muted/30"
                        >
                            <input
                                v-model="form.online_billing_ids"
                                type="checkbox"
                                :value="site.id"
                                class="mt-1 size-4 rounded border"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium">{{
                                    site.nama_site
                                }}</span>
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    No. jaringan:
                                    {{ site.no_jaringan || '—' }} · ID
                                    {{ site.id }}
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
                <p
                    v-if="form.errors.online_billing_ids"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.online_billing_ids }}
                </p>
            </section>

            <section class="space-y-4 rounded-xl border bg-card p-4 md:p-6">
                <div>
                    <h2 class="font-semibold">2. Tentukan target SLA</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Pilih nilai umum atau masukkan target sendiri. Nilai ini
                        hanya diterapkan ke site terpilih; target SLA Global
                        tidak berubah.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in presets"
                        :key="preset.value"
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm font-medium transition hover:bg-muted"
                        :class="
                            form.sla_percentage === preset.value
                                ? 'border-primary bg-primary/10 text-primary'
                                : ''
                        "
                        @click="form.sla_percentage = preset.value"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <div class="max-w-sm space-y-2">
                    <label for="sla_percentage" class="text-sm font-medium"
                        >Target SLA (%)</label
                    >
                    <div class="relative">
                        <input
                            id="sla_percentage"
                            v-model.number="form.sla_percentage"
                            type="number"
                            min="0.0001"
                            max="100"
                            step="0.0001"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 pr-10 text-sm"
                        />
                        <span
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <p
                        v-if="form.errors.sla_percentage"
                        class="text-sm text-red-600"
                    >
                        {{ form.errors.sla_percentage }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Rentang yang diizinkan: lebih dari 0% hingga 100%.
                    </p>
                </div>
            </section>

            <section class="space-y-4 rounded-xl border bg-card p-4 md:p-6">
                <div>
                    <h2 class="font-semibold">3. Tanggal mulai berlaku</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Target akan berlaku mulai tanggal ini dan berlanjut
                        sampai ada aturan berikutnya. Aturan site sebelumnya
                        yang masih berlaku akan diakhiri sehari sebelum tanggal
                        ini.
                    </p>
                </div>

                <div class="max-w-sm space-y-2">
                    <label for="effective_from" class="text-sm font-medium"
                        >Berlaku mulai</label
                    >
                    <input
                        id="effective_from"
                        v-model="form.effective_from"
                        type="date"
                        required
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <p
                        v-if="form.errors.effective_from"
                        class="text-sm text-red-600"
                    >
                        {{ form.errors.effective_from }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                >
                    Sistem tidak akan menghapus histori aturan SLA. Jika site
                    yang dipilih sudah memiliki aturan terjadwal mulai pada atau
                    setelah tanggal ini, proses akan ditolak agar jadwal
                    tersebut tidak tertimpa.
                </div>
            </section>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    {{ form.online_billing_ids.length }} site akan menerima
                    target {{ String(form.sla_percentage).replace('.', ',') }}%.
                </p>
                <button
                    type="submit"
                    :disabled="
                        form.processing ||
                        !form.pelanggan_id ||
                        form.online_billing_ids.length === 0 ||
                        !form.effective_from ||
                        Number(form.sla_percentage) <= 0 ||
                        Number(form.sla_percentage) > 100
                    "
                    class="inline-flex items-center justify-center rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? 'Menyimpan...'
                            : 'Terapkan Target ke Site Terpilih'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>

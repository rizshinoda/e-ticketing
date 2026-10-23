<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';

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

interface SlaRuleRow {
    id: number;
    pelanggan_id: number;
    scope: 'global' | 'site';
    online_billing_id: number | null;
    sla_percentage: string | number;
    effective_from: string;
    effective_until: string | null;
    pelanggan?: Customer;
    online_billing?: Site | null;
}

const props = defineProps<{
    customers: Customer[];
    sites: Site[];
    rules: SlaRuleRow[];
    today: string;
    success: string | null;
}>();

const editingId = ref<number | null>(null);

const form = useForm({
    pelanggan_id: '',
    scope: 'global' as 'global' | 'site',
    online_billing_id: '',
    sla_percentage: '99.5',
    effective_from: props.today,
    effective_until: '',
});

const filteredSites = computed(() =>
    props.sites.filter(
        (site) => String(site.pelanggan_id) === form.pelanggan_id,
    ),
);

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    form.clearErrors();

    const options = {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    };

    if (editingId.value !== null) {
        form.put(`/sla-rules/${editingId.value}`, options);
    } else {
        form.post('/sla-rules', options);
    }
}

function editRule(rule: SlaRuleRow) {
    editingId.value = rule.id;

    form.clearErrors();

    form.pelanggan_id = String(rule.pelanggan_id);
    form.scope = rule.scope;
    form.online_billing_id = rule.online_billing_id
        ? String(rule.online_billing_id)
        : '';
    form.sla_percentage = String(rule.sla_percentage);
    form.effective_from = dateOnly(rule.effective_from);
    form.effective_until = dateOnly(rule.effective_until);
}

function deleteRule(rule: SlaRuleRow) {
    const confirmed = window.confirm(
        `Hapus aturan SLA ${rule.scope === 'global' ? 'Global' : 'Site'} ` +
            `untuk ${rule.pelanggan?.nama_pelanggan ?? 'pelanggan ini'}? ` +
            'Penghapusan dapat memengaruhi perhitungan laporan historis.',
    );

    if (!confirmed) return;

    router.delete(`/sla-rules/${rule.id}`, {
        preserveScroll: true,
    });
}

function dateOnly(value: string | null): string {
    return value ? value.substring(0, 10) : '';
}

function formatPercent(value: string | number): string {
    return `${Number(value).toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    })}%`;
}

function scopeLabel(scope: string): string {
    return scope === 'global' ? 'Global' : 'Per Site';
}
</script>

<template>
    <div class="space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Pengaturan Target SLA
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Kelola target SLA Global dan per Site beserta periode
                berlakunya.
            </p>
        </div>

        <div
            v-if="props.success"
            class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ props.success }}
        </div>

        <section class="rounded-xl border bg-card p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{
                            editingId ? 'Edit Aturan SLA' : 'Tambah Aturan SLA'
                        }}
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Aturan Global tidak terikat pada satu site. Aturan Site
                        hanya berlaku untuk site yang dipilih.
                    </p>
                    <Link
                        href="/sla-rules/bulk-sites"
                        class="inline-flex items-center rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
                    >
                        Terapkan Target ke Banyak Site
                    </Link>
                </div>

                <button
                    v-if="editingId"
                    type="button"
                    class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    @click="resetForm"
                >
                    Batal edit
                </button>
            </div>

            <form class="mt-5 space-y-5" @submit.prevent="submitForm">
                <div class="grid gap-5 md:grid-cols-2">
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
                            @change="form.online_billing_id = ''"
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
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.pelanggan_id }}
                        </p>
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
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            @change="form.online_billing_id = ''"
                        >
                            <option value="global">SLA Global</option>
                            <option value="site">SLA per Site</option>
                        </select>

                        <p
                            v-if="form.errors.scope"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.scope }}
                        </p>
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
                            v-if="form.errors.online_billing_id"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.online_billing_id }}
                        </p>

                        <p
                            v-if="
                                form.pelanggan_id && filteredSites.length === 0
                            "
                            class="mt-1 text-sm text-amber-600"
                        >
                            Tidak ada site milik pelanggan ini.
                        </p>
                    </div>

                    <div>
                        <label
                            for="target"
                            class="mb-2 block text-sm font-medium"
                        >
                            Target SLA (%)
                        </label>

                        <input
                            id="target"
                            v-model="form.sla_percentage"
                            type="number"
                            min="0.0001"
                            max="100"
                            step="0.0001"
                            required
                            placeholder="Contoh: 99.5"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />

                        <p
                            v-if="form.errors.sla_percentage"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.sla_percentage }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="effective_from"
                            class="mb-2 block text-sm font-medium"
                        >
                            Berlaku mulai
                        </label>

                        <input
                            id="effective_from"
                            v-model="form.effective_from"
                            type="date"
                            required
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />

                        <p
                            v-if="form.errors.effective_from"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.effective_from }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="effective_until"
                            class="mb-2 block text-sm font-medium"
                        >
                            Berlaku sampai
                        </label>

                        <input
                            id="effective_until"
                            v-model="form.effective_until"
                            type="date"
                            :min="form.effective_from"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />

                        <p class="mt-1 text-xs text-muted-foreground">
                            Kosongkan jika belum ditentukan tanggal akhirnya.
                        </p>

                        <p
                            v-if="form.errors.effective_until"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.effective_until }}
                        </p>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? 'Menyimpan...'
                            : editingId
                              ? 'Simpan Perubahan'
                              : 'Simpan Aturan SLA'
                    }}
                </button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b p-5">
                <h2 class="text-lg font-semibold">Daftar Aturan SLA</h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.rules.length }} aturan terdaftar.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Cakupan</th>
                            <th class="px-4 py-3">Site</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Mulai</th>
                            <th class="px-4 py-3">Sampai</th>
                            <th class="px-4 py-3">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="rule in props.rules"
                            :key="rule.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3">
                                {{ rule.pelanggan?.nama_pelanggan ?? '-' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ scopeLabel(rule.scope) }}
                            </td>

                            <td class="px-4 py-3">
                                {{
                                    rule.scope === 'global'
                                        ? 'Semua site'
                                        : (rule.online_billing?.nama_site ??
                                          '-')
                                }}
                            </td>

                            <td class="px-4 py-3 font-semibold">
                                {{ formatPercent(rule.sla_percentage) }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ dateOnly(rule.effective_from) }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{
                                    dateOnly(rule.effective_until) ||
                                    'Tidak dibatasi'
                                }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-1.5 hover:bg-muted"
                                        @click="editRule(rule)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-md border border-red-300 px-3 py-1.5 text-red-600 hover:bg-red-50"
                                        @click="deleteRule(rule)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="props.rules.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                Belum ada aturan SLA. Tambahkan aturan pertama
                                melalui formulir di atas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

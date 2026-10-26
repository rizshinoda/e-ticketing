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
}

interface SlaRuleRow {
    id: number;
    pelanggan_id: number;
    scope: 'global' | 'site';
    online_billing_id: number | null;
    sla_percentage: number | string;
    effective_from: string;
    effective_until: string | null;
    pelanggan?: Customer | null;
    online_billing?: Site | null;
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
    sites: Site[];
    rules: Paginated<SlaRuleRow>;
    filters: {
        search?: string;
    };
    success?: string | null;
}>();

const editingId = ref<number | null>(null);
const search = ref(props.filters?.search ?? '');

const form = useForm({
    pelanggan_id: '',
    scope: 'global' as 'global' | 'site',
    online_billing_id: '',
    sla_percentage: '99.5',
});

const isEditing = computed(() => editingId.value !== null);

const filteredSites = computed(() => {
    if (!form.pelanggan_id) {
        return [];
    }

    return props.sites.filter(
        (site) => String(site.pelanggan_id) === form.pelanggan_id,
    );
});

// Error validasi umum jika controller menggunakan key "rule".
const ruleError = computed(() => {
    const errors = form.errors as unknown as Record<string, string | undefined>;

    return errors.rule ?? '';
});

watch(
    () => form.pelanggan_id,
    () => {
        form.online_billing_id = '';
    },
);

watch(
    () => form.scope,
    (scope) => {
        if (scope === 'global') {
            form.online_billing_id = '';
        }
    },
);

watch(
    () => props.filters?.search,
    (value) => {
        search.value = value ?? '';
    },
);

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

async function submitForm() {
    form.clearErrors();

    const isEditing = editingId.value !== null;

    // Tampilkan dialog konfirmasi sebelum mengirim form.
    const confirmed = await confirmAction({
        title: isEditing ? 'Perbarui Target SLA?' : 'Simpan Target SLA?',
        text: isEditing
            ? 'Target SLA akan diperbarui menggunakan nilai yang baru.'
            : 'Target SLA baru akan disimpan sesuai pelanggan dan cakupan yang dipilih.',
        confirmButtonText: isEditing ? 'Ya, perbarui' : 'Ya, simpan',
        cancelButtonText: 'Batal',
    });

    // Berhenti jika pengguna membatalkan.
    if (!confirmed) {
        return;
    }

    const options = {
        preserveScroll: true,

        onSuccess: () => {
            resetForm();

            showSuccessToast(
                isEditing
                    ? 'Target SLA berhasil diperbarui.'
                    : 'Target SLA berhasil ditambahkan.',
            );
        },

        onError: () => {
            showErrorToast(
                'Target SLA gagal disimpan. Periksa kembali data yang dimasukkan.',
            );
        },
    };

    if (isEditing) {
        form.put(`/sla-rules/${editingId.value}`, options);
        return;
    }

    form.post('/sla-rules', options);
}
function editRule(rule: SlaRuleRow) {
    editingId.value = rule.id;
    form.clearErrors();

    form.pelanggan_id = String(rule.pelanggan_id);
    form.scope = rule.scope;
    form.online_billing_id =
        rule.online_billing_id !== null ? String(rule.online_billing_id) : '';
    form.sla_percentage = String(rule.sla_percentage);
}

async function deleteRule(rule: SlaRuleRow) {
    const customerName = rule.pelanggan?.nama_pelanggan ?? 'pelanggan ini';

    const confirmed = await confirmAction({
        title: 'Hapus Target SLA?',
        text: `Target SLA aktif untuk ${customerName} akan dihapus. Tindakan ini tidak dapat dibatalkan.`,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) return;

    router.delete(`/sla-rules/${rule.id}`, {
        preserveScroll: true,

        onSuccess: () => {
            showSuccessToast('Target SLA berhasil dihapus.');
        },

        onError: () => {
            showErrorToast('Target SLA gagal dihapus.');
        },
    });
}

function applySearch() {
    router.get(
        '/sla-rules',
        { search: search.value.trim() || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearSearch() {
    search.value = '';

    router.get(
        '/sla-rules',
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
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

function scopeLabel(scope: 'global' | 'site') {
    return scope === 'global' ? 'Global Pelanggan' : 'Per Site';
}

function siteName(rule: SlaRuleRow) {
    if (rule.scope === 'global') {
        return 'Semua site pelanggan';
    }

    return (
        rule.online_billing?.nama_site ??
        `Site ID: ${rule.online_billing_id ?? '—'}`
    );
}
</script>

<template>
    <Head title="Pengaturan Target SLA" />

    <div class="space-y-6 p-4 text-foreground sm:p-6 lg:p-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Pengaturan Target SLA
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Kelola target SLA aktif untuk pelanggan atau site. Target
                    berlaku sampai diperbarui oleh admin.
                </p>
            </div>

            <Link
                href="/sla-rules/bulk-sites"
                class="inline-flex items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition hover:bg-accent hover:text-accent-foreground"
            >
                Atur Banyak Site
            </Link>
        </div>

        <!-- Form tambah/edit -->
        <section
            class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
        >
            <div
                class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold text-foreground">
                        {{
                            isEditing ? 'Edit Target SLA' : 'Tambah Target SLA'
                        }}
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            isEditing
                                ? 'Perbarui nilai target SLA yang aktif.'
                                : 'Buat atau atur target SLA aktif untuk cakupan yang dipilih.'
                        }}
                    </p>
                </div>

                <button
                    v-if="isEditing"
                    type="button"
                    class="self-start rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-accent sm:self-auto"
                    @click="resetForm"
                >
                    Batal Edit
                </button>
            </div>

            <form class="space-y-5" @submit.prevent="submitForm">
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

                    <!-- Cakupan -->
                    <div>
                        <label
                            for="scope"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            Cakupan Target
                        </label>

                        <select
                            id="scope"
                            v-model="form.scope"
                            required
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
                        >
                            <option value="global">Global Pelanggan</option>
                            <option value="site">Per Site</option>
                        </select>

                        <p
                            v-if="form.errors.scope"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.scope }}
                        </p>
                    </div>

                    <!-- Site -->
                    <div v-if="form.scope === 'site'" class="md:col-span-2">
                        <label
                            for="online_billing_id"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            Site
                        </label>

                        <select
                            id="online_billing_id"
                            v-model="form.online_billing_id"
                            required
                            :disabled="!form.pelanggan_id"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-70"
                        >
                            <option value="" disabled>
                                {{
                                    form.pelanggan_id
                                        ? 'Pilih site'
                                        : 'Pilih pelanggan terlebih dahulu'
                                }}
                            </option>

                            <option
                                v-for="site in filteredSites"
                                :key="site.id"
                                :value="String(site.id)"
                            >
                                {{ site.nama_site }}
                                <template v-if="site.no_jaringan">
                                    — {{ site.no_jaringan }}
                                </template>
                            </option>
                        </select>

                        <p
                            v-if="form.errors.online_billing_id"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.online_billing_id }}
                        </p>

                        <p
                            v-if="
                                form.pelanggan_id && filteredSites.length === 0
                            "
                            class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                        >
                            Tidak ada site yang tersedia untuk pelanggan ini.
                        </p>
                    </div>

                    <!-- Target SLA -->
                    <div :class="form.scope === 'site' ? 'md:col-span-2' : ''">
                        <label
                            for="sla_percentage"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            Target SLA (%)
                        </label>

                        <div class="relative">
                            <input
                                id="sla_percentage"
                                v-model="form.sla_percentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                placeholder="Contoh: 99.5"
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

                        <p
                            v-if="ruleError"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ ruleError }}
                        </p>

                        <p class="mt-1.5 text-xs text-muted-foreground">
                            Target aktif akan berlaku sampai admin
                            memperbaruinya.
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-3 border-t border-border pt-4 sm:flex-row sm:justify-end"
                >
                    <button
                        v-if="isEditing"
                        type="button"
                        class="rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium text-foreground hover:bg-accent"
                        @click="resetForm"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : isEditing
                                  ? 'Simpan Perubahan'
                                  : 'Simpan Target SLA'
                        }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Daftar target aktif -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div
                class="flex flex-col gap-4 border-b border-border p-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold text-foreground">
                        Target SLA Aktif
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ props.rules.total }} target aktif ditemukan.
                    </p>
                </div>

                <form
                    class="flex w-full flex-col gap-2 sm:flex-row lg:max-w-xl"
                    @submit.prevent="applySearch"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari pelanggan, site, jaringan, target..."
                        class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2.5 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/30"
                    />

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Cari
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium text-foreground hover:bg-accent"
                            @click="clearSearch"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[760px] divide-y divide-border text-sm"
                >
                    <thead class="bg-muted/50">
                        <tr>
                            <th
                                class="px-5 py-3 text-left font-semibold text-muted-foreground"
                            >
                                Pelanggan
                            </th>
                            <th
                                class="px-5 py-3 text-left font-semibold text-muted-foreground"
                            >
                                Cakupan
                            </th>
                            <th
                                class="px-5 py-3 text-left font-semibold text-muted-foreground"
                            >
                                Site
                            </th>
                            <th
                                class="px-5 py-3 text-left font-semibold text-muted-foreground"
                            >
                                Target SLA
                            </th>
                            <th
                                class="px-5 py-3 text-right font-semibold text-muted-foreground"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="rule in props.rules.data"
                            :key="rule.id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-4 font-medium text-foreground">
                                {{
                                    rule.pelanggan?.nama_pelanggan ??
                                    `Pelanggan ID: ${rule.pelanggan_id}`
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        rule.scope === 'global'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                                            : 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300'
                                    "
                                >
                                    {{ scopeLabel(rule.scope) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-muted-foreground">
                                {{ siteName(rule) }}

                                <div
                                    v-if="
                                        rule.scope === 'site' &&
                                        rule.online_billing?.no_jaringan
                                    "
                                    class="mt-1 text-xs text-muted-foreground/80"
                                >
                                    {{ rule.online_billing.no_jaringan }}
                                </div>
                            </td>

                            <td class="px-5 py-4 font-semibold text-foreground">
                                {{ formatPercent(rule.sla_percentage) }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-accent"
                                        @click="editRule(rule)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-destructive/30 bg-background px-3 py-1.5 text-xs font-medium text-destructive hover:bg-destructive/10"
                                        @click="deleteRule(rule)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="props.rules.data.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center">
                                <p class="font-medium text-foreground">
                                    Target SLA tidak ditemukan.
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Coba ubah kata pencarian atau tambahkan
                                    target SLA baru.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                class="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan
                    <span class="font-medium text-foreground">
                        {{ props.rules.from ?? 0 }}–{{ props.rules.to ?? 0 }}
                    </span>
                    dari
                    <span class="font-medium text-foreground">
                        {{ props.rules.total }}
                    </span>
                    target
                </p>

                <div class="flex flex-wrap items-center gap-1">
                    <button
                        v-for="(link, index) in props.rules.links"
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
        </section>
    </div>
</template>

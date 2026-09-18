<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
/*
|--------------------------------------------------------------------------
| Interfaces
|--------------------------------------------------------------------------
*/

interface User {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
    is_downtime: boolean;
}

interface StopClock {
    id: number;
    started_at: string;
    ended_at: string | null;
    reason: string;
}

interface Rfo {
    id: number;
    rfo_number: string;
    content: string;
    created_by: number;
    created_at: string;
    creator: {
        id: number;
        name: string;
    };
}

interface Incident {
    id: number;
    incident_number: number;
    reported_at: string;
    resolved_at: string | null;

    category: Category;
}

interface Customer {
    id: number;
    customer_name: string | null;
    site_name: string | null;
    no_jaringan: string | null;
    reported_via: string | null;
    online_billing: {
        id: number;
    } | null;
}

interface Attachment {
    id: number;
    file_path: string;
    file_name: string;
    mime_type: string | null;
    file_size: number | null;
}

interface Update {
    id: number;
    message: string;
    created_at: string;
    user: User;
    attachments: Attachment[];
}

interface Ticket {
    id: number;
    ticket_number: string;
    ticket_type: 'individual' | 'gamas';
    description: string | null;
    priority: string;
    status: string;
    first_response_at: string | null;
    reported_at: string;
    resolved_at: string | null;
    downtime_minutes: number | null;
    closed_at: string | null;
    created_at: string;
    creator: User | null;

    customers: Customer[];
    incidents: Incident[];

    stop_clocks: StopClock[];
    rfos: Rfo[];
    updates: Update[];
}
interface AvailableSite {
    id: number;
    pelanggan_id: number;
    nama_site: string | null;
    no_jaringan: string | null;
}
/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/
const groupedCustomers = computed(() => {
    const groups: Record<string, Customer[]> = {};

    props.ticket.customers.forEach((customer) => {
        const customerName =
            customer.customer_name || 'Customer Tidak Diketahui';

        if (!groups[customerName]) {
            groups[customerName] = [];
        }

        groups[customerName].push(customer);
    });

    return Object.entries(groups).map(([customerName, sites]) => ({
        customerName,
        sites,
    }));
});
const props = defineProps<{
    ticket: Ticket;
    categories: Category[];
    availableSites: AvailableSite[];
}>();
const page = usePage<{
    flash: {
        success?: string;
    };
}>();

const showAddSiteForm = ref(false);

const addSiteForm = useForm({
    online_billing_id: null as number | null,
});

const siteSearch = ref('');
const showSiteResults = ref(false);
const filteredSites = computed(() => {
    const search = siteSearch.value.toLowerCase().trim();

    if (!search) {
        return props.availableSites;
    }

    return props.availableSites.filter((site) => {
        return (
            site.nama_site?.toLowerCase().includes(search) ||
            site.no_jaringan?.toLowerCase().includes(search)
        );
    });
});
const selectSite = (site: AvailableSite) => {
    addSiteForm.online_billing_id = site.id;

    siteSearch.value = `${site.nama_site || '-'} - ${site.no_jaringan || '-'}`;

    showSiteResults.value = false;
};
const openAddSiteForm = () => {
    addSiteForm.reset();
    siteSearch.value = '';
    showSiteResults.value = false;
    showAddSiteForm.value = true;
};

const cancelAddSiteForm = () => {
    addSiteForm.reset();
    siteSearch.value = '';
    showSiteResults.value = false;
    showAddSiteForm.value = false;
};
const submitAddSite = () => {
    if (!addSiteForm.online_billing_id) {
        return;
    }

    addSiteForm.post(`/tickets/${props.ticket.id}/sites`, {
        preserveScroll: true,

        onSuccess: () => {
            addSiteForm.reset();
            showAddSiteForm.value = false;
        },
    });
};
/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

const formatDate = (date: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

/*
|--------------------------------------------------------------------------
| Status Label
|--------------------------------------------------------------------------
*/

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        open: 'Open',
        on_progress: 'On Progress',
        resolved: 'Resolved',
        closed: 'Closed',
    };

    return labels[status] ?? status;
};

/*
|--------------------------------------------------------------------------
| Priority Label
|--------------------------------------------------------------------------
*/

const priorityLabel = (priority: string) => {
    const labels: Record<string, string> = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        critical: 'Critical',
    };

    return labels[priority] ?? priority;
};

/*
|--------------------------------------------------------------------------
| Priority Class
|--------------------------------------------------------------------------
*/

const priorityClass = (priority: string) => {
    const classes: Record<string, string> = {
        low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

        medium: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',

        high: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',

        critical: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    };

    return classes[priority] ?? 'bg-muted text-muted-foreground';
};

/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

const statusClass = (status: string) => {
    const classes: Record<string, string> = {
        open: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',

        on_progress:
            'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',

        resolved:
            'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',

        closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status] ?? 'bg-muted text-muted-foreground';
};

/*
|--------------------------------------------------------------------------
| Format Downtime
|--------------------------------------------------------------------------
*/

const formatDowntime = (minutes: number | null) => {
    if (minutes === null) {
        return '-';
    }

    const hours = Math.floor(minutes / 60);

    const remainingMinutes = minutes % 60;

    if (hours === 0) {
        return `${remainingMinutes} menit`;
    }

    return `${hours} jam ${remainingMinutes} menit`;
};

/*
|--------------------------------------------------------------------------
| Update Form
|--------------------------------------------------------------------------
*/

const updateForm = useForm<{
    message: string;
    attachments: File[];
}>({
    message: '',
    attachments: [],
});
const rfoForm = useForm({
    content: '',
});
const showRfoForm = ref(false);
const submitRfo = () => {
    rfoForm.post(`/tickets/${props.ticket.id}/rfo`, {
        preserveScroll: true,
        onSuccess: () => {
            rfoForm.reset();
            showRfoForm.value = false;
        },
    });
};
const editingRfoId = ref<number | null>(null);

const editRfoForm = useForm({
    content: '',
});
const startEditRfo = (rfo: Rfo) => {
    editingRfoId.value = rfo.id;
    editRfoForm.content = rfo.content;
};
const cancelEditRfo = () => {
    editingRfoId.value = null;
    editRfoForm.reset();
};
const submitEditRfo = (rfo: Rfo) => {
    editRfoForm.put(`/tickets/${props.ticket.id}/rfo/${rfo.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingRfoId.value = null;
            editRfoForm.reset();
        },
    });
};
/*
|--------------------------------------------------------------------------
| Handle Files
|--------------------------------------------------------------------------
*/

const handleFiles = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (!target.files) {
        return;
    }

    updateForm.attachments = Array.from(target.files);
};

/*
|--------------------------------------------------------------------------
| Submit Update
|--------------------------------------------------------------------------
*/

const submitUpdate = () => {
    updateForm.post(`/tickets/${props.ticket.id}/updates`, {
        forceFormData: true,

        preserveScroll: true,

        onSuccess: () => {
            updateForm.reset();
        },
    });
};

/*
|--------------------------------------------------------------------------
| Stop Clock Form
|--------------------------------------------------------------------------
*/

const stopClockForm = useForm<{
    reason: string;
}>({
    reason: '',
});
const showStopClockForm = ref(false);
const showReopenForm = ref(false);

const reopenForm = useForm({
    kendala_id: null as number | null,
    reported_at: new Date()
        .toLocaleString('sv-SE', {
            timeZone: 'Asia/Jakarta',
        })
        .replace(' ', 'T')
        .slice(0, 16),
    reason: '',
});

const openReopenForm = () => {
    showReopenForm.value = true;
};

const submitReopen = () => {
    reopenForm.post(`/tickets/${props.ticket.id}/reopen`, {
        preserveScroll: true,

        onSuccess: () => {
            reopenForm.reset();
            showReopenForm.value = false;
        },
    });
};
/*
|--------------------------------------------------------------------------
| Cancel Stop Clock
|--------------------------------------------------------------------------
*/

const cancelStopClockForm = () => {
    stopClockForm.reset();
};

/*
|--------------------------------------------------------------------------
| Start Stop Clock
|--------------------------------------------------------------------------
*/

const submitStopClock = () => {
    stopClockForm.post(`/tickets/${props.ticket.id}/stop-clock`, {
        preserveScroll: true,
        onSuccess: () => {
            stopClockForm.reset();
        },
    });
};

/*
|--------------------------------------------------------------------------
| Resume Stop Clock
|--------------------------------------------------------------------------
*/

const resumeStopClock = () => {
    stopClockForm.post(`/tickets/${props.ticket.id}/stop-clock/resume`, {
        preserveScroll: true,
        onSuccess: () => {
            stopClockForm.reset();
        },
    });
};
/*
|--------------------------------------------------------------------------
| Resolve Ticket
|--------------------------------------------------------------------------
*/
const showResolveForm = ref(false);

const resolveForm = useForm({
    resolution: '',
});

const openResolveForm = () => {
    resolveForm.reset();
    showResolveForm.value = true;
};

const cancelResolveForm = () => {
    resolveForm.reset();
    showResolveForm.value = false;
};

const submitResolve = () => {
    resolveForm.post(`/tickets/${props.ticket.id}/resolve`, {
        preserveScroll: true,
        onSuccess: () => {
            resolveForm.reset();
            showResolveForm.value = false;
        },
    });
};
const closeTicket = () => {
    useForm({}).post(`/tickets/${props.ticket.id}/close`, {
        preserveScroll: true,

        onError: (errors) => {
            console.log('CLOSE ERRORS:', errors);
        },
    });
};
/*
|--------------------------------------------------------------------------
| Check Active Stop Clock
|--------------------------------------------------------------------------
*/

const hasActiveStopClock = () => {
    return props.ticket.stop_clocks.some(
        (stopClock) => stopClock.ended_at === null,
    );
};

const showSuccess = ref(false);
const showResolveError = ref(false);
const showCloseError = ref(false);

let toastTimer: ReturnType<typeof setTimeout> | null = null;

const startToastTimer = () => {
    if (toastTimer) {
        clearTimeout(toastTimer);
    }

    toastTimer = setTimeout(() => {
        showSuccess.value = false;
        showResolveError.value = false;
        showCloseError.value = false;
    }, 3000);
};

watch(
    () => page.props.flash.success,
    (message) => {
        if (!message) {
            return;
        }

        showSuccess.value = true;
        startToastTimer();
    },
    { immediate: true },
);

watch(
    () => page.props.errors.resolve,
    (message) => {
        if (!message) {
            return;
        }

        showResolveError.value = true;
        startToastTimer();
    },
    { immediate: true },
);

watch(
    () => page.props.errors.close,
    (message) => {
        if (!message) {
            return;
        }

        showCloseError.value = true;
        startToastTimer();
    },
    { immediate: true },
);
</script>

<template>
    <!-- =====================================================
         FLASH MESSAGE
    ====================================================== -->
    <!-- SUCCESS TOAST -->
    <div
        v-if="showSuccess && page.props.flash.success"
        class="fixed top-6 right-6 z-50 flex max-w-md items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 shadow-lg"
    >
        <div class="flex-1">
            {{ page.props.flash.success }}
        </div>

        <button
            type="button"
            class="text-lg leading-none text-green-500 hover:text-green-700"
            @click="showSuccess = false"
        >
            ×
        </button>
    </div>

    <!-- RESOLVE ERROR TOAST -->
    <div
        v-if="showResolveError && page.props.errors.resolve"
        class="fixed top-6 right-6 z-50 flex max-w-md items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg"
    >
        <div class="flex-1">
            {{ page.props.errors.resolve }}
        </div>

        <button
            type="button"
            class="text-lg leading-none text-red-500 hover:text-red-700"
            @click="showResolveError = false"
        >
            ×
        </button>
    </div>

    <!-- CLOSE ERROR TOAST -->
    <div
        v-if="showCloseError && page.props.errors.close"
        class="fixed top-6 right-6 z-50 flex max-w-md items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg"
    >
        <div class="flex-1">
            {{ page.props.errors.close }}
        </div>

        <button
            type="button"
            class="text-lg leading-none text-red-500 hover:text-red-700"
            @click="showCloseError = false"
        >
            ×
        </button>
    </div>
    <div class="p-6">
        <!-- =====================================================
             HEADER
        ====================================================== -->
        <div class="mb-6 flex items-start justify-between">
            <div>
                <div class="mb-1 text-sm text-muted-foreground">Ticket</div>

                <h1 class="text-2xl font-semibold">
                    {{ ticket.ticket_number }}
                </h1>

                <div class="mt-2 flex flex-wrap gap-2">
                    <!-- Ticket Type -->
                    <span class="rounded-md bg-muted px-2 py-1 text-xs">
                        {{
                            ticket.ticket_type === 'gamas'
                                ? 'GAMAS'
                                : 'Individual'
                        }}
                    </span>

                    <!-- Priority -->
                    <span
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="priorityClass(ticket.priority)"
                    >
                        {{ priorityLabel(ticket.priority) }}
                    </span>

                    <!-- Status -->
                    <span
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="statusClass(ticket.status)"
                    >
                        {{ statusLabel(ticket.status) }}
                    </span>
                </div>
            </div>

            <Link
                href="/tickets"
                class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
            >
                Kembali
            </Link>
        </div>

        <!-- =====================================================
             MAIN GRID
        ====================================================== -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- =================================================
                 LEFT / MAIN
            ================================================== -->
            <div class="space-y-6 lg:col-span-2">
                <!-- =================================================
                     DESCRIPTION
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-3 font-semibold">Deskripsi Gangguan</h2>

                    <div class="text-sm whitespace-pre-wrap">
                        {{ ticket.description || '-' }}
                    </div>
                </div>

                <!-- =================================================
                     CUSTOMER / SITE
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">Customer / Site Terdampak</h2>

                        <span class="text-sm text-muted-foreground">
                            {{ ticket.customers.length }}
                            site
                        </span>
                        <button
                            v-if="ticket.ticket_type === 'gamas'"
                            type="button"
                            class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
                            @click="openAddSiteForm"
                        >
                            + Tambah Site
                        </button>
                    </div>
                    <div
                        v-if="showAddSiteForm"
                        class="mb-4 rounded-lg border p-4"
                    >
                        <div class="mb-3">
                            <h3 class="font-medium">Tambah Site</h3>

                            <p class="text-sm text-muted-foreground">
                                Cari site yang akan ditambahkan ke ticket GAMAS.
                            </p>
                        </div>

                        <div class="relative">
                            <label class="mb-1 block text-sm font-medium">
                                Site
                            </label>

                            <input
                                v-model="siteSearch"
                                type="text"
                                placeholder="Cari nama site atau no jaringan..."
                                class="w-full rounded-md border px-3 py-2 text-sm"
                                @focus="showSiteResults = true"
                            />

                            <!-- HASIL PENCARIAN -->
                            <div
                                v-if="showSiteResults && filteredSites.length"
                                class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-background shadow-lg"
                            >
                                <button
                                    v-for="site in filteredSites"
                                    :key="site.id"
                                    type="button"
                                    class="block w-full border-b px-3 py-3 text-left hover:bg-muted"
                                    @click="selectSite(site)"
                                >
                                    <div class="text-sm font-medium">
                                        {{ site.nama_site || '-' }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        No Jaringan:
                                        {{ site.no_jaringan || '-' }}
                                    </div>
                                </button>
                            </div>

                            <!-- TIDAK ADA HASIL -->
                            <div
                                v-if="
                                    showSiteResults &&
                                    siteSearch &&
                                    filteredSites.length === 0
                                "
                                class="absolute z-50 mt-1 w-full rounded-md border bg-background p-3 text-sm text-muted-foreground shadow-lg"
                            >
                                Site tidak ditemukan.
                            </div>
                        </div>

                        <!-- SITE YANG DIPILIH -->
                        <div
                            v-if="addSiteForm.online_billing_id"
                            class="mt-3 rounded-md bg-muted/40 p-3"
                        >
                            <div class="text-sm font-medium">Site dipilih</div>

                            <div class="mt-1 text-sm">
                                {{ siteSearch }}
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button
                                type="button"
                                class="rounded-md border px-4 py-2 text-sm"
                                @click="cancelAddSiteForm"
                            >
                                Batal
                            </button>

                            <button
                                type="button"
                                class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
                                :disabled="
                                    !addSiteForm.online_billing_id ||
                                    addSiteForm.processing
                                "
                                @click="submitAddSite"
                            >
                                {{
                                    addSiteForm.processing
                                        ? 'Menyimpan...'
                                        : 'Tambah Site'
                                }}
                            </button>
                        </div>
                    </div>
                    <div v-if="ticket.customers.length" class="space-y-4">
                        <!-- GROUP CUSTOMER -->
                        <div
                            v-for="group in groupedCustomers"
                            :key="group.customerName"
                            class="rounded-lg border p-4"
                        >
                            <!-- CUSTOMER NAME -->
                            <div class="mb-4">
                                <div class="font-medium">
                                    {{ group.customerName }}
                                </div>
                            </div>

                            <!-- SITES -->
                            <div class="space-y-4">
                                <div
                                    v-for="customer in group.sites"
                                    :key="customer.id"
                                    class="rounded-md bg-muted/40 p-4"
                                >
                                    <!-- SITE INFO -->
                                    <div>
                                        <div class="text-sm font-medium">
                                            {{ customer.site_name || '-' }}
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            No Jaringan:
                                            {{ customer.no_jaringan || '-' }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Dilaporkan melalui:
                                            {{ customer.reported_via || '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada customer/site.
                    </div>
                </div>
                <!-- =================================================
     INCIDENTS
================================================== -->

                <div class="rounded-lg border p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">Case / Insiden</h2>

                        <span class="text-sm text-muted-foreground">
                            {{ ticket.incidents.length }} Case
                        </span>
                    </div>

                    <div v-if="ticket.incidents.length" class="space-y-3">
                        <div
                            v-for="incident in ticket.incidents"
                            :key="incident.id"
                            class="rounded-md bg-muted/40 p-4"
                        >
                            <!-- Incident Header -->
                            <div class="mb-3 flex items-center justify-between">
                                <div class="font-medium">
                                    Case #{{ incident.incident_number }}
                                </div>

                                <span
                                    class="rounded-md bg-muted px-2 py-1 text-xs"
                                >
                                    {{ incident.category.name }}
                                </span>
                            </div>

                            <!-- Incident Reported -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <!-- Reported -->
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        Dilaporkan
                                    </div>

                                    <div>
                                        {{ formatDate(incident.reported_at) }}
                                    </div>
                                </div>

                                <!-- Resolved -->
                                <div>
                                    <div class="text-xs text-muted-foreground">
                                        Diselesaikan
                                    </div>

                                    <div>
                                        {{
                                            incident.resolved_at
                                                ? formatDate(
                                                      incident.resolved_at,
                                                  )
                                                : '-'
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Belum ada incident.
                    </div>
                </div>
                <!-- =================================================
     STOP CLOCK - TICKET LEVEL
================================================== -->

                <div class="rounded-lg border p-5">
                    <!-- =================================================
         STOP CLOCK HEADER
    ================================================== -->

                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold">Stop Clock</h2>

                            <div
                                v-if="hasActiveStopClock()"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                            >
                                SLA sedang dihentikan
                            </div>

                            <div
                                v-else
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                SLA berjalan
                            </div>
                        </div>

                        <!-- =================================================
             ACTION BUTTON
        ================================================== -->

                        <div class="flex items-center gap-2">
                            <!-- Start Stop Clock -->
                            <button
                                v-if="
                                    !hasActiveStopClock() &&
                                    ticket.status === 'on_progress'
                                "
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                @click="
                                    showStopClockForm = !showStopClockForm;

                                    if (showStopClockForm) {
                                        stopClockForm.reason = '';
                                    }
                                "
                            >
                                {{
                                    showStopClockForm
                                        ? 'Tutup Form'
                                        : 'Stop Clock'
                                }}
                            </button>

                            <!-- Resume Stop Clock -->
                            <button
                                v-else-if="hasActiveStopClock()"
                                type="button"
                                class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                                :disabled="stopClockForm.processing"
                                @click="resumeStopClock"
                            >
                                {{
                                    stopClockForm.processing
                                        ? 'Memproses...'
                                        : 'Resume Clock'
                                }}
                            </button>
                        </div>
                    </div>

                    <!-- =================================================
         STOP CLOCK FORM
    ================================================== -->

                    <div
                        v-if="
                            showStopClockForm &&
                            ticket.status === 'on_progress' &&
                            !hasActiveStopClock()
                        "
                        class="mt-3 rounded-md border bg-background p-4"
                    >
                        <form
                            class="space-y-3"
                            @submit.prevent="submitStopClock"
                        >
                            <!-- Reason -->
                            <div>
                                <label class="mb-2 block text-sm font-medium">
                                    Alasan Stop Clock
                                </label>

                                <textarea
                                    v-model="stopClockForm.reason"
                                    rows="3"
                                    class="w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Contoh: Menunggu customer melakukan pengecekan power CPE..."
                                ></textarea>

                                <p
                                    v-if="stopClockForm.errors.reason"
                                    class="mt-1 text-sm text-destructive"
                                >
                                    {{ stopClockForm.errors.reason }}
                                </p>
                            </div>

                            <!-- Form Action -->
                            <div class="flex justify-end gap-2">
                                <!-- Batal -->
                                <button
                                    type="button"
                                    class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                    :disabled="stopClockForm.processing"
                                    @click="cancelStopClockForm"
                                >
                                    Batal
                                </button>

                                <!-- Mulai Stop Clock -->
                                <button
                                    type="submit"
                                    class="rounded-md bg-yellow-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                                    :disabled="
                                        stopClockForm.processing ||
                                        !stopClockForm.reason.trim()
                                    "
                                >
                                    {{
                                        stopClockForm.processing
                                            ? 'Menyimpan...'
                                            : 'Mulai Stop Clock'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- =================================================
         STOP CLOCK HISTORY
    ================================================== -->

                    <div
                        v-if="ticket.stop_clocks.length"
                        class="mt-4 space-y-2"
                    >
                        <div class="text-xs font-medium text-muted-foreground">
                            Riwayat Stop Clock
                        </div>

                        <div
                            v-for="stopClock in ticket.stop_clocks"
                            :key="stopClock.id"
                            class="rounded-md border bg-background p-3 text-xs"
                        >
                            <!-- =================================================
                 TIME
            ================================================== -->

                            <div class="grid gap-2 sm:grid-cols-2">
                                <div>
                                    <span class="text-muted-foreground">
                                        Mulai:
                                    </span>

                                    <span class="ml-1">
                                        {{ formatDate(stopClock.started_at) }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-muted-foreground">
                                        Selesai:
                                    </span>

                                    <span class="ml-1">
                                        {{
                                            stopClock.ended_at
                                                ? formatDate(stopClock.ended_at)
                                                : '-'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- =================================================
                 REASON
            ================================================== -->

                            <div class="mt-2">
                                <span class="text-muted-foreground">
                                    Reason:
                                </span>

                                <span class="ml-1">
                                    {{ stopClock.reason }}
                                </span>
                            </div>

                            <!-- =================================================
                 ACTIVE INDICATOR
            ================================================== -->

                            <div
                                v-if="stopClock.ended_at === null"
                                class="mt-2 font-medium text-yellow-600 dark:text-yellow-400"
                            >
                                ⏸ Sedang aktif
                            </div>
                        </div>
                    </div>

                    <!-- =================================================
         EMPTY HISTORY
    ================================================== -->

                    <div
                        v-else
                        class="mt-4 py-4 text-center text-sm text-muted-foreground"
                    >
                        Belum ada riwayat Stop Clock.
                    </div>
                </div>

                <!-- =================================================
     RFO
================================================== -->

                <div class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="font-semibold">RFO</h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Root Cause &amp; Resolution ticket.
                        </p>
                    </div>

                    <!-- FORM BUAT RFO -->
                    <div v-if="ticket.status === 'resolved'">
                        <button
                            type="button"
                            class="rounded-md border px-4 py-2 text-sm font-medium"
                            @click="showRfoForm = !showRfoForm"
                        >
                            {{ showRfoForm ? 'Tutup Form RFO' : 'Buat RFO' }}
                        </button>

                        <form
                            v-if="showRfoForm"
                            @submit.prevent="submitRfo"
                            class="mt-4 space-y-4"
                        >
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Isi RFO
                                </label>

                                <textarea
                                    v-model="rfoForm.content"
                                    rows="8"
                                    class="w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Tuliskan root cause, tindakan penyelesaian, dan preventive action..."
                                ></textarea>

                                <div
                                    v-if="rfoForm.errors.content"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ rfoForm.errors.content }}
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                                :disabled="rfoForm.processing"
                            >
                                {{
                                    rfoForm.processing
                                        ? 'Menyimpan...'
                                        : 'Simpan RFO'
                                }}
                            </button>
                        </form>
                    </div>

                    <!-- RFO YANG SUDAH TERSIMPAN -->
                    <div class="mt-6">
                        <div v-if="ticket.rfos.length" class="space-y-4">
                            <div
                                v-for="rfo in ticket.rfos"
                                :key="rfo.id"
                                class="rounded-md bg-muted/40 p-4"
                            >
                                <div
                                    class="mb-3 flex items-center justify-between"
                                >
                                    <div class="font-medium">
                                        {{ rfo.rfo_number }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{ formatDate(rfo.created_at) }}
                                    </div>
                                    <button
                                        v-if="
                                            ticket.status === 'resolved' &&
                                            editingRfoId !== rfo.id
                                        "
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        @click="startEditRfo(rfo)"
                                    >
                                        Edit
                                    </button>
                                </div>

                                <div class="mb-3 text-sm">
                                    <span class="text-muted-foreground">
                                        Dibuat oleh:
                                    </span>

                                    {{ rfo.creator.name }}
                                </div>

                                <!-- Tampilan normal -->
                                <div
                                    v-if="editingRfoId !== rfo.id"
                                    class="text-sm whitespace-pre-line"
                                >
                                    {{ rfo.content }}
                                </div>

                                <!-- Form Edit -->
                                <div v-else class="space-y-3">
                                    <textarea
                                        v-model="editRfoForm.content"
                                        rows="8"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                    ></textarea>

                                    <div
                                        v-if="editRfoForm.errors.content"
                                        class="text-sm text-red-600"
                                    >
                                        {{ editRfoForm.errors.content }}
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                            :disabled="editRfoForm.processing"
                                            @click="cancelEditRfo"
                                        >
                                            Batal
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                                            :disabled="
                                                editRfoForm.processing ||
                                                !editRfoForm.content.trim()
                                            "
                                            @click="submitEditRfo(rfo)"
                                        >
                                            {{
                                                editRfoForm.processing
                                                    ? 'Menyimpan...'
                                                    : 'Simpan Perubahan'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-md bg-muted/40 p-4 text-center text-sm text-muted-foreground"
                        >
                            Belum ada RFO.
                        </div>
                    </div>
                </div>
                <!-- =================================================
                     ACTIVITY / PROGRESS
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-5 font-semibold">Activity / Progress</h2>

                    <div v-if="ticket.updates.length" class="space-y-5">
                        <div
                            v-for="update in ticket.updates"
                            :key="update.id"
                            class="relative border-l pl-5"
                        >
                            <!-- Timeline Dot -->
                            <div
                                class="absolute top-1 -left-1.5 h-3 w-3 rounded-full bg-primary"
                            ></div>

                            <!-- Update Header -->
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="font-medium">
                                    {{ update.user.name }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(update.created_at) }}
                                </div>
                            </div>

                            <!-- Message -->
                            <div class="mt-2 text-sm whitespace-pre-wrap">
                                {{ update.message }}
                            </div>

                            <!-- Attachments -->
                            <div
                                v-if="update.attachments.length"
                                class="mt-3 space-y-2"
                            >
                                <div
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Attachment
                                </div>

                                <div
                                    v-for="attachment in update.attachments"
                                    :key="attachment.id"
                                    class="flex items-center justify-between rounded-md border p-2 text-sm"
                                >
                                    <a
                                        :href="`/storage/${attachment.file_path}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="truncate hover:underline"
                                    >
                                        {{ attachment.file_name }}
                                    </a>

                                    <span
                                        v-if="attachment.file_size"
                                        class="ml-3 shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{
                                            (
                                                attachment.file_size /
                                                1024 /
                                                1024
                                            ).toFixed(2)
                                        }}
                                        MB
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Belum ada aktivitas.
                    </div>
                </div>
            </div>

            <!-- =================================================
                 RIGHT SIDEBAR
            ================================================== -->
            <div class="space-y-6">
                <!-- =================================================
                     TICKET INFORMATION
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-4 font-semibold">Informasi Ticket</h2>

                    <div class="space-y-4 text-sm">
                        <!-- Created By -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Dibuat oleh
                            </div>

                            <div>
                                {{ ticket.creator?.name || '-' }}
                            </div>
                        </div>

                        <!-- Created -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Dibuat
                            </div>

                            <div>
                                {{ formatDate(ticket.created_at) }}
                            </div>
                        </div>

                        <!-- Reported -->
                        <!-- <div>
                            <div class="text-xs text-muted-foreground">
                                Laporan Diterima
                            </div>

                            <div>
                                {{ formatDate(ticket.reported_at) }}
                            </div>
                        </div> -->

                        <!-- First Response -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Respon Pertama
                            </div>

                            <div>
                                {{ formatDate(ticket.first_response_at) }}
                            </div>
                        </div>

                        <!-- Downtime -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Waktu Down
                            </div>

                            <div>
                                {{ formatDowntime(ticket.downtime_minutes) }}
                            </div>
                        </div>

                        <!-- Resolved -->
                        <!-- <div>
                            <div class="text-xs text-muted-foreground">
                                Diselesaikan
                            </div>

                            <div>
                                {{ formatDate(ticket.resolved_at) }}
                            </div>
                        </div> -->

                        <!-- Closed -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Ditutup/Close
                            </div>

                            <div>
                                {{ formatDate(ticket.closed_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =================================================
                     ADD UPDATE
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-4 font-semibold">Tambah Progress</h2>

                    <form class="space-y-4" @submit.prevent="submitUpdate">
                        <!-- Message -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Progress / Catatan
                            </label>

                            <textarea
                                v-model="updateForm.message"
                                rows="4"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="Contoh: Dilakukan pengecekan CPE, ditemukan LOS..."
                            ></textarea>

                            <p
                                v-if="updateForm.errors.message"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ updateForm.errors.message }}
                            </p>
                        </div>

                        <!-- Attachment -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Foto / Attachment
                            </label>

                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,application/pdf"
                                class="block w-full rounded-md border px-3 py-2 text-sm"
                                @change="handleFiles"
                            />

                            <p class="mt-1 text-xs text-muted-foreground">
                                Bisa memilih beberapa foto sekaligus. Maksimal 5
                                MB per file.
                            </p>

                            <p
                                v-if="updateForm.errors.attachments"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ updateForm.errors.attachments }}
                            </p>
                        </div>

                        <!-- Selected Files -->
                        <div
                            v-if="updateForm.attachments.length"
                            class="space-y-2"
                        >
                            <div class="text-sm font-medium">
                                File yang dipilih
                            </div>

                            <div
                                v-for="file in updateForm.attachments"
                                :key="file.name + file.size"
                                class="flex items-center justify-between rounded-md border p-2 text-sm"
                            >
                                <span class="truncate">
                                    {{ file.name }}
                                </span>

                                <span
                                    class="ml-3 shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ (file.size / 1024 / 1024).toFixed(2) }}
                                    MB
                                </span>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                                :disabled="
                                    updateForm.processing ||
                                    !updateForm.message.trim()
                                "
                            >
                                {{
                                    updateForm.processing
                                        ? 'Menyimpan...'
                                        : 'Tambah Progress'
                                }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- =================================================
                     ACTIONS
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-4 font-semibold">Action</h2>

                    <div class="space-y-2">
                        <!-- Resolve -->
                        <!-- Resolve -->
                        <button
                            v-if="ticket.status === 'on_progress'"
                            type="button"
                            class="w-full rounded-md bg-green-600 px-4 py-2 text-sm text-white"
                            @click="openResolveForm"
                        >
                            Resolve Ticket
                        </button>

                        <div
                            v-if="showResolveForm"
                            class="mt-4 rounded-md border p-4"
                        >
                            <div class="mb-4 font-medium">Resolve Ticket</div>

                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium"
                                    >
                                        Hasil Penyelesaian
                                    </label>

                                    <select
                                        v-model="resolveForm.resolution"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        <option value="">
                                            Pilih Hasil Penyelesaian
                                        </option>

                                        <option value="provider_issue">
                                            Provider Issue
                                        </option>

                                        <option value="no_issue">
                                            No Issue
                                        </option>
                                    </select>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border px-4 py-2 text-sm"
                                        @click="cancelResolveForm"
                                    >
                                        Batal
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-md bg-green-600 px-4 py-2 text-sm text-white disabled:opacity-50"
                                        :disabled="
                                            resolveForm.processing ||
                                            !resolveForm.resolution
                                        "
                                        @click="submitResolve"
                                    >
                                        Resolve
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button
                            v-if="ticket.status === 'resolved'"
                            type="button"
                            class="w-full rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                            @click="closeTicket"
                        >
                            Close Ticket
                        </button>
                        <!-- Re-Open -->
                        <button
                            v-if="ticket.status === 'closed'"
                            type="button"
                            class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm text-white"
                            @click="openReopenForm"
                        >
                            Re-Open Ticket
                        </button>

                        <div
                            v-if="showReopenForm"
                            class="mt-4 rounded-md border p-4"
                        >
                            <div class="mb-4 font-medium">Re-Open Ticket</div>

                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium"
                                    >
                                        Kendala Baru
                                    </label>

                                    <select
                                        v-model="reopenForm.kendala_id"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        <option value="">Pilih Kendala</option>

                                        <option
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium"
                                    >
                                        Waktu Gangguan
                                    </label>

                                    <input
                                        v-model="reopenForm.reported_at"
                                        type="datetime-local"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium"
                                    >
                                        Alasan
                                    </label>

                                    <textarea
                                        v-model="reopenForm.reason"
                                        rows="3"
                                        class="w-full rounded-md border px-3 py-2 text-sm"
                                        placeholder="Jelaskan alasan Re-Open..."
                                    ></textarea>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border px-4 py-2 text-sm"
                                        @click="showReopenForm = false"
                                    >
                                        Batal
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-md bg-orange-600 px-4 py-2 text-sm text-white disabled:opacity-50"
                                        :disabled="reopenForm.processing"
                                        @click="submitReopen"
                                    >
                                        Re-Open
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

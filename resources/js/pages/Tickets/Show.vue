<script setup lang="ts">
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    confirmAction,
    showSuccessToast,
    showErrorToast,
} from '@/lib/sla-notifications';

/* ---------- Interfaces ---------- */

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

interface TicketAssignment {
    id: number;
    user_id: number;
    assigned_at: string;
    ended_at: string | null;
    handover_note: string | null;
    user: User;
}

interface TicketParticipant {
    id: number;
    user_id: number;
    joined_at: string;
    left_at: string | null;
    notes: string | null;
    user: User;
}

interface Ticket {
    active_participants: TicketParticipant[];
    active_assignment: TicketAssignment | null;
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
    assignments: TicketAssignment[];
    participants: TicketParticipant[];
}

interface AvailableSite {
    id: number;
    pelanggan_id: number;
    nama_site: string | null;
    no_jaringan: string | null;
}

/* ---------- Props ---------- */

const props = defineProps<{
    ticket: Ticket;
    categories: Category[];
    availableSites: AvailableSite[];
    ticketingStaff: User[];
    canHandover: boolean;
    availableParticipants: User[];
    canManageParticipants: boolean;
    canRemoveParticipants: boolean;
}>();

/* ---------- UI helpers (tab & gaya berulang) ---------- */

const activeTab = ref<'overview' | 'activity' | 'sla'>('overview');

const tabs = computed(() => [
    {
        key: 'overview' as const,
        label: 'Ringkasan',
        count: props.ticket.customers.length,
    },
    {
        key: 'activity' as const,
        label: 'Aktivitas',
        count: props.ticket.updates.length,
    },
    {
        key: 'sla' as const,
        label: 'Stop Clock & RFO',
        count: props.ticket.rfos.length,
    },
]);

const ui = {
    card: 'rounded-xl border bg-card p-5 shadow-sm',
    title: 'text-base font-semibold',
    label: 'mb-1.5 block text-sm font-medium',
    input: 'w-full rounded-lg border bg-background px-3 py-2.5 text-sm transition focus:ring-2 focus:ring-ring focus:outline-none',
    primary:
        'rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50',
    ghost: 'rounded-lg border bg-background px-4 py-2 text-sm transition hover:bg-muted disabled:opacity-50',
    muted: 'text-xs text-muted-foreground',
    error: 'mt-1 text-sm text-destructive',
    panel: 'rounded-lg border bg-muted/30 p-4',
};

/* ---------- Grouped customers ---------- */

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

/* ---------- Handover ---------- */

const showHandoverForm = ref(false);

const handoverForm = useForm<{
    user_id: number | null;
    handover_note: string;
}>({
    user_id: null,
    handover_note: '',
});

const handoverHistory = computed(() => {
    return [...(props.ticket.assignments || [])].sort(
        (a, b) =>
            new Date(b.assigned_at).getTime() -
            new Date(a.assigned_at).getTime(),
    );
});

const submitHandover = async () => {
    if (!handoverForm.user_id) {
        handoverForm.setError('user_id', 'Pilih pemilik tiket baru.');
        return;
    }

    const selectedStaff = props.ticketingStaff.find(
        (staff) => staff.id === handoverForm.user_id,
    );

    const confirmed = await confirmAction({
        title: 'Serahkan Tiket?',
        text: `Tiket akan diserahkan kepada ${selectedStaff?.name || 'staf yang dipilih'}.`,
        icon: 'question',
        confirmButtonText: 'Ya, Serahkan',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    handoverForm.post(`/tickets/${props.ticket.id}/handover`, {
        preserveScroll: true,

        onSuccess: () => {
            handoverForm.reset();
            showHandoverForm.value = false;

            showSuccessToast('Tiket berhasil diserahkan kepada pemilik baru.');
        },

        onError: (errors) => {
            console.error('HANDOVER ERROR:', errors);

            showErrorToast(
                'Tiket gagal diserahkan. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

/* ---------- Participants ---------- */

const showParticipantForm = ref(false);

const participantForm = useForm<{
    user_id: number | null;
    notes: string;
}>({
    user_id: null,
    notes: '',
});

const submitParticipant = async () => {
    if (!participantForm.user_id) {
        participantForm.setError(
            'user_id',
            'Pilih staf yang akan ditambahkan.',
        );
        return;
    }

    const selectedStaff = props.availableParticipants.find(
        (staff) => staff.id === participantForm.user_id,
    );

    const confirmed = await confirmAction({
        title: 'Tambah Peserta?',
        text: `${selectedStaff?.name || 'Staf yang dipilih'} akan ditambahkan sebagai peserta penanganan.`,
        icon: 'question',
        confirmButtonText: 'Ya, Tambah',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) return;

    participantForm.post(`/tickets/${props.ticket.id}/participants`, {
        preserveScroll: true,

        onSuccess: () => {
            participantForm.reset();
            showParticipantForm.value = false;

            showSuccessToast('Peserta penanganan berhasil ditambahkan.');
        },

        onError: (errors) => {
            console.error('ADD PARTICIPANT ERROR:', errors);

            showErrorToast(
                'Peserta gagal ditambahkan. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

const removeParticipant = async (participantId: number) => {
    const confirmed = await confirmAction({
        title: 'Hapus Participant?',
        text: 'Participant ini tidak akan mendapatkan kredit KPI jika tiket di-Close.',
        icon: 'warning',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) return;

    router.delete(`/tickets/${props.ticket.id}/participants/${participantId}`, {
        preserveScroll: true,

        onSuccess: () => {
            showSuccessToast('Participant berhasil dihapus.');
        },

        onError: (errors) => {
            console.error('REMOVE PARTICIPANT ERROR:', errors);
            showErrorToast('Participant gagal dihapus.');
        },
    });
};

/* ---------- Sites ---------- */

const showAddSiteForm = ref(false);

const addSiteForm = useForm({
    source: 'online_billing' as 'online_billing' | 'manual',
    online_billing_id: null as number | null,
    site_name: '',
    no_jaringan: '',
    reported_via: '',
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
    addSiteForm.site_name = '';
    addSiteForm.no_jaringan = '';
    siteSearch.value = `${site.nama_site || '-'} - ${site.no_jaringan || '-'}`;
    showSiteResults.value = false;
};

const openAddSiteForm = () => {
    addSiteForm.reset();
    addSiteForm.source = 'online_billing';
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

const submitAddSite = async () => {
    if (
        addSiteForm.source === 'online_billing' &&
        !addSiteForm.online_billing_id
    ) {
        return;
    }

    if (addSiteForm.source === 'manual' && !addSiteForm.site_name.trim()) {
        return;
    }

    const siteName =
        addSiteForm.source === 'online_billing'
            ? siteSearch.value || '-'
            : addSiteForm.site_name;

    const confirmed = await confirmAction({
        title: 'Tambah Site?',
        text: `Site "${siteName}" akan ditambahkan ke ticket GAMAS.`,
        icon: 'question',
        confirmButtonText: 'Ya, Tambah',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    addSiteForm.post(`/tickets/${props.ticket.id}/sites`, {
        preserveScroll: true,

        onSuccess: () => {
            addSiteForm.reset();
            addSiteForm.source = 'online_billing';

            showAddSiteForm.value = false;
            siteSearch.value = '';
            showSiteResults.value = false;

            showSuccessToast('Site berhasil ditambahkan ke ticket.');
        },

        onError: (errors) => {
            console.error('ADD SITE ERROR:', errors);

            showErrorToast(
                'Site gagal ditambahkan. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

const removeSite = async (customer: Customer) => {
    const confirmed = await confirmAction({
        title: 'Lepas Site?',
        text: `Site "${customer.site_name || '-'}" akan dilepas dari ticket ini.`,
        icon: 'warning',
        confirmButtonText: 'Ya, Lepas',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/tickets/${props.ticket.id}/sites/${customer.id}`, {
        preserveScroll: true,

        onSuccess: () => {
            showSuccessToast('Site berhasil dilepas dari ticket.');
        },

        onError: (errors) => {
            console.error('REMOVE SITE ERROR:', errors);

            showErrorToast('Site gagal dilepas dari ticket.');
        },
    });
};

/* ---------- Formatters ---------- */

const formatDate = (date: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        open: 'Open',
        on_progress: 'On Progress',
        resolved: 'Resolved',
        closed: 'Closed',
    };

    return labels[status] ?? status;
};

const priorityLabel = (priority: string) => {
    const labels: Record<string, string> = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        critical: 'Critical',
    };

    return labels[priority] ?? priority;
};

const priorityClass = (priority: string) => {
    const classes: Record<string, string> = {
        low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        medium: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',
        high: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
        critical: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    };

    return classes[priority] ?? 'bg-muted text-muted-foreground';
};

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

/* ---------- Update (progress) ---------- */

const updateForm = useForm<{
    message: string;
    attachments: File[];
}>({
    message: '',
    attachments: [],
});

const handleFiles = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (!target.files) {
        return;
    }

    updateForm.attachments = Array.from(target.files);
};

const submitUpdate = async () => {
    const confirmed = await confirmAction({
        title: 'Kirim Update Ticket?',
        text: 'Update dan lampiran yang dimasukkan akan disimpan pada ticket ini.',
        icon: 'question',
        confirmButtonText: 'Ya, Kirim',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    updateForm.post(`/tickets/${props.ticket.id}/updates`, {
        forceFormData: true,
        preserveScroll: true,

        onSuccess: () => {
            updateForm.reset();

            showSuccessToast('Update ticket berhasil dikirim.');
        },

        onError: (errors) => {
            console.error('SUBMIT UPDATE ERROR:', errors);

            showErrorToast(
                'Update ticket gagal dikirim. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

/* ---------- RFO ---------- */

const rfoForm = useForm({
    content: '',
});
const showRfoForm = ref(false);

const submitRfo = async () => {
    const confirmed = await confirmAction({
        title: 'Buat RFO?',
        text: 'RFO akan disimpan pada ticket ini.',
        icon: 'question',
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    rfoForm.post(`/tickets/${props.ticket.id}/rfo`, {
        preserveScroll: true,

        onSuccess: () => {
            rfoForm.reset();
            showRfoForm.value = false;

            showSuccessToast('RFO berhasil dibuat.');
        },

        onError: (errors) => {
            console.error('CREATE RFO ERROR:', errors);

            showErrorToast(
                'RFO gagal dibuat. Periksa kembali data yang dimasukkan.',
            );
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

const submitEditRfo = async (rfo: Rfo) => {
    const confirmed = await confirmAction({
        title: 'Simpan Perubahan RFO?',
        text: 'Perubahan pada RFO akan disimpan.',
        icon: 'question',
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    editRfoForm.put(`/tickets/${props.ticket.id}/rfo/${rfo.id}`, {
        preserveScroll: true,

        onSuccess: () => {
            editingRfoId.value = null;
            editRfoForm.reset();

            showSuccessToast('RFO berhasil diperbarui.');
        },

        onError: (errors) => {
            console.error('UPDATE RFO ERROR:', errors);

            showErrorToast(
                'RFO gagal diperbarui. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

/* ---------- Stop Clock ---------- */

const stopClockForm = useForm<{
    reason: string;
}>({
    reason: '',
});
const showStopClockForm = ref(false);

const hasActiveStopClock = () => {
    return props.ticket.stop_clocks.some(
        (stopClock) => stopClock.ended_at === null,
    );
};

const cancelStopClockForm = () => {
    stopClockForm.reset();
};

const submitStopClock = async () => {
    if (!stopClockForm.reason.trim()) {
        stopClockForm.setError('reason', 'Alasan Stop Clock wajib diisi.');
        return;
    }

    stopClockForm.clearErrors();

    const confirmed = await confirmAction({
        title: 'Mulai Stop Clock?',
        text: 'SLA ticket akan dihentikan sementara.',
        icon: 'warning',
        confirmButtonText: 'Ya, Mulai',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    stopClockForm.post(`/tickets/${props.ticket.id}/stop-clock`, {
        preserveScroll: true,

        onSuccess: () => {
            stopClockForm.reset();

            showSuccessToast('Stop Clock berhasil dimulai.');
        },

        onError: (errors) => {
            console.error('START STOP CLOCK ERROR:', errors);

            showErrorToast(
                'Stop Clock gagal dimulai. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

const resumeStopClock = async () => {
    const confirmed = await confirmAction({
        title: 'Lanjutkan SLA?',
        text: 'Stop Clock akan dihentikan dan penghitungan SLA akan dilanjutkan.',
        icon: 'question',
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    stopClockForm.post(`/tickets/${props.ticket.id}/stop-clock/resume`, {
        preserveScroll: true,

        onSuccess: () => {
            stopClockForm.reset();

            showSuccessToast(
                'Stop Clock berhasil dihentikan. Penghitungan SLA dilanjutkan.',
            );
        },

        onError: (errors) => {
            console.error('RESUME STOP CLOCK ERROR:', errors);

            showErrorToast('Gagal melanjutkan SLA ticket.');
        },
    });
};

/* ---------- Re-Open ---------- */

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

const submitReopen = async () => {
    const confirmed = await confirmAction({
        title: 'Re-Open Ticket?',
        text: 'Ticket akan dibuka kembali dan dapat diproses kembali.',
        icon: 'warning',
        confirmButtonText: 'Ya, Re-Open',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    reopenForm.post(`/tickets/${props.ticket.id}/reopen`, {
        preserveScroll: true,

        onSuccess: () => {
            reopenForm.reset();
            showReopenForm.value = false;

            showSuccessToast('Ticket berhasil di-Re-Open.');
        },

        onError: (errors) => {
            console.error('RE-OPEN TICKET ERROR:', errors);

            showErrorToast(
                'Ticket gagal di-Re-Open. Periksa kembali data yang dimasukkan.',
            );
        },
    });
};

/* ---------- Resolve & Close ---------- */

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

const submitResolve = async () => {
    const confirmed = await confirmAction({
        title: 'Resolve Ticket?',
        text: 'Ticket akan ditandai sebagai selesai dan tidak dapat diproses kembali sampai di-Re-Open.',
        icon: 'warning',
        confirmButtonText: 'Ya, Resolve',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    resolveForm.post(`/tickets/${props.ticket.id}/resolve`, {
        preserveScroll: true,

        onSuccess: () => {
            resolveForm.reset();
            showResolveForm.value = false;

            showSuccessToast('Ticket berhasil di-Resolve.');
        },

        onError: (errors) => {
            console.error('RESOLVE TICKET ERROR:', errors);

            showErrorToast('Ticket gagal di-Resolve.');
        },
    });
};

const closeForm = useForm({});

const closeTicket = async () => {
    const confirmed = await confirmAction({
        title: 'Close Ticket?',
        text: 'Ticket akan ditutup dan statusnya menjadi Closed.',
        icon: 'warning',
        confirmButtonText: 'Ya, Close',
        cancelButtonText: 'Batal',
    });

    if (!confirmed) {
        return;
    }

    closeForm.post(`/tickets/${props.ticket.id}/close`, {
        preserveScroll: true,

        onSuccess: () => {
            showSuccessToast('Ticket berhasil ditutup.');
        },

        onError: (errors) => {
            console.error('CLOSE TICKET ERROR:', errors);

            showErrorToast('Ticket gagal ditutup.');
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-background px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- ================= HEADER ================= -->
            <header class="space-y-4">
                <Link
                    href="/tickets"
                    class="inline-block text-sm text-muted-foreground hover:text-foreground"
                >
                    ← Kembali ke daftar tiket
                </Link>

                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="min-w-0">
                        <h1 class="text-3xl font-bold tracking-tight">
                            {{ ticket.ticket_number }}
                        </h1>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-md bg-muted px-2.5 py-1 text-xs font-medium"
                            >
                                {{
                                    ticket.ticket_type === 'gamas'
                                        ? 'GAMAS'
                                        : 'Individual'
                                }}
                            </span>
                            <span
                                class="rounded-md px-2.5 py-1 text-xs font-medium"
                                :class="priorityClass(ticket.priority)"
                            >
                                Prioritas {{ priorityLabel(ticket.priority) }}
                            </span>
                            <span
                                class="rounded-md px-2.5 py-1 text-xs font-medium"
                                :class="statusClass(ticket.status)"
                            >
                                {{ statusLabel(ticket.status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Aksi utama: hanya satu langkah berikutnya yang tampil -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="ticket.status === 'on_progress'"
                            type="button"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700"
                            @click="openResolveForm"
                        >
                            Resolve tiket
                        </button>
                        <button
                            v-if="ticket.status === 'resolved'"
                            type="button"
                            :class="ui.primary"
                            @click="closeTicket"
                        >
                            Close tiket
                        </button>
                        <button
                            v-if="ticket.status === 'closed'"
                            type="button"
                            class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-orange-700"
                            @click="openReopenForm"
                        >
                            Re-Open tiket
                        </button>
                    </div>
                </div>

                <!-- Banner SLA -->
                <div
                    v-if="hasActiveStopClock()"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-900 dark:bg-yellow-950 dark:text-yellow-200"
                >
                    <span>
                        Stop Clock aktif. Penghitungan SLA sedang dihentikan.
                    </span>
                    <button
                        type="button"
                        :class="ui.primary"
                        :disabled="stopClockForm.processing"
                        @click="resumeStopClock"
                    >
                        {{
                            stopClockForm.processing
                                ? 'Memproses...'
                                : 'Lanjutkan SLA'
                        }}
                    </button>
                </div>

                <!-- Form Resolve -->
                <div v-if="showResolveForm" :class="ui.card">
                    <h2 :class="ui.title" class="mb-3">Resolve tiket</h2>
                    <p class="mb-3 text-sm text-muted-foreground">
                        Tiket tidak bisa diproses lagi sampai di-Re-Open.
                    </p>
                    <label :class="ui.label">Hasil penyelesaian</label>
                    <select v-model="resolveForm.resolution" :class="ui.input">
                        <option value="">Pilih hasil penyelesaian</option>
                        <option value="provider_issue">Provider Issue</option>
                        <option value="no_issue">No Issue</option>
                    </select>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            :class="ui.ghost"
                            @click="cancelResolveForm"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700 disabled:opacity-50"
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

                <!-- Form Re-Open -->
                <div v-if="showReopenForm" :class="ui.card">
                    <h2 :class="ui.title" class="mb-3">Re-Open tiket</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label :class="ui.label">Kendala baru</label>
                            <select
                                v-model="reopenForm.kendala_id"
                                :class="ui.input"
                            >
                                <option :value="null">Pilih kendala</option>
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
                            <label :class="ui.label">Waktu gangguan</label>
                            <input
                                v-model="reopenForm.reported_at"
                                type="datetime-local"
                                :class="ui.input"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label :class="ui.label">Alasan</label>
                            <textarea
                                v-model="reopenForm.reason"
                                rows="3"
                                :class="ui.input"
                                placeholder="Jelaskan alasan Re-Open..."
                            ></textarea>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            :class="ui.ghost"
                            @click="showReopenForm = false"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-orange-700 disabled:opacity-50"
                            :disabled="reopenForm.processing"
                            @click="submitReopen"
                        >
                            Re-Open
                        </button>
                    </div>
                </div>

                <!-- Ringkasan cepat -->
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div :class="ui.card" class="!p-4">
                        <div :class="ui.muted">Pemilik tiket</div>
                        <div class="mt-1 truncate font-medium">
                            {{ ticket.active_assignment?.user?.name || '-' }}
                        </div>
                    </div>
                    <div :class="ui.card" class="!p-4">
                        <div :class="ui.muted">Respon pertama</div>
                        <div class="mt-1 text-sm font-medium">
                            {{ formatDate(ticket.first_response_at) }}
                        </div>
                    </div>
                    <div :class="ui.card" class="!p-4">
                        <div :class="ui.muted">Waktu down</div>
                        <div class="mt-1 text-sm font-medium">
                            {{ formatDowntime(ticket.downtime_minutes) }}
                        </div>
                    </div>
                    <div :class="ui.card" class="!p-4">
                        <div :class="ui.muted">Ditutup</div>
                        <div class="mt-1 text-sm font-medium">
                            {{ formatDate(ticket.closed_at) }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- ================= TABS ================= -->
            <div class="border-b">
                <nav class="flex gap-1 overflow-x-auto overflow-y-hidden">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                        :class="
                            activeTab === tab.key
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs">
                            {{ tab.count }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <!-- ================= KOLOM UTAMA ================= -->
                <main class="space-y-6 xl:col-span-2">
                    <!-- ---------- TAB: RINGKASAN ---------- -->
                    <template v-if="activeTab === 'overview'">
                        <section :class="ui.card">
                            <h2 :class="ui.title" class="mb-3">
                                Deskripsi gangguan
                            </h2>
                            <p
                                class="text-sm leading-relaxed whitespace-pre-wrap"
                            >
                                {{ ticket.description || '-' }}
                            </p>
                        </section>

                        <section :class="ui.card">
                            <div
                                class="mb-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h2 :class="ui.title">
                                    Customer / site terdampak
                                    <span
                                        class="ml-1 font-normal text-muted-foreground"
                                    >
                                        ({{ ticket.customers.length }})
                                    </span>
                                </h2>
                                <button
                                    v-if="
                                        ticket.ticket_type === 'gamas' &&
                                        !showAddSiteForm
                                    "
                                    type="button"
                                    :class="ui.ghost"
                                    class="!px-3 !py-1.5 !text-xs"
                                    @click="openAddSiteForm"
                                >
                                    + Tambah site
                                </button>
                            </div>

                            <!-- Form tambah site -->
                            <div
                                v-if="showAddSiteForm"
                                :class="ui.panel"
                                class="mb-4 space-y-4"
                            >
                                <div>
                                    <label :class="ui.label">Sumber site</label>
                                    <select
                                        v-model="addSiteForm.source"
                                        :class="ui.input"
                                    >
                                        <option value="online_billing">
                                            Sudah ada di Online Billing
                                        </option>
                                        <option value="manual">
                                            Belum ada di Online Billing
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="
                                        addSiteForm.source === 'online_billing'
                                    "
                                >
                                    <label :class="ui.label">Cari site</label>
                                    <div class="relative">
                                        <input
                                            v-model="siteSearch"
                                            type="text"
                                            placeholder="Nama site atau no jaringan..."
                                            :class="ui.input"
                                            @focus="showSiteResults = true"
                                        />
                                        <div
                                            v-if="
                                                showSiteResults &&
                                                filteredSites.length
                                            "
                                            class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border bg-background shadow-lg"
                                        >
                                            <button
                                                v-for="site in filteredSites"
                                                :key="site.id"
                                                type="button"
                                                class="block w-full border-b px-3 py-2.5 text-left last:border-b-0 hover:bg-muted"
                                                @click="selectSite(site)"
                                            >
                                                <div
                                                    class="text-sm font-medium"
                                                >
                                                    {{ site.nama_site || '-' }}
                                                </div>
                                                <div :class="ui.muted">
                                                    No Jaringan:
                                                    {{
                                                        site.no_jaringan || '-'
                                                    }}
                                                </div>
                                            </button>
                                        </div>
                                        <div
                                            v-if="
                                                showSiteResults &&
                                                siteSearch &&
                                                filteredSites.length === 0
                                            "
                                            class="absolute z-50 mt-1 w-full rounded-lg border bg-background p-3 text-sm text-muted-foreground shadow-lg"
                                        >
                                            Site tidak ditemukan.
                                        </div>
                                    </div>
                                    <p
                                        v-if="addSiteForm.online_billing_id"
                                        class="mt-2 text-sm"
                                    >
                                        Dipilih:
                                        <span class="font-medium">{{
                                            siteSearch
                                        }}</span>
                                    </p>
                                </div>

                                <div v-else class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label :class="ui.label"
                                            >Nama site</label
                                        >
                                        <input
                                            v-model="addSiteForm.site_name"
                                            type="text"
                                            placeholder="Masukkan nama site"
                                            :class="ui.input"
                                        />
                                    </div>
                                    <div>
                                        <label :class="ui.label"
                                            >No jaringan</label
                                        >
                                        <input
                                            v-model="addSiteForm.no_jaringan"
                                            type="text"
                                            placeholder="Jika sudah ada"
                                            :class="ui.input"
                                        />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label :class="ui.label"
                                            >Dilaporkan melalui</label
                                        >
                                        <input
                                            v-model="addSiteForm.reported_via"
                                            type="text"
                                            placeholder="Contoh: WhatsApp, Telepon, Email"
                                            :class="ui.input"
                                        />
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        :class="ui.ghost"
                                        @click="cancelAddSiteForm"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="button"
                                        :class="ui.primary"
                                        :disabled="
                                            addSiteForm.processing ||
                                            (addSiteForm.source ===
                                                'online_billing' &&
                                                !addSiteForm.online_billing_id) ||
                                            (addSiteForm.source === 'manual' &&
                                                !addSiteForm.site_name)
                                        "
                                        @click="submitAddSite"
                                    >
                                        {{
                                            addSiteForm.processing
                                                ? 'Menyimpan...'
                                                : 'Tambah site'
                                        }}
                                    </button>
                                </div>
                            </div>

                            <!-- Daftar site -->
                            <div
                                v-if="ticket.customers.length"
                                class="space-y-5"
                            >
                                <div
                                    v-for="group in groupedCustomers"
                                    :key="group.customerName"
                                >
                                    <div class="mb-2 text-sm font-semibold">
                                        {{ group.customerName }}
                                    </div>
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div
                                            v-for="customer in group.sites"
                                            :key="customer.id"
                                            class="relative"
                                            :class="ui.panel"
                                        >
                                            <button
                                                v-if="
                                                    ticket.ticket_type ===
                                                    'gamas'
                                                "
                                                type="button"
                                                class="absolute top-2 right-2 rounded-md px-2 py-0.5 text-lg leading-none text-muted-foreground hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                                title="Lepas site"
                                                @click="removeSite(customer)"
                                            >
                                                ×
                                            </button>
                                            <div
                                                class="pr-6 text-sm font-medium"
                                            >
                                                {{ customer.site_name || '-' }}
                                            </div>
                                            <div class="mt-1" :class="ui.muted">
                                                No Jaringan:
                                                {{
                                                    customer.no_jaringan || '-'
                                                }}
                                            </div>
                                            <div :class="ui.muted">
                                                Dilaporkan via:
                                                {{
                                                    customer.reported_via || '-'
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                Belum ada customer atau site.
                            </p>
                        </section>

                        <section :class="ui.card">
                            <h2 :class="ui.title" class="mb-4">
                                Case / insiden
                                <span
                                    class="ml-1 font-normal text-muted-foreground"
                                >
                                    ({{ ticket.incidents.length }})
                                </span>
                            </h2>
                            <div
                                v-if="ticket.incidents.length"
                                class="space-y-3"
                            >
                                <div
                                    v-for="incident in ticket.incidents"
                                    :key="incident.id"
                                    :class="ui.panel"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between gap-2"
                                    >
                                        <div class="font-medium">
                                            Case #{{ incident.incident_number }}
                                        </div>
                                        <span
                                            class="rounded-md bg-muted px-2 py-1 text-xs"
                                        >
                                            {{ incident.category.name }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <div :class="ui.muted">
                                                Dilaporkan
                                            </div>
                                            {{
                                                formatDate(incident.reported_at)
                                            }}
                                        </div>
                                        <div>
                                            <div :class="ui.muted">
                                                Diselesaikan
                                            </div>
                                            {{
                                                formatDate(incident.resolved_at)
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="py-4 text-center text-sm text-muted-foreground"
                            >
                                Belum ada insiden.
                            </p>
                        </section>
                    </template>

                    <!-- ---------- TAB: AKTIVITAS ---------- -->
                    <template v-if="activeTab === 'activity'">
                        <section :class="ui.card">
                            <h2 :class="ui.title" class="mb-4">
                                Tambah progress
                            </h2>
                            <form
                                class="space-y-4"
                                @submit.prevent="submitUpdate"
                            >
                                <div>
                                    <label :class="ui.label"
                                        >Progress / catatan</label
                                    >
                                    <textarea
                                        v-model="updateForm.message"
                                        rows="4"
                                        :class="ui.input"
                                        placeholder="Contoh: Dilakukan pengecekan CPE, ditemukan LOS..."
                                    ></textarea>
                                    <p
                                        v-if="updateForm.errors.message"
                                        :class="ui.error"
                                    >
                                        {{ updateForm.errors.message }}
                                    </p>
                                </div>

                                <div>
                                    <label :class="ui.label"
                                        >Foto / lampiran</label
                                    >
                                    <input
                                        type="file"
                                        multiple
                                        accept="image/jpeg,image/png,application/pdf"
                                        :class="ui.input"
                                        @change="handleFiles"
                                    />
                                    <p class="mt-1" :class="ui.muted">
                                        Bisa pilih beberapa file. Maksimal 5 MB
                                        per file.
                                    </p>
                                    <p
                                        v-if="updateForm.errors.attachments"
                                        :class="ui.error"
                                    >
                                        {{ updateForm.errors.attachments }}
                                    </p>
                                </div>

                                <div
                                    v-if="updateForm.attachments.length"
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="file in updateForm.attachments"
                                        :key="file.name + file.size"
                                        class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                    >
                                        <span class="truncate">{{
                                            file.name
                                        }}</span>
                                        <span
                                            class="ml-3 shrink-0"
                                            :class="ui.muted"
                                        >
                                            {{
                                                (
                                                    file.size /
                                                    1024 /
                                                    1024
                                                ).toFixed(2)
                                            }}
                                            MB
                                        </span>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :class="ui.primary"
                                        :disabled="
                                            updateForm.processing ||
                                            !updateForm.message.trim()
                                        "
                                    >
                                        {{
                                            updateForm.processing
                                                ? 'Menyimpan...'
                                                : 'Kirim progress'
                                        }}
                                    </button>
                                </div>
                            </form>
                        </section>

                        <section :class="ui.card">
                            <h2 :class="ui.title" class="mb-5">
                                Riwayat aktivitas
                            </h2>
                            <div v-if="ticket.updates.length" class="space-y-6">
                                <div
                                    v-for="update in ticket.updates"
                                    :key="update.id"
                                    class="relative border-l pl-5"
                                >
                                    <div
                                        class="absolute top-1.5 -left-1.5 h-3 w-3 rounded-full bg-primary"
                                    ></div>
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <div class="text-sm font-medium">
                                            {{ update.user.name }}
                                        </div>
                                        <div :class="ui.muted">
                                            {{ formatDate(update.created_at) }}
                                        </div>
                                    </div>
                                    <p
                                        class="mt-2 text-sm leading-relaxed whitespace-pre-wrap"
                                    >
                                        {{ update.message }}
                                    </p>
                                    <div
                                        v-if="update.attachments.length"
                                        class="mt-3 space-y-2"
                                    >
                                        <a
                                            v-for="attachment in update.attachments"
                                            :key="attachment.id"
                                            :href="`/storage/${attachment.file_path}`"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                        >
                                            <span class="truncate">{{
                                                attachment.file_name
                                            }}</span>
                                            <span
                                                v-if="attachment.file_size"
                                                class="ml-3 shrink-0"
                                                :class="ui.muted"
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
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                Belum ada aktivitas. Tambahkan progress pertama
                                di atas.
                            </p>
                        </section>
                    </template>

                    <!-- ---------- TAB: STOP CLOCK & RFO ---------- -->
                    <template v-if="activeTab === 'sla'">
                        <section :class="ui.card">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div>
                                    <h2 :class="ui.title">Stop Clock</h2>
                                    <p
                                        class="mt-1 text-xs"
                                        :class="
                                            hasActiveStopClock()
                                                ? 'text-yellow-600 dark:text-yellow-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            hasActiveStopClock()
                                                ? 'SLA sedang dihentikan'
                                                : 'SLA berjalan'
                                        }}
                                    </p>
                                </div>

                                <button
                                    v-if="
                                        !hasActiveStopClock() &&
                                        ticket.status === 'on_progress'
                                    "
                                    type="button"
                                    :class="ui.ghost"
                                    @click="
                                        showStopClockForm = !showStopClockForm;
                                        if (showStopClockForm)
                                            stopClockForm.reason = '';
                                    "
                                >
                                    {{
                                        showStopClockForm
                                            ? 'Tutup form'
                                            : 'Mulai Stop Clock'
                                    }}
                                </button>
                                <button
                                    v-else-if="hasActiveStopClock()"
                                    type="button"
                                    :class="ui.primary"
                                    :disabled="stopClockForm.processing"
                                    @click="resumeStopClock"
                                >
                                    {{
                                        stopClockForm.processing
                                            ? 'Memproses...'
                                            : 'Lanjutkan SLA'
                                    }}
                                </button>
                            </div>

                            <form
                                v-if="
                                    showStopClockForm &&
                                    ticket.status === 'on_progress' &&
                                    !hasActiveStopClock()
                                "
                                class="mt-4 space-y-3"
                                :class="ui.panel"
                                @submit.prevent="submitStopClock"
                            >
                                <label :class="ui.label"
                                    >Alasan Stop Clock</label
                                >
                                <textarea
                                    v-model="stopClockForm.reason"
                                    rows="3"
                                    :class="ui.input"
                                    placeholder="Contoh: Menunggu customer mengecek power CPE..."
                                ></textarea>
                                <p
                                    v-if="stopClockForm.errors.reason"
                                    :class="ui.error"
                                >
                                    {{ stopClockForm.errors.reason }}
                                </p>
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        :class="ui.ghost"
                                        :disabled="stopClockForm.processing"
                                        @click="cancelStopClockForm"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        class="rounded-lg bg-yellow-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-yellow-700 disabled:opacity-50"
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

                            <div
                                v-if="ticket.stop_clocks.length"
                                class="mt-5 space-y-2"
                            >
                                <div :class="ui.muted">Riwayat</div>
                                <div
                                    v-for="stopClock in ticket.stop_clocks"
                                    :key="stopClock.id"
                                    class="rounded-lg border p-3 text-sm"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span :class="ui.muted">
                                            {{
                                                formatDate(stopClock.started_at)
                                            }}
                                            –
                                            {{
                                                stopClock.ended_at
                                                    ? formatDate(
                                                          stopClock.ended_at,
                                                      )
                                                    : 'sekarang'
                                            }}
                                        </span>
                                        <span
                                            v-if="stopClock.ended_at === null"
                                            class="rounded-md bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300"
                                        >
                                            Aktif
                                        </span>
                                    </div>
                                    <p class="mt-1.5">{{ stopClock.reason }}</p>
                                </div>
                            </div>
                            <p
                                v-else
                                class="mt-4 text-sm text-muted-foreground"
                            >
                                Belum ada riwayat Stop Clock.
                            </p>
                        </section>

                        <section :class="ui.card">
                            <div
                                class="mb-4 flex flex-wrap items-start justify-between gap-2"
                            >
                                <div>
                                    <h2 :class="ui.title">RFO</h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Root cause dan penyelesaian tiket.
                                    </p>
                                </div>
                                <button
                                    v-if="
                                        ticket.status === 'resolved' ||
                                        ticket.status === 'closed'
                                    "
                                    type="button"
                                    :class="ui.ghost"
                                    @click="showRfoForm = !showRfoForm"
                                >
                                    {{
                                        showRfoForm
                                            ? 'Tutup form'
                                            : '+ Buat RFO'
                                    }}
                                </button>
                            </div>

                            <form
                                v-if="showRfoForm"
                                class="mb-5 space-y-3"
                                :class="ui.panel"
                                @submit.prevent="submitRfo"
                            >
                                <label :class="ui.label">Isi RFO</label>
                                <textarea
                                    v-model="rfoForm.content"
                                    rows="8"
                                    :class="ui.input"
                                    placeholder="Tuliskan root cause, tindakan penyelesaian, dan preventive action..."
                                ></textarea>
                                <p
                                    v-if="rfoForm.errors.content"
                                    :class="ui.error"
                                >
                                    {{ rfoForm.errors.content }}
                                </p>
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :class="ui.primary"
                                        :disabled="rfoForm.processing"
                                    >
                                        {{
                                            rfoForm.processing
                                                ? 'Menyimpan...'
                                                : 'Simpan RFO'
                                        }}
                                    </button>
                                </div>
                            </form>

                            <div v-if="ticket.rfos.length" class="space-y-4">
                                <div
                                    v-for="rfo in ticket.rfos"
                                    :key="rfo.id"
                                    :class="ui.panel"
                                >
                                    <div
                                        class="mb-2 flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <div class="font-medium">
                                            {{ rfo.rfo_number }}
                                        </div>
                                        <button
                                            v-if="
                                                (ticket.status === 'resolved' ||
                                                    ticket.status ===
                                                        'closed') &&
                                                editingRfoId !== rfo.id
                                            "
                                            type="button"
                                            class="rounded-md border px-2.5 py-1 text-xs hover:bg-muted"
                                            @click="startEditRfo(rfo)"
                                        >
                                            Edit
                                        </button>
                                    </div>
                                    <div class="mb-3" :class="ui.muted">
                                        Dibuat oleh {{ rfo.creator.name }} ·
                                        {{ formatDate(rfo.created_at) }}
                                    </div>

                                    <p
                                        v-if="editingRfoId !== rfo.id"
                                        class="text-sm leading-relaxed whitespace-pre-line"
                                    >
                                        {{ rfo.content }}
                                    </p>

                                    <div v-else class="space-y-3">
                                        <textarea
                                            v-model="editRfoForm.content"
                                            rows="8"
                                            :class="ui.input"
                                        ></textarea>
                                        <p
                                            v-if="editRfoForm.errors.content"
                                            :class="ui.error"
                                        >
                                            {{ editRfoForm.errors.content }}
                                        </p>
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                :class="ui.ghost"
                                                :disabled="
                                                    editRfoForm.processing
                                                "
                                                @click="cancelEditRfo"
                                            >
                                                Batal
                                            </button>
                                            <button
                                                type="button"
                                                :class="ui.primary"
                                                :disabled="
                                                    editRfoForm.processing ||
                                                    !editRfoForm.content.trim()
                                                "
                                                @click="submitEditRfo(rfo)"
                                            >
                                                {{
                                                    editRfoForm.processing
                                                        ? 'Menyimpan...'
                                                        : 'Simpan perubahan'
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="text-center text-sm text-muted-foreground"
                            >
                                Belum ada RFO.
                                <template
                                    v-if="
                                        ticket.status === 'on_progress' ||
                                        ticket.status === 'open'
                                    "
                                >
                                    RFO bisa dibuat setelah tiket di-resolve.
                                </template>
                            </p>
                        </section>
                    </template>
                </main>

                <!-- ================= SIDEBAR ================= -->
                <aside class="space-y-6">
                    <section :class="ui.card">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 :class="ui.title">Pemilik tiket</h2>
                            <button
                                v-if="canHandover"
                                type="button"
                                :class="ui.ghost"
                                class="!px-3 !py-1.5 !text-xs"
                                @click="showHandoverForm = !showHandoverForm"
                            >
                                {{ showHandoverForm ? 'Tutup' : 'Serahkan' }}
                            </button>
                        </div>

                        <div :class="ui.panel">
                            <div class="font-medium">
                                {{
                                    ticket.active_assignment?.user?.name || '-'
                                }}
                            </div>
                            <div
                                v-if="ticket.active_assignment"
                                class="mt-1"
                                :class="ui.muted"
                            >
                                Sejak
                                {{
                                    formatDate(
                                        ticket.active_assignment.assigned_at,
                                    )
                                }}
                            </div>
                            <div v-else class="mt-1 text-xs text-destructive">
                                Belum ada pemilik tiket aktif.
                            </div>
                        </div>

                        <form
                            v-if="showHandoverForm"
                            class="mt-4 space-y-4 rounded-lg border p-4"
                            @submit.prevent="submitHandover"
                        >
                            <div>
                                <label :class="ui.label">Pemilik baru</label>
                                <select
                                    v-model="handoverForm.user_id"
                                    :class="ui.input"
                                >
                                    <option :value="null">
                                        Pilih staf ticketing
                                    </option>
                                    <option
                                        v-for="staff in ticketingStaff"
                                        :key="staff.id"
                                        :value="staff.id"
                                    >
                                        {{ staff.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="handoverForm.errors.user_id"
                                    :class="ui.error"
                                >
                                    {{ handoverForm.errors.user_id }}
                                </p>
                                <p
                                    v-if="ticketingStaff.length === 0"
                                    class="mt-1"
                                    :class="ui.muted"
                                >
                                    Tidak ada staf ticketing aktif lainnya.
                                </p>
                            </div>
                            <div>
                                <label :class="ui.label"
                                    >Catatan serah terima</label
                                >
                                <textarea
                                    v-model="handoverForm.handover_note"
                                    rows="3"
                                    maxlength="2000"
                                    :class="ui.input"
                                    placeholder="Kondisi terakhir tiket dan pekerjaan yang perlu dilanjutkan..."
                                ></textarea>
                                <p
                                    v-if="handoverForm.errors.handover_note"
                                    :class="ui.error"
                                >
                                    {{ handoverForm.errors.handover_note }}
                                </p>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    :class="ui.ghost"
                                    :disabled="handoverForm.processing"
                                    @click="
                                        handoverForm.reset();
                                        handoverForm.clearErrors();
                                        showHandoverForm = false;
                                    "
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :class="ui.primary"
                                    :disabled="
                                        handoverForm.processing ||
                                        !handoverForm.user_id ||
                                        ticketingStaff.length === 0
                                    "
                                >
                                    {{
                                        handoverForm.processing
                                            ? 'Menyerahkan...'
                                            : 'Serahkan tiket'
                                    }}
                                </button>
                            </div>
                        </form>

                        <details class="group mt-4">
                            <summary
                                class="cursor-pointer text-sm font-medium text-muted-foreground hover:text-foreground"
                            >
                                Riwayat pemilik ({{ handoverHistory.length }})
                            </summary>
                            <div
                                v-if="handoverHistory.length"
                                class="mt-3 space-y-3"
                            >
                                <div
                                    v-for="assignment in handoverHistory"
                                    :key="assignment.id"
                                    class="border-l-2 border-primary pl-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2 text-sm font-medium"
                                    >
                                        {{ assignment.user?.name || '-' }}
                                        <span
                                            v-if="!assignment.ended_at"
                                            class="rounded-md bg-green-100 px-2 py-0.5 text-xs text-green-700 dark:bg-green-950 dark:text-green-300"
                                        >
                                            Saat ini
                                        </span>
                                    </div>
                                    <div class="mt-0.5" :class="ui.muted">
                                        {{ formatDate(assignment.assigned_at) }}
                                        <template v-if="assignment.ended_at">
                                            –
                                            {{
                                                formatDate(assignment.ended_at)
                                            }}
                                        </template>
                                    </div>
                                    <p
                                        v-if="assignment.handover_note"
                                        class="mt-2 rounded-md bg-muted/50 p-2.5 text-xs whitespace-pre-wrap"
                                    >
                                        {{ assignment.handover_note }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-else
                                class="mt-3 text-sm text-muted-foreground"
                            >
                                Belum ada riwayat.
                            </p>
                        </details>
                    </section>

                    <section :class="ui.card">
                        <div
                            class="mb-4 flex items-center justify-between gap-2"
                        >
                            <h2 :class="ui.title">Peserta penanganan</h2>
                            <button
                                v-if="canManageParticipants"
                                type="button"
                                :class="ui.ghost"
                                class="!px-3 !py-1.5 !text-xs"
                                @click="
                                    showParticipantForm = !showParticipantForm
                                "
                            >
                                {{ showParticipantForm ? 'Tutup' : '+ Tambah' }}
                            </button>
                        </div>

                        <form
                            v-if="showParticipantForm && canManageParticipants"
                            class="mb-4 space-y-3 rounded-lg border p-4"
                            @submit.prevent="submitParticipant"
                        >
                            <div>
                                <label :class="ui.label">Staf ticketing</label>
                                <select
                                    v-model="participantForm.user_id"
                                    :class="ui.input"
                                >
                                    <option :value="null">Pilih staf</option>
                                    <option
                                        v-for="staff in availableParticipants"
                                        :key="staff.id"
                                        :value="staff.id"
                                    >
                                        {{ staff.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="participantForm.errors.user_id"
                                    :class="ui.error"
                                >
                                    {{ participantForm.errors.user_id }}
                                </p>
                                <p
                                    v-if="availableParticipants.length === 0"
                                    class="mt-1"
                                    :class="ui.muted"
                                >
                                    Tidak ada staf ticketing aktif yang
                                    tersedia.
                                </p>
                            </div>
                            <div>
                                <label :class="ui.label"
                                    >Catatan (opsional)</label
                                >
                                <textarea
                                    v-model="participantForm.notes"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Contoh: Membantu troubleshooting jaringan."
                                    :class="ui.input"
                                ></textarea>
                                <p
                                    v-if="participantForm.errors.notes"
                                    :class="ui.error"
                                >
                                    {{ participantForm.errors.notes }}
                                </p>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    :class="ui.ghost"
                                    :disabled="participantForm.processing"
                                    @click="
                                        participantForm.reset();
                                        participantForm.clearErrors();
                                        showParticipantForm = false;
                                    "
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :class="ui.primary"
                                    :disabled="
                                        participantForm.processing ||
                                        !participantForm.user_id ||
                                        availableParticipants.length === 0
                                    "
                                >
                                    {{
                                        participantForm.processing
                                            ? 'Menyimpan...'
                                            : 'Tambah peserta'
                                    }}
                                </button>
                            </div>
                        </form>

                        <div
                            v-if="ticket.active_participants?.length"
                            class="space-y-2"
                        >
                            <div
                                v-for="participant in ticket.active_participants"
                                :key="participant.id"
                                :class="ui.panel"
                                class="!px-3 !py-2.5"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium">
                                            {{ participant.user?.name || '-' }}
                                        </div>

                                        <div :class="ui.muted">
                                            Bergabung
                                            {{
                                                formatDate(
                                                    participant.joined_at,
                                                )
                                            }}
                                        </div>
                                    </div>

                                    <button
                                        v-if="
                                            canRemoveParticipants &&
                                            participant.user_id !==
                                                ticket.active_assignment
                                                    ?.user_id
                                        "
                                        type="button"
                                        :class="ui.ghost"
                                        class="!px-3 !py-1.5 !text-xs text-red-600 hover:text-red-700"
                                        @click="
                                            removeParticipant(participant.id)
                                        "
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Belum ada peserta penanganan.
                        </p>
                    </section>

                    <section :class="ui.card">
                        <h2 :class="ui.title" class="mb-4">Informasi tiket</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">
                                    Dibuat oleh
                                </dt>
                                <dd class="text-right">
                                    {{ ticket.creator?.name || '-' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Dibuat</dt>
                                <dd class="text-right">
                                    {{ formatDate(ticket.created_at) }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</template>

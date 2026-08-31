<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

interface Category {
    id: number;
    name: string;
    is_downtime: boolean;
}

const props = defineProps<{
    categories: Category[];
}>();

const showForm = ref(false);
const editingCategory = ref<Category | null>(null);

const form = useForm({
    name: '',
    is_downtime: false,
});

const title = computed(() =>
    editingCategory.value ? 'Edit Kendala' : 'Tambah Kendala',
);

const submit = () => {
    if (editingCategory.value) {
        form.put(`/master/kendala/${editingCategory.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
            },
        });

        return;
    }

    form.post('/master/kendala', {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
};

const edit = (category: Category) => {
    editingCategory.value = category;

    form.name = category.name;
    form.is_downtime = category.is_downtime;

    showForm.value = true;
};

const resetForm = () => {
    form.reset();

    editingCategory.value = null;
    showForm.value = false;
};

const remove = (category: Category) => {
    if (!confirm(`Yakin ingin menghapus kendala "${category.name}"?`)) {
        return;
    }

    router.delete(`/master/kendala/${category.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Master Kendala</h1>

                <p class="text-sm text-muted-foreground">
                    Kelola jenis kendala yang digunakan pada ticket.
                </p>
            </div>

            <button
                type="button"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                @click="showForm = true"
            >
                Tambah Kendala
            </button>
        </div>

        <!-- Form -->
        <div v-if="showForm" class="mb-6 rounded-lg border p-5">
            <div class="mb-4">
                <h2 class="text-lg font-semibold">
                    {{ title }}
                </h2>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label for="name" class="mb-1 block text-sm font-medium">
                        Nama Kendala
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-md border px-3 py-2"
                        placeholder="Contoh: Down"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="mb-5 flex items-center gap-2">
                    <input
                        id="is_downtime"
                        v-model="form.is_downtime"
                        type="checkbox"
                    />

                    <label for="is_downtime" class="text-sm">
                        Termasuk downtime / SLA
                    </label>
                </div>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : editingCategory
                                  ? 'Update'
                                  : 'Simpan'
                        }}
                    </button>

                    <button
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm"
                        @click="resetForm"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-lg border">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-muted/50">
                        <th class="px-4 py-3 text-left">No</th>

                        <th class="px-4 py-3 text-left">Kendala</th>

                        <th class="px-4 py-3 text-left">Downtime / SLA</th>

                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="(category, index) in props.categories"
                        :key="category.id"
                        class="border-b"
                    >
                        <td class="px-4 py-3">
                            {{ index + 1 }}
                        </td>

                        <td class="px-4 py-3 font-medium">
                            {{ category.name }}
                        </td>

                        <td class="px-4 py-3">
                            <span
                                v-if="category.is_downtime"
                                class="rounded-md px-2 py-1 text-xs"
                            >
                                Ya
                            </span>

                            <span v-else class="rounded-md px-2 py-1 text-xs">
                                Tidak
                            </span>
                        </td>

                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="mr-3 text-sm"
                                @click="edit(category)"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                class="text-sm text-destructive"
                                @click="remove(category)"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>

                    <tr v-if="props.categories.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            Belum ada data kendala.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

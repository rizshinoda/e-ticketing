<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Lupa kata sandi',
        description:
            'Masukkan email Anda untuk menerima tautan atur ulang kata sandi',
    },
});

defineProps<{
    status?: string;
}>();

// Gaya kolom input: lebih tinggi, sudut membulat, fokus berwarna amber.
const fieldClass =
    'h-11 rounded-xl focus-visible:border-amber-500 focus-visible:ring-amber-500/30';
</script>

<template>
    <Head title="Lupa kata sandi" />

    <div
        class="rounded-2xl border bg-card p-6 text-card-foreground shadow-sm sm:p-8"
    >
        <div
            v-if="status"
            class="mb-5 rounded-xl bg-green-500/10 px-4 py-3 text-center text-sm font-medium text-green-700 dark:text-green-400"
        >
            {{ status }}
        </div>

        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="email">Alamat email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="email@example.com"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button
                    class="h-11 w-full rounded-xl bg-amber-400 text-sm font-semibold text-neutral-950 transition-all hover:bg-amber-300 active:scale-[0.98]"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Kirim tautan atur ulang
                </Button>
            </div>

            <div
                class="space-x-1 border-t border-dashed border-border pt-6 text-center text-sm text-muted-foreground"
            >
                <span>Ingat kata sandi Anda?</span>
                <TextLink :href="login()">Kembali masuk</TextLink>
            </div>
        </Form>
    </div>
</template>

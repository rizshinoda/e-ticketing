<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Buat akun baru',
        description: 'Isi data di bawah untuk mulai mengelola tiket Anda',
    },
});

// Gaya kolom input: lebih tinggi, sudut membulat, fokus berwarna amber.
const fieldClass =
    'h-11 rounded-xl focus-visible:border-amber-500 focus-visible:ring-amber-500/30';

// PasswordInput membungkus input di dalamnya, jadi gayanya diarahkan ke <input>.
const passwordFieldClass =
    'h-11 [&_input]:h-11 [&_input]:rounded-xl [&_input]:focus-visible:border-amber-500 [&_input]:focus-visible:ring-amber-500/30';
</script>

<template>
    <Head title="Daftar" />

    <div
        class="rounded-2xl border bg-card p-6 text-card-foreground shadow-sm sm:p-8"
    >
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="name">Nama lengkap</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Nama lengkap"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Alamat email</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Kata sandi</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Buat kata sandi"
                        :passwordrules="passwordRules"
                        :class="passwordFieldClass"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Ulangi kata sandi</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Ketik ulang kata sandi"
                        :passwordrules="passwordRules"
                        :class="passwordFieldClass"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-1 h-11 w-full rounded-xl bg-amber-400 text-sm font-semibold text-neutral-950 transition-all hover:bg-amber-300 active:scale-[0.98]"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Buat akun
                </Button>
            </div>

            <div
                class="border-t border-dashed border-border pt-6 text-center text-sm text-muted-foreground"
            >
                Sudah punya akun?
                <TextLink :href="login()" :tabindex="6">Masuk</TextLink>
            </div>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Masuk ke akun Anda',
        description: 'Masukkan email dan kata sandi untuk melanjutkan',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// Gaya kolom input: lebih tinggi, sudut membulat, fokus berwarna amber.
const fieldClass =
    'h-11 rounded-xl focus-visible:border-amber-500 focus-visible:ring-amber-500/30';

// PasswordInput membungkus input di dalamnya, jadi gayanya diarahkan ke <input>.
const passwordFieldClass =
    'h-11 [&_input]:h-11 [&_input]:rounded-xl [&_input]:focus-visible:border-amber-500 [&_input]:focus-visible:ring-amber-500/30';
</script>

<template>
    <Head title="Masuk" />

    <div
        class="rounded-2xl border bg-card p-6 text-card-foreground shadow-sm sm:p-8"
    >
        <div
            v-if="status"
            class="mb-4 rounded-xl bg-green-500/10 px-4 py-3 text-center text-sm font-medium text-green-700 dark:text-green-400"
        >
            {{ status }}
        </div>

        <PasskeyVerify />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
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
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                        :class="fieldClass"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Kata sandi</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        :class="passwordFieldClass"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label
                        for="remember"
                        class="flex items-center gap-2.5 font-normal"
                    >
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                            class="data-[state=checked]:border-amber-500 data-[state=checked]:bg-amber-400 data-[state=checked]:text-neutral-950"
                        />
                        <span>Ingat saya</span>
                    </Label>

                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Lupa kata sandi?
                    </TextLink>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full rounded-xl bg-amber-400 text-sm font-semibold text-neutral-950 transition-all hover:bg-amber-300 active:scale-[0.98]"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Masuk
                </Button>
            </div>

            <div
                class="border-t border-dashed border-border pt-6 text-center text-sm text-muted-foreground"
            >
                Belum punya akun?
                <TextLink :href="register()" :tabindex="5"
                    >Daftar sekarang</TextLink
                >
            </div>
        </Form>
    </div>
</template>

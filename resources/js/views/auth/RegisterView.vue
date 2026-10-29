<template>
    <AuthLayout
        title="Create your account"
        description="New accounts start with Viewer access. Your administrator manages additional permissions."
    >
        <div
            v-if="generalError"
            role="alert"
            class="mb-4 rounded-md border border-[#B42318]/30 bg-[#FDE8E8] p-3 text-sm text-[#B42318]"
        >
            {{ generalError }}
        </div>
        <form class="space-y-5" @submit.prevent="handleSubmit">
            <FormField label="Full name" id="name" required :error="errors.name">
                <template #default="{ inputClass }"
                    ><input
                        id="name"
                        v-model="form.name"
                        autocomplete="name"
                        maxlength="255"
                        required
                        :class="inputClass"
                /></template>
            </FormField>
            <FormField label="Email address" id="email" required :error="errors.email">
                <template #default="{ inputClass }"
                    ><input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        maxlength="255"
                        required
                        :class="inputClass"
                /></template>
            </FormField>
            <FormField label="Password" id="password" required :error="errors.password">
                <template #default="{ inputClass }">
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            :class="[inputClass, 'pr-11']"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex min-h-[44px] items-center px-3 text-muted hover:text-main focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#1E5AA8]"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            :aria-pressed="showPassword"
                            @click="showPassword = !showPassword"
                        >
                            <svg
                                v-if="!showPassword"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            <svg
                                v-else
                                class="h-5 w-5 text-primary-app"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 013.14-4.887m3.435-2.02A10.01 10.01 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21m-2.122-2.122L3 3"
                                />
                            </svg>
                        </button>
                    </div>
                </template>
            </FormField>
            <FormField
                label="Confirm password"
                id="password_confirmation"
                required
                :error="errors.password_confirmation"
            >
                <template #default="{ inputClass }">
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showConfirmationPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            :class="[inputClass, 'pr-11']"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex min-h-[44px] items-center px-3 text-muted hover:text-main focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#1E5AA8]"
                            :aria-label="
                                showConfirmationPassword
                                    ? 'Hide confirmation password'
                                    : 'Show confirmation password'
                            "
                            :aria-pressed="showConfirmationPassword"
                            @click="showConfirmationPassword = !showConfirmationPassword"
                        >
                            <svg
                                v-if="!showConfirmationPassword"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            <svg
                                v-else
                                class="h-5 w-5 text-primary-app"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 013.14-4.887m3.435-2.02A10.01 10.01 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21m-2.122-2.122L3 3"
                                />
                            </svg>
                        </button>
                    </div>
                </template>
            </FormField>
            <button
                type="submit"
                :disabled="submitting"
                class="w-full min-h-[44px] rounded-md bg-primary-app px-4 py-2.5 text-sm font-medium text-white transition-colors bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ submitting ? 'Creating account...' : 'Create account' }}
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-muted">
            Already have an account?
            <router-link
                :to="{ name: 'login' }"
                class="font-medium text-primary-app hover:text-[#174A8A]"
                >Sign in</router-link
            >
        </p>
    </AuthLayout>
</template>

<script setup>
import AuthLayout from '../../layouts/AuthLayout.vue';
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import FormField from '../../components/FormField.vue';

const router = useRouter();
const authStore = useAuthStore();
const toastStore = useToastStore();
const showPassword = ref(false);
const showConfirmationPassword = ref(false);
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const generalError = ref('');
const submitting = ref(false);

const handleSubmit = async () => {
    errors.value = {};
    generalError.value = '';
    submitting.value = true;

    try {
        await authStore.register(form);
        await router.push({ name: 'dashboard' });
        toastStore.success('Account created. You have Viewer access.');
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            generalError.value =
                error.response?.data?.message ||
                'We could not create your account. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AuthLayout
        title="Forgot your password?"
        description="Enter your account email and we will send you a password reset link."
    >
        <div
            v-if="successMessage"
            role="status"
            class="mb-4 p-3 bg-[#E6F4EA] border border-[#1E7B3A]/30 rounded-md text-sm text-[#1E7B3A]"
        >
            {{ successMessage }}
        </div>

        <div
            v-if="generalError"
            role="alert"
            class="mb-4 p-3 bg-[#FDE8E8] border border-[#B42318]/30 rounded-md text-sm text-[#B42318]"
        >
            {{ generalError }}
        </div>

        <form class="space-y-5" @submit.prevent="handleSubmit">
            <FormField label="Email Address" id="email" required :error="errors.email">
                <template #default="{ inputClass }">
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        :class="inputClass"
                        placeholder="you@example.com"
                    />
                </template>
            </FormField>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-md text-sm font-medium text-white bg-primary-app bg-primary-hover focus:outline-none min-h-[44px] transition-colors disabled:opacity-50"
            >
                {{ submitting ? 'Sending link...' : 'Send reset link' }}
            </button>
        </form>

        <div class="mt-6 text-center">
            <router-link
                :to="{ name: 'login' }"
                class="text-xs font-medium text-primary-app hover:text-[#174A8A]"
            >
                Back to login
            </router-link>
        </div>
    </AuthLayout>
</template>

<script setup>
import AuthLayout from '../../layouts/AuthLayout.vue';
import { ref } from 'vue';
import api, { getCsrfCookie } from '../../api/axios';
import FormField from '../../components/FormField.vue';

const email = ref('');
const submitting = ref(false);
const successMessage = ref('');
const generalError = ref('');
const errors = ref({});

const handleSubmit = async () => {
    submitting.value = true;
    successMessage.value = '';
    generalError.value = '';
    errors.value = {};

    try {
        await getCsrfCookie();
        const res = await api.post('/api/forgot-password', { email: email.value });
        successMessage.value = res.data.message || 'Password reset link sent to your email!';
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            generalError.value = err.response?.data?.message || 'Unable to send reset link.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

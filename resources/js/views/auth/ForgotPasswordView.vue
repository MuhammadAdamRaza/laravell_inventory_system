<template>
    <div class="min-h-screen bg-[#F5F7FA] flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <div
                class="mx-auto w-12 h-12 rounded-lg bg-[#1E5AA8] flex items-center justify-center font-bold text-white text-xl"
            >
                IM
            </div>
            <h2 class="mt-4 text-2xl font-semibold tracking-tight text-[#1F2933]">
                Forgot your password?
            </h2>
            <p class="mt-1 text-sm text-[#637083]">
                Enter your email to receive a password reset link.
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
            <div class="bg-white py-8 px-6 sm:px-10 rounded-lg card-shadow border border-[#DDE3EA]">
                <div
                    v-if="successMessage"
                    class="mb-4 p-3 bg-[#E6F4EA] border border-[#1E7B3A]/30 rounded-md text-sm text-[#1E7B3A]"
                >
                    {{ successMessage }}
                </div>

                <div
                    v-if="generalError"
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
                        class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-md text-sm font-medium text-white bg-[#1E5AA8] hover:bg-[#174A8A] focus:outline-none min-h-[44px] transition-colors disabled:opacity-50"
                    >
                        {{ submitting ? 'Sending link...' : 'Send reset link' }}
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <router-link
                        :to="{ name: 'login' }"
                        class="text-xs font-medium text-[#1E5AA8] hover:text-[#174A8A]"
                    >
                        Back to login
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
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

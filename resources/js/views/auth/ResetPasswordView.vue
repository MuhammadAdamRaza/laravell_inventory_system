<template>
    <div class="min-h-screen bg-[#F5F7FA] flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <div
                class="mx-auto w-12 h-12 rounded-lg bg-[#1E5AA8] flex items-center justify-center font-bold text-white text-xl"
            >
                IM
            </div>
            <h2 class="mt-4 text-2xl font-semibold tracking-tight text-[#1F2933]">
                Reset your password
            </h2>
            <p class="mt-1 text-sm text-[#637083]">Enter your new password below.</p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
            <div class="bg-white py-8 px-6 sm:px-10 rounded-lg card-shadow border border-[#DDE3EA]">
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
                                v-model="form.email"
                                type="email"
                                required
                                :class="inputClass"
                            />
                        </template>
                    </FormField>

                    <FormField label="New Password" id="password" required :error="errors.password">
                        <template #default="{ inputClass }">
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    :class="[inputClass, 'pr-11']"
                                    placeholder="Min 8 characters"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#637083] hover:text-[#1F2933] focus:outline-none min-h-[44px]"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg
                                        v-if="!showPassword"
                                        class="w-5 h-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
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
                                        class="w-5 h-5 text-[#1E5AA8]"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
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
                        label="Confirm Password"
                        id="password_confirmation"
                        required
                        :error="errors.password_confirmation"
                    >
                        <template #default="{ inputClass }">
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                :class="inputClass"
                            />
                        </template>
                    </FormField>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-md text-sm font-medium text-white bg-[#1E5AA8] hover:bg-[#174A8A] focus:outline-none min-h-[44px] transition-colors disabled:opacity-50"
                    >
                        {{ submitting ? 'Resetting...' : 'Reset Password' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api, { getCsrfCookie } from '../../api/axios';
import { useToastStore } from '../../stores/toast';
import FormField from '../../components/FormField.vue';

const route = useRoute();
const router = useRouter();
const toastStore = useToastStore();

const form = reactive({
    token: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const errors = ref({});
const generalError = ref('');
const submitting = ref(false);

onMounted(() => {
    form.token = route.query.token || '';
    form.email = route.query.email || '';
});

const handleSubmit = async () => {
    submitting.value = true;
    generalError.value = '';
    errors.value = {};

    try {
        await getCsrfCookie();
        const res = await api.post('/api/reset-password', form);
        toastStore.success(res.data.message || 'Password reset successfully.');
        router.push({ name: 'login' });
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            generalError.value = err.response?.data?.message || 'Password reset failed.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="min-w-0 space-y-6">
        <PageHeader
            title="System Settings"
            subtitle="Manage the business contact details shown to your team."
        />

        <div
            v-if="loading"
            class="surface-card space-y-4 rounded-lg border border-app bg-white p-5 sm:p-6"
            aria-live="polite"
        >
            <div class="h-5 w-40 animate-pulse rounded bg-[#E8EDF3]"></div>
            <div class="h-11 animate-pulse rounded bg-app-bg"></div>
            <div class="h-11 animate-pulse rounded bg-app-bg"></div>
        </div>

        <p
            v-else-if="loadError"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            role="alert"
        >
            {{ loadError }}
        </p>

        <form v-else class="space-y-5" @submit.prevent="save">
            <section class="surface-card rounded-lg border border-app bg-white p-4 sm:p-6">
                <div class="mb-5 border-b border-[#E8EDF3] pb-4">
                    <h2 class="text-base font-semibold text-main">Business profile</h2>
                    <p class="mt-1 text-sm text-muted">
                        These details identify your organization inside the inventory system.
                    </p>
                </div>

                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="min-w-0 text-sm font-medium text-[#344054]" for="business-name">
                        Business name <span class="text-red-600">*</span>
                        <input
                            id="business-name"
                            v-model.trim="form.business_name"
                            type="text"
                            required
                            maxlength="150"
                            autocomplete="organization"
                            class="mt-1 min-h-11 w-full min-w-0 rounded-md border border-app px-3 text-sm text-main focus:border-[#1E5AA8] focus:outline-none focus:ring-2 focus:ring-[#1E5AA8]/20"
                            :aria-invalid="Boolean(fieldErrors.business_name)"
                            aria-describedby="business-name-error"
                        />
                        <span
                            v-if="fieldErrors.business_name"
                            id="business-name-error"
                            class="mt-1 block text-xs text-red-700"
                            >{{ fieldErrors.business_name[0] }}</span
                        >
                    </label>

                    <label class="min-w-0 text-sm font-medium text-[#344054]" for="support-email">
                        Support email
                        <input
                            id="support-email"
                            v-model.trim="form.support_email"
                            type="email"
                            maxlength="255"
                            autocomplete="email"
                            class="mt-1 min-h-11 w-full min-w-0 rounded-md border border-app px-3 text-sm text-main focus:border-[#1E5AA8] focus:outline-none focus:ring-2 focus:ring-[#1E5AA8]/20"
                            :aria-invalid="Boolean(fieldErrors.support_email)"
                            aria-describedby="support-email-error"
                        />
                        <span
                            v-if="fieldErrors.support_email"
                            id="support-email-error"
                            class="mt-1 block text-xs text-red-700"
                            >{{ fieldErrors.support_email[0] }}</span
                        >
                    </label>

                    <label class="min-w-0 text-sm font-medium text-[#344054]" for="support-phone">
                        Support phone
                        <input
                            id="support-phone"
                            v-model.trim="form.support_phone"
                            type="tel"
                            maxlength="40"
                            autocomplete="tel"
                            class="mt-1 min-h-11 w-full min-w-0 rounded-md border border-app px-3 text-sm text-main focus:border-[#1E5AA8] focus:outline-none focus:ring-2 focus:ring-[#1E5AA8]/20"
                            :aria-invalid="Boolean(fieldErrors.support_phone)"
                            aria-describedby="support-phone-error"
                        />
                        <span
                            v-if="fieldErrors.support_phone"
                            id="support-phone-error"
                            class="mt-1 block text-xs text-red-700"
                            >{{ fieldErrors.support_phone[0] }}</span
                        >
                    </label>
                </div>
            </section>

            <p
                v-if="saveError"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                role="alert"
            >
                {{ saveError }}
            </p>
            <p
                v-if="saved"
                class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                role="status"
            >
                Settings saved successfully.
            </p>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="submit"
                    class="min-h-11 w-full rounded-md bg-primary-app px-5 text-sm font-semibold text-white transition bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    :disabled="saving"
                >
                    {{ saving ? 'Saving…' : 'Save settings' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';

const form = reactive({
    business_name: '',
    support_email: '',
    support_phone: '',
});
const fieldErrors = ref({});
const loadError = ref('');
const saveError = ref('');
const loading = ref(true);
const saving = ref(false);
const saved = ref(false);

async function loadSettings() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api.get('/api/system-settings');
        Object.assign(form, response.data.data);
    } catch (error) {
        loadError.value =
            error.response?.data?.message || 'Could not load system settings. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (saving.value) {
        return;
    }

    saving.value = true;
    saved.value = false;
    saveError.value = '';
    fieldErrors.value = {};

    try {
        const response = await api.put('/api/system-settings', form);
        Object.assign(form, response.data.data);
        saved.value = true;
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        saveError.value = fieldErrors.value.business_name
            ? ''
            : error.response?.data?.message || 'Could not save settings. Please try again.';
    } finally {
        saving.value = false;
    }
}

onMounted(loadSettings);
</script>

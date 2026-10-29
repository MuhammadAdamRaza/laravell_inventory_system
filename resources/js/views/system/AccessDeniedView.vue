<template>
    <section class="mx-auto max-w-2xl rounded-xl border border-app bg-surface p-6 shadow-sm sm:p-10">
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[#B42318]">403 ? Access denied</p>
        <h1 class="mt-3 text-2xl font-bold text-main sm:text-3xl">You do not have access to this page</h1>
        <p class="mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">
            Your current role does not include permission for this section. You can return to a page available to your account.
        </p>
        <RouterLink
            :to="returnRoute"
            class="mt-6 inline-flex min-h-[44px] items-center rounded-md bg-primary-app px-4 py-2 font-semibold text-white bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E5AA8]"
        >
            Return to {{ returnLabel }}
        </RouterLink>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();
const returnRoute = computed(() =>
    authStore.hasPermission('reports.view') ? { name: 'dashboard' } : { name: 'notifications' },
);
const returnLabel = computed(() =>
    authStore.hasPermission('reports.view') ? 'dashboard' : 'notifications',
);
</script>

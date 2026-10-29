<template>
    <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div class="min-w-0">
            <p
                v-if="eyebrow || active?.group"
                class="mb-2 text-[10px] font-semibold uppercase tracking-[.16em] text-primary-app"
            >
                {{ eyebrow || active?.group }}
            </p>
            <h1 class="page-heading text-main">{{ title }}</h1>
            <p v-if="subtitle" class="mt-2 max-w-2xl text-[13px] leading-relaxed text-muted">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.actions" class="page-actions flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { getActiveNavigation, getNavigationGroups } from './navigation';
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
});
const route = useRoute();
const auth = useAuthStore();
const active = computed(() => getActiveNavigation(route.path, getNavigationGroups(auth)));
</script>

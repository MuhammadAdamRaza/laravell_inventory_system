<template>
    <nav
        v-if="meta && meta.total > 0"
        aria-label="Pagination"
        class="flex flex-col items-center justify-between gap-3 py-4 sm:flex-row"
    >
        <p class="text-xs text-muted">
            <span class="font-medium text-main">{{ meta.from || 0 }}–{{ meta.to || 0 }}</span> of
            <span class="font-medium text-main">{{ Number(meta.total).toLocaleString() }}</span>
            results
        </p>
        <div class="flex items-center gap-1">
            <button
                type="button"
                class="icon-button border border-app bg-white disabled:opacity-35"
                :disabled="meta.current_page <= 1"
                aria-label="Previous page"
                @click="$emit('page-change', meta.current_page - 1)"
            >
                <AppIcon name="left" class="h-4 w-4" /></button
            ><button
                v-for="page in pages"
                :key="page"
                type="button"
                class="h-10 min-w-9 rounded-lg px-2 text-xs font-semibold"
                :class="
                    page === Number(meta.current_page)
                        ? 'bg-primary-app text-white'
                        : 'text-muted hover:bg-white'
                "
                :aria-label="`Page ${page}`"
                :aria-current="page === Number(meta.current_page) ? 'page' : undefined"
                @click="$emit('page-change', page)"
            >
                {{ page }}</button
            ><button
                type="button"
                class="icon-button border border-app bg-white disabled:opacity-35"
                :disabled="meta.current_page >= meta.last_page"
                aria-label="Next page"
                @click="$emit('page-change', meta.current_page + 1)"
            >
                <AppIcon name="chevron" class="h-4 w-4" />
            </button>
        </div>
    </nav>
</template>
<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
const props = defineProps({ meta: { type: Object, required: true } });
defineEmits(['page-change']);
const pages = computed(() => {
    const total = Number(props.meta?.last_page || 1);
    const start = Math.max(1, Math.min(Number(props.meta?.current_page || 1) - 2, total - 4));
    return Array.from({ length: Math.min(5, total) }, (_, index) => start + index);
});
</script>

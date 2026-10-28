<template>
    <div
        v-if="meta && meta.total > 0"
        class="flex flex-col sm:flex-row items-center justify-between gap-4 py-3 border-t border-[#DDE3EA] text-sm text-[#637083]"
    >
        <div class="text-xs sm:text-sm">
            Showing <span class="font-medium text-[#1F2933]">{{ meta.from || 0 }}</span> to
            <span class="font-medium text-[#1F2933]">{{ meta.to || 0 }}</span> of
            <span class="font-medium text-[#1F2933]">{{ meta.total }}</span> results
        </div>
        <div class="flex items-center space-x-1">
            <button
                :disabled="meta.current_page <= 1"
                class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-[#DDE3EA] bg-white text-[#1F2933] hover:bg-[#F7F9FC] disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px] text-sm font-medium"
                @click="$emit('page-change', meta.current_page - 1)"
            >
                Previous
            </button>

            <span class="px-3 py-2 text-sm font-medium text-[#1F2933]">
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </span>

            <button
                :disabled="meta.current_page >= meta.last_page"
                class="inline-flex items-center justify-center px-3 py-2 rounded-md border border-[#DDE3EA] bg-white text-[#1F2933] hover:bg-[#F7F9FC] disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px] text-sm font-medium"
                @click="$emit('page-change', meta.current_page + 1)"
            >
                Next
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    meta: {
        type: Object,
        required: true,
    },
});

defineEmits(['page-change']);
</script>

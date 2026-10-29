<template>
    <span
        :style="{ backgroundColor: badgeColors.bg, color: badgeColors.text }"
        class="status-badge inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[10px] font-semibold capitalize"
    >
        <span class="w-1 h-1 rounded-full" :style="{ backgroundColor: badgeColors.text }"></span>
        {{ displayLabel }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
});

const statusMap = {
    // Success / OK
    active: { bg: '#E6F4EA', text: '#1E7B3A', label: 'Active' },
    ok: { bg: '#E6F4EA', text: '#1E7B3A', label: 'In Stock (OK)' },
    approved: { bg: '#E6F4EA', text: '#1E7B3A', label: 'Approved' },
    confirmed: { bg: '#E6F4EA', text: '#1E7B3A', label: 'Confirmed' },
    completed: { bg: '#E6F4EA', text: '#1E7B3A', label: 'Completed' },

    // Warning / Low
    low_stock: { bg: '#FFF4E0', text: '#9A5B00', label: 'Low Stock' },
    pending: { bg: '#FFF4E0', text: '#9A5B00', label: 'Pending' },
    pending_approval: { bg: '#FFF4E0', text: '#9A5B00', label: 'Pending Approval' },
    in_transit: { bg: '#FFF4E0', text: '#9A5B00', label: 'In Transit' },
    partially_received: { bg: '#EAF2FB', text: '#1E5AA8', label: 'Partially Received' },
    received: { bg: '#E6F4EA', text: '#1E7B3A', label: 'Received' },

    // Danger / Out
    out_of_stock: { bg: '#FDE8E8', text: '#B42318', label: 'Out of Stock' },
    inactive: { bg: '#FDE8E8', text: '#B42318', label: 'Inactive' },
    cancelled: { bg: '#FDE8E8', text: '#B42318', label: 'Cancelled' },
    error: { bg: '#FDE8E8', text: '#B42318', label: 'Error' },

    // Info
    draft: { bg: '#E8F1FD', text: '#1E5AA8', label: 'Draft' },
    adjustment: { bg: '#E8F1FD', text: '#1E5AA8', label: 'Adjustment' },

    // Neutral
    archived: { bg: '#EEF1F5', text: '#4B5563', label: 'Archived' },
    unknown: { bg: '#EEF1F5', text: '#4B5563', label: 'Unknown' },
};

const badgeColors = computed(() => {
    const key = props.status ? props.status.toLowerCase() : 'unknown';
    return statusMap[key] || { bg: '#EEF1F5', text: '#4B5563', label: props.status };
});

const displayLabel = computed(() => {
    if (props.label) return props.label;
    const key = props.status ? props.status.toLowerCase() : '';
    return statusMap[key]?.label || props.status || 'Unknown';
});
</script>

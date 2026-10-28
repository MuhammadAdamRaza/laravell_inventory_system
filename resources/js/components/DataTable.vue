<template>
    <div class="w-full bg-white rounded-lg border border-[#DDE3EA] card-shadow overflow-hidden">
        <!-- Error State -->
        <div v-if="error" class="p-6 text-center text-[#B42318] bg-[#FDE8E8]/30">
            <svg class="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
            </svg>
            <p class="font-medium text-sm">{{ error }}</p>
            <button
                type="button"
                class="mt-3 inline-flex items-center px-3 py-1.5 border border-[#DDE3EA] rounded-md text-xs font-medium text-[#1F2933] bg-white hover:bg-[#F7F9FC]"
                @click="$emit('retry')"
            >
                Retry
            </button>
        </div>

        <!-- Skeleton Loading -->
        <div v-else-if="loading" class="p-4 space-y-4">
            <div v-for="i in 5" :key="i" class="h-12 bg-gray-100 rounded animate-pulse"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!rows || rows.length === 0" class="p-12 text-center text-[#637083]">
            <svg
                class="w-12 h-12 mx-auto mb-3 text-[#DDE3EA]"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                />
            </svg>
            <h3 class="text-base font-medium text-[#1F2933]">No records found</h3>
            <p class="text-sm mt-1">There is no data to display right now.</p>
        </div>

        <!-- Table Container (Horizontal scroll strictly bounded to table container) -->
        <div v-else class="overflow-x-auto w-full">
            <table class="w-full text-left text-sm divide-y divide-[#DDE3EA]">
                <thead
                    class="bg-[#F5F7FA] text-[#637083] font-medium text-xs uppercase tracking-wider"
                >
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'px-4 py-3',
                                col.class || '',
                                col.sortable
                                    ? 'cursor-pointer select-none hover:text-[#1F2933]'
                                    : '',
                            ]"
                            @click="col.sortable && $emit('sort', col.key)"
                        >
                            <div class="flex items-center space-x-1">
                                <span>{{ col.label }}</span>
                                <span v-if="col.sortable" class="text-xs">
                                    <svg
                                        v-if="sortKey === col.key && sortOrder === 'asc'"
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 15l7-7 7 7"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="sortKey === col.key && sortOrder === 'desc'"
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-3.5 h-3.5 opacity-40"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
                                        />
                                    </svg>
                                </span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DDE3EA] bg-white">
                    <tr
                        v-for="(row, idx) in rows"
                        :key="row.id || idx"
                        class="table-row-hover transition-colors h-12"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="['px-4 py-3 text-sm text-[#1F2933]', col.cellClass || '']"
                        >
                            <slot :name="col.key" :row="row" :value="row[col.key]">
                                {{ row[col.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    sortKey: {
        type: String,
        default: '',
    },
    sortOrder: {
        type: String,
        default: 'desc',
    },
});

defineEmits(['sort', 'retry']);
</script>

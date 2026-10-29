<template>
    <div class="surface-card w-full overflow-hidden" :aria-busy="loading">
        <div v-if="error" class="px-6 py-12 text-center" role="alert">
            <AppIcon name="alert" class="mx-auto mb-3 h-8 w-8 text-red-600" />
            <p class="text-sm font-medium text-main">{{ error }}</p>
            <button type="button" class="ui-button mt-4" @click="$emit('retry')">
                <AppIcon name="refresh" class="h-4 w-4" />Try again
            </button>
        </div>
        <div v-else-if="loading" class="space-y-5 p-5" role="status" aria-label="Loading records">
            <div v-for="n in 5" :key="n" class="flex animate-pulse items-center gap-4">
                <div class="h-10 w-10 rounded-lg bg-slate-100"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-3 w-2/5 rounded bg-slate-100"></div>
                    <div class="h-2.5 w-1/4 rounded bg-slate-100"></div>
                </div>
                <div class="h-6 w-16 rounded-full bg-slate-100"></div>
            </div>
            <span class="sr-only">Loading records...</span>
        </div>
        <div v-else-if="!rows?.length" class="px-6 py-14 text-center">
            <span
                class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-100 bg-slate-50 text-slate-400"
                ><AppIcon name="box" class="h-7 w-7"
            /></span>
            <h3 class="text-sm font-semibold text-main">No records found</h3>
            <p class="mt-1.5 text-xs text-muted">Try adjusting your search or filters.</p>
        </div>
        <template v-else>
            <div class="data-table-scroll hidden w-full md:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                scope="col"
                                :class="col.class"
                                :aria-sort="
                                    col.sortable
                                        ? sortKey === col.key
                                            ? sortOrder === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                        : undefined
                                "
                            >
                                <button
                                    v-if="col.sortable"
                                    type="button"
                                    class="inline-flex items-center gap-2 text-left uppercase"
                                    :aria-label="`Sort by ${col.label}`"
                                    @click="$emit('sort', col.key)"
                                >
                                    {{ col.label
                                    }}<AppIcon
                                        name="sort"
                                        class="h-3 w-3"
                                        :class="
                                            sortKey === col.key ? 'text-primary-app' : 'opacity-40'
                                        "
                                    /></button
                                ><span v-else>{{ col.label }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, index) in rows" :key="row.id ?? index">
                            <td v-for="col in columns" :key="col.key" :class="col.cellClass">
                                <slot :name="col.key" :row="row" :value="row[col.key]">{{
                                    row[col.key]
                                }}</slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">
                <div
                    v-if="sortableColumns.length"
                    class="flex flex-wrap items-center gap-2 bg-slate-50 px-4 py-3"
                >
                    <span class="text-[11px] text-muted">Sort by</span
                    ><button
                        v-for="col in sortableColumns"
                        :key="col.key"
                        type="button"
                        class="rounded-md border border-app bg-white px-2.5 py-2 text-[11px]"
                        :class="{ 'text-primary-app': sortKey === col.key }"
                        @click="$emit('sort', col.key)"
                    >
                        {{ col.label
                        }}<span v-if="sortKey === col.key">
                            {{ sortOrder === 'asc' ? '↑' : '↓' }}</span
                        >
                    </button>
                </div>
                <article v-for="(row, index) in rows" :key="row.id ?? index" class="space-y-4 p-4">
                    <div class="font-semibold text-main">
                        <slot :name="columns[0].key" :row="row" :value="row[columns[0].key]">{{
                            row[columns[0].key]
                        }}</slot>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <div v-for="col in detailColumns" :key="col.key" class="min-w-0">
                            <dt
                                class="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted"
                            >
                                {{ col.label }}
                            </dt>
                            <dd class="break-words text-xs text-main">
                                <slot :name="col.key" :row="row" :value="row[col.key]">{{
                                    row[col.key]
                                }}</slot>
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="columns.some((col) => col.key === 'actions')"
                        class="border-t border-slate-100 pt-3"
                    >
                        <slot name="actions" :row="row" />
                    </div>
                </article>
            </div>
        </template>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    sortKey: { type: String, default: '' },
    sortOrder: { type: String, default: 'desc' },
});
defineEmits(['sort', 'retry']);
const detailColumns = computed(() => props.columns.slice(1).filter((col) => col.key !== 'actions'));
const sortableColumns = computed(() => props.columns.filter((col) => col.sortable));
</script>

<template>
    <section class="mx-auto max-w-5xl space-y-5">
        <PageHeader
            :title="record?.return_number || 'Return details'"
            subtitle="Review returned items, stock eligibility and completion history"
            ><template #actions
                ><router-link
                    :to="{ name: 'sales-returns' }"
                    class="inline-flex min-h-11 items-center rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >All returns</router-link
                ></template
            ></PageHeader
        >
        <p v-if="error" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="load">Retry</button>
        </p>
        <div
            v-if="loading"
            class="surface-card rounded-lg border bg-white p-8 text-center text-sm text-slate-500"
        >
            Loading return
        </div>
        <template v-else-if="record">
            <div
                class="surface-card flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge :status="record.status" /><span class="text-sm text-slate-600"
                        >{{ record.sales_order?.order_number }}
                        {{ record.sales_order?.customer?.name }} {{ record.return_date }}</span
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <router-link
                        v-if="record.allowed_actions.edit"
                        :to="{ name: 'sales-return-edit', params: { id: record.id } }"
                        class="inline-flex min-h-10 items-center rounded-md border px-3 text-sm"
                        >Edit draft</router-link
                    ><button
                        v-if="record.allowed_actions.complete"
                        :disabled="busy"
                        class="min-h-10 rounded-md bg-emerald-700 px-3 text-sm font-semibold text-white disabled:opacity-50"
                        @click="complete"
                    >
                        Complete return</button
                    ><button
                        v-if="record.allowed_actions.cancel"
                        :disabled="busy"
                        class="min-h-10 rounded-md border border-red-300 px-3 text-sm text-red-700 disabled:opacity-50"
                        @click="cancel"
                    >
                        Cancel draft
                    </button>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(240px,1fr)]">
                <div
                    class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white"
                >
                    <h2 class="border-b px-4 py-3 font-semibold">
                        Returned items {{ record.quantity_total }} units
                    </h2>
                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="item in record.items"
                            :key="item.id"
                            class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="font-medium">{{ item.product?.name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ item.product?.sku }} {{ label(item.reason) }}
                                    {{ label(item.condition) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span
                                    >Qty <b>{{ item.quantity_returned }}</b></span
                                ><span
                                    :class="
                                        item.restock_eligible
                                            ? 'text-emerald-700'
                                            : 'text-slate-500'
                                    "
                                    >{{
                                        item.restock_eligible ? 'Restock eligible' : 'Not restocked'
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="record.reason_summary"
                        class="border-t bg-slate-50 p-4 text-sm text-slate-600"
                    >
                        {{ record.reason_summary }}
                    </p>
                </div>
                <aside class="surface-card rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="mb-3 font-semibold">Return history</h2>
                    <p>Created by {{ record.creator?.name || '' }}</p>
                    <p v-if="record.completed_at" class="mt-2">
                        Completed by {{ record.completer?.name || '' }}
                    </p>
                    <p v-if="record.completed_at" class="mt-1 text-slate-500">
                        {{ formatDate(record.completed_at) }}
                    </p>
                    <p v-if="record.cancelled_at" class="mt-2 text-slate-500">
                        Cancelled {{ formatDate(record.cancelled_at) }}
                    </p>
                    <router-link
                        v-if="record.sales_order"
                        :to="{ name: 'sales-order-detail', params: { id: record.sales_order.id } }"
                        class="mt-4 inline-block text-primary-app hover:underline"
                        >View sales order</router-link
                    >
                </aside>
            </div>
        </template>
    </section>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import StatusBadge from '../../components/StatusBadge.vue';
const route = useRoute();
const record = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
function label(value = '') {
    return value.replaceAll('_', ' ');
}
function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
        new Date(value),
    );
}
async function load() {
    loading.value = true;
    error.value = '';
    try {
        record.value = (await api.get('/api/sales-returns/' + route.params.id)).data.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load this return.';
    } finally {
        loading.value = false;
    }
}
async function complete() {
    if (
        !window.confirm(
            'Complete this return? Eligible quantities will be added back to warehouse stock.',
        )
    )
        return;
    busy.value = true;
    error.value = '';
    try {
        const key =
            globalThis.crypto?.randomUUID?.() ||
            'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                const value = (Math.random() * 16) | 0;
                return (char === 'x' ? value : (value & 3) | 8).toString(16);
            });
        await api.post('/api/sales-returns/' + record.value.id + '/complete', {
            idempotency_key: key,
        });
        await load();
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not complete this return.';
    } finally {
        busy.value = false;
    }
}
async function cancel() {
    if (
        !window.confirm(
            'Cancel this draft return? Its reserved quantities will become available again.',
        )
    )
        return;
    busy.value = true;
    error.value = '';
    try {
        await api.post('/api/sales-returns/' + record.value.id + '/cancel');
        await load();
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not cancel this return.';
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>

<template>
    <section class="mx-auto max-w-3xl space-y-5">
        <PageHeader
            :title="adjustment?.adjustment_number || 'Stock adjustment'"
            subtitle="Read-only stock correction record"
            ><template #actions
                ><router-link
                    :to="{ name: 'stock-adjustments' }"
                    class="min-h-11 rounded border px-4 py-2 text-sm"
                    >All adjustments</router-link
                ></template
            ></PageHeader
        >
        <p v-if="error" class="rounded bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="load">Retry</button>
        </p>
        <div v-else-if="!adjustment" class="rounded bg-white p-8 text-center text-slate-500">
            Loading adjustment...
        </div>
        <div v-else class="surface-card space-y-4 rounded-lg border bg-white p-4 sm:p-6">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Product</dt>
                    <dd class="mt-1 font-semibold">
                        {{ adjustment.product?.name }}
                        <small class="font-normal text-slate-500">{{
                            adjustment.product?.sku
                        }}</small>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Warehouse</dt>
                    <dd class="mt-1 font-semibold">{{ adjustment.warehouse?.name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Quantity change</dt>
                    <dd
                        class="mt-1 text-lg font-bold"
                        :class="
                            adjustment.quantity_change > 0 ? 'text-emerald-700' : 'text-red-700'
                        "
                    >
                        {{ adjustment.quantity_change > 0 ? '+' : ''
                        }}{{ adjustment.quantity_change }} units
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Current available stock</dt>
                    <dd class="mt-1 font-semibold">{{ available ?? 'Loading…' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Reason code</dt>
                    <dd class="mt-1 font-semibold capitalize">
                        {{ adjustment.reason_code.replaceAll('_', ' ') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Created by · date</dt>
                    <dd class="mt-1 font-semibold">
                        {{ adjustment.creator?.name }} ·
                        {{ new Date(adjustment.created_at).toLocaleString() }}
                    </dd>
                </div>
            </dl>
            <div class="border-t pt-4">
                <h2 class="text-sm font-semibold">Reason</h2>
                <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                    {{ adjustment.reason }}
                </p>
            </div>
            <p class="rounded bg-slate-50 p-3 text-xs text-slate-600">
                This adjustment is permanent and cannot be edited or deleted. Post a new adjustment
                to correct it.
            </p>
        </div>
    </section>
</template>
<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
const route = useRoute();
const adjustment = ref(null);
const available = ref(null);
const error = ref('');
async function load() {
    error.value = '';
    try {
        adjustment.value = (await api.get(`/api/stock-adjustments/${route.params.id}`)).data.data;
        const r = await api.get('/api/inventory/availability', {
            params: {
                warehouse_id: adjustment.value.warehouse.id,
                product_ids: [adjustment.value.product.id],
            },
        });
        available.value = r.data.data[0]?.available_quantity ?? 0;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load stock adjustment.';
    }
}
onMounted(load);
</script>

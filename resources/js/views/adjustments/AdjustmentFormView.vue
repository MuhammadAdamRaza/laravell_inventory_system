<template>
    <section class="mx-auto max-w-3xl space-y-5">
        <PageHeader
            title="New stock adjustment"
            subtitle="Post an append-only correction with a clear reason"
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
        <div v-else-if="loading" class="rounded bg-white p-8 text-center text-slate-500">
            Loading form...
        </div>
        <form
            v-else
            class="surface-card space-y-5 rounded-lg border bg-white p-4 sm:p-6"
            @submit.prevent="confirmOpen = true"
        >
            <label class="block text-sm"
                >Warehouse *<select
                    v-model="form.warehouse_id"
                    required
                    class="mt-1 min-h-11 w-full rounded border bg-white px-3"
                >
                    <option value="">Select warehouse</option>
                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                        {{ wh.name }}
                    </option></select
                ><span v-if="errors.warehouse_id" class="text-xs text-red-700">{{
                    errors.warehouse_id[0]
                }}</span></label
            ><label class="block text-sm"
                >Product *<select
                    v-model="form.product_id"
                    required
                    class="mt-1 min-h-11 w-full rounded border bg-white px-3"
                >
                    <option value="">Select product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">
                        {{ p.name }} ({{ p.sku }})
                    </option></select
                ><span v-if="errors.product_id" class="text-xs text-red-700">{{
                    errors.product_id[0]
                }}</span></label
            >
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm"
                    >Quantity change *<input
                        v-model.number="form.quantity_change"
                        type="number"
                        step="1"
                        required
                        class="mt-1 min-h-11 w-full rounded border px-3"
                    /><small class="mt-1 block text-slate-500"
                        >Use positive quantity to add stock and negative quantity to remove
                        it.</small
                    ><span v-if="errors.quantity_change" class="text-xs text-red-700">{{
                        errors.quantity_change[0]
                    }}</span></label
                ><label class="text-sm"
                    >Reason code *<select
                        v-model="form.reason_code"
                        class="mt-1 min-h-11 w-full rounded border bg-white px-3"
                    >
                        <option v-for="reason in reasons" :key="reason" :value="reason">
                            {{ reason.replaceAll('_', ' ') }}
                        </option>
                    </select></label
                >
            </div>
            <label class="block text-sm"
                >Reason *<textarea
                    v-model="form.reason"
                    minlength="10"
                    maxlength="10000"
                    rows="4"
                    required
                    class="mt-1 w-full rounded border px-3 py-2"
                ></textarea
                ><small class="text-slate-500"
                    >At least 10 characters ({{ form.reason.length }}/10).</small
                ><span v-if="errors.reason" class="block text-xs text-red-700">{{
                    errors.reason[0]
                }}</span></label
            >
            <p v-if="saveError" class="text-sm text-red-700">{{ saveError }}</p>
            <div class="flex justify-end gap-2">
                <router-link
                    :to="{ name: 'stock-adjustments' }"
                    class="min-h-11 rounded border px-4 py-2"
                    >Cancel</router-link
                ><button
                    :disabled="saving"
                    class="min-h-11 rounded bg-primary-app px-5 py-2 font-semibold text-white disabled:opacity-50"
                >
                    Review adjustment
                </button>
            </div>
        </form>
        <ConfirmDialog
            :is-open="confirmOpen"
            title="Post stock adjustment"
            :message="`Apply ${form.quantity_change > 0 ? '+' : ''}${form.quantity_change || 0} units to the selected warehouse? This entry cannot be edited or deleted.`"
            confirm-text="Post adjustment"
            :is-danger="form.quantity_change < 0"
            @confirm="save"
            @cancel="confirmOpen = false"
        />
    </section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
const router = useRouter();
const warehouses = ref([]);
const products = ref([]);
const loading = ref(true);
const saving = ref(false);
const confirmOpen = ref(false);
const error = ref('');
const saveError = ref('');
const errors = ref({});
const reasons = ['count_correction', 'damage', 'loss', 'found', 'other'];
const form = reactive({
    warehouse_id: '',
    product_id: '',
    quantity_change: 1,
    reason_code: 'count_correction',
    reason: '',
});
async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [w, p] = await Promise.all([api.get('/api/warehouses'), api.get('/api/products')]);
        warehouses.value = w.data.data;
        products.value = p.data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load adjustment form.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    saving.value = true;
    confirmOpen.value = false;
    errors.value = {};
    saveError.value = '';
    try {
        const r = await api.post('/api/stock-adjustments', {
            ...form,
            warehouse_id: Number(form.warehouse_id),
            product_id: Number(form.product_id),
            quantity_change: Number(form.quantity_change),
        });
        await router.push({ name: 'stock-adjustment-detail', params: { id: r.data.data.id } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        saveError.value = e.response?.data?.message || 'Could not post adjustment.';
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>

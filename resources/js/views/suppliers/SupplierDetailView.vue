<template>
    <section class="space-y-5">
        <PageHeader
            :title="supplier?.name || 'Supplier'"
            subtitle="Purchase history and account balance"
        >
            <template #actions
                ><router-link
                    :to="{ name: 'suppliers' }"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >Suppliers</router-link
                ></template
            >
        </PageHeader>
        <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        <div v-if="summary" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <article
                v-for="card in cards"
                :key="card.label"
                class="surface-card rounded-lg border border-slate-200 bg-white p-4"
            >
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                <p class="mt-2 text-xl font-semibold">
                    {{ card.money ? 'PKR ' + formatMoney(summary[card.key]) : summary[card.key] }}
                </p>
            </article>
        </div>
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr]">
            <div class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white">
                <h2 class="border-b p-4 font-semibold">Purchase history</h2>
                <div v-if="history.length === 0" class="p-6 text-sm text-slate-500">
                    No purchases for this supplier.
                </div>
                <div
                    v-for="item in history"
                    :key="item.id"
                    class="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <router-link
                            :to="{ name: 'purchase-detail', params: { id: item.id } }"
                            class="font-semibold text-primary-app hover:underline"
                            >{{ item.purchase_number }}</router-link
                        >
                        <p class="text-xs text-slate-500">
                            {{ item.order_date }} - {{ item.warehouse?.name }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <StatusBadge :status="item.status" /><b class="text-sm"
                            >PKR {{ formatMoney(item.grand_total) }}</b
                        >
                    </div>
                </div>
                <Pagination :meta="page" @page-change="loadHistory" />
            </div>
            <form
                v-if="auth.hasPermission('suppliers.manage')"
                class="surface-card h-fit space-y-3 rounded-lg border border-slate-200 bg-white p-4"
                @submit.prevent="recordPayment"
            >
                <h2 class="font-semibold">Record supplier payment</h2>
                <label class="block text-sm"
                    >Amount<input
                        v-model="payment.amount"
                        required
                        type="text"
                        inputmode="decimal"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                /></label>
                <label class="block text-sm"
                    >Payment date<input
                        v-model="payment.payment_date"
                        required
                        type="date"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                /></label>
                <label class="block text-sm"
                    >Reference<input
                        v-model="payment.reference"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                /></label>
                <button
                    :disabled="saving"
                    class="min-h-11 w-full rounded-md bg-primary-app px-4 font-semibold text-white disabled:opacity-50"
                >
                    {{ saving ? 'Saving...' : 'Save payment' }}
                </button>
            </form>
        </div>
    </section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/axios';
import { formatMoney } from '../../api/money';
import { useAuthStore } from '../../stores/auth';
import PageHeader from '../../components/PageHeader.vue';
import Pagination from '../../components/Pagination.vue';
import StatusBadge from '../../components/StatusBadge.vue';
const route = useRoute();
const auth = useAuthStore();
const supplier = ref(null);
const summary = ref(null);
const history = ref([]);
const error = ref('');
const saving = ref(false);
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const payment = reactive({
    amount: '',
    payment_date: new Date().toISOString().slice(0, 10),
    reference: '',
});
const cards = [
    { label: 'Purchases', key: 'total_purchases', money: false },
    { label: 'Total purchased', key: 'total_amount', money: true },
    { label: 'Total paid', key: 'total_paid', money: true },
    { label: 'Outstanding balance', key: 'outstanding_balance', money: true },
];
async function loadHistory(n = 1) {
    try {
        const response = await api.get('/api/suppliers/' + route.params.id + '/purchases', {
            params: { page: n },
        });
        history.value = response.data.data;
        Object.assign(page, response.data.meta);
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load supplier purchases.';
    }
}
async function load() {
    try {
        supplier.value = (await api.get('/api/suppliers/' + route.params.id)).data.data;
        summary.value = (await api.get('/api/suppliers/' + route.params.id + '/summary')).data.data;
        await loadHistory(1);
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load supplier account.';
    }
}
async function recordPayment() {
    saving.value = true;
    error.value = '';
    try {
        await api.post('/api/supplier-payments', {
            ...payment,
            supplier_id: Number(route.params.id),
        });
        payment.amount = '';
        payment.reference = '';
        summary.value = (await api.get('/api/suppliers/' + route.params.id + '/summary')).data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not record payment.';
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>

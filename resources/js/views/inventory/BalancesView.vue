<template>
    <div class="space-y-6">
        <PageHeader
            title="Stock Balances"
            subtitle="Track live stock levels per product per warehouse with automated threshold alerts"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center px-3.5 py-2 border border-[#DDE3EA] rounded-md text-sm font-medium text-[#1F2933] bg-white hover:bg-[#F7F9FC] min-h-[44px] transition-colors"
                    @click="exportCsv"
                >
                    <svg
                        class="w-4 h-4 mr-2 text-[#637083]"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                    Export CSV
                </button>

                <button
                    v-if="canAdjust"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-[#1E5AA8] hover:bg-[#174A8A] min-h-[44px] transition-colors"
                    @click="openOpeningStockModal"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                        />
                    </svg>
                    Record Opening / Adjust Stock
                </button>
            </template>
        </PageHeader>

        <!-- Professional KPI Metrics Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
            <div class="bg-white p-4 rounded-lg border border-[#DDE3EA] card-shadow">
                <p class="text-xs font-medium text-[#637083]">Total Stock Units</p>
                <p class="text-xl sm:text-2xl font-bold text-[#1F2933] mt-1">
                    {{ stats.total_units?.toLocaleString() ?? 0 }}
                </p>
            </div>

            <div class="bg-white p-4 rounded-lg border border-[#DDE3EA] card-shadow">
                <p class="text-xs font-medium text-[#637083]">Catalog Products</p>
                <p class="text-xl sm:text-2xl font-bold text-[#1F2933] mt-1">
                    {{ stats.total_products ?? 0 }}
                </p>
            </div>

            <div class="bg-white p-4 rounded-lg border border-[#DDE3EA] card-shadow">
                <p class="text-xs font-medium text-[#637083]">Active Warehouses</p>
                <p class="text-xl sm:text-2xl font-bold text-[#1F2933] mt-1">
                    {{ stats.total_warehouses ?? 0 }}
                </p>
            </div>

            <div class="bg-[#FFF4E0]/40 p-4 rounded-lg border border-[#9A5B00]/20 card-shadow">
                <p class="text-xs font-medium text-[#9A5B00]">Low Stock Alerts</p>
                <p class="text-xl sm:text-2xl font-bold text-[#9A5B00] mt-1">
                    {{ stats.low_stock_count ?? 0 }}
                </p>
            </div>

            <div
                class="bg-[#FDE8E8]/40 p-4 rounded-lg border border-[#B42318]/20 card-shadow col-span-2 sm:col-span-1"
            >
                <p class="text-xs font-medium text-[#B42318]">Out of Stock</p>
                <p class="text-xl sm:text-2xl font-bold text-[#B42318] mt-1">
                    {{ stats.out_of_stock_count ?? 0 }}
                </p>
            </div>
        </div>

        <!-- Filters Bar -->
        <div
            class="bg-white p-4 rounded-lg border border-[#DDE3EA] card-shadow flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between"
        >
            <SearchInput
                v-model="search"
                placeholder="Search by product name, SKU..."
                @search="fetchBalances(1)"
            />

            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="warehouseFilter"
                    class="px-3 py-2 bg-white border border-[#DDE3EA] rounded-md text-sm text-[#1F2933] min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchBalances(1)"
                >
                    <option value="">All Warehouses</option>
                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                        {{ wh.name }}
                    </option>
                </select>

                <select
                    v-model="stockStatusFilter"
                    class="px-3 py-2 bg-white border border-[#DDE3EA] rounded-md text-sm text-[#1F2933] min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchBalances(1)"
                >
                    <option value="">All Stock Levels</option>
                    <option value="ok">In Stock (OK)</option>
                    <option value="low_stock">Low Stock Alerts</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>

                <button
                    v-if="search || warehouseFilter || stockStatusFilter"
                    type="button"
                    class="text-xs text-[#637083] hover:text-[#1F2933] underline px-2 py-1 min-h-[44px]"
                    @click="resetFilters"
                >
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :columns="columns"
            :rows="balances"
            :loading="loading"
            :error="error"
            @retry="fetchBalances(pagination.current_page)"
        >
            <template #product="{ row }">
                <div>
                    <p class="font-medium text-[#1F2933]">{{ row.product_name }}</p>
                    <p class="text-xs text-[#637083] font-mono">{{ row.sku }}</p>
                </div>
            </template>

            <template #warehouse="{ row }">
                <span class="text-xs font-medium text-[#1F2933]">{{ row.warehouse_name }}</span>
            </template>

            <template #quantity="{ row }">
                <span
                    :class="[
                        'font-semibold text-sm',
                        row.quantity === 0
                            ? 'text-[#B42318]'
                            : row.quantity <= row.minimum_stock
                              ? 'text-[#9A5B00]'
                              : 'text-[#1F2933]',
                    ]"
                >
                    {{ row.quantity?.toLocaleString() }}
                </span>
            </template>

            <template #minimum_stock="{ row }">
                <span class="text-xs text-[#637083]">{{ row.minimum_stock }} units</span>
            </template>

            <template #status="{ row }">
                <StatusBadge :status="row.stock_status" />
            </template>
        </DataTable>

        <Pagination :meta="pagination" @page-change="fetchBalances" />

        <!-- Opening Stock Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-black/40 transition-opacity" @click="closeModal"></div>

                <div
                    class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full p-6 space-y-4"
                >
                    <div class="flex justify-between items-center border-b border-[#DDE3EA] pb-3">
                        <h3 class="text-lg font-semibold text-[#1F2933]">
                            Record Stock Intake / Adjustment
                        </h3>
                        <button
                            type="button"
                            class="text-[#637083] hover:text-[#1F2933]"
                            @click="closeModal"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="saveStock">
                        <FormField
                            label="Product"
                            id="product_id"
                            required
                            :error="formErrors.product_id"
                        >
                            <template #default="{ inputClass }">
                                <select
                                    id="product_id"
                                    v-model="form.product_id"
                                    required
                                    :class="inputClass"
                                >
                                    <option value="" disabled>Select Product</option>
                                    <option v-for="p in productsList" :key="p.id" :value="p.id">
                                        {{ p.name }} ({{ p.sku }})
                                    </option>
                                </select>
                            </template>
                        </FormField>

                        <FormField
                            label="Warehouse"
                            id="warehouse_id"
                            required
                            :error="formErrors.warehouse_id"
                        >
                            <template #default="{ inputClass }">
                                <select
                                    id="warehouse_id"
                                    v-model="form.warehouse_id"
                                    required
                                    :class="inputClass"
                                >
                                    <option value="" disabled>Select Warehouse</option>
                                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                                        {{ wh.name }}
                                    </option>
                                </select>
                            </template>
                        </FormField>

                        <FormField
                            label="Quantity Units"
                            id="quantity"
                            required
                            :error="formErrors.quantity"
                        >
                            <template #default="{ inputClass }">
                                <input
                                    id="quantity"
                                    v-model.number="form.quantity"
                                    type="number"
                                    min="1"
                                    required
                                    :class="inputClass"
                                    placeholder="e.g. 50"
                                />
                            </template>
                        </FormField>

                        <FormField
                            label="Reason / Audit Note"
                            id="reason"
                            :error="formErrors.reason"
                        >
                            <template #default="{ inputClass }">
                                <input
                                    id="reason"
                                    v-model="form.reason"
                                    type="text"
                                    :class="inputClass"
                                    placeholder="e.g. Opening stock initial intake"
                                />
                            </template>
                        </FormField>

                        <div class="flex justify-end space-x-3 pt-3 border-t border-[#DDE3EA]">
                            <button
                                type="button"
                                class="px-4 py-2 border border-[#DDE3EA] rounded-md text-sm font-medium text-[#1F2933] bg-white hover:bg-[#F7F9FC] min-h-[44px]"
                                @click="closeModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 rounded-md text-sm font-medium text-white bg-[#1E5AA8] hover:bg-[#174A8A] disabled:opacity-50 min-h-[44px] transition-colors"
                            >
                                {{ saving ? 'Saving...' : 'Apply Stock Change' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../../api/axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import Pagination from '../../components/Pagination.vue';
import SearchInput from '../../components/SearchInput.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormField from '../../components/FormField.vue';

const authStore = useAuthStore();
const toastStore = useToastStore();

const canAdjust = computed(() => authStore.hasPermission('inventory.adjust'));

const balances = ref([]);
const warehouses = ref([]);
const productsList = ref([]);
const stats = ref({});
const loading = ref(false);
const error = ref(null);
const search = ref('');
const warehouseFilter = ref('');
const stockStatusFilter = ref('');

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const columns = [
    { key: 'product', label: 'Product & SKU' },
    { key: 'warehouse', label: 'Warehouse' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'minimum_stock', label: 'Threshold (Min)' },
    { key: 'status', label: 'Stock Status' },
];

const showModal = ref(false);
const saving = ref(false);
const formErrors = ref({});

const form = reactive({
    product_id: '',
    warehouse_id: '',
    quantity: 50,
    reason: 'Opening stock',
});

const fetchStats = async () => {
    try {
        const res = await api.get('/api/inventory/stats');
        stats.value = res.data;
    } catch (err) {
        // silent
    }
};

const fetchWarehouses = async () => {
    try {
        const res = await api.get('/api/warehouses', { params: { per_page: 100 } });
        warehouses.value = res.data.data;
    } catch (err) {
        // silent
    }
};

const fetchProductsList = async () => {
    try {
        const res = await api.get('/api/products', { params: { per_page: 100 } });
        productsList.value = res.data.data;
    } catch (err) {
        // silent
    }
};

const fetchBalances = async (page = 1) => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/api/inventory/balances', {
            params: {
                page,
                search: search.value,
                warehouse_id: warehouseFilter.value,
                stock_status: stockStatusFilter.value,
            },
        });
        balances.value = res.data.data;
        Object.assign(pagination, res.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load stock balances.';
    } finally {
        loading.value = false;
    }
};

const resetFilters = () => {
    search.value = '';
    warehouseFilter.value = '';
    stockStatusFilter.value = '';
    fetchBalances(1);
};

const exportCsv = () => {
    if (!balances.value.length) {
        toastStore.info('No balance records to export.');
        return;
    }

    const headers = ['Product', 'SKU', 'Warehouse', 'Quantity', 'Min Stock', 'Status'];
    const rows = balances.value.map((b) => [
        `"${b.product_name}"`,
        `"${b.sku}"`,
        `"${b.warehouse_name}"`,
        b.quantity,
        b.minimum_stock,
        `"${b.stock_status}"`,
    ]);

    const csvContent =
        'data:text/csv;charset=utf-8,' +
        [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute(
        'download',
        `inventory_balances_${new Date().toISOString().slice(0, 10)}.csv`,
    );
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toastStore.success('CSV exported successfully.');
};

const openOpeningStockModal = () => {
    form.product_id = productsList.value[0]?.id || '';
    form.warehouse_id = warehouses.value[0]?.id || '';
    form.quantity = 50;
    form.reason = 'Opening stock';
    formErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    formErrors.value = {};
};

const saveStock = async () => {
    saving.value = true;
    formErrors.value = {};
    try {
        await api.post('/api/inventory/opening-stock', form);
        toastStore.success('Stock adjusted successfully.');
        closeModal();
        fetchBalances(1);
        fetchStats();
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            toastStore.error(err.response?.data?.message || 'Failed to record stock.');
        }
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    fetchStats();
    fetchWarehouses();
    fetchProductsList();
    fetchBalances(1);
});
</script>

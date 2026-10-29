<template>
    <div>
        <PageHeader
            title="Stock Movements"
            subtitle="Append-only audit trail of every stock change and intake"
        />

        <!-- Filters Bar -->
        <div
            class="filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between"
        >
            <SearchInput
                v-model="search"
                placeholder="Search by reason, product, or warehouse..."
                @search="fetchMovements(1)"
            />

            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="movementTypeFilter"
                    class="px-3 py-2 bg-white border border-app rounded-md text-sm text-main min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchMovements(1)"
                >
                    <option value="">All Movement Types</option>
                    <option value="adjustment">Adjustment</option>
                    <option value="opening_stock">Opening Stock</option>
                    <option value="purchase">Purchase</option>
                    <option value="transfer">Transfer</option>
                    <option value="sale">Sale</option>
                </select>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :columns="columns"
            :rows="movements"
            :loading="loading"
            :error="error"
            @retry="fetchMovements(pagination.current_page)"
        >
            <template #product="{ row }">
                <div>
                    <p class="font-medium text-main">{{ row.product_name }}</p>
                    <p class="text-xs text-muted font-mono">{{ row.sku }}</p>
                </div>
            </template>

            <template #warehouse="{ row }">
                <span class="text-xs font-medium text-main">{{ row.warehouse_name }}</span>
            </template>

            <template #movement_type="{ row }">
                <StatusBadge :status="row.movement_type" />
            </template>

            <template #quantity_change="{ row }">
                <span
                    :class="[
                        'font-semibold text-xs px-2 py-0.5 rounded',
                        row.quantity_change > 0
                            ? 'bg-[#E6F4EA] text-[#1E7B3A]'
                            : 'bg-[#FDE8E8] text-[#B42318]',
                    ]"
                >
                    {{ row.quantity_change > 0 ? `+${row.quantity_change}` : row.quantity_change }}
                </span>
            </template>

            <template #balance_after="{ row }">
                <span class="font-semibold text-sm text-main">{{ row.balance_after }}</span>
            </template>

            <template #user="{ row }">
                <span class="text-xs text-muted">{{ row.user_name || 'System' }}</span>
            </template>

            <template #date="{ row }">
                <span class="text-xs text-muted">{{ formatDate(row.created_at) }}</span>
            </template>
        </DataTable>

        <Pagination :meta="pagination" @page-change="fetchMovements" />
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import Pagination from '../../components/Pagination.vue';
import SearchInput from '../../components/SearchInput.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const movements = ref([]);
const loading = ref(false);
const error = ref(null);
const search = ref('');
const movementTypeFilter = ref('');

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const columns = [
    { key: 'date', label: 'Date & Time' },
    { key: 'product', label: 'Product' },
    { key: 'warehouse', label: 'Warehouse' },
    { key: 'movement_type', label: 'Type' },
    { key: 'quantity_change', label: 'Change' },
    { key: 'balance_after', label: 'Balance After' },
    { key: 'user', label: 'User' },
    { key: 'reason', label: 'Reason' },
];

const fetchMovements = async (page = 1) => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/api/inventory/movements', {
            params: {
                page,
                search: search.value,
                movement_type: movementTypeFilter.value,
            },
        });
        movements.value = res.data.data;
        Object.assign(pagination, res.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load stock movements.';
    } finally {
        loading.value = false;
    }
};

const formatDate = (isoStr) => {
    if (!isoStr) return '-';
    const d = new Date(isoStr);
    return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    fetchMovements(1);
});
</script>

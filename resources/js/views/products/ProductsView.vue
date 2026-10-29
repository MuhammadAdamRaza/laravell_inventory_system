<template>
    <div>
        <PageHeader
            title="Products"
            subtitle="Manage catalog products, pricing, and stock thresholds"
        >
            <template #actions>
                <button
                    v-if="canCreate"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-primary-app bg-primary-hover min-h-[44px] transition-colors"
                    @click="openCreateModal"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Add Product
                </button>
            </template>
        </PageHeader>

        <!-- Filters Bar -->
        <div
            class="filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between"
        >
            <SearchInput
                v-model="search"
                placeholder="Search by name, SKU, barcode..."
                @search="fetchProducts(1)"
            />

            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="categoryFilter"
                    class="px-3 py-2 bg-white border border-app rounded-md text-sm text-main min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchProducts(1)"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.name }}
                    </option>
                </select>

                <select
                    v-model="statusFilter"
                    class="px-3 py-2 bg-white border border-app rounded-md text-sm text-main min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchProducts(1)"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :columns="columns"
            :rows="products"
            :loading="loading"
            :error="error"
            :sort-key="sortBy"
            :sort-order="sortDirection"
            @sort="handleSort"
            @retry="fetchProducts(pagination.current_page)"
        >
            <template #product="{ row }">
                <div
                    class="flex items-center gap-3 cursor-pointer rounded-lg py-1 text-left"
                    role="button"
                    tabindex="0"
                    :aria-label="`View ${row.name}`"
                    @keydown.enter="viewDetails(row)"
                    @keydown.space.prevent="viewDetails(row)"
                    @click="viewDetails(row)"
                >
                    <div
                        class="w-12 h-12 rounded-lg border border-app bg-app-bg overflow-hidden flex items-center justify-center flex-shrink-0"
                    >
                        <img
                            v-if="row.image_url"
                            loading="lazy"
                            decoding="async"
                            :src="row.image_url"
                            :alt="row.name"
                            class="w-full h-full object-cover"
                        />
                        <svg
                            v-else
                            class="w-5 h-5 text-muted"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-main hover:text-primary-app">
                            {{ row.name }}
                        </p>
                        <p class="text-xs text-muted font-mono">{{ row.sku }}</p>
                    </div>
                </div>
            </template>

            <template #category="{ row }">
                <span class="text-xs text-main font-medium">{{ row.category?.name || '-' }}</span>
            </template>

            <template #pricing="{ row }">
                <div class="text-xs">
                    <p class="font-medium text-main">
                        Sell: PKR {{ Number(row.selling_price).toFixed(2) }}
                    </p>
                    <p class="text-muted">Buy: PKR {{ Number(row.purchase_price).toFixed(2) }}</p>
                </div>
            </template>

            <template #stock_info="{ row }">
                <div class="text-xs text-muted">
                    Min: <span class="font-medium text-main">{{ row.minimum_stock }}</span>
                    {{ row.unit }}
                </div>
            </template>

            <template #status="{ row }">
                <StatusBadge :status="row.status" />
            </template>

            <template #actions="{ row }">
                <div class="flex items-center justify-end space-x-2">
                    <button
                        type="button"
                        class="text-muted hover:text-main font-medium text-xs py-1 px-2 rounded hover:bg-app-bg min-h-[32px]"
                        @click="viewDetails(row)"
                    >
                        View
                    </button>
                    <button
                        v-if="canUpdate"
                        type="button"
                        class="text-primary-app hover:text-[#174A8A] font-medium text-xs py-1 px-2 rounded hover:bg-[#EAF2FB] min-h-[32px]"
                        @click="openEditModal(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="canArchive && row.status !== 'archived'"
                        type="button"
                        class="text-[#B42318] hover:text-[#912018] font-medium text-xs py-1 px-2 rounded hover:bg-[#FDE8E8] min-h-[32px]"
                        @click="confirmArchive(row)"
                    >
                        Archive
                    </button>
                </div>
            </template>
        </DataTable>

        <Pagination :meta="pagination" @page-change="fetchProducts" />

        <!-- Create / Edit Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-black/40 transition-opacity" @click="closeModal"></div>

                <div
                    class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-2xl sm:w-full p-6 space-y-4"
                >
                    <div class="flex justify-between items-center border-b border-app pb-3">
                        <h3 class="text-lg font-semibold text-main">
                            {{ isEditing ? 'Edit Product' : 'Create Product' }}
                        </h3>
                        <button
                            type="button"
                            class="text-muted hover:text-main"
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

                    <form class="space-y-4" @submit.prevent="saveProduct">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <FormField
                                label="SKU (Unique)"
                                id="sku"
                                required
                                :error="formErrors.sku"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="sku"
                                        v-model="form.sku"
                                        type="text"
                                        required
                                        :class="inputClass"
                                        placeholder="e.g. ELEC-002"
                                    />
                                </template>
                            </FormField>

                            <FormField
                                label="Product Name"
                                id="name"
                                required
                                :error="formErrors.name"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <FormField
                                label="Category"
                                id="category_id"
                                required
                                :error="formErrors.category_id"
                            >
                                <template #default="{ inputClass }">
                                    <select
                                        id="category_id"
                                        v-model="form.category_id"
                                        required
                                        :class="inputClass"
                                    >
                                        <option value="" disabled>Select active category</option>
                                        <option
                                            v-for="cat in activeCategories"
                                            :key="cat.id"
                                            :value="cat.id"
                                        >
                                            {{ cat.name }}
                                        </option>
                                    </select>
                                </template>
                            </FormField>

                            <FormField label="Brand" id="brand" :error="formErrors.brand">
                                <template #default="{ inputClass }">
                                    <input
                                        id="brand"
                                        v-model="form.brand"
                                        type="text"
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <FormField label="Unit" id="unit" required :error="formErrors.unit">
                                <template #default="{ inputClass }">
                                    <input
                                        id="unit"
                                        v-model="form.unit"
                                        type="text"
                                        required
                                        :class="inputClass"
                                        placeholder="pcs, box, kg"
                                    />
                                </template>
                            </FormField>

                            <FormField label="Barcode" id="barcode" :error="formErrors.barcode">
                                <template #default="{ inputClass }">
                                    <input
                                        id="barcode"
                                        v-model="form.barcode"
                                        type="text"
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>

                            <FormField
                                label="Minimum Stock"
                                id="minimum_stock"
                                required
                                :error="formErrors.minimum_stock"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="minimum_stock"
                                        v-model.number="form.minimum_stock"
                                        type="number"
                                        min="0"
                                        required
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <FormField
                                label="Purchase Price ($)"
                                id="purchase_price"
                                required
                                :error="formErrors.purchase_price"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="purchase_price"
                                        v-model.number="form.purchase_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>

                            <FormField
                                label="Selling Price ($)"
                                id="selling_price"
                                required
                                :error="formErrors.selling_price"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="selling_price"
                                        v-model.number="form.selling_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>

                            <FormField
                                label="Status"
                                id="status"
                                required
                                :error="formErrors.status"
                            >
                                <template #default="{ inputClass }">
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        required
                                        :class="inputClass"
                                    >
                                        <option value="active">Active</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </template>
                            </FormField>
                        </div>

                        <FormField
                            label="Description"
                            id="description"
                            :error="formErrors.description"
                        >
                            <template #default="{ inputClass }">
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="2"
                                    :class="inputClass"
                                ></textarea>
                            </template>
                        </FormField>

                        <div>
                            <label class="block text-[13px] font-medium text-muted mb-1.5"
                                >Product Image</label
                            >
                            <ImageUploader
                                :current-url="imagePreviewUrl"
                                @file-selected="selectedImageFile = $event"
                                @file-removed="
                                    selectedImageFile = null;
                                    imagePreviewUrl = null;
                                "
                            />
                            <p v-if="formErrors.image" class="text-xs text-[#B42318] mt-1">
                                {{ formErrors.image[0] }}
                            </p>
                        </div>

                        <div class="flex justify-end space-x-3 pt-3 border-t border-app">
                            <button
                                type="button"
                                class="px-4 py-2 border border-app rounded-md text-sm font-medium text-main bg-white bg-hover-surface min-h-[44px]"
                                @click="closeModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-app bg-primary-hover disabled:opacity-50 min-h-[44px] transition-colors"
                            >
                                {{
                                    saving
                                        ? 'Saving...'
                                        : isEditing
                                          ? 'Update Product'
                                          : 'Create Product'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Product Details Modal -->
        <div
            v-if="detailProduct"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
                <div
                    class="fixed inset-0 bg-black/40 transition-opacity"
                    @click="detailProduct = null"
                ></div>

                <div
                    class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-xl sm:w-full p-6 space-y-4"
                >
                    <div class="flex justify-between items-center border-b border-app pb-3">
                        <h3 class="text-lg font-semibold text-main">Product Details</h3>
                        <button
                            type="button"
                            class="text-muted hover:text-main"
                            @click="detailProduct = null"
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

                    <div class="flex flex-col sm:flex-row gap-4">
                        <div
                            class="w-full sm:w-48 h-48 rounded-md border border-app overflow-hidden bg-app-bg flex items-center justify-center flex-shrink-0"
                        >
                            <img
                                v-if="detailProduct.image_url"
                                :src="detailProduct.image_url"
                                :alt="detailProduct.name"
                                class="w-full h-full object-cover"
                            />
                            <svg
                                v-else
                                class="w-16 h-16 text-muted"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>

                        <div class="flex-1 space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-semibold text-main">
                                    {{ detailProduct.name }}
                                </h4>
                                <StatusBadge :status="detailProduct.status" />
                            </div>
                            <p class="text-xs font-mono text-muted">SKU: {{ detailProduct.sku }}</p>
                            <p class="text-xs text-muted">
                                Barcode: {{ detailProduct.barcode || 'N/A' }}
                            </p>
                            <p class="text-xs text-muted">
                                Category: {{ detailProduct.category?.name }}
                            </p>
                            <p class="text-xs text-muted">
                                Brand: {{ detailProduct.brand || 'N/A' }}
                            </p>

                            <div class="pt-2 grid grid-cols-2 gap-2 text-xs border-t border-app">
                                <div>
                                    <span class="text-muted">Purchase Price:</span>
                                    <p class="font-semibold text-main">
                                        PKR {{ Number(detailProduct.purchase_price).toFixed(2) }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-muted">Selling Price:</span>
                                    <p class="font-semibold text-[#1E7B3A]">
                                        PKR {{ Number(detailProduct.selling_price).toFixed(2) }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-muted">Minimum Stock:</span>
                                    <p class="font-semibold text-main">
                                        {{ detailProduct.minimum_stock }} {{ detailProduct.unit }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="detailProduct.description" class="pt-2 text-xs text-muted">
                                {{ detailProduct.description }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Archive Dialog -->
        <ConfirmDialog
            :is-open="showArchiveDialog"
            title="Archive Product"
            :message="`Are you sure you want to archive '${targetProduct?.name}'?`"
            confirm-text="Archive"
            @confirm="executeArchive"
            @cancel="showArchiveDialog = false"
        />
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
import ImageUploader from '../../components/ImageUploader.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const authStore = useAuthStore();
const toastStore = useToastStore();

const canCreate = computed(() => authStore.hasPermission('products.create'));
const canUpdate = computed(() => authStore.hasPermission('products.update'));
const canArchive = computed(() => authStore.hasPermission('products.archive'));

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const error = ref(null);
const search = ref('');
const categoryFilter = ref('');
const statusFilter = ref('');
const sortBy = ref('created_at');
const sortDirection = ref('desc');

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const columns = [
    { key: 'product', label: 'Product & SKU', sortable: true },
    { key: 'category', label: 'Category' },
    { key: 'pricing', label: 'Pricing', sortable: true },
    { key: 'stock_info', label: 'Threshold' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Actions', cellClass: 'text-right' },
];

const showModal = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const formErrors = ref({});
const targetProduct = ref(null);
const detailProduct = ref(null);
const showArchiveDialog = ref(false);

const selectedImageFile = ref(null);
const imagePreviewUrl = ref(null);

const form = reactive({
    id: null,
    sku: '',
    name: '',
    description: '',
    category_id: '',
    brand: '',
    unit: 'pcs',
    barcode: '',
    purchase_price: 0,
    selling_price: 0,
    minimum_stock: 0,
    status: 'active',
});

const activeCategories = computed(() => {
    return categories.value.filter((c) => c.status === 'active');
});

const fetchCategories = async () => {
    try {
        const res = await api.get('/api/categories', { params: { per_page: 100 } });
        categories.value = res.data.data;
    } catch (err) {
        // silent
    }
};

const fetchProducts = async (page = 1) => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/api/products', {
            params: {
                page,
                search: search.value,
                category_id: categoryFilter.value,
                status: statusFilter.value,
                sort_by: sortBy.value,
                sort_direction: sortDirection.value,
            },
        });
        products.value = res.data.data;
        Object.assign(pagination, res.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load products.';
    } finally {
        loading.value = false;
    }
};

const handleSort = (key) => {
    const map = { product: 'name', pricing: 'selling_price' };
    const target = map[key] || key;
    if (sortBy.value === target) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = target;
        sortDirection.value = 'asc';
    }
    fetchProducts(1);
};

const openCreateModal = () => {
    isEditing.value = false;
    form.id = null;
    form.sku = '';
    form.name = '';
    form.description = '';
    form.category_id = activeCategories.value[0]?.id || '';
    form.brand = '';
    form.unit = 'pcs';
    form.barcode = '';
    form.purchase_price = 0;
    form.selling_price = 0;
    form.minimum_stock = 0;
    form.status = 'active';
    selectedImageFile.value = null;
    imagePreviewUrl.value = null;
    formErrors.value = {};
    showModal.value = true;
};

const openEditModal = (p) => {
    isEditing.value = true;
    form.id = p.id;
    form.sku = p.sku;
    form.name = p.name;
    form.description = p.description || '';
    form.category_id = p.category_id;
    form.brand = p.brand || '';
    form.unit = p.unit || 'pcs';
    form.barcode = p.barcode || '';
    form.purchase_price = p.purchase_price;
    form.selling_price = p.selling_price;
    form.minimum_stock = p.minimum_stock;
    form.status = p.status;
    selectedImageFile.value = null;
    imagePreviewUrl.value = p.image_url;
    formErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    formErrors.value = {};
};

const viewDetails = (p) => {
    detailProduct.value = p;
};

const saveProduct = async () => {
    saving.value = true;
    formErrors.value = {};

    const formData = new FormData();
    formData.append('sku', form.sku);
    formData.append('name', form.name);
    if (form.description) formData.append('description', form.description);
    formData.append('category_id', form.category_id);
    if (form.brand) formData.append('brand', form.brand);
    formData.append('unit', form.unit);
    if (form.barcode) formData.append('barcode', form.barcode);
    formData.append('purchase_price', form.purchase_price);
    formData.append('selling_price', form.selling_price);
    formData.append('minimum_stock', form.minimum_stock);
    formData.append('status', form.status);

    if (selectedImageFile.value) {
        formData.append('image', selectedImageFile.value);
    }

    try {
        if (isEditing.value) {
            await api.post(`/api/products/${form.id}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toastStore.success('Product updated successfully.');
        } else {
            await api.post('/api/products', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toastStore.success('Product created successfully.');
        }
        closeModal();
        fetchProducts(pagination.current_page);
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            toastStore.error(err.response?.data?.message || 'Failed to save product.');
        }
    } finally {
        saving.value = false;
    }
};

const confirmArchive = (p) => {
    targetProduct.value = p;
    showArchiveDialog.value = true;
};

const executeArchive = async () => {
    showArchiveDialog.value = false;
    try {
        await api.delete(`/api/products/${targetProduct.value.id}`);
        toastStore.success('Product archived successfully.');
        fetchProducts(pagination.current_page);
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to archive product.');
    }
};

onMounted(() => {
    fetchCategories();
    fetchProducts(1);
});
</script>

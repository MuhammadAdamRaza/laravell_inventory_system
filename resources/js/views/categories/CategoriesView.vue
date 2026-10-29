<template>
    <div>
        <PageHeader title="Categories" subtitle="Manage product classification categories">
            <template #actions>
                <button
                    v-if="canManage"
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
                    Add Category
                </button>
            </template>
        </PageHeader>

        <!-- Filters Bar -->
        <div
            class="filter-bar mb-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between"
        >
            <SearchInput
                v-model="search"
                placeholder="Search by name or description..."
                @search="fetchCategories(1)"
            />

            <div class="flex items-center space-x-3">
                <select
                    v-model="statusFilter"
                    class="px-3 py-2 bg-white border border-app rounded-md text-sm text-main min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchCategories(1)"
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
            :rows="categories"
            :loading="loading"
            :error="error"
            :sort-key="sortBy"
            :sort-order="sortDirection"
            @sort="handleSort"
            @retry="fetchCategories(pagination.current_page)"
        >
            <template #status="{ row }">
                <StatusBadge :status="row.status" />
            </template>

            <template #actions="{ row }">
                <div v-if="canManage" class="flex items-center space-x-2">
                    <button
                        type="button"
                        class="text-primary-app hover:text-[#174A8A] font-medium text-xs py-1 px-2 rounded hover:bg-[#EAF2FB] min-h-[32px]"
                        @click="openEditModal(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="row.status !== 'archived'"
                        type="button"
                        class="text-[#B42318] hover:text-[#912018] font-medium text-xs py-1 px-2 rounded hover:bg-[#FDE8E8] min-h-[32px]"
                        @click="confirmArchive(row)"
                    >
                        Archive
                    </button>
                </div>
                <span v-else class="text-xs text-muted">-</span>
            </template>
        </DataTable>

        <Pagination :meta="pagination" @page-change="fetchCategories" />

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
                    class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full p-6 space-y-4"
                >
                    <div class="flex justify-between items-center border-b border-app pb-3">
                        <h3 class="text-lg font-semibold text-main">
                            {{ isEditing ? 'Edit Category' : 'Create Category' }}
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

                    <form class="space-y-4" @submit.prevent="saveCategory">
                        <FormField
                            label="Category Name"
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
                                    placeholder="e.g. Peripherals"
                                />
                            </template>
                        </FormField>

                        <FormField
                            label="Description"
                            id="description"
                            :error="formErrors.description"
                        >
                            <template #default="{ inputClass }">
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    :class="inputClass"
                                    placeholder="Optional category description..."
                                ></textarea>
                            </template>
                        </FormField>

                        <FormField label="Status" id="status" required :error="formErrors.status">
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
                                          ? 'Update Category'
                                          : 'Create Category'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Confirm Archive Dialog -->
        <ConfirmDialog
            :is-open="showArchiveDialog"
            title="Archive Category"
            :message="`Are you sure you want to archive '${targetCategory?.name}'? Archived categories cannot be assigned to new products.`"
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
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const authStore = useAuthStore();
const toastStore = useToastStore();

const canManage = computed(() => authStore.hasPermission('categories.manage'));

const categories = ref([]);
const loading = ref(false);
const error = ref(null);
const search = ref('');
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
    { key: 'name', label: 'Name', sortable: true },
    { key: 'description', label: 'Description' },
    { key: 'products_count', label: 'Products' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Actions', cellClass: 'text-right' },
];

const showModal = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const formErrors = ref({});
const targetCategory = ref(null);
const showArchiveDialog = ref(false);

const form = reactive({
    id: null,
    name: '',
    description: '',
    status: 'active',
});

const fetchCategories = async (page = 1) => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/api/categories', {
            params: {
                page,
                search: search.value,
                status: statusFilter.value,
                sort_by: sortBy.value,
                sort_direction: sortDirection.value,
            },
        });
        categories.value = res.data.data;
        Object.assign(pagination, res.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load categories.';
    } finally {
        loading.value = false;
    }
};

const handleSort = (key) => {
    if (sortBy.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortDirection.value = 'asc';
    }
    fetchCategories(1);
};

const openCreateModal = () => {
    isEditing.value = false;
    form.id = null;
    form.name = '';
    form.description = '';
    form.status = 'active';
    formErrors.value = {};
    showModal.value = true;
};

const openEditModal = (cat) => {
    isEditing.value = true;
    form.id = cat.id;
    form.name = cat.name;
    form.description = cat.description || '';
    form.status = cat.status;
    formErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    formErrors.value = {};
};

const saveCategory = async () => {
    saving.value = true;
    formErrors.value = {};
    try {
        if (isEditing.value) {
            await api.put(`/api/categories/${form.id}`, form);
            toastStore.success('Category updated successfully.');
        } else {
            await api.post('/api/categories', form);
            toastStore.success('Category created successfully.');
        }
        closeModal();
        fetchCategories(pagination.current_page);
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            toastStore.error(err.response?.data?.message || 'Failed to save category.');
        }
    } finally {
        saving.value = false;
    }
};

const confirmArchive = (cat) => {
    targetCategory.value = cat;
    showArchiveDialog.value = true;
};

const executeArchive = async () => {
    showArchiveDialog.value = false;
    try {
        await api.delete(`/api/categories/${targetCategory.value.id}`);
        toastStore.success('Category archived successfully.');
        fetchCategories(pagination.current_page);
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to archive category.');
    }
};

onMounted(() => {
    fetchCategories(1);
});
</script>

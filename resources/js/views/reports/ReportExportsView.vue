<template>
    <section class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#1E5AA8]">Reports</p>
                <h1 class="mt-1 text-2xl font-semibold text-[#1F2933]">Report exports</h1>
                <p class="mt-1 text-sm text-[#637083]">Large CSV files are generated in the background and kept private to your account.</p>
            </div>
            <button type="button" class="min-h-10 rounded-md border border-[#DDE3EA] bg-white px-4 text-sm font-semibold text-[#405064] hover:bg-[#F7F9FC]" :disabled="loading" @click="loadExports">Refresh status</button>
        </header>

        <p v-if="error" class="rounded-md border border-[#F2C7C4] bg-[#FEF3F2] p-3 text-sm text-[#912018]">{{ error }}</p>
        <div v-if="loading" class="rounded-lg border border-[#DDE3EA] bg-white p-8 text-center text-sm text-[#637083]">Loading exports…</div>
        <div v-else-if="exports.length === 0" class="rounded-lg border border-dashed border-[#C9D3E0] bg-white px-5 py-12 text-center text-sm text-[#637083]">No queued exports yet. Reports above 5,000 rows appear here.</div>
        <div v-else class="overflow-hidden rounded-lg border border-[#DDE3EA] bg-white">
            <div v-for="item in exports" :key="item.id" class="flex flex-col gap-3 border-b border-[#E7EBF0] p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold capitalize text-[#1F2933]">{{ item.report.replaceAll('-', ' ') }}</h2>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(item.status)">{{ item.status }}</span>
                    </div>
                    <p class="mt-1 break-all text-sm text-[#637083]">{{ item.file_name || item.id }}</p>
                    <p class="mt-1 text-xs text-[#7A8797]">Requested {{ formatDate(item.created_at) }}</p>
                </div>
                <button v-if="item.status === 'ready'" type="button" class="min-h-10 shrink-0 rounded-md bg-[#14213D] px-4 text-sm font-semibold text-white hover:bg-[#1E3A66] disabled:opacity-50" :disabled="downloading === item.id" @click="downloadExport(item)">{{ downloading === item.id ? 'Preparing…' : 'Download CSV' }}</button>
                <span v-else-if="item.status === 'failed'" class="text-sm font-medium text-[#B42318]">Export failed. Request it again from the report.</span>
                <span v-else class="text-sm text-[#637083]">This page updates when you refresh.</span>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/axios';

const exports = ref([]);
const loading = ref(false);
const downloading = ref(null);
const error = ref('');

const loadExports = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await api.get('/api/report-exports');
        exports.value = response.data.data || [];
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load report exports.';
    } finally {
        loading.value = false;
    }
};

const downloadExport = async (item) => {
    downloading.value = item.id;
    error.value = '';

    try {
        const response = await api.get(`/api/report-exports/${item.id}/download`, { responseType: 'blob' });
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = item.file_name || `${item.report}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (exception) {
        error.value = 'Could not download this export.';
    } finally {
        downloading.value = null;
    }
};

const statusClass = (status) => ({
    ready: 'bg-[#ECFDF3] text-[#027A48]',
    failed: 'bg-[#FEF3F2] text-[#B42318]',
    processing: 'bg-[#FFFAEB] text-[#B54708]',
    queued: 'bg-[#EEF5FC] text-[#1E5AA8]',
}[status] || 'bg-[#F2F4F7] text-[#475467]');

const formatDate = (value) => new Date(value).toLocaleString();
onMounted(() => loadExports());
</script>

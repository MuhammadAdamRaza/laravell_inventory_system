<template>
    <section class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-app">
                    System administration
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-main">Failed jobs</h1>
                <p class="mt-1 text-sm text-muted">
                    Review failed queue jobs and retry them safely.
                </p>
            </div>
            <button
                type="button"
                class="min-h-10 rounded-md border border-app bg-white px-4 text-sm font-semibold text-[#405064] bg-hover-surface"
                @click="loadJobs"
            >
                Refresh
            </button>
        </header>

        <p
            v-if="message"
            class="rounded-md border border-[#B8E3C4] bg-[#F0FDF4] p-3 text-sm text-[#166534]"
        >
            {{ message }}
        </p>
        <p
            v-if="error"
            class="rounded-md border border-[#F2C7C4] bg-[#FEF3F2] p-3 text-sm text-[#912018]"
        >
            {{ error }}
        </p>
        <div
            v-if="loading"
            class="surface-card rounded-lg border border-app bg-white p-8 text-center text-sm text-muted"
        >
            Loading failed jobs…
        </div>
        <div
            v-else-if="jobs.length === 0"
            class="surface-card rounded-lg border border-dashed border-[#C9D3E0] bg-white px-5 py-12 text-center text-sm text-muted"
        >
            No failed jobs recorded.
        </div>
        <div v-else class="space-y-3">
            <article
                v-for="job in jobs"
                :key="job.uuid"
                class="surface-card rounded-lg border border-app bg-white p-4 sm:p-5"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-[#FEF3F2] px-2.5 py-1 text-xs font-semibold text-[#B42318]"
                                >{{ job.queue }}</span
                            >
                            <span class="text-xs text-muted"
                                >{{ job.connection }} · {{ formatDate(job.failed_at) }}</span
                            >
                        </div>
                        <p class="mt-2 break-all font-mono text-xs text-muted">{{ job.uuid }}</p>
                        <p class="mt-2 break-words text-sm text-main">{{ job.summary }}</p>
                    </div>
                    <button
                        type="button"
                        class="min-h-10 shrink-0 rounded-md bg-sidebar px-4 text-sm font-semibold text-white hover:bg-sidebar-active disabled:opacity-50"
                        :disabled="retrying === job.uuid"
                        @click="retryJob(job.uuid)"
                    >
                        {{ retrying === job.uuid ? 'Retrying…' : 'Retry job' }}
                    </button>
                </div>
            </article>
        </div>
        <footer
            v-if="meta.last_page > 1"
            class="flex items-center justify-between text-sm text-muted"
        >
            <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <button
                    class="min-h-10 rounded-md border border-app px-3 disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="loadJobs(meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    class="min-h-10 rounded-md border border-app px-3 disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="loadJobs(meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </footer>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/axios';

const jobs = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const loading = ref(false);
const retrying = ref(null);
const error = ref('');
const message = ref('');

const loadJobs = async (page = 1) => {
    loading.value = true;
    error.value = '';

    try {
        const response = await api.get('/api/failed-jobs', { params: { page } });
        jobs.value = response.data.data || [];
        meta.value = response.data.meta || { current_page: 1, last_page: 1 };
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load failed jobs.';
    } finally {
        loading.value = false;
    }
};

const retryJob = async (uuid) => {
    retrying.value = uuid;
    error.value = '';
    message.value = '';

    try {
        const response = await api.post(`/api/failed-jobs/${uuid}/retry`);
        message.value = response.data.message || 'Job queued for retry.';
        await loadJobs(meta.value.current_page);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not retry this job.';
    } finally {
        retrying.value = null;
    }
};

const formatDate = (value) => new Date(value).toLocaleString();
onMounted(() => loadJobs());
</script>

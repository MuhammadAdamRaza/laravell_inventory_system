<template>
    <section class="space-y-5">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-app">
                    Your account
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-main">Notifications</h1>
                <p class="mt-1 text-sm text-muted">Updates for your account and assigned work.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="min-h-10 rounded-md border px-3 text-sm font-medium"
                    :class="
                        unreadOnly
                            ? 'border-[#1E5AA8] bg-[#EAF2FB] text-[#164A88]'
                            : 'border-app bg-white text-[#405064]'
                    "
                    @click="setUnreadOnly(true)"
                >
                    Unread
                </button>
                <button
                    type="button"
                    class="min-h-10 rounded-md border px-3 text-sm font-medium"
                    :class="
                        !unreadOnly
                            ? 'border-[#1E5AA8] bg-[#EAF2FB] text-[#164A88]'
                            : 'border-app bg-white text-[#405064]'
                    "
                    @click="setUnreadOnly(false)"
                >
                    All notifications
                </button>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="min-h-10 rounded-md bg-sidebar px-3 text-sm font-semibold text-white hover:bg-sidebar-active"
                    @click="markAllRead"
                >
                    Mark all read
                </button>
            </div>
        </header>

        <div
            v-if="error"
            class="rounded-md border border-[#F2C7C4] bg-[#FEF3F2] p-4 text-sm text-[#912018]"
        >
            {{ error }}
        </div>
        <div
            v-if="loading"
            class="surface-card rounded-lg border border-app bg-white p-8 text-center text-sm text-muted"
        >
            Loading notifications…
        </div>
        <div
            v-else-if="notifications.length === 0"
            class="surface-card rounded-lg border border-dashed border-[#C9D3E0] bg-white px-5 py-12 text-center"
        >
            <h2 class="font-semibold text-main">No notifications here</h2>
            <p class="mt-1 text-sm text-muted">
                New stock, order, and report updates will appear here.
            </p>
        </div>
        <div
            v-else
            class="surface-card divide-y divide-[#E7EBF0] overflow-hidden rounded-lg border border-app bg-white"
        >
            <article
                v-for="notification in notifications"
                :key="notification.id"
                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5"
                :class="notification.read_at ? '' : 'bg-[#EAF2FB]'"
            >
                <div class="flex min-w-0 gap-3">
                    <span
                        class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                        :class="
                            notification.read_at
                                ? 'bg-[#C9D3E0]'
                                : notification.data?.kind === 'out_of_stock'
                                  ? 'bg-[#B42318]'
                                  : 'bg-primary-app'
                        "
                        aria-hidden="true"
                    ></span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-main">
                            {{ notification.data?.title || notification.type }}
                        </h2>
                        <p class="mt-1 break-words text-sm leading-6 text-[#526174]">
                            {{ notification.data?.message || 'You have a new update.' }}
                        </p>
                        <p class="mt-2 text-xs text-[#7A8797]">
                            {{ formatDate(notification.created_at) }}
                        </p>
                    </div>
                </div>
                <button
                    v-if="notification.data?.url"
                    type="button"
                    class="min-h-10 shrink-0 rounded-md border border-app px-3 text-sm font-semibold text-primary-app bg-hover-surface"
                    @click="openNotification(notification)"
                >
                    Open update
                </button>
                <button
                    v-else-if="!notification.read_at"
                    type="button"
                    class="min-h-10 shrink-0 rounded-md border border-app px-3 text-sm font-semibold text-[#405064] bg-hover-surface"
                    @click="markRead(notification)"
                >
                    Mark read
                </button>
            </article>
        </div>

        <footer v-if="meta.last_page > 1" class="flex items-center justify-between gap-3">
            <span class="text-sm text-muted"
                >Page {{ meta.current_page }} of {{ meta.last_page }}</span
            >
            <div class="flex gap-2">
                <button
                    type="button"
                    class="min-h-10 rounded-md border border-app px-3 text-sm disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="loadPage(meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="min-h-10 rounded-md border border-app px-3 text-sm disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="loadPage(meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </footer>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../api/axios';
import { useNotificationsStore } from '../../stores/notifications';

const router = useRouter();
const notificationStore = useNotificationsStore();
const notifications = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const unreadOnly = ref(false);
const loading = ref(false);
const error = ref('');
const unreadCount = ref(notificationStore.unreadCount);

const fetchPage = async (page = 1) => {
    loading.value = true;
    error.value = '';

    try {
        const response = await api.get('/api/notifications', {
            params: { page, unread: unreadOnly.value ? 1 : 0 },
        });
        notifications.value = response.data.data || [];
        meta.value = response.data.meta || { current_page: 1, last_page: 1 };
        await notificationStore.refreshUnreadCount();
        unreadCount.value = notificationStore.unreadCount;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load notifications.';
    } finally {
        loading.value = false;
    }
};

const loadPage = (page) => fetchPage(page);
const setUnreadOnly = (value) => {
    unreadOnly.value = value;
    fetchPage(1);
};

const markRead = async (notification) => {
    await api.patch(`/api/notifications/${notification.id}/read`);
    notification.read_at = new Date().toISOString();
    await notificationStore.refreshUnreadCount();
    unreadCount.value = notificationStore.unreadCount;
};

const openNotification = async (notification) => {
    if (!notification.read_at) await markRead(notification);
    if (typeof notification.data?.url === 'string' && notification.data.url.startsWith('/')) {
        router.push(notification.data.url);
    }
};

const markAllRead = async () => {
    await api.post('/api/notifications/read-all');
    await fetchPage(meta.value.current_page);
};

const formatDate = (value) => new Date(value).toLocaleString();
onMounted(() => fetchPage());
</script>

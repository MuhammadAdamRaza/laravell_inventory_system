<template>
    <header
        class="h-16 bg-white border-b border-[#DDE3EA] flex items-center justify-between px-4 sm:px-6 lg:px-8 flex-shrink-0 z-10"
    >
        <div class="flex items-center space-x-3">
            <!-- Hamburger button for mobile / tablet (< 1024px) -->
            <button
                type="button"
                class="lg:hidden p-2 rounded-md text-[#637083] hover:text-[#1F2933] hover:bg-[#F5F7FA] min-h-[44px] min-w-[44px] flex items-center justify-center"
                @click="$emit('toggle-sidebar')"
                aria-label="Toggle navigation"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
            </button>

            <span class="text-sm font-medium text-[#637083] hidden sm:inline-block">
                Inventory Management System
            </span>
        </div>

        <!-- Right Controls: User info & Logout -->
        <div class="flex items-center gap-2 sm:gap-4">
            <div class="min-w-0 max-w-28 text-right">
                <span class="block truncate text-xs font-semibold text-[#1F2933] sm:text-sm">
                    {{ authStore.roleLabel }}
                </span>
            </div>

            <div class="relative">
                <button
                    type="button"
                    class="relative inline-flex h-11 w-11 items-center justify-center rounded-md border border-[#DDE3EA] bg-white text-[#405064] transition hover:bg-[#F7F9FC] focus:outline-none focus:ring-2 focus:ring-[#1E5AA8]"
                    aria-label="Notifications"
                    :aria-expanded="notificationsOpen"
                    @click="toggleNotifications"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.66V5a2 2 0 00-4 0v.34A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span
                        v-if="notificationStore.unreadCount > 0"
                        class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#B42318] px-1 text-[10px] font-bold leading-none text-white"
                    >
                        {{ notificationStore.unreadCount > 99 ? '99+' : notificationStore.unreadCount }}
                    </span>
                </button>

                <section
                    v-if="notificationsOpen"
                    class="fixed inset-x-2 top-[4.5rem] z-50 max-h-[calc(100dvh-5rem)] overflow-hidden rounded-lg border border-[#DDE3EA] bg-white shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[24rem]"
                    aria-label="Recent notifications"
                >
                    <div class="flex items-center justify-between border-b border-[#E7EBF0] px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-[#1F2933]">Notifications</h2>
                            <p class="text-xs text-[#637083]">{{ notificationStore.unreadCount }} unread</p>
                        </div>
                        <button
                            v-if="notificationStore.unreadCount > 0"
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold text-[#1E5AA8] hover:bg-[#EEF5FC]"
                            @click="markAllNotificationsRead"
                        >
                            Mark all read
                        </button>
                    </div>
                    <div class="max-h-[min(65dvh,28rem)] overflow-y-auto">
                        <p v-if="notificationStore.loading" class="px-4 py-8 text-center text-sm text-[#637083]">Loading notifications…</p>
                        <p v-else-if="notificationStore.latest.length === 0" class="px-4 py-8 text-center text-sm text-[#637083]">You’re all caught up.</p>
                        <button
                            v-for="notification in notificationStore.latest"
                            :key="notification.id"
                            type="button"
                            class="block w-full border-b border-[#EEF1F4] px-4 py-3 text-left transition hover:bg-[#F7F9FC]"
                            :class="notification.read_at ? 'bg-white' : 'bg-[#EAF2FB]'"
                            @click="openNotification(notification)"
                        >
                            <span class="flex items-start gap-2">
                                <span v-if="!notification.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#1E5AA8]" aria-hidden="true"></span>
                                <span v-else class="mt-1.5 h-2 w-2 shrink-0"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-[#1F2933]">{{ notification.data?.title || notification.type }}</span>
                                    <span class="mt-0.5 block text-sm leading-5 text-[#526174]">{{ notification.data?.message || 'You have a new update.' }}</span>
                                    <span class="mt-1 block text-xs text-[#7A8797]">{{ formatNotificationDate(notification.created_at) }}</span>
                                </span>
                            </span>
                        </button>
                    </div>
                    <div class="border-t border-[#E7EBF0] p-2">
                        <button type="button" class="w-full rounded-md px-3 py-2 text-sm font-semibold text-[#1E5AA8] hover:bg-[#EEF5FC]" @click="showAllNotifications">
                            View all notifications
                        </button>
                    </div>
                </section>
            </div>

            <button
                type="button"
                class="inline-flex items-center px-3 py-2 border border-[#DDE3EA] rounded-md text-sm font-medium text-[#1F2933] bg-white hover:bg-[#F7F9FC] min-h-[44px] transition-colors"
                @click="handleLogout"
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
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                    />
                </svg>
                <span>Logout</span>
            </button>
        </div>
    </header>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useNotificationsStore } from '../stores/notifications';
import { useToastStore } from '../stores/toast';

defineEmits(['toggle-sidebar']);

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationsStore();
const toastStore = useToastStore();
const notificationsOpen = ref(false);
let pollTimer = null;

const stopPolling = () => {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
};

const startPolling = () => {
    if (pollTimer !== null || document.hidden) return;

    notificationStore.refreshUnreadCount().catch(() => {});
    pollTimer = window.setInterval(() => {
        if (!document.hidden) notificationStore.refreshUnreadCount().catch(() => {});
    }, 60000);
};

const handleVisibilityChange = () => {
    if (document.hidden) stopPolling();
    else startPolling();
};

const toggleNotifications = async () => {
    notificationsOpen.value = !notificationsOpen.value;

    if (notificationsOpen.value) {
        try {
            await notificationStore.fetchLatest();
        } catch (error) {
            toastStore.error('Could not load notifications.');
        }
    }
};

const openNotification = async (notification) => {
    try {
        if (!notification.read_at) await notificationStore.markRead(notification.id);
        notificationsOpen.value = false;

        if (typeof notification.data?.url === 'string' && notification.data.url.startsWith('/')) {
            router.push(notification.data.url);
        }
    } catch (error) {
        toastStore.error('Could not open this notification.');
    }
};

const markAllNotificationsRead = async () => {
    try {
        await notificationStore.markAllRead();
    } catch (error) {
        toastStore.error('Could not update notifications.');
    }
};

const showAllNotifications = () => {
    notificationsOpen.value = false;
    router.push({ name: 'notifications' });
};

const formatNotificationDate = (value) => new Date(value).toLocaleString([], {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
});

onMounted(() => {
    startPolling();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

const handleLogout = async () => {
    try {
        await authStore.logout();
        toastStore.info('You have been logged out.');
        router.push({ name: 'login' });
    } catch (err) {
        router.push({ name: 'login' });
    }
};
</script>

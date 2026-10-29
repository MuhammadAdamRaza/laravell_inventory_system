<template>
    <header
        class="app-topbar sticky top-0 z-30 flex h-20 min-w-0 items-center justify-between gap-3 border-b border-app px-4 sm:px-7 xl:px-9"
    >
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="mobile-navigation-toggle icon-button lg:hidden"
                aria-label="Toggle navigation"
                aria-controls="main-navigation"
                :aria-expanded="sidebarOpen"
                @click="emit('toggle-sidebar')"
            >
                <AppIcon name="menu" class="h-5 w-5" />
            </button>
            <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-xs">
                <span class="hidden text-muted md:inline">{{ active?.group || 'Workspace' }}</span
                ><AppIcon name="chevron" class="hidden h-3.5 w-3.5 text-slate-300 md:block" /><span
                    class="truncate font-semibold text-main"
                    >{{ active?.label || 'Workspace' }}</span
                >
            </nav>
        </div>
        <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
            <button
                type="button"
                class="workspace-search"
                aria-label="Search workspace pages"
                aria-haspopup="dialog"
                @click="openSearch"
            >
                <AppIcon name="search" class="h-[18px] w-[18px]" /><span class="hidden lg:inline"
                    >Search pages...</span
                ><kbd
                    class="ml-auto hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] lg:inline"
                    >Ctrl K</kbd
                >
            </button>
            <div ref="notificationArea" class="relative">
                <button
                    ref="notificationButton"
                    type="button"
                    class="icon-button relative"
                    aria-label="Notifications"
                    aria-controls="notification-panel"
                    :aria-expanded="notificationsOpen"
                    @click="toggleNotifications"
                >
                    <AppIcon name="bell" class="h-5 w-5" /><span
                        v-if="notificationStore.unreadCount > 0"
                        class="notification-count"
                        >{{
                            notificationStore.unreadCount > 99
                                ? '99+'
                                : notificationStore.unreadCount
                        }}</span
                    >
                </button>
                <Transition name="popover"
                    ><section
                        v-if="notificationsOpen"
                        id="notification-panel"
                        class="fixed inset-x-3 top-[5.5rem] z-50 overflow-hidden rounded-2xl border border-app bg-white shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-3 sm:w-96"
                        aria-label="Recent notifications"
                        @keydown.esc.stop="closeNotifications"
                    >
                        <div
                            class="flex items-center justify-between border-b border-app px-5 py-4"
                        >
                            <div>
                                <h2 class="text-sm font-semibold text-main">Notifications</h2>
                                <p class="mt-1 text-xs text-muted">
                                    {{ notificationStore.unreadCount }} unread updates
                                </p>
                            </div>
                            <button
                                v-if="notificationStore.unreadCount > 0"
                                type="button"
                                class="text-xs font-semibold text-primary-app"
                                @click="markAllNotificationsRead"
                            >
                                Mark all read
                            </button>
                        </div>
                        <div class="max-h-[min(60dvh,28rem)] overflow-y-auto">
                            <p
                                v-if="notificationStore.loading"
                                class="px-5 py-12 text-center text-sm text-muted"
                            >
                                Loading updates...
                            </p>
                            <div
                                v-else-if="notificationError"
                                class="p-6 text-center text-sm text-muted"
                            >
                                <p>{{ notificationError }}</p>
                                <button
                                    type="button"
                                    class="mt-3 font-semibold text-primary-app"
                                    @click="fetchNotifications"
                                >
                                    Try again
                                </button>
                            </div>
                            <div
                                v-else-if="!notificationStore.latest.length"
                                class="px-5 py-10 text-center"
                            >
                                <span
                                    class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-primary-app"
                                    ><AppIcon name="check" class="h-6 w-6"
                                /></span>
                                <p class="text-sm font-semibold text-main">You're all caught up</p>
                                <p class="mt-1 text-xs text-muted">
                                    Your next update will appear here.
                                </p>
                            </div>
                            <button
                                v-for="notification in notificationStore.latest"
                                :key="notification.id"
                                type="button"
                                class="flex w-full gap-3 border-b border-slate-100 px-5 py-4 text-left hover:bg-slate-50"
                                :class="{ 'bg-blue-50/50': !notification.read_at }"
                                @click="openNotification(notification)"
                            >
                                <span
                                    class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                                    :class="notification.read_at ? 'bg-slate-300' : 'bg-blue-600'"
                                ></span
                                ><span class="min-w-0"
                                    ><span class="block text-sm font-semibold text-main">{{
                                        notification.data?.title || 'Notification'
                                    }}</span
                                    ><span class="mt-1 block text-xs leading-relaxed text-muted">{{
                                        notification.data?.message || ''
                                    }}</span
                                    ><span class="mt-2 block text-[11px] text-muted">{{
                                        formatNotificationDate(notification.created_at)
                                    }}</span></span
                                >
                            </button>
                        </div>
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-center gap-2 bg-slate-50 px-4 text-xs font-semibold text-primary-app"
                            @click="showAllNotifications"
                        >
                            View all notifications<AppIcon name="arrow" class="h-4 w-4" />
                        </button></section
                ></Transition>
            </div>
            <div class="hidden items-center gap-2.5 border-l border-app pl-3 sm:flex">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-blue-100 bg-blue-50 text-xs font-bold text-primary-app"
                    >{{ initials }}</span
                >
                <div class="hidden max-w-32 xl:block">
                    <p class="truncate text-xs font-semibold text-main">
                        {{ authStore.roleLabel }}
                    </p>
                    <p class="mt-0.5 text-[10px] text-muted">Workspace member</p>
                </div>
            </div>
            <button
                type="button"
                class="icon-button"
                aria-label="Logout"
                title="Logout"
                @click="handleLogout"
            >
                <AppIcon name="logout" class="h-[18px] w-[18px]" />
            </button>
        </div>
    </header>
    <NavigationSearch :is-open="searchOpen" @close="searchOpen = false" />
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useNotificationsStore } from '../stores/notifications';
import { useToastStore } from '../stores/toast';
import AppIcon from './AppIcon.vue';
import NavigationSearch from './NavigationSearch.vue';
import { getActiveNavigation, getNavigationGroups } from './navigation';
defineProps({ sidebarOpen: { type: Boolean, default: false } });
const emit = defineEmits(['toggle-sidebar']);
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const notificationStore = useNotificationsStore();
const toastStore = useToastStore();
const notificationsOpen = ref(false);
const notificationError = ref('');
const notificationArea = ref(null);
const notificationButton = ref(null);
const searchOpen = ref(false);
const active = computed(() => getActiveNavigation(route.path, getNavigationGroups(authStore)));
const initials = computed(() =>
    authStore.roleLabel
        .split(' ')
        .map((word) => word[0])
        .slice(0, 2)
        .join(''),
);
let pollTimer = null;
function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}
function startPolling() {
    if (pollTimer !== null || document.hidden) return;
    notificationStore.refreshUnreadCount().catch(() => {});
    pollTimer = window.setInterval(() => {
        if (!document.hidden) notificationStore.refreshUnreadCount().catch(() => {});
    }, 60000);
}
const handleVisibilityChange = () => (document.hidden ? stopPolling() : startPolling());
async function fetchNotifications() {
    notificationError.value = '';
    try {
        await notificationStore.fetchLatest();
    } catch {
        notificationError.value = 'Updates could not be loaded.';
    }
}
function toggleNotifications() {
    notificationsOpen.value = !notificationsOpen.value;
    if (notificationsOpen.value) fetchNotifications();
}
function closeNotifications() {
    notificationsOpen.value = false;
    notificationButton.value?.focus();
}
async function openNotification(notification) {
    try {
        if (!notification.read_at) await notificationStore.markRead(notification.id);
        notificationsOpen.value = false;
        if (
            typeof notification.data?.url === 'string' &&
            notification.data.url.startsWith('/') &&
            !notification.data.url.startsWith('//')
        )
            router.push(notification.data.url);
    } catch {
        toastStore.error('Could not open this notification.');
    }
}
async function markAllNotificationsRead() {
    try {
        await notificationStore.markAllRead();
    } catch {
        toastStore.error('Could not update notifications.');
    }
}
function showAllNotifications() {
    notificationsOpen.value = false;
    router.push({ name: 'notifications' });
}
function openSearch() {
    notificationsOpen.value = false;
    searchOpen.value = true;
}
function handleShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        notificationsOpen.value = false;
        searchOpen.value = !searchOpen.value;
    }
    if (event.key === 'Escape' && notificationsOpen.value) closeNotifications();
}
function handleOutsideClick(event) {
    if (notificationsOpen.value && !notificationArea.value?.contains(event.target))
        notificationsOpen.value = false;
}
const formatNotificationDate = (value) =>
    new Date(value).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
watch(
    () => route.fullPath,
    () => {
        notificationsOpen.value = false;
        searchOpen.value = false;
    },
);
onMounted(() => {
    startPolling();
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('keydown', handleShortcut);
    document.addEventListener('click', handleOutsideClick);
});
onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    document.removeEventListener('keydown', handleShortcut);
    document.removeEventListener('click', handleOutsideClick);
});
async function handleLogout() {
    let message = 'You have been logged out.';
    try {
        await authStore.logout();
    } catch {
        message = 'Your session has ended.';
    }
    await router.push({ name: 'login' });
    toastStore.info(message);
}
</script>

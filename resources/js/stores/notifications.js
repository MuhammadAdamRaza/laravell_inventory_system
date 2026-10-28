import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/axios';

export const useNotificationsStore = defineStore('notifications', () => {
    const unreadCount = ref(0);
    const latest = ref([]);
    const loading = ref(false);

    const refreshUnreadCount = async () => {
        const response = await api.get('/api/notifications/unread-count');
        unreadCount.value = Number(response.data.count || 0);
    };

    const fetchLatest = async () => {
        loading.value = true;

        try {
            const response = await api.get('/api/notifications', { params: { page: 1 } });
            latest.value = (response.data.data || []).slice(0, 10);
        } finally {
            loading.value = false;
        }
    };

    const markRead = async (notificationId) => {
        await api.patch(`/api/notifications/${notificationId}/read`);
        const notification = latest.value.find((item) => item.id === notificationId);

        if (notification && !notification.read_at) {
            notification.read_at = new Date().toISOString();
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }
    };

    const markAllRead = async () => {
        await api.post('/api/notifications/read-all');
        const readAt = new Date().toISOString();
        latest.value.forEach((notification) => {
            notification.read_at = notification.read_at || readAt;
        });
        unreadCount.value = 0;
    };

    return { unreadCount, latest, loading, refreshUnreadCount, fetchLatest, markRead, markAllRead };
});

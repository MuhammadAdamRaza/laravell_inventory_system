<template>
    <router-view />
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const handleSessionExpired = () => {
    const currentRoute = router.currentRoute.value;
    authStore.clearSession();

    if (currentRoute.meta.requiresAuth) {
        router.replace({ name: 'login', query: { redirect: currentRoute.fullPath } });
    }
};

onMounted(() => window.addEventListener('inventory:session-expired', handleSessionExpired));
onUnmounted(() => window.removeEventListener('inventory:session-expired', handleSessionExpired));
</script>

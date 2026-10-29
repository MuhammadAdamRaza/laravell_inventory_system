<template>
    <div class="app-shell min-h-dvh bg-app-bg">
        <a href="#page-content" class="skip-link">Skip to content</a>
        <AppSidebar :is-open="sidebarOpen" @close="sidebarOpen = false" />
        <div class="flex min-h-dvh min-w-0 flex-col lg:pl-64" :inert="sidebarOpen || undefined">
            <AppTopbar :sidebar-open="sidebarOpen" @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <main
                id="page-content"
                tabindex="-1"
                class="app-content mx-auto w-full max-w-[1680px] flex-1 px-4 pb-10 pt-6 sm:px-7 sm:pt-8 xl:px-9"
            >
                <router-view />
            </main>
            <footer
                class="mx-4 flex flex-wrap items-center justify-between gap-2 border-t border-app py-4 text-[11px] text-muted sm:mx-7 xl:mx-9"
            >
                <span>Inventory Hub</span
                ><span>Inventory, purchasing &amp; warehouse management</span>
            </footer>
        </div>
        <ToastContainer />
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppSidebar from '../components/AppSidebar.vue';
import AppTopbar from '../components/AppTopbar.vue';
import ToastContainer from '../components/ToastContainer.vue';
const route = useRoute();
const sidebarOpen = ref(false);
let desktopMedia;
let previousOverflow = '';
watch(sidebarOpen, (open) => {
    if (open) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    } else document.body.style.overflow = previousOverflow;
});
watch(
    () => route.fullPath,
    () => {
        sidebarOpen.value = false;
    },
);
const handleResize = () => {
    if (desktopMedia.matches) sidebarOpen.value = false;
};
onMounted(() => {
    desktopMedia = window.matchMedia('(min-width: 1024px)');
    desktopMedia.addEventListener('change', handleResize);
});
onUnmounted(() => {
    desktopMedia?.removeEventListener('change', handleResize);
    if (sidebarOpen.value) document.body.style.overflow = previousOverflow;
});
</script>

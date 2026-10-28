<template>
    <div class="min-h-screen bg-[#F5F7FA] flex flex-col lg:flex-row overflow-x-hidden">
        <!-- Sidebar -->
        <AppSidebar :is-open="sidebarOpen" @close="sidebarOpen = false" />

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col lg:pl-64 min-w-0 w-full min-h-screen">
            <!-- Topbar -->
            <AppTopbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <!-- Page Content View -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <router-view />
            </main>
        </div>

        <!-- Global Toast Container -->
        <ToastContainer />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import AppSidebar from '../components/AppSidebar.vue';
import AppTopbar from '../components/AppTopbar.vue';
import ToastContainer from '../components/ToastContainer.vue';

const sidebarOpen = ref(false);
</script>

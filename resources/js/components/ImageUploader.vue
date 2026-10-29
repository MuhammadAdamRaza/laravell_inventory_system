<template>
    <div class="space-y-2">
        <div class="flex items-center space-x-4">
            <!-- Preview Image -->
            <div
                class="w-20 h-20 rounded-md border border-app overflow-hidden bg-app-bg flex items-center justify-center relative flex-shrink-0"
            >
                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    alt="Product preview"
                    class="w-full h-full object-cover"
                />
                <svg
                    v-else
                    class="w-8 h-8 text-muted"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </div>

            <!-- Controls -->
            <div class="flex-1 space-y-2">
                <div class="flex items-center space-x-2">
                    <label
                        class="cursor-pointer inline-flex items-center px-4 py-2 border border-app rounded-md shadow-sm text-sm font-medium text-main bg-white bg-hover-surface min-h-[44px]"
                    >
                        <span>Choose Image</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="handleFileChange"
                        />
                    </label>
                    <button
                        v-if="previewUrl"
                        type="button"
                        class="px-3 py-2 text-xs font-medium text-[#B42318] hover:bg-[#FDE8E8]/40 rounded-md min-h-[44px]"
                        @click="clearImage"
                    >
                        Remove
                    </button>
                </div>
                <p class="text-xs text-muted">JPG, PNG, or WEBP up to 2MB.</p>
            </div>
        </div>

        <!-- Client-side Error -->
        <p v-if="clientError" class="text-xs text-[#B42318]">
            {{ clientError }}
        </p>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    currentUrl: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['file-selected', 'file-removed']);

const previewUrl = ref(props.currentUrl || null);
const clientError = ref('');

watch(
    () => props.currentUrl,
    (newVal) => {
        if (
            !previewUrl.value ||
            previewUrl.value.startsWith('http') ||
            previewUrl.value.startsWith('/storage')
        ) {
            previewUrl.value = newVal;
        }
    },
);

const handleFileChange = (e) => {
    clientError.value = '';
    const file = e.target.files[0];
    if (!file) return;

    // Validate MIME type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        clientError.value = 'Only JPG, PNG, and WEBP image files are allowed.';
        e.target.value = '';
        return;
    }

    // Validate size (2MB = 2 * 1024 * 1024 bytes)
    if (file.size > 2 * 1024 * 1024) {
        clientError.value = 'Image size exceeds the 2 MB limit.';
        e.target.value = '';
        return;
    }

    previewUrl.value = URL.createObjectURL(file);
    emit('file-selected', file);
};

const clearImage = () => {
    previewUrl.value = null;
    clientError.value = '';
    emit('file-removed');
};
</script>

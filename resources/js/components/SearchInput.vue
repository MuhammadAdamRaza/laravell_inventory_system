<template>
    <div class="relative w-full max-w-sm">
        <AppIcon
            name="search"
            class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
        /><input
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            :aria-label="placeholder"
            class="min-h-11 w-full rounded-lg border border-app bg-white py-2.5 pl-10 pr-11 text-sm text-main outline-none focus:ring-2 focus:ring-blue-100"
            @input="onInput"
        /><button
            v-if="modelValue"
            type="button"
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted hover:text-main"
            aria-label="Clear search"
            @click="clear"
        >
            <AppIcon name="close" class="h-4 w-4" />
        </button>
    </div>
</template>
<script setup>
import { onUnmounted } from 'vue';
import AppIcon from './AppIcon.vue';
const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search...' },
    debounce: { type: Number, default: 300 },
});
const emit = defineEmits(['update:modelValue', 'search']);
let timer;
function onInput(event) {
    const value = event.target.value;
    clearTimeout(timer);
    timer = setTimeout(() => {
        emit('update:modelValue', value);
        emit('search', value);
    }, props.debounce);
}
function clear() {
    clearTimeout(timer);
    emit('update:modelValue', '');
    emit('search', '');
}
onUnmounted(() => clearTimeout(timer));
</script>

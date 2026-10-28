<template>
    <div class="relative w-full max-w-sm">
        <div
            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#637083]"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
        </div>
        <input
            type="text"
            :value="modelValue"
            :placeholder="placeholder"
            class="w-full pl-9 pr-8 py-2 text-sm bg-white border border-[#DDE3EA] rounded-md focus:outline-none focus:ring-2 focus:ring-[#1E5AA8] focus:border-transparent min-h-[44px]"
            @input="onInput"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#637083] hover:text-[#1F2933]"
            @click="clear"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search...',
    },
    debounce: {
        type: Number,
        default: 300,
    },
});

const emit = defineEmits(['update:modelValue', 'search']);

let timer = null;

const onInput = (e) => {
    const value = e.target.value;
    clearTimeout(timer);
    timer = setTimeout(() => {
        emit('update:modelValue', value);
        emit('search', value);
    }, props.debounce);
};

const clear = () => {
    clearTimeout(timer);
    emit('update:modelValue', '');
    emit('search', '');
};
</script>

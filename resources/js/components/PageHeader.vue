<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#1F2933]">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-[#637083] mt-1">
                {{ subtitle }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <slot name="actions"></slot>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

nninniiniinijnjnjnininjjnjnn

<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, default: '' },
    lineClass: { type: String, default: '' },
});

const lines = computed(() => String(props.text || '').split(/\r?\n/));
</script>

<template>
    <div>
        <p
            v-for="(line, index) in lines"
            :key="index"
            dir="auto"
            :class="[line ? '' : 'min-h-6', lineClass]"
        >
            {{ line }}
        </p>
    </div>
</template>

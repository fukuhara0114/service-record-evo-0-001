<template>
    <th
        class="sortable-th"
        :class="{ 'is-sorted': isActive }"
        :title="titleText"
        @click="onClick"
    >
        <slot />
        <span v-if="isActive" class="sort-mark" aria-hidden="true">{{ mark }}</span>
    </th>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    sortKey: { type: String, required: true },
    activeKey: { type: String, default: null },
    direction: { type: String, default: 'asc' },
    title: { type: String, default: 'クリックで並べ替え' },
})

const emit = defineEmits(['sort'])

const isActive = computed(() => props.activeKey === props.sortKey)
const mark = computed(() => (props.direction === 'desc' ? ' ▼' : ' ▲'))
const titleText = computed(() => {
    if (!isActive.value) return props.title
    return props.direction === 'desc'
        ? '降順（クリックで昇順）'
        : '昇順（クリックで降順）'
})

function onClick() {
    emit('sort', props.sortKey)
}
</script>

<style scoped>
.sortable-th {
    cursor: pointer;
    user-select: none;
    font-weight: 700;
}

.sortable-th:hover {
    filter: brightness(1.08);
}

.sort-mark {
    font-size: 0.85em;
    opacity: 0.95;
}

.is-sorted {
    background: #1e4a9e !important;
}
</style>

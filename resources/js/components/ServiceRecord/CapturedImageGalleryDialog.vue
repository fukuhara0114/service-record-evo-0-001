<template>
    <div class="gallery-dialog-host">
        <BaseDialog :title="dialogTitle" large @close="$emit('close')">
            <CapturedImageGallery
                ref="galleryRef"
                :associatedID="resolvedAssociatedId"
                :associated-id="resolvedAssociatedId"
                :filter-by-associated="filterByAssociated"
                :initial-captured-by="initialCapturedBy"
                :selection-only="selectionOnly"
                @select="(item) => $emit('select', item)"
                @selection-change="(items) => $emit('selection-change', items)"
                @associated="(payload) => $emit('associated', payload)"
                @confirm-selection="(items) => $emit('confirm-selection', items)"
            />
        </BaseDialog>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import BaseDialog from '@/components/ServiceRecord/Layer3/BaseDialog.vue'
import CapturedImageGallery from '@/components/ServiceRecord/CapturedImageGallery.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Gallery',
    },
    // Vue kebab `associated-id` binds to associatedId, not associatedID — accept both.
    associatedID: {
        type: [Number, String],
        default: null,
    },
    associatedId: {
        type: [Number, String],
        default: null,
    },
    filterByAssociated: {
        type: Boolean,
        default: false,
    },
    initialCapturedBy: {
        type: String,
        default: '',
    },
    selectionOnly: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close', 'select', 'selection-change', 'associated', 'confirm-selection'])

const galleryRef = ref(null)
const dialogTitle = computed(() => props.title || 'Gallery')
const resolvedAssociatedId = computed(() => {
    const raw = props.associatedID ?? props.associatedId
    if (raw == null || raw === '') return null
    return raw
})

defineExpose({
    getSelectedImages: () => galleryRef.value?.getSelectedImages?.() ?? [],
    clearSelection: () => galleryRef.value?.clearSelection?.(),
    selectAllVisible: () => galleryRef.value?.selectAllVisible?.(),
    reload: (...args) => galleryRef.value?.reload?.(...args),
})
</script>

<style scoped>
.gallery-dialog-host :deep(.dialog-overlay) {
    padding: 12px;
    box-sizing: border-box;
    align-items: center;
    overflow: hidden;
}

.gallery-dialog-host :deep(.dialog-panel.large) {
    height: 100%;
    max-height: min(100%, calc(100dvh / 1.1 - 24px));
    width: min(96vw, 1600px, 100%);
    max-width: 100%;
}

.gallery-dialog-host :deep(.dialog-panel.large .dialog-body) {
    overflow: hidden;
    min-height: 0;
}
</style>

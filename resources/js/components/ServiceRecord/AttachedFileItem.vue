<template>
    <div
        class="file-item"
        :class="{ 'file-item-selected': selected }"
        @click="$emit('select', file.id)"
    >
        <div class="file-meta">
            <span class="file-name">{{ file.documentName || '（名称なし）' }}</span>
            <span class="file-type">{{ file.fileType || '—' }}</span>
            <span v-if="file.documentType" class="file-doc-type">{{ file.documentType }}</span>
            <span v-if="file.sortNum != null" class="file-sort">順序: {{ file.sortNum }}</span>
        </div>

        <div class="file-actions">
            <button
                v-if="canPreview"
                type="button"
                class="preview-btn"
                @click.stop="showPreview = !showPreview"
            >
                {{ showPreview ? 'プレビューを閉じる' : 'プレビューを表示' }}
            </button>
            <a :href="fileUrl" target="_blank" rel="noopener" class="open-link" @click.stop>別タブで開く</a>
        </div>

        <div v-if="showPreview && isPdf" class="file-preview" @click.stop>
            <iframe
                :src="fileUrl"
                class="pdf-frame"
                title="PDFプレビュー"
            />
        </div>

        <div v-else-if="showPreview && isImage" class="file-preview" @click.stop>
            <img :src="fileUrl" :alt="file.documentName || '画像'" class="image-preview">
        </div>

        <p v-else-if="!isPdf && !isImage" class="other-file">
            このファイル形式はプレビュー非対応です。「別タブで開く」から確認してください。
        </p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    file: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['select'])

const isPdf = computed(() => props.file.fileType === 'application/pdf')
const isImage = computed(() => (props.file.fileType || '').startsWith('image/'))
const canPreview = computed(() => isPdf.value || isImage.value)

const showPreview = ref(canPreview.value)

const fileUrl = computed(() => {
    const basePath = window.location.pathname.replace(/\/administrator\/?$/, '')
    return `${window.location.origin}${basePath}/files/${props.file.id}`
})
</script>

<style scoped>
.file-item {
    border: 1px solid #94a3b8;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    background: #f8fafc;
    cursor: pointer;
}

.file-item:hover {
    background: #eff6ff;
}

.file-item-selected {
    border-color: #7e25eb;
    background: #f3e8ff;
    box-shadow: 0 0 0 2px rgba(126, 37, 235, 0.25);
}

.file-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    margin-bottom: 8px;
}

.file-name {
    font-weight: bold;
    color: #1e293b;
}

.file-type,
.file-doc-type,
.file-sort {
    font-size: 13px;
    color: #64748b;
}

.file-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 8px;
}

.preview-btn,
.open-link {
    font-size: 13px;
}

.preview-btn {
    padding: 6px 12px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.open-link {
    color: #2563eb;
}

.file-preview {
    margin-top: 8px;
}

.pdf-frame {
    width: 100%;
    height: 480px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: white;
}

.image-preview {
    max-width: 100%;
    max-height: 480px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}

.other-file {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}
</style>

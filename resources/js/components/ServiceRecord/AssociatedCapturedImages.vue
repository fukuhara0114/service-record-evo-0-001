<template>
    <div class="captured-list">
        <template v-if="images.length">
            <div
                v-for="image in images"
                :key="image.id"
                class="captured-row"
            >
                <button
                    type="button"
                    class="thumb-btn"
                    :title="image.title || image.file_name"
                    @click="openPreview(image)"
                >
                    <img
                        :src="image.thumbnail_url"
                        :alt="image.title || image.file_name"
                        class="thumb"
                        loading="lazy"
                    >
                </button>

                <div class="meta">
                    <span class="type-badge">撮影画像</span>
                    <strong class="title">{{ image.title || image.file_name || '（無題）' }}</strong>
                    <span class="date">{{ image.captured_at || '—' }}</span>
                    <span v-if="image.captured_by" class="by">{{ image.captured_by }}</span>
                </div>

                <div class="actions">
                    <button
                        type="button"
                        class="action-link"
                        @click="openPreview(image)"
                    >
                        プレビュー
                    </button>
                    <a
                        :href="image.image_url"
                        target="_blank"
                        rel="noopener"
                        class="action-link"
                        @click.stop
                    >
                        開く
                    </a>
                    <button
                        v-if="canUnlink"
                        type="button"
                        class="unlink-btn"
                        :disabled="unlinkingId === image.id"
                        @click="unlink(image)"
                    >
                        {{ unlinkingId === image.id ? '解除中...' : '紐づけ解除' }}
                    </button>
                </div>
            </div>
        </template>

        <div v-if="previewImage" class="preview-overlay" @click.self="closePreview">
            <div class="preview-panel">
                <div class="preview-header">
                    <div>
                        <h3>{{ previewImage.title || previewImage.file_name }}</h3>
                        <p>
                            {{ previewImage.captured_at || '—' }}
                            ／ {{ previewImage.captured_by || '—' }}
                        </p>
                    </div>
                    <button type="button" class="close-btn" @click="closePreview">×</button>
                </div>
                <div class="preview-body">
                    <img
                        :src="previewImage.image_url"
                        :alt="previewImage.title || previewImage.file_name"
                        class="preview-image"
                    >
                </div>
            </div>
        </div>

        <p v-if="unlinkError" class="unlink-error">{{ unlinkError }}</p>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { apiFetch } from '@/utils/apiFetch'

defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    canUnlink: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['changed'])

const page = usePage()
const previewImage = ref(null)
const unlinkingId = ref(null)
const unlinkError = ref('')

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function openPreview(image) {
    previewImage.value = image
}

function closePreview() {
    previewImage.value = null
}

async function unlink(image) {
    if (!image?.id || unlinkingId.value) return

    unlinkError.value = ''
    unlinkingId.value = image.id

    try {
        const result = await apiFetch(`${page.props.appBaseUrl}/servicerecord/camera/disassociate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ ids: [image.id] }),
        })

        if (!result) {
            throw new Error('紐づけ解除に失敗しました。')
        }

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data?.message || `紐づけ解除に失敗しました。（HTTP ${response.status}）`)
        }

        if (previewImage.value?.id === image.id) {
            closePreview()
        }
        emit('changed')
    } catch (e) {
        unlinkError.value = e.message || '紐づけ解除に失敗しました。'
    } finally {
        unlinkingId.value = null
    }
}
</script>

<style scoped>
.captured-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.captured-list:has(.captured-row) {
    margin-top: 4px;
}

.captured-row {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #94a3b8;
    border-radius: 8px;
    padding: 8px 10px;
    background: #f8fafc;
    min-width: 0;
}

.captured-row:hover {
    background: #ecfeff;
}

.thumb-btn {
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    overflow: hidden;
    background: #e2e8f0;
    cursor: pointer;
}

.thumb {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.meta {
    flex: 1 1 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 10px;
    min-width: 0;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    background: #ecfeff;
    color: #0e7490;
    border: 1px solid #67e8f9;
    font-size: 12px;
    font-weight: 700;
    flex: 0 0 auto;
}

.title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    max-width: 100%;
}

.date,
.by {
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex: 0 0 auto;
}

.action-link {
    font-size: 12px;
    color: #2563eb;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    text-decoration: none;
}

.unlink-btn {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid #fca5a5;
    background: #fff;
    color: #b91c1c;
    cursor: pointer;
}

.unlink-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.unlink-error {
    margin: 0;
    font-size: 12px;
    color: #b91c1c;
}

.preview-overlay {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.55);
    padding: 16px;
}

.preview-panel {
    width: min(920px, 100%);
    max-height: min(90vh, 900px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}

.preview-header h3 {
    margin: 0 0 4px;
    font-size: 16px;
}

.preview-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.close-btn {
    border: none;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    color: #475569;
}

.preview-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-image {
    max-width: 100%;
    max-height: min(75vh, 780px);
    object-fit: contain;
}
</style>

<template>
    <div class="preview-overlay" @click.self="close">
        <div class="preview-panel">
            <div class="preview-header">
                <div class="preview-title">
                    <h3>{{ file?.documentName || '（名称なし）' }}</h3>
                    <div class="preview-meta">
                        <span>ID: {{ file?.id }}</span>
                        <span>{{ file?.documentType || '—' }}</span>
                        <span>{{ file?.fileType || '—' }}</span>
                        <span v-if="isPdf">回転: {{ rotationDegrees }}°</span>
                    </div>
                </div>
                <label v-if="showSelectableToggle" class="selection-toggle">
                    <input
                        type="checkbox"
                        :checked="isSelected"
                        :disabled="busy"
                        @change="toggleSelected"
                    >
                    <span style="font-size: 18px; font-weight: bold;">このファイルを案件に紐付ける</span>
                </label>
                <div class="preview-actions">
                    <button
                        v-if="hasFiles"
                        type="button"
                        class="btn btn-secondary"
                        :disabled="busy || !hasPrevFile"
                        @click="goPrev"
                    >
                        &lt;
                    </button>
                    <button
                        v-if="hasFiles"
                        type="button"
                        class="btn btn-secondary"
                        :disabled="busy || !hasNextFile"
                        @click="goNext"
                    >
                        &gt;
                    </button>
                    <template v-if="isPdf">
                        <button type="button" class="btn btn-secondary" :disabled="busy" @click="rotate(-90)">
                            左へ90°
                        </button>
                        <button type="button" class="btn btn-secondary" :disabled="busy" @click="rotate(90)">
                            右へ90°
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="busy || !hasUnsavedRotation"
                            @click="saveRotated"
                        >
                            {{ saving ? '保存中...' : '正対させて上書き保存' }}
                        </button>
                    </template>
                    <a
                        v-if="originalFileUrl"
                        :href="originalFileUrl"
                        class="btn btn-secondary"
                        target="_blank"
                        rel="noopener"
                    >
                        別タブで開く
                    </a>
                    <button type="button" class="btn btn-secondary" :disabled="busy" @click="close">閉じる</button>
                </div>
            </div>

            <p v-if="message" class="status-message" :class="{ error: isError }">{{ message }}</p>

            <div class="preview-body">
                <iframe
                    v-if="isPdf && displayUrl"
                    :src="displayUrl"
                    class="preview-frame"
                    title="PDFプレビュー"
                />
                <img
                    v-else-if="isImage"
                    :src="originalFileUrl"
                    :alt="file?.documentName || '画像'"
                    class="preview-image"
                >
                <div v-else class="preview-fallback">
                    <p>この形式は埋め込みプレビュー非対応です。</p>
                    <a v-if="originalFileUrl" :href="originalFileUrl" target="_blank" rel="noopener">別タブで開く</a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { PDFDocument, degrees } from 'pdf-lib'
import { apiFetch } from '@/utils/apiFetch'

const props = defineProps({
    file: {
        type: Object,
        required: true,
    },
    files: {
        type: Array,
        default: () => [],
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    selectedFileIds: {
        type: Array,
        default: () => [],
    },
    fixedFileIds: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'saved', 'navigate', 'toggle-selected'])

const page = usePage()
const rotationDegrees = ref(0)
const originalBytes = ref(null)
const previewBytes = ref(null)
const previewObjectUrl = ref('')
const cacheBust = ref(Date.now())
const loading = ref(false)
const saving = ref(false)
const rotating = ref(false)
const message = ref('')
const isError = ref(false)

const busy = computed(() => loading.value || saving.value || rotating.value)
const hasUnsavedRotation = computed(() => ((rotationDegrees.value % 360) + 360) % 360 !== 0)
const hasFiles = computed(() => (props.files?.length ?? 0) > 1)
const currentIndex = computed(() =>
    (props.files ?? []).findIndex(item => Number(item?.id) === Number(props.file?.id)),
)
const hasPrevFile = computed(() => currentIndex.value > 0)
const hasNextFile = computed(() =>
    currentIndex.value !== -1 && currentIndex.value < (props.files?.length ?? 0) - 1,
)
const isFixedFile = computed(() =>
    (props.fixedFileIds ?? []).some(id => Number(id) === Number(props.file?.id)),
)
const showSelectableToggle = computed(() => props.selectable && !isFixedFile.value)
const isSelected = computed(() =>
    (props.selectedFileIds ?? []).some(id => Number(id) === Number(props.file?.id)),
)

const isPdf = computed(() => props.file?.fileType === 'application/pdf')
const isImage = computed(() => String(props.file?.fileType || '').startsWith('image/'))

const originalFileUrl = computed(() => {
    if (!props.file?.id) return ''
    return withPdfFitView(`${page.props.appBaseUrl}/servicerecord/files/${props.file.id}?t=${cacheBust.value}`)
})

const displayUrl = computed(() => previewObjectUrl.value || originalFileUrl.value)

function withPdfFitView(url) {
    if (!url) return ''
    const [base] = String(url).split('#')
    return `${base}#view=FitV`
}

watch(
    () => props.file?.id,
    async () => {
        await resetState()
        if (isPdf.value) {
            await loadOriginalPdf()
        }
    },
    { immediate: true },
)

onMounted(() => {
    // immediate watch already loads
})

onBeforeUnmount(() => {
    revokePreviewUrl()
})

function setStatus(text, error = false) {
    message.value = text
    isError.value = error
}

function revokePreviewUrl() {
    if (previewObjectUrl.value) {
        const [blobUrl] = previewObjectUrl.value.split('#')
        URL.revokeObjectURL(blobUrl)
        previewObjectUrl.value = ''
    }
}

async function resetState() {
    revokePreviewUrl()
    rotationDegrees.value = 0
    originalBytes.value = null
    previewBytes.value = null
    message.value = ''
    isError.value = false
}

async function loadOriginalPdf() {
    if (!props.file?.id) return

    loading.value = true
    // setStatus('PDFを読み込み中...')

    try {
        const response = await fetch(originalFileUrl.value, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            throw new Error(`PDFの読み込みに失敗しました。（HTTP ${response.status}）`)
        }

        const buffer = await response.arrayBuffer()
        originalBytes.value = new Uint8Array(buffer)
        previewBytes.value = originalBytes.value
        setStatus('')
    } catch (e) {
        setStatus(e.message || 'PDFの読み込みに失敗しました。', true)
    } finally {
        loading.value = false
    }
}

async function rotate(delta) {
    if (!isPdf.value || !originalBytes.value || busy.value) return

    rotating.value = true
    setStatus('回転処理中...')

    try {
        const nextDegrees = (((rotationDegrees.value + delta) % 360) + 360) % 360
        const pdfDoc = await PDFDocument.load(originalBytes.value)
        const pages = pdfDoc.getPages()

        for (const pageItem of pages) {
            const current = pageItem.getRotation().angle
            pageItem.setRotation(degrees((((current + nextDegrees) % 360) + 360) % 360))
        }

        const saved = await pdfDoc.save()
        previewBytes.value = saved
        rotationDegrees.value = nextDegrees
        revokePreviewUrl()
        previewObjectUrl.value = URL.createObjectURL(new Blob([saved], { type: 'application/pdf' }))
        previewObjectUrl.value = withPdfFitView(previewObjectUrl.value)
        setStatus(nextDegrees === 0 ? '元の向きに戻しました。' : `プレビューを ${nextDegrees}° 回転しました。上書き保存できます。`)
    } catch (e) {
        setStatus(e.message || '回転に失敗しました。', true)
    } finally {
        rotating.value = false
    }
}

function uint8ToBase64(bytes) {
    let binary = ''
    const chunkSize = 0x8000
    for (let i = 0; i < bytes.length; i += chunkSize) {
        const chunk = bytes.subarray(i, i + chunkSize)
        binary += String.fromCharCode(...chunk)
    }
    return btoa(binary)
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function saveRotated() {
    if (!hasUnsavedRotation.value || !previewBytes.value || !props.file?.id) return

    saving.value = true
    setStatus('上書き保存中...')

    try {
        const url = `${page.props.appBaseUrl}/servicerecord/files/${props.file.id}/content`
        const result = await apiFetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                content: uint8ToBase64(previewBytes.value),
                fileType: 'application/pdf',
            }),
        })

        if (!result) return

        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `保存に失敗しました。（HTTP ${response.status}）`)
        }

        originalBytes.value = previewBytes.value
        rotationDegrees.value = 0
        revokePreviewUrl()
        cacheBust.value = Date.now()
        setStatus('上書き保存しました。')
        emit('saved', data.file ?? props.file)
    } catch (e) {
        setStatus(e.message || '保存に失敗しました。', true)
    } finally {
        saving.value = false
    }
}

function close() {
    if (busy.value) return
    emit('close')
}

function goPrev() {
    if (!hasPrevFile.value || busy.value) return
    emit('navigate', props.files[currentIndex.value - 1])
}

function goNext() {
    if (!hasNextFile.value || busy.value) return
    emit('navigate', props.files[currentIndex.value + 1])
}

function toggleSelected() {
    if (!showSelectableToggle.value || busy.value) return
    emit('toggle-selected', props.file)
}
</script>

<style scoped>
.preview-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    z-index: 400;
    display: flex;
    justify-content: center;
    align-items: stretch;
    padding: 12px;
    box-sizing: border-box;
}

.preview-panel {
    width: min(96vw, 1600px);
    height: calc(100vh - 24px);
    max-width: 96vw;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 12px 16px;
    background: #1e293b;
    color: #fff;
}

.preview-title h3 {
    margin: 0 0 6px;
    font-size: 16px;
}

.preview-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    font-size: 12px;
    color: #cbd5e1;
}

.selection-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
}

.preview-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.status-message {
    margin: 0;
    padding: 8px 16px;
    background: #ecfdf5;
    color: #047857;
    font-size: 13px;
}

.status-message.error {
    background: #fef2f2;
    color: #b91c1c;
}

.preview-body {
    flex: 1;
    min-height: 0;
    background: #e2e8f0;
    padding: 8px;
    box-sizing: border-box;
    display: flex;
}

.preview-frame,
.preview-image {
    width: 100%;
    height: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
}

.preview-image {
    object-fit: contain;
}

.preview-fallback {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    color: #64748b;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}
</style>

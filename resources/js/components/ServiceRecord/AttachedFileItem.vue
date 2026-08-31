<template>
    <div
        ref="rootEl"
        class="file-item"
        :class="{ 'file-item-selected': selected }"
        @click="$emit('select', file.id)"
    >
        <div class="file-toolbar">
            <div class="file-actions">
                <button
                    v-if="canPreview"
                    type="button"
                    class="preview-btn"
                    @click.stop="togglePreview"
                >
                    {{ showPreview ? 'プレビューを閉じる' : 'プレビューを表示' }}
                </button>
                <button
                    v-if="isEml"
                    type="button"
                    class="preview-btn draft-btn"
                    :disabled="draftCreating"
                    @click.stop="openDraftDialog"
                >
                    {{ draftCreating ? '作成中...' : 'メールドラフト作成' }}
                </button>
                <a :href="fileViewUrl" target="_blank" rel="noopener" class="open-link" @click.stop>別タブで開く</a>
            </div>

            <div class="file-scroll-nav" @click.stop>
                <button
                    type="button"
                    class="file-scroll-nav-btn"
                    :disabled="!canMoveUp"
                    title="前のファイルへ"
                    aria-label="前のファイルへ"
                    @click="scrollToAdjacent('up')"
                >
                    ↑
                </button>
                <button
                    type="button"
                    class="file-scroll-nav-btn"
                    :disabled="!canMoveDown"
                    title="次のファイルへ"
                    aria-label="次のファイルへ"
                    @click="scrollToAdjacent('down')"
                >
                    ↓
                </button>
            </div>

            <div class="sort-control" @click.stop>
                <span class="sort-label">順序</span>
                <button
                    type="button"
                    class="sort-btn"
                    :disabled="!canMoveUp || sorting"
                    title="順序を上へ"
                    @click="$emit('move', 'up')"
                >
                    ↑
                </button>
                <input
                    v-model.number="draftSortNum"
                    type="number"
                    class="sort-input"
                    :disabled="sorting"
                    @keydown.enter.prevent="commitSortNum"
                    @change="commitSortNum"
                >
                <button
                    type="button"
                    class="sort-btn"
                    :disabled="!canMoveDown || sorting"
                    title="順序を下へ"
                    @click="$emit('move', 'down')"
                >
                    ↓
                </button>
            </div>
        </div>

        <p v-if="sortError" class="sort-error" @click.stop>{{ sortError }}</p>

        <div v-if="showPreview && isPdf" class="file-preview" @click.stop>
            <iframe
                :src="fileViewUrl"
                class="pdf-frame"
                title="PDFプレビュー"
            />
        </div>

        <div v-else-if="showPreview && isImage" class="file-preview" @click.stop>
            <img :src="fileViewUrl" :alt="file.documentName || '画像'" class="image-preview">
        </div>

        <div v-else-if="showPreview && isEml" class="eml-preview" @click.stop>
            <p v-if="emlLoading" class="eml-status">メールを読み込み中...</p>
            <p v-else-if="emlError" class="eml-error">{{ emlError }}</p>
            <template v-else-if="emlData">
                <div class="eml-header">
                    <div class="eml-row">
                        <span class="eml-label">件名</span>
                        <strong>{{ emlData.subject || '(件名なし)' }}</strong>
                    </div>
                    <div v-if="emlData.from" class="eml-row">
                        <span class="eml-label">From</span>
                        <span>{{ emlData.from }}</span>
                    </div>
                    <div v-if="emlData.to" class="eml-row">
                        <span class="eml-label">To</span>
                        <span>{{ emlData.to }}</span>
                    </div>
                    <div v-if="emlData.cc" class="eml-row">
                        <span class="eml-label">Cc</span>
                        <span>{{ emlData.cc }}</span>
                    </div>
                    <div v-if="emlData.date" class="eml-row">
                        <span class="eml-label">Date</span>
                        <span>{{ emlData.date }}</span>
                    </div>
                </div>

                <div class="eml-section">
                    <h4>本文</h4>
                    <div v-if="emlData.bodyHtml" class="eml-body-html" v-html="emlData.bodyHtml" />
                    <pre v-else class="eml-body-text">{{ emlData.bodyText || '(本文なし)' }}</pre>
                </div>

                <div class="eml-section">
                    <h4>添付一覧（{{ emlData.attachments?.length || 0 }}件）</h4>
                    <ul v-if="emlData.attachments?.length" class="eml-attachments">
                        <li v-for="attachment in emlData.attachments" :key="attachment.index">
                            <div class="eml-attachment-meta">
                                <strong>{{ attachment.filename }}</strong>
                                <span>{{ attachment.contentType || '—' }}</span>
                                <span>{{ formatBytes(attachment.size) }}</span>
                            </div>
                            <a
                                class="eml-attachment-link"
                                :href="emlAttachmentUrl(attachment.index)"
                                target="_blank"
                                rel="noopener"
                            >
                                ダウンロード
                            </a>
                        </li>
                    </ul>
                    <p v-else class="eml-empty">添付ファイルはありません。</p>
                </div>
            </template>
        </div>

        <p v-else-if="!isPdf && !isImage && !isEml" class="other-file">
            このファイル形式はプレビュー非対応です。「別タブで開く」から確認してください。
        </p>

        <EmailDraftTypeDialog
            v-if="showDraftDialog"
            :creating="draftCreating"
            :error="draftError"
            :initial-type="selectedDraftType"
            confirm-label="ドラフト作成してダウンロード"
            @close="closeDraftDialog"
            @confirm="createReplyDraft"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { apiFetch } from '@/utils/apiFetch'
import EmailDraftTypeDialog from '@/components/ServiceRecord/Layer3/EmailDraftTypeDialog.vue'

const props = defineProps({
    file: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    canMoveUp: {
        type: Boolean,
        default: false,
    },
    canMoveDown: {
        type: Boolean,
        default: false,
    },
    sorting: {
        type: Boolean,
        default: false,
    },
    orderId: {
        type: [Number, String],
        default: null,
    },
    fileBaseUrl: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['select', 'move', 'sort-num-change'])

const rootEl = ref(null)

function isOverflowYScrollable(node) {
    const overflowY = getComputedStyle(node).overflowY
    return overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay'
}

function findOverflowingAncestor(start) {
    let node = start
    while (node && node !== document.body) {
        if (isOverflowYScrollable(node) && node.scrollHeight > node.clientHeight + 1) {
            return node
        }
        node = node.parentElement
    }
    return null
}

function findScrollParent(el) {
    const list = el?.closest?.('.files-list, .files-list-wrap')
    if (list && isOverflowYScrollable(list)) {
        return list
    }
    return findOverflowingAncestor(el?.parentElement)
}

function cssPixelScaleY(el) {
    const layoutHeight = el.offsetHeight
    if (!layoutHeight) return 1
    const visualHeight = el.getBoundingClientRect().height
    if (!visualHeight) return 1
    return visualHeight / layoutHeight
}

function scrollToAdjacent(direction) {
    const el = rootEl.value
    if (!el?.parentElement) return

    const siblings = Array.from(el.parentElement.children).filter((child) => (
        child.classList?.contains('file-item')
    ))
    const index = siblings.indexOf(el)
    if (index < 0) return

    const target = direction === 'up' ? siblings[index - 1] : siblings[index + 1]
    if (!target) return

    const scrollParent = findScrollParent(el)
    if (scrollParent) {
        const scaleY = cssPixelScaleY(scrollParent)
        const parentRect = scrollParent.getBoundingClientRect()
        const targetRect = target.getBoundingClientRect()
        const nextTop = scrollParent.scrollTop + (targetRect.top - parentRect.top) / scaleY
        scrollParent.scrollTo({ top: Math.max(0, nextTop), behavior: 'smooth' })
        return
    }

    target.scrollIntoView({ block: 'start', behavior: 'smooth' })
}

const isPdf = computed(() => props.file.fileType === 'application/pdf')
const isImage = computed(() => (props.file.fileType || '').startsWith('image/'))
const isEml = computed(() => {
    const name = String(props.file.documentName || '').toLowerCase()
    const type = String(props.file.fileType || '').toLowerCase()
    return name.endsWith('.eml')
        || type.includes('message/rfc822')
        || type === 'application/eml'
        || type === 'message/rfc822'
})
const canPreview = computed(() => isPdf.value || isImage.value || isEml.value)

const showPreview = ref(isPdf.value || isImage.value)
const draftSortNum = ref(props.file.sortNum ?? null)
const sortError = ref('')
const emlLoading = ref(false)
const emlError = ref('')
const emlData = ref(null)
const showDraftDialog = ref(false)
const selectedDraftType = ref('receipt')
const draftCreating = ref(false)
const draftError = ref('')

watch(
    () => props.file.sortNum,
    (value) => {
        draftSortNum.value = value ?? null
    },
)

watch(
    () => props.file.id,
    () => {
        emlData.value = null
        emlError.value = ''
        if (showPreview.value && isEml.value) {
            loadEmlPreview()
        }
    },
)

/** API 用ベース（/files/{id}）。eml-preview 等はこちらを使う */
const fileApiBaseUrl = computed(() => {
    if (props.fileBaseUrl) {
        return `${props.fileBaseUrl.replace(/\/$/, '')}/${props.file.id}`
    }
    // administrator 等以外（intake 作成画面など）でも /servicerecord/files を指す
    const basePath = window.location.pathname
        .replace(/\/(administrator|engineer|logistics|shipping-prep)\/?$/, '')
        .replace(/\/intake(?:\/.*)?$/, '')
        .replace(/\/loaner(?:\/.*)?$/, '')
    return `${window.location.origin}${basePath}/files/${props.file.id}`
})

const MIME_EXTENSION_MAP = {
    'application/pdf': 'pdf',
    'image/jpeg': 'jpg',
    'image/jpg': 'jpg',
    'image/png': 'png',
    'image/gif': 'gif',
    'image/webp': 'webp',
    'image/bmp': 'bmp',
    'image/tiff': 'tif',
    'message/rfc822': 'eml',
    'application/eml': 'eml',
    'application/msword': 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
    'application/vnd.ms-excel': 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    'text/plain': 'txt',
    'text/csv': 'csv',
}

/** PDFビューア保存ダイアログ用: 元ファイル名＋拡張子 */
function resolveDownloadFileName(file) {
    let name = String(file?.documentName || '').trim() || `file-${file?.id ?? 'download'}`
    name = name.replace(/[\\/]/g, '-')
    if (/\.[A-Za-z0-9]{1,8}$/.test(name)) return name
    const ext = MIME_EXTENSION_MAP[String(file?.fileType || '').toLowerCase()]
    return ext ? `${name}.${ext}` : name
}

/**
 * プレビュー／別タブ用 URL。
 * Chrome PDF ビューアは URL 末尾を保存名に使うため、拡張子付きファイル名を path に載せる。
 */
const fileViewUrl = computed(() => {
    const name = resolveDownloadFileName(props.file)
    return `${fileApiBaseUrl.value}/view/${encodeURIComponent(name)}`
})

const fileUrl = fileApiBaseUrl

const emlPreviewUrl = computed(() => `${fileApiBaseUrl.value}/eml-preview`)

function emlAttachmentUrl(index) {
    return `${fileApiBaseUrl.value}/eml-attachment/${index}`
}

function formatBytes(size) {
    const num = Number(size)
    if (!Number.isFinite(num) || num < 0) return '—'
    if (num < 1024) return `${num} B`
    if (num < 1024 * 1024) return `${(num / 1024).toFixed(1)} KB`
    return `${(num / (1024 * 1024)).toFixed(1)} MB`
}

async function loadEmlPreview() {
    if (!isEml.value) return
    emlLoading.value = true
    emlError.value = ''

    try {
        const result = await apiFetch(emlPreviewUrl.value, {
            headers: { Accept: 'application/json' },
        })
        if (!result) {
            throw new Error('メールの読み込みに失敗しました。')
        }
        const { response, data } = result
        if (!response.ok) {
            throw new Error(data.message || `メールの読み込みに失敗しました。（HTTP ${response.status}）`)
        }
        emlData.value = data
    } catch (e) {
        emlData.value = null
        emlError.value = e.message || 'メールの読み込みに失敗しました。'
    } finally {
        emlLoading.value = false
    }
}

function togglePreview() {
    showPreview.value = !showPreview.value
    if (showPreview.value && isEml.value && !emlData.value && !emlLoading.value) {
        loadEmlPreview()
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function openDraftDialog() {
    selectedDraftType.value = 'receipt'
    draftError.value = ''
    showDraftDialog.value = true
}

function closeDraftDialog() {
    if (draftCreating.value) return
    showDraftDialog.value = false
    draftError.value = ''
}

function parseFilenameFromDisposition(headerValue) {
    if (!headerValue) return null
    const utfMatch = String(headerValue).match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
    if (utfMatch?.[1]) {
        try {
            return decodeURIComponent(utfMatch[1].trim().replace(/^"|"$/g, ''))
        } catch {
            return utfMatch[1]
        }
    }
    const match = String(headerValue).match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
    return (match?.[1] || match?.[2] || '').trim() || null
}

async function createReplyDraft(templateType) {
    const type = templateType || selectedDraftType.value
    if (!type) {
        draftError.value = '定型メールの種類を選択してください。'
        return
    }
    selectedDraftType.value = type

    draftCreating.value = true
    draftError.value = ''

    try {
        const downloadResponse = await fetch(`${fileUrl.value}/eml-reply-draft`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'message/rfc822, application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                templateType: type,
                orderID: props.orderId != null ? Number(props.orderId) : null,
            }),
        })

        if (!downloadResponse.ok) {
            let message = `ドラフト作成に失敗しました。（HTTP ${downloadResponse.status}）`
            try {
                const errJson = await downloadResponse.json()
                message = errJson.message || message
            } catch {
                // ignore
            }
            throw new Error(message)
        }

        const blob = await downloadResponse.blob()
        const filename = parseFilenameFromDisposition(downloadResponse.headers.get('Content-Disposition'))
            || 'reply-draft.eml'
        const objectUrl = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = objectUrl
        anchor.download = filename
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(objectUrl)

        showDraftDialog.value = false
    } catch (e) {
        draftError.value = e.message || 'ドラフト作成に失敗しました。'
    } finally {
        draftCreating.value = false
    }
}

function commitSortNum() {
    sortError.value = ''
    const next = draftSortNum.value
    if (next === '' || next == null) {
        if (props.file.sortNum == null) return
        emit('sort-num-change', null)
        return
    }
    const num = Number(next)
    if (!Number.isFinite(num)) {
        sortError.value = '順序は数値で入力してください。'
        draftSortNum.value = props.file.sortNum ?? null
        return
    }
    if (Number(props.file.sortNum) === num) return
    emit('sort-num-change', Math.trunc(num))
}
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

.file-toolbar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
    flex-wrap: nowrap;
}

.file-scroll-nav {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    z-index: 1;
}

.file-scroll-nav-btn {
    width: 28px;
    height: 26px;
    padding: 0;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 14px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
}

.file-scroll-nav-btn:hover:not(:disabled) {
    background: #e2e8f0;
    border-color: #475569;
}

.file-scroll-nav-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.file-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex: 1 1 auto;
    min-width: 0;
    flex-wrap: wrap;
}

.sort-control {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex: 0 0 auto;
    padding: 2px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #fff;
}

.sort-label {
    font-size: 12px;
    color: #64748b;
    margin-right: 2px;
}

.sort-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #f8fafc;
    color: #1e293b;
    cursor: pointer;
    line-height: 1;
}

.sort-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.sort-input {
    width: 56px;
    padding: 4px 6px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    font-size: 13px;
    text-align: center;
}

.sort-error {
    margin: 0 0 8px;
    font-size: 12px;
    color: #b91c1c;
    text-align: right;
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

.draft-btn {
    background: #0f766e;
}

.open-link {
    color: #2563eb;
}

.draft-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.file-preview {
    width: 100%;
    aspect-ratio: 210 / 297;
    max-height: 90vh;
    margin-top: 8px;
}

.pdf-frame {
    width: 100%;
    height: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: white;
}

.image-preview {
    width: 100%;
    height: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    object-fit: contain;
}

.other-file {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.eml-preview {
    margin-top: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    padding: 12px;
    max-height: 70vh;
    overflow: auto;
}

.eml-status,
.eml-error,
.eml-empty {
    margin: 0;
    font-size: 13px;
}

.eml-error {
    color: #b91c1c;
}

.eml-header {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.eml-row {
    display: grid;
    grid-template-columns: 56px 1fr;
    gap: 8px;
    font-size: 13px;
    color: #334155;
}

.eml-label {
    color: #64748b;
    font-weight: 700;
}

.eml-section h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #1e293b;
}

.eml-section + .eml-section {
    margin-top: 12px;
}

.eml-body-html {
    font-size: 13px;
    line-height: 1.5;
    color: #1e293b;
    overflow-wrap: anywhere;
}

.eml-body-html :deep(img) {
    max-width: 100%;
    height: auto;
}

.eml-body-text {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 13px;
    line-height: 1.5;
    color: #1e293b;
    font-family: inherit;
}

.eml-attachments {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.eml-attachments li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    background: #f8fafc;
}

.eml-attachment-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    min-width: 0;
    font-size: 12px;
    color: #64748b;
}

.eml-attachment-meta strong {
    color: #1e293b;
}

.eml-attachment-link {
    flex: 0 0 auto;
    font-size: 12px;
    color: #2563eb;
}
</style>

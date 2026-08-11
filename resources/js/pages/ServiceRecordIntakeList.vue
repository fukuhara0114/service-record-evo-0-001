<template>
    <div class="intake-page">
        <div class="page-header">
            <div>
                <h1>未登録ファイル一覧</h1>
            </div>
            <div class="header-actions">
                <a :href="adminUrl" class="btn btn-primary">既存案件一覧</a>
                <CloseToHomeButton :href="homeUrl" />
            </div>
        </div>

        <p v-if="deleteError" class="global-error">{{ deleteError }}</p>
        <div v-if="importStatus || importBusy" class="global-info" role="status">
            <span class="global-info-text">{{ importStatus }}</span>
            <div
                v-if="showImportProgress"
                class="import-progress"
                role="progressbar"
                :aria-valuemin="0"
                :aria-valuemax="100"
                :aria-valuenow="importBusy ? undefined : 100"
                :aria-label="importBusy ? '処理中' : '完了'"
            >
                <div
                    class="import-progress-bar"
                    :class="importBusy ? 'is-indeterminate' : 'is-complete'"
                />
            </div>
        </div>

        <section class="list-card">
            <div class="list-header">
                <h2>対象ファイル（{{ files.length }}件）</h2>
            </div>

            <div class="file-scroll">
                <div class="file-grid">
                    <article class="file-card file-card-empty">
                        <div class="file-preview-wrap file-preview-empty">
                            <p class="empty-card-title">添付ファイル無し</p>
                            <p class="empty-card-help">情報入力のみで新規案件を作成</p>
                        </div>
                        <div class="file-card-actions">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click="openCaseTypeDialog({ mode: 'blank' })"
                            >
                                添付なしで新規登録
                            </button>
                        </div>
                    </article>

                    <article
                        class="file-card file-card-empty file-card-upload"
                        :class="{
                            'file-card-upload-active': uploadDropActive,
                            'file-card-upload-busy': uploadBusy,
                        }"
                        @dragenter.prevent="onUploadDragEnter"
                        @dragover.prevent="onUploadDragOver"
                        @dragleave.prevent="onUploadDragLeave"
                        @drop.prevent="onUploadDrop"
                    >
                        <div class="file-preview-wrap file-preview-empty" @click="openUploadCaseTypeDialog">
                            <p class="empty-card-title">ファイルを追加して作成</p>
                            <p class="empty-card-help">
                                {{ uploadBusy
                                    ? (uploadProgress || 'アップロード中...')
                                    : 'クリックまたは D&D でファイルを追加し、新規案件へ進みます' }}
                            </p>
                            <p v-if="uploadError" class="upload-card-error">{{ uploadError }}</p>
                        </div>
                        <div class="file-card-actions">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="uploadBusy"
                                @click="openUploadCaseTypeDialog"
                            >
                                {{ uploadBusy ? 'アップロード中...' : 'ファイルを選択' }}
                            </button>
                            <input
                                ref="uploadInputRef"
                                type="file"
                                class="upload-input"
                                multiple
                                :disabled="uploadBusy"
                                @change="onUploadInputChange"
                            >
                        </div>
                    </article>

                    <article
                        v-for="file in files"
                        :key="file.id"
                        class="file-card"
                        @click="openPreview(file)"
                    >
                        <div class="file-preview-wrap">
                            <iframe
                                v-if="isPdf(file)"
                                :src="fileUrl(file.id)"
                                class="file-preview"
                                :title="`file preview ${file.id}`"
                                tabindex="-1"
                            />
                            <img
                                v-else-if="isImage(file)"
                                :src="fileUrl(file.id)"
                                :alt="file.documentName || '画像'"
                                class="file-preview-image"
                            >
                            <div v-else class="file-preview-fallback">
                                <p>{{ file.documentName || '（名称なし）' }}</p>
                                <p class="fallback-type">{{ file.fileType || 'プレビュー非対応' }}</p>
                            </div>
                        </div>

                        <div class="file-card-meta">
                            <span class="file-card-name">{{ file.documentName || '（名称なし）' }}</span>
                            <span class="file-card-type">{{ file.fileType || '—' }}</span>
                        </div>

                        <div class="file-card-actions" @click.stop>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click="openCaseTypeDialog({ mode: 'file', file })"
                            >
                                このファイルで新規登録
                            </button>
                            <button
                                type="button"
                                class="btn btn-danger btn-sm btn-delete"
                                :disabled="deletingFileId === file.id"
                                @click="deleteFile(file)"
                            >
                                {{ deletingFileId === file.id ? '削除中...' : '削除' }}
                            </button>
                        </div>
                    </article>
                </div>

                <p v-if="deleteError" class="delete-error">{{ deleteError }}</p>
                <p v-if="!files.length" class="empty-message">未登録ファイルはありません（添付なしでの新規登録は可能です）。</p>
            </div>
        </section>

        <IntakeFilePreviewDialog
            v-if="previewFile"
            :file="previewFile"
            :files="files"
            :show-create-action="true"
            @close="previewFile = null"
            @saved="onPreviewSaved"
            @navigate="openPreview"
            @create="onPreviewCreate"
        />

        <IntakeCaseTypeDialog
            v-if="caseTypeDialog"
            :mode="caseTypeDialog.mode"
            :file-name="caseTypeDialog.fileName"
            @close="caseTypeDialog = null"
            @confirm="onCaseTypeConfirm"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import CloseToHomeButton from '@/components/CloseToHomeButton.vue'
import IntakeCaseTypeDialog from '@/components/ServiceRecord/Intake/IntakeCaseTypeDialog.vue'
import IntakeFilePreviewDialog from '@/components/ServiceRecord/Intake/IntakeFilePreviewDialog.vue'
import { startFileImport } from '@/utils/startFileImport'

const props = defineProps({
    unregisteredFiles: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const previewFile = ref(null)
const previewCacheBust = ref(Date.now())
const uploadInputRef = ref(null)
const uploadBusy = ref(false)
const uploadProgress = ref('')
const uploadError = ref('')
const uploadDropActive = ref(false)
const uploadDragDepth = ref(0)
const files = ref([...(props.unregisteredFiles ?? [])])
const deletingFileId = ref(null)
const deleteError = ref('')
const importStatus = ref('')
const importBusy = ref(false)
const showImportProgress = ref(false)
const caseTypeDialog = ref(null)
const pendingCaseType = ref(null)
let importProgressHideTimer = null
let pendingUploadFiles = null

watch(
    () => props.unregisteredFiles,
    (value) => {
        files.value = [...(value ?? [])]
    },
)

onMounted(() => {
    if (importProgressHideTimer) {
        clearTimeout(importProgressHideTimer)
        importProgressHideTimer = null
    }

    // 取込はバックグラウンドで起動し、UI（ファイル選択ダイアログ含む）を塞がない
    importBusy.value = true
    showImportProgress.value = true
    importStatus.value = '処理を開始しています...'

    startFileImport({
        appBaseUrl: page.props.appBaseUrl,
        associatedID: -1,
    }).then((result) => {
        importStatus.value = result.message || ''
        importBusy.value = false
        importProgressHideTimer = setTimeout(() => {
            showImportProgress.value = false
            if (result.ok) {
                importStatus.value = ''
                router.reload({
                    only: ['unregisteredFiles'],
                    preserveScroll: true,
                    preserveState: true,
                })
            }
            importProgressHideTimer = null
        }, 500)
    }).catch(() => {
        importBusy.value = false
        showImportProgress.value = false
        importStatus.value = '取込開始に失敗しました。'
    })
})

onBeforeUnmount(() => {
    if (importProgressHideTimer) {
        clearTimeout(importProgressHideTimer)
        importProgressHideTimer = null
    }
})

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const adminUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/administrator`)
const uploadUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/intake/upload`)

function createWithoutFileUrl(caseType = 'service') {
    const type = caseType === 'loaner' ? 'loaner' : 'service'
    return `${page.props.appBaseUrl}/servicerecord/intake/create?order_type=${encodeURIComponent(type)}`
}

function createUrl(fileId, caseType = 'service') {
    const type = caseType === 'loaner' ? 'loaner' : 'service'
    return `${page.props.appBaseUrl}/servicerecord/intake/${fileId}/create?order_type=${encodeURIComponent(type)}`
}

function openCaseTypeDialog({ mode, file = null, files = null } = {}) {
    pendingUploadFiles = Array.isArray(files) && files.length ? files : null
    caseTypeDialog.value = {
        mode,
        fileId: file?.id ?? null,
        fileName: file?.documentName || '',
    }
}

function openUploadCaseTypeDialog() {
    if (uploadBusy.value) return
    uploadError.value = ''
    openCaseTypeDialog({ mode: 'upload' })
}

function onCaseTypeConfirm(caseType) {
    const dialog = caseTypeDialog.value
    caseTypeDialog.value = null
    if (!dialog) return

    if (dialog.mode === 'blank') {
        window.location.href = createWithoutFileUrl(caseType)
        return
    }

    if (dialog.mode === 'file') {
        window.location.href = createUrl(dialog.fileId, caseType)
        return
    }

    if (dialog.mode === 'upload') {
        pendingCaseType.value = caseType
        if (pendingUploadFiles?.length) {
            const list = pendingUploadFiles
            pendingUploadFiles = null
            uploadThenCreate(list)
            return
        }
        openUploadPicker()
    }
}

function fileUrl(fileId) {
    return `${page.props.appBaseUrl}/servicerecord/files/${fileId}?t=${previewCacheBust.value}`
}

function isPdf(file) {
    return file?.fileType === 'application/pdf'
}

function isImage(file) {
    return String(file?.fileType || '').startsWith('image/')
}

function openPreview(file) {
    previewFile.value = file
}

function onPreviewCreate(file) {
    const target = file || previewFile.value
    if (!target?.id) return
    previewFile.value = null
    openCaseTypeDialog({ mode: 'file', file: target })
}

function onPreviewSaved() {
    previewCacheBust.value = Date.now()
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function deleteFile(file) {
    if (!file?.id || deletingFileId.value) return

    const name = file.documentName || `ID ${file.id}`
    if (!window.confirm(`「${name}」を削除しますか？\nこの操作は取り消せません。`)) {
        return
    }

    deletingFileId.value = file.id
    deleteError.value = ''

    try {
        const response = await fetch(
            `${page.props.appBaseUrl}/servicerecord/files/${file.id}?mode=delete`,
            {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        )

        let data = {}
        try {
            data = await response.json()
        } catch {
            // ignore
        }

        if (!response.ok) {
            throw new Error(data.message || `削除に失敗しました。（HTTP ${response.status}）`)
        }

        files.value = files.value.filter((item) => Number(item.id) !== Number(file.id))
        if (previewFile.value && Number(previewFile.value.id) === Number(file.id)) {
            previewFile.value = null
        }
    } catch (e) {
        deleteError.value = e.message || '削除に失敗しました。'
    } finally {
        deletingFileId.value = null
    }
}

function guessDocumentType(file) {
    const name = String(file?.name || '').toLowerCase()
    const type = String(file?.type || '').toLowerCase()
    if (name.endsWith('.eml') || name.endsWith('.msg') || type.includes('message') || type.includes('ms-outlook')) {
        return 'メール'
    }
    if (type === 'application/pdf' || name.endsWith('.pdf')) {
        return 'PDF'
    }
    if (type.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|tiff?)$/i.test(name)) {
        return '画像'
    }
    return '添付ファイル'
}

function openUploadPicker() {
    if (uploadBusy.value) return
    uploadError.value = ''
    uploadInputRef.value?.click()
}

function onUploadInputChange(event) {
    const list = [...(event.target.files ?? [])]
    event.target.value = ''
    if (!list.length) return
    uploadThenCreate(list)
}

function onUploadDragEnter(event) {
    if (uploadBusy.value) return
    if (![...(event.dataTransfer?.types ?? [])].includes('Files')) return
    uploadDragDepth.value += 1
    uploadDropActive.value = true
}

function onUploadDragOver(event) {
    if (uploadBusy.value) return
    if (![...(event.dataTransfer?.types ?? [])].includes('Files')) return
    event.dataTransfer.dropEffect = 'copy'
    uploadDropActive.value = true
}

function onUploadDragLeave() {
    if (uploadBusy.value) return
    uploadDragDepth.value = Math.max(0, uploadDragDepth.value - 1)
    if (uploadDragDepth.value === 0) {
        uploadDropActive.value = false
    }
}

function onUploadDrop(event) {
    uploadDragDepth.value = 0
    uploadDropActive.value = false
    if (uploadBusy.value) return
    const list = [...(event.dataTransfer?.files ?? [])]
    if (!list.length) {
        uploadError.value = 'アップロード可能なファイルがありません。'
        return
    }
    openCaseTypeDialog({ mode: 'upload', files: list })
}

async function uploadSingleFile(file, sortNum) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('documentName', file.name || 'untitled')
    formData.append('documentType', guessDocumentType(file))
    formData.append('sortNum', String(sortNum))

    const response = await fetch(uploadUrl.value, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: formData,
    })

    let data = {}
    try {
        data = await response.json()
    } catch {
        // ignore
    }

    if (!response.ok) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : null
        throw new Error(
            validationMessage
            || data.message
            || `${file.name || 'ファイル'} のアップロードに失敗しました。（HTTP ${response.status}）`,
        )
    }

    return data.file
}

async function uploadThenCreate(fileList) {
    const list = fileList.filter(file => file && file.size >= 0)
    if (!list.length) {
        uploadError.value = 'アップロード可能なファイルがありません。'
        return
    }

    uploadBusy.value = true
    uploadError.value = ''
    uploadProgress.value = ''

    try {
        let firstFileId = null
        let sortNum = 10
        for (let i = 0; i < list.length; i += 1) {
            const file = list[i]
            uploadProgress.value = `${i + 1}/${list.length}: ${file.name || 'untitled'}`
            const saved = await uploadSingleFile(file, sortNum)
            if (!firstFileId && saved?.id) {
                firstFileId = saved.id
            }
            sortNum += 10
        }

        if (!firstFileId) {
            throw new Error('ファイルの登録結果を取得できませんでした。')
        }

        const caseType = pendingCaseType.value || 'service'
        pendingCaseType.value = null
        window.location.href = createUrl(firstFileId, caseType)
    } catch (e) {
        uploadError.value = e.message || 'アップロードに失敗しました。'
        uploadBusy.value = false
        uploadProgress.value = ''
        pendingCaseType.value = null
    }
}
</script>

<style scoped>
.intake-page {
    height: 100vh;
    padding: 24px;
    background: #e2e8f0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 20px;
    flex-shrink: 0;
}

.page-header h1 {
    margin: 0 0 8px;
    font-size: 24px;
    color: #1e293b;
}

.global-error {
    margin: 0 0 12px;
    padding: 10px 14px;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
    flex-shrink: 0;
}

.global-info {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0 0 12px;
    padding: 10px 14px;
    border: 1px solid #93c5fd;
    border-radius: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    flex-shrink: 0;
}

.global-info-text {
    flex-shrink: 0;
    white-space: nowrap;
}

.import-progress {
    flex: 1;
    min-width: 80px;
    height: 8px;
    border-radius: 999px;
    background: #dbeafe;
    overflow: hidden;
}

.import-progress-bar {
    height: 100%;
    border-radius: inherit;
    background: #3b82f6;
}

.import-progress-bar.is-indeterminate {
    width: 36%;
    animation: import-progress-slide 1.15s ease-in-out infinite;
}

.import-progress-bar.is-complete {
    width: 100%;
    transition: width 0.35s ease;
}

@keyframes import-progress-slide {
    0% {
        transform: translateX(-120%);
    }
    100% {
        transform: translateX(320%);
    }
}

.page-header p {
    margin: 0;
    color: #475569;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.list-card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.list-header {
    flex-shrink: 0;
}

.list-header h2 {
    margin: 0 0 16px;
    font-size: 18px;
    color: #1e293b;
}

.file-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.file-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    padding: 6px;
    min-width: 0;
    cursor: pointer;
}

.file-card:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.file-card-empty {
    cursor: default;
    border-style: dashed;
    background: #fff;
}

.file-card-empty:hover {
    border-color: #64748b;
    background: #f8fafc;
}

.file-card-upload {
    border-color: #0f766e;
}

.file-card-upload:hover {
    border-color: #0f766e;
    background: #f0fdfa;
}

.file-card-upload-active {
    border-color: #0f766e;
    background: #ccfbf1;
    box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.25);
}

.file-card-upload-busy {
    opacity: 0.85;
    pointer-events: none;
}

.file-card-upload .file-preview-empty {
    cursor: pointer;
}

.upload-card-error {
    margin: 0;
    padding: 0 12px;
    font-size: 12px;
    color: #b91c1c;
    text-align: center;
}

.upload-input {
    display: none;
}

.file-preview-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 210 / 297;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    overflow: hidden;
}

.file-preview-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #f1f5f9;
    border-style: dashed;
}

.empty-card-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #334155;
}

.empty-card-help {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    text-align: center;
    padding: 0 12px;
}

.file-preview,
.file-preview-image {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    background: #fff;
    pointer-events: none;
}

.file-preview-image {
    object-fit: contain;
}

.file-preview-fallback {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px;
    color: #64748b;
    font-size: 12px;
    text-align: center;
    box-sizing: border-box;
}

.fallback-type {
    margin: 0;
    word-break: break-all;
    color: #94a3b8;
}

.file-card-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0 2px;
    min-width: 0;
}

.file-card-name {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-card-type {
    font-size: 11px;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-card-actions {
    display: flex;
    gap: 6px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-sm {
    padding: 5px 10px;
    flex: 1;
}

.btn-delete {
    flex: 0 0 auto;
    min-width: 64px;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-danger {
    background: #dc2626;
    color: #fff;
}

.btn-danger:disabled,
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.delete-error {
    margin: 12px 0 0;
    color: #b91c1c;
    font-size: 13px;
}

.empty-message {
    margin: 12px 0 0;
    color: #64748b;
}
</style>

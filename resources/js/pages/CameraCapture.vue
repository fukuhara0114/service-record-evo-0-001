<template>
    <div class="camera-page">
        <header class="page-header">
            <div>
                <h1>カメラ</h1>
                <p class="subtitle">モバイルではカメラが起動します。撮影画像は JPEG 品質 90% に圧縮します。</p>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="intakeUrl" class="btn btn-secondary">未登録一覧</a>
            </div>
        </header>

        <section class="card">
            <input
                ref="inputRef"
                type="file"
                class="camera-input"
                accept="image/*"
                capture="environment"
                @change="onFileChange"
            >

            <div class="preview-wrap">
                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    alt="撮影プレビュー"
                    class="preview-image"
                >
                <p v-else class="preview-placeholder">まだ写真がありません</p>
            </div>

            <p v-if="compressInfo" class="info-message">{{ compressInfo }}</p>
            <p v-if="error" class="error-message">{{ error }}</p>
            <p v-if="success" class="success-message">{{ success }}</p>

            <div class="actions">
                <button type="button" class="btn btn-primary" :disabled="busy" @click="openCamera">
                    {{ previewUrl ? '撮り直す' : 'カメラを開く' }}
                </button>
                <button
                    type="button"
                    class="btn btn-secondary"
                    :disabled="busy || !selectedFile"
                    @click="clearPreview"
                >
                    クリア
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="busy || !selectedFile"
                    @click="uploadAsUnregistered"
                >
                    {{ busy ? 'アップロード中...' : '未登録ファイルとして保存' }}
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const inputRef = ref(null)
const selectedFile = ref(null)
const previewUrl = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const compressInfo = ref('')

const JPEG_QUALITY = 0.9

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const intakeUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/intake`)
const uploadUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/intake/upload`)

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '—'
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

function revokePreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = ''
    }
}

function openCamera() {
    error.value = ''
    success.value = ''
    compressInfo.value = ''
    inputRef.value?.click()
}

function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file)
        const image = new Image()
        image.onload = () => {
            URL.revokeObjectURL(url)
            resolve(image)
        }
        image.onerror = () => {
            URL.revokeObjectURL(url)
            reject(new Error('画像の読み込みに失敗しました。'))
        }
        image.src = url
    })
}

function canvasToJpegBlob(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('JPEG 圧縮に失敗しました。'))
                    return
                }
                resolve(blob)
            },
            'image/jpeg',
            quality,
        )
    })
}

async function compressImageToJpeg(file, quality = JPEG_QUALITY) {
    const image = await loadImageFromFile(file)
    const canvas = document.createElement('canvas')
    canvas.width = image.naturalWidth || image.width
    canvas.height = image.naturalHeight || image.height

    if (!canvas.width || !canvas.height) {
        throw new Error('画像サイズを取得できませんでした。')
    }

    const ctx = canvas.getContext('2d')
    if (!ctx) {
        throw new Error('Canvas を初期化できませんでした。')
    }

    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height)

    const blob = await canvasToJpegBlob(canvas, quality)
    const baseName = String(file.name || 'camera').replace(/\.[^.]+$/, '') || 'camera'
    return new File([blob], `${baseName}.jpg`, {
        type: 'image/jpeg',
        lastModified: Date.now(),
    })
}

async function onFileChange(event) {
    error.value = ''
    success.value = ''
    compressInfo.value = ''
    const file = event.target.files?.[0] ?? null
    event.target.value = ''

    revokePreview()
    selectedFile.value = null

    if (!file) return
    if (!String(file.type || '').startsWith('image/')) {
        error.value = '画像ファイルを選択してください。'
        return
    }

    busy.value = true
    try {
        const compressed = await compressImageToJpeg(file, JPEG_QUALITY)
        selectedFile.value = compressed
        previewUrl.value = URL.createObjectURL(compressed)
        compressInfo.value = `JPEG 90%: ${formatBytes(file.size)} → ${formatBytes(compressed.size)}`
    } catch (e) {
        error.value = e.message || '画像の圧縮に失敗しました。'
    } finally {
        busy.value = false
    }
}

function clearPreview() {
    error.value = ''
    success.value = ''
    compressInfo.value = ''
    selectedFile.value = null
    revokePreview()
}

async function uploadAsUnregistered() {
    if (!selectedFile.value || busy.value) return

    busy.value = true
    error.value = ''
    success.value = ''

    try {
        const formData = new FormData()
        formData.append('file', selectedFile.value)
        formData.append('documentName', selectedFile.value.name || `camera-${Date.now()}.jpg`)
        formData.append('documentType', '画像')

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
            throw new Error(validationMessage || data.message || `アップロードに失敗しました。（HTTP ${response.status}）`)
        }

        success.value = data.message || '未登録ファイルとして保存しました。'
        compressInfo.value = ''
        selectedFile.value = null
        revokePreview()
    } catch (e) {
        error.value = e.message || 'アップロードに失敗しました。'
    } finally {
        busy.value = false
    }
}

onBeforeUnmount(() => {
    revokePreview()
})
</script>

<style scoped>
.camera-page {
    min-height: 100vh;
    padding: 20px;
    box-sizing: border-box;
    background: #e2e8f0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 22px;
    color: #1e293b;
}

.subtitle {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.card {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.camera-input {
    display: none;
}

.preview-wrap {
    width: 100%;
    min-height: 280px;
    border: 1px dashed #94a3b8;
    border-radius: 8px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 12px;
}

.preview-image {
    display: block;
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
}

.preview-placeholder {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 14px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.error-message {
    margin: 0 0 10px;
    color: #b91c1c;
    font-size: 13px;
}

.info-message {
    margin: 0 0 10px;
    color: #334155;
    font-size: 13px;
}

.success-message {
    margin: 0 0 10px;
    color: #047857;
    font-size: 13px;
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
    }

    .actions .btn {
        flex: 1 1 100%;
    }
}
</style>

<template>
    <div class="camera-page">
        <header class="page-header">
            <div>
                <h1>カメラ</h1>
                <p class="subtitle">{{ pageSubtitle }}</p>
            </div>
            <div class="header-actions">
                <a :href="homeUrl" class="btn btn-secondary">Home</a>
                <a :href="galleryUrl" class="btn btn-secondary">Gallery</a>
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

            <label class="title-field">
                タイトル
                <input
                    v-model="title"
                    type="text"
                    class="title-input"
                    maxlength="255"
                    placeholder="例: 外観写真"
                    :disabled="busy"
                >
            </label>

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
                    @click="uploadCapturedImage"
                >
                    {{ busy ? 'アップロード中...' : '撮影画像を保存' }}
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    imageMaxEdge: {
        type: Number,
        default: 1024,
    },
    jpegQuality: {
        type: Number,
        default: 90,
    },
})

const page = usePage()
const inputRef = ref(null)
const selectedFile = ref(null)
const previewUrl = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const compressInfo = ref('')
const title = ref('')

const maxEdge = computed(() => {
    const value = Number(props.imageMaxEdge)
    return Number.isFinite(value) && value > 0 ? value : 1024
})

const jpegQualityPercent = computed(() => {
    const value = Number(props.jpegQuality)
    if (!Number.isFinite(value)) return 90
    return Math.min(100, Math.max(1, Math.round(value)))
})

const jpegQualityRatio = computed(() => jpegQualityPercent.value / 100)

const homeUrl = computed(() => page.props.homeUrl ?? `${page.props.appBaseUrl}/home`)
const intakeUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/intake`)
const galleryUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/gallery`)
const uploadUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/camera/upload`)

const pageSubtitle = computed(() =>
    `モバイルではカメラが起動します。撮影画像は最大 ${maxEdge.value}px・JPEG 品質 ${jpegQualityPercent.value}% に圧縮してプレビューします。`,
)

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

function calcFitSize(width, height, maxEdge) {
    const w = Number(width) || 0
    const h = Number(height) || 0
    if (!w || !h) return { width: 0, height: 0, scaled: false }
    const longest = Math.max(w, h)
    if (longest <= maxEdge) {
        return { width: w, height: h, scaled: false }
    }
    const scale = maxEdge / longest
    return {
        width: Math.max(1, Math.round(w * scale)),
        height: Math.max(1, Math.round(h * scale)),
        scaled: true,
    }
}

async function compressImageToJpeg(file, quality = jpegQualityRatio.value, edge = maxEdge.value) {
    const image = await loadImageFromFile(file)
    const srcW = image.naturalWidth || image.width
    const srcH = image.naturalHeight || image.height
    const fitted = calcFitSize(srcW, srcH, edge)

    if (!fitted.width || !fitted.height) {
        throw new Error('画像サイズを取得できませんでした。')
    }

    const canvas = document.createElement('canvas')
    canvas.width = fitted.width
    canvas.height = fitted.height

    const ctx = canvas.getContext('2d')
    if (!ctx) {
        throw new Error('Canvas を初期化できませんでした。')
    }

    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height)

    const blob = await canvasToJpegBlob(canvas, quality)
    return {
        file: new File([blob], 'preview.jpg', {
            type: 'image/jpeg',
            lastModified: Date.now(),
        }),
        width: fitted.width,
        height: fitted.height,
        sourceWidth: srcW,
        sourceHeight: srcH,
    }
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
        const compressed = await compressImageToJpeg(file, jpegQualityRatio.value, maxEdge.value)
        selectedFile.value = compressed.file
        previewUrl.value = URL.createObjectURL(compressed.file)
        compressInfo.value = `最大 ${maxEdge.value}px / JPEG ${jpegQualityPercent.value}%: ${compressed.sourceWidth}×${compressed.sourceHeight} → ${compressed.width}×${compressed.height}（${formatBytes(file.size)} → ${formatBytes(compressed.file.size)}）`
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

async function uploadCapturedImage() {
    if (!selectedFile.value || busy.value) return

    busy.value = true
    error.value = ''
    success.value = ''

    try {
        const formData = new FormData()
        formData.append('file', selectedFile.value)
        formData.append('title', title.value.trim())
        formData.append('associatedID', '-1')

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

        const rawText = await response.text()
        let data = {}
        try {
            data = rawText ? JSON.parse(rawText) : {}
        } catch {
            throw new Error(
                `アップロードに失敗しました。（HTTP ${response.status}） ${rawText.slice(0, 240)}`,
            )
        }

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            const detail = data.error ? ` ${data.error}` : ''
            throw new Error(
                validationMessage
                || `${data.message || `アップロードに失敗しました。（HTTP ${response.status}）`}${detail}`
                || `アップロードに失敗しました。（HTTP ${response.status}）`,
            )
        }

        const savedName = data.image?.file_name ? `（${data.image.file_name}）` : ''
        success.value = `${data.message || '撮影画像を保存しました。'}${savedName}`
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

.title-field {
    display: block;
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.title-input {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 8px 10px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 14px;
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

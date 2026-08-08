<template>
    <div class="camera-page">
        <h1 class="cam-title">SR Cam</h1>

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
                alt="Preview"
                class="preview-image"
            >
            <p v-else class="preview-placeholder">Preview</p>
        </div>

        <p v-if="error" class="status-message is-error">{{ error }}</p>
        <p v-else-if="success" class="status-message is-success">{{ success }}</p>

        <div class="action-stack">
            <button
                type="button"
                class="cam-btn"
                :disabled="busy || !selectedFile"
                @click="uploadCapturedImage"
            >
                {{ busy && selectedFile ? 'Uploading...' : 'Upload' }}
            </button>
            <button
                type="button"
                class="cam-btn"
                :disabled="busy"
                @click="openCamera"
            >
                {{ previewUrl ? 'Capture again' : 'Capture' }}
            </button>
        </div>

        <form class="logout-form" method="POST" :action="logoutUrl">
            <input type="hidden" name="_token" :value="csrfToken">
            <button type="submit" class="cam-btn cam-btn-close" :disabled="busy">Close</button>
        </form>
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

const logoutUrl = computed(() => `${page.props.appBaseUrl}/logout`)
const uploadUrl = computed(() => `${page.props.appBaseUrl}/servicerecord/camera/upload`)
const csrfToken = computed(() => getCsrfToken())

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
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

function calcFitSize(width, height, maxEdgeValue) {
    const w = Number(width) || 0
    const h = Number(height) || 0
    if (!w || !h) return { width: 0, height: 0 }
    const longest = Math.max(w, h)
    if (longest <= maxEdgeValue) {
        return { width: w, height: h }
    }
    const scale = maxEdgeValue / longest
    return {
        width: Math.max(1, Math.round(w * scale)),
        height: Math.max(1, Math.round(h * scale)),
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
    return new File([blob], 'preview.jpg', {
        type: 'image/jpeg',
        lastModified: Date.now(),
    })
}

async function onFileChange(event) {
    error.value = ''
    success.value = ''
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
        selectedFile.value = compressed
        previewUrl.value = URL.createObjectURL(compressed)
    } catch (e) {
        error.value = e.message || '画像の圧縮に失敗しました。'
    } finally {
        busy.value = false
    }
}

async function uploadCapturedImage() {
    if (!selectedFile.value || busy.value) return

    busy.value = true
    error.value = ''
    success.value = ''

    try {
        const formData = new FormData()
        formData.append('file', selectedFile.value)
        formData.append('title', '')
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

        success.value = data.message || '撮影画像を保存しました。'
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
    min-height: 100dvh;
    width: 100%;
    margin: 0;
    padding: 16px;
    box-sizing: border-box;
    background: #cccccc;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
}

.cam-title {
    margin: 0;
    text-align: center;
    font-size: clamp(28px, 6vw, 40px);
    font-weight: 700;
    color: #0f172a;
    flex: 0 0 auto;
}

.camera-input {
    display: none;
}

.preview-wrap {
    width: 100%;
    flex: 1 1 auto;
    min-height: 220px;
    border: 2px solid #0f172a;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-sizing: border-box;
}

.preview-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #fff;
}

.preview-placeholder {
    margin: 0;
    color: #334155;
    font-size: clamp(22px, 4vw, 32px);
    font-weight: 600;
}

.status-message {
    margin: 0;
    font-size: 14px;
    text-align: center;
    flex: 0 0 auto;
}

.status-message.is-error {
    color: #b91c1c;
}

.status-message.is-success {
    color: #047857;
}

.action-stack {
    display: flex;
    flex-direction: column;
    gap: 15px;
    width: 100%;
    flex: 0 0 auto;
}

.cam-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 52px;
    padding: 12px 16px;
    border: 2px solid #0f172a;
    border-radius: 0;
    background: #fff;
    color: #0f172a;
    font-size: clamp(18px, 3.5vw, 24px);
    font-weight: 700;
    text-decoration: none;
    text-align: center;
    box-sizing: border-box;
    cursor: pointer;
}

.action-stack .cam-btn {
    min-height: 104px;
}

.cam-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.logout-form {
    width: 100%;
    margin: 0;
    padding: 0;
}

.cam-btn-close {
    margin-top: 50px;
    min-height: 52px;
}

@media (orientation: landscape) {
    .camera-page {
        padding: 12px 16px;
        gap: 10px;
    }

    .preview-wrap {
        min-height: 160px;
    }

    .action-stack .cam-btn {
        min-height: 88px;
    }

    .cam-btn-close {
        min-height: 44px;
    }
}
</style>

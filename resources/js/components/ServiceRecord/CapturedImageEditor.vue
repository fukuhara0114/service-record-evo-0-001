<template>
    <div class="editor-overlay" @click.self="requestClose">
        <div class="editor-panel">
            <div class="editor-header">
                <div>
                    <h3>画像編集</h3>
                    <p>{{ image.title || image.file_name || '—' }}</p>
                </div>
                <button type="button" class="close-btn" :disabled="busy" @click="requestClose">×</button>
            </div>

            <div class="editor-toolbar">
                <div class="tool-group">
                    <button
                        type="button"
                        class="tool-btn"
                        :disabled="busy || !ready"
                        @click="rotate(-90)"
                    >
                        左回転
                    </button>
                    <button
                        type="button"
                        class="tool-btn"
                        :disabled="busy || !ready"
                        @click="rotate(90)"
                    >
                        右回転
                    </button>
                    <button
                        type="button"
                        class="tool-btn"
                        :class="{ active: tool === 'crop' }"
                        :disabled="busy || !ready"
                        @click="setTool('crop')"
                    >
                        切り抜き
                    </button>
                    <button
                        type="button"
                        class="tool-btn"
                        :class="{ active: tool === 'text' }"
                        :disabled="busy || !ready"
                        @click="setTool('text')"
                    >
                        文字
                    </button>
                    <button
                        type="button"
                        class="tool-btn"
                        :class="{ active: isDrawTool }"
                        :disabled="busy || !ready"
                        @click="setTool('draw')"
                    >
                        描画
                    </button>
                    <button
                        type="button"
                        class="tool-btn"
                        :disabled="busy || !ready || history.length <= 1"
                        @click="undo"
                    >
                        元に戻す
                    </button>
                </div>

                <div v-if="tool === 'crop'" class="tool-options">
                    <button
                        type="button"
                        class="tool-btn tool-btn-accent"
                        :disabled="busy || !cropRect"
                        @click="applyCrop"
                    >
                        切り抜き適用
                    </button>
                    <button
                        type="button"
                        class="tool-btn"
                        :disabled="busy || !cropRect"
                        @click="clearCrop"
                    >
                        選択解除
                    </button>
                </div>

                <div v-else-if="tool === 'text'" class="tool-options">
                    <label class="option-field">
                        文字
                        <input
                            v-model="textValue"
                            type="text"
                            class="option-input option-input-wide"
                            maxlength="80"
                            placeholder="クリック位置に配置"
                            :disabled="busy"
                        >
                    </label>
                    <label class="option-field">
                        色
                        <input v-model="textColor" type="color" class="option-color" :disabled="busy">
                    </label>
                    <label class="option-field">
                        サイズ
                        <input
                            v-model.number="textSize"
                            type="number"
                            min="12"
                            max="120"
                            class="option-input"
                            :disabled="busy"
                        >
                    </label>
                </div>

                <div v-else-if="isDrawTool" class="tool-options">
                    <div class="tool-group">
                        <button
                            type="button"
                            class="tool-btn"
                            :class="{ active: drawMode === 'freehand' }"
                            :disabled="busy"
                            @click="drawMode = 'freehand'"
                        >
                            フリーハンド
                        </button>
                        <button
                            type="button"
                            class="tool-btn"
                            :class="{ active: drawMode === 'rect' }"
                            :disabled="busy"
                            @click="drawMode = 'rect'"
                        >
                            枠矩形
                        </button>
                        <button
                            type="button"
                            class="tool-btn"
                            :class="{ active: drawMode === 'circle' }"
                            :disabled="busy"
                            @click="drawMode = 'circle'"
                        >
                            枠円
                        </button>
                    </div>
                    <label class="option-field">
                        色
                        <input v-model="drawColor" type="color" class="option-color" :disabled="busy">
                    </label>
                    <label class="option-field">
                        太さ
                        <input
                            v-model.number="drawWidth"
                            type="number"
                            min="1"
                            max="40"
                            class="option-input"
                            :disabled="busy"
                        >
                    </label>
                </div>
            </div>

            <div class="editor-body">
                <p v-if="loadError" class="status-message error">{{ loadError }}</p>
                <template v-else>
                    <p v-if="!ready" class="status-message">画像を読み込み中...</p>
                    <!-- Keep canvas mounted while loading; v-if on ready made canvasRef null in loadImage -->
                    <div
                        v-show="ready"
                        class="canvas-wrap"
                        ref="wrapRef"
                    >
                        <canvas
                            ref="canvasRef"
                            class="edit-canvas"
                            :class="{ 'cursor-cross': tool === 'crop' || isDrawTool, 'cursor-text': tool === 'text' }"
                            @pointerdown="onPointerDown"
                            @pointermove="onPointerMove"
                            @pointerup="onPointerUp"
                            @pointercancel="onPointerUp"
                            @pointerleave="onPointerUp"
                        />
                    </div>
                </template>
            </div>

            <div class="editor-footer">
                <p v-if="error" class="status-message error footer-status">{{ error }}</p>
                <p v-else-if="success" class="status-message success footer-status">{{ success }}</p>
                <div class="footer-actions">
                    <button type="button" class="action-btn" :disabled="busy" @click="requestClose">
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="action-btn action-btn-primary"
                        :disabled="busy || !ready || !dirty"
                        @click="saveEditedImage"
                    >
                        {{ busy ? '保存中...' : '保存' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    image: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'saved'])

const page = usePage()

const canvasRef = ref(null)
const wrapRef = ref(null)
const ready = ref(false)
const busy = ref(false)
const dirty = ref(false)
const loadError = ref('')
const error = ref('')
const success = ref('')
const tool = ref('draw')
/** @type {import('vue').Ref<'freehand' | 'rect' | 'circle'>} */
const drawMode = ref('freehand')
const isDrawTool = computed(() => tool.value === 'draw')

const textValue = ref('テキスト')
const textColor = ref('#ff0000')
const textSize = ref(32)
const drawColor = ref('#ff0000')
const drawWidth = ref(4)

/** @type {import('vue').Ref<ImageData[]>} */
const history = ref([])
const MAX_HISTORY = 30

const cropRect = ref(null)
const cropStart = ref(null)
const isDrawing = ref(false)
const lastPoint = ref(null)
const shapeStart = ref(null)

let displayScale = 1

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function getCtx() {
    const canvas = canvasRef.value
    if (!canvas) return null
    return canvas.getContext('2d')
}

function pushHistory() {
    const canvas = canvasRef.value
    const ctx = getCtx()
    if (!canvas || !ctx) return

    try {
        const snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height)
        history.value.push(snapshot)
        if (history.value.length > MAX_HISTORY) {
            history.value.shift()
        }
    } catch {
        // ignore tainted / oversized canvas snapshots
    }
}

function restoreFromHistory(imageData) {
    const canvas = canvasRef.value
    const ctx = getCtx()
    if (!canvas || !ctx || !imageData) return

    canvas.width = imageData.width
    canvas.height = imageData.height
    ctx.putImageData(imageData, 0, 0)
    fitCanvasDisplay()
}

function fitCanvasDisplay() {
    const canvas = canvasRef.value
    const wrap = wrapRef.value
    if (!canvas || !wrap) return

    const maxW = Math.max(120, wrap.clientWidth - 8)
    const maxH = Math.max(120, wrap.clientHeight - 8)
    const scale = Math.min(maxW / canvas.width, maxH / canvas.height, 1)
    displayScale = scale > 0 ? scale : 1
    canvas.style.width = `${Math.round(canvas.width * displayScale)}px`
    canvas.style.height = `${Math.round(canvas.height * displayScale)}px`
}

function restoreBaseImage() {
    const ctx = getCtx()
    const base = history.value[history.value.length - 1]
    if (!ctx || !base) return false
    ctx.putImageData(base, 0, 0)
    return true
}

function redrawCropOverlay() {
    const canvas = canvasRef.value
    const ctx = getCtx()
    if (!canvas || !ctx || !restoreBaseImage()) return

    const rect = cropRect.value
    if (!rect) return

    const x = Math.min(rect.x, rect.x + rect.w)
    const y = Math.min(rect.y, rect.y + rect.h)
    const w = Math.abs(rect.w)
    const h = Math.abs(rect.h)

    // Dim OUTSIDE the selection only — keep INSIDE image visible
    ctx.save()
    ctx.fillStyle = 'rgba(15, 23, 42, 0.5)'
    if (y > 0) {
        ctx.fillRect(0, 0, canvas.width, y)
    }
    if (y + h < canvas.height) {
        ctx.fillRect(0, y + h, canvas.width, canvas.height - (y + h))
    }
    if (h > 0 && x > 0) {
        ctx.fillRect(0, y, x, h)
    }
    if (h > 0 && x + w < canvas.width) {
        ctx.fillRect(x + w, y, canvas.width - (x + w), h)
    }
    ctx.strokeStyle = '#38bdf8'
    ctx.lineWidth = Math.max(2, Math.round(2 / displayScale))
    ctx.setLineDash([6, 4])
    ctx.strokeRect(x + 0.5, y + 0.5, Math.max(0, w - 1), Math.max(0, h - 1))
    ctx.restore()
}

function applyStrokeStyle(ctx) {
    ctx.strokeStyle = drawColor.value || '#ff0000'
    ctx.lineWidth = Math.max(1, Math.min(40, Number(drawWidth.value) || 4))
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.fillStyle = 'transparent'
}

function drawHollowRect(ctx, start, end) {
    const x = Math.min(start.x, end.x)
    const y = Math.min(start.y, end.y)
    const w = Math.abs(end.x - start.x)
    const h = Math.abs(end.y - start.y)
    if (w < 1 && h < 1) return
    ctx.save()
    applyStrokeStyle(ctx)
    ctx.beginPath()
    ctx.strokeRect(x, y, w, h)
    ctx.restore()
}

function drawHollowCircle(ctx, start, end) {
    const cx = (start.x + end.x) / 2
    const cy = (start.y + end.y) / 2
    const rx = Math.abs(end.x - start.x) / 2
    const ry = Math.abs(end.y - start.y) / 2
    if (rx < 0.5 && ry < 0.5) return
    ctx.save()
    applyStrokeStyle(ctx)
    ctx.beginPath()
    ctx.ellipse(cx, cy, Math.max(rx, 0.5), Math.max(ry, 0.5), 0, 0, Math.PI * 2)
    ctx.stroke()
    ctx.restore()
}

function previewShape(end) {
    const ctx = getCtx()
    const start = shapeStart.value
    if (!ctx || !start || !restoreBaseImage()) return

    if (drawMode.value === 'rect') {
        drawHollowRect(ctx, start, end)
    } else if (drawMode.value === 'circle') {
        drawHollowCircle(ctx, start, end)
    }
}

function setTool(next) {
    if (tool.value === 'crop' && next !== 'crop') {
        clearCrop()
    }
    if (isDrawing.value) {
        isDrawing.value = false
        lastPoint.value = null
        shapeStart.value = null
        if (history.value.length) {
            restoreBaseImage()
        }
    }
    tool.value = next
}

function clearCrop() {
    cropRect.value = null
    cropStart.value = null
    if (tool.value === 'crop' && history.value.length) {
        restoreFromHistory(history.value[history.value.length - 1])
    }
}

function canvasPointFromEvent(event) {
    const canvas = canvasRef.value
    if (!canvas) return null
    const bounds = canvas.getBoundingClientRect()
    if (!bounds.width || !bounds.height) return null

    const x = ((event.clientX - bounds.left) / bounds.width) * canvas.width
    const y = ((event.clientY - bounds.top) / bounds.height) * canvas.height
    return {
        x: Math.max(0, Math.min(canvas.width, x)),
        y: Math.max(0, Math.min(canvas.height, y)),
    }
}

function rotate(degrees) {
    const canvas = canvasRef.value
    const ctx = getCtx()
    if (!canvas || !ctx || busy.value) return

    clearCrop()

    const radians = (degrees * Math.PI) / 180
    const srcW = canvas.width
    const srcH = canvas.height
    const destW = Math.abs(degrees) % 180 === 0 ? srcW : srcH
    const destH = Math.abs(degrees) % 180 === 0 ? srcH : srcW

    const temp = document.createElement('canvas')
    temp.width = srcW
    temp.height = srcH
    const tempCtx = temp.getContext('2d')
    if (!tempCtx) return
    tempCtx.drawImage(canvas, 0, 0)

    canvas.width = destW
    canvas.height = destH
    ctx.save()
    ctx.translate(destW / 2, destH / 2)
    ctx.rotate(radians)
    ctx.drawImage(temp, -srcW / 2, -srcH / 2)
    ctx.restore()

    dirty.value = true
    pushHistory()
    fitCanvasDisplay()
}

function applyCrop() {
    const canvas = canvasRef.value
    const ctx = getCtx()
    const rect = cropRect.value
    if (!canvas || !ctx || !rect || busy.value) return

    const x = Math.round(Math.min(rect.x, rect.x + rect.w))
    const y = Math.round(Math.min(rect.y, rect.y + rect.h))
    const w = Math.round(Math.abs(rect.w))
    const h = Math.round(Math.abs(rect.h))

    if (w < 2 || h < 2) {
        error.value = '切り抜き範囲が小さすぎます。'
        return
    }

    // Restore clean base (without overlay) before cropping
    const base = history.value[history.value.length - 1]
    if (base) {
        ctx.putImageData(base, 0, 0)
    }

    const cropped = ctx.getImageData(x, y, w, h)
    canvas.width = w
    canvas.height = h
    ctx.putImageData(cropped, 0, 0)

    cropRect.value = null
    cropStart.value = null
    dirty.value = true
    pushHistory()
    fitCanvasDisplay()
    error.value = ''
}

function placeText(point) {
    const ctx = getCtx()
    if (!ctx || !point) return

    const text = String(textValue.value || '').trim()
    if (!text) {
        error.value = '配置する文字を入力してください。'
        return
    }

    clearCrop()
    const size = Math.max(12, Math.min(120, Number(textSize.value) || 32))
    ctx.save()
    ctx.font = `bold ${size}px sans-serif`
    ctx.fillStyle = textColor.value || '#ff0000'
    ctx.textBaseline = 'top'
    ctx.lineWidth = Math.max(1, Math.round(size / 16))
    ctx.strokeStyle = 'rgba(15, 23, 42, 0.55)'
    ctx.strokeText(text, point.x, point.y)
    ctx.fillText(text, point.x, point.y)
    ctx.restore()

    dirty.value = true
    pushHistory()
    error.value = ''
}

function undo() {
    if (history.value.length <= 1 || busy.value) return

    clearCrop()
    history.value.pop()
    const prev = history.value[history.value.length - 1]
    restoreFromHistory(prev)
    dirty.value = history.value.length > 1
    error.value = ''
    success.value = ''
}

function onPointerDown(event) {
    if (!ready.value || busy.value) return

    const point = canvasPointFromEvent(event)
    if (!point) return

    error.value = ''
    success.value = ''

    if (tool.value === 'text') {
        placeText(point)
        return
    }

    if (tool.value === 'crop') {
        cropStart.value = point
        cropRect.value = { x: point.x, y: point.y, w: 0, h: 0 }
        event.currentTarget.setPointerCapture?.(event.pointerId)
        redrawCropOverlay()
        return
    }

    if (tool.value === 'draw') {
        const ctx = getCtx()
        if (!ctx) return

        if (drawMode.value === 'rect' || drawMode.value === 'circle') {
            shapeStart.value = point
            isDrawing.value = true
            previewShape(point)
            event.currentTarget.setPointerCapture?.(event.pointerId)
            return
        }

        isDrawing.value = true
        lastPoint.value = point
        ctx.save()
        applyStrokeStyle(ctx)
        ctx.beginPath()
        ctx.moveTo(point.x, point.y)
        ctx.lineTo(point.x + 0.01, point.y + 0.01)
        ctx.stroke()
        ctx.restore()
        event.currentTarget.setPointerCapture?.(event.pointerId)
    }
}

function onPointerMove(event) {
    if (!ready.value || busy.value) return
    const point = canvasPointFromEvent(event)
    if (!point) return

    if (tool.value === 'crop' && cropStart.value) {
        cropRect.value = {
            x: cropStart.value.x,
            y: cropStart.value.y,
            w: point.x - cropStart.value.x,
            h: point.y - cropStart.value.y,
        }
        redrawCropOverlay()
        return
    }

    if (tool.value === 'draw' && isDrawing.value) {
        if ((drawMode.value === 'rect' || drawMode.value === 'circle') && shapeStart.value) {
            previewShape(point)
            return
        }

        if (lastPoint.value) {
            const ctx = getCtx()
            if (!ctx) return
            ctx.save()
            applyStrokeStyle(ctx)
            ctx.beginPath()
            ctx.moveTo(lastPoint.value.x, lastPoint.value.y)
            ctx.lineTo(point.x, point.y)
            ctx.stroke()
            ctx.restore()
            lastPoint.value = point
        }
    }
}

function onPointerUp(event) {
    if (tool.value === 'crop' && cropStart.value) {
        cropStart.value = null
        const rect = cropRect.value
        if (rect && Math.abs(rect.w) < 2 && Math.abs(rect.h) < 2) {
            clearCrop()
        }
        try {
            event.currentTarget.releasePointerCapture?.(event.pointerId)
        } catch {
            // ignore
        }
        return
    }

    if (tool.value === 'draw' && isDrawing.value) {
        const point = canvasPointFromEvent(event)
        if ((drawMode.value === 'rect' || drawMode.value === 'circle') && shapeStart.value) {
            if (point) {
                previewShape(point)
                const dx = Math.abs(point.x - shapeStart.value.x)
                const dy = Math.abs(point.y - shapeStart.value.y)
                if (dx >= 2 || dy >= 2) {
                    dirty.value = true
                    pushHistory()
                } else {
                    restoreBaseImage()
                }
            } else {
                restoreBaseImage()
            }
            shapeStart.value = null
            isDrawing.value = false
            lastPoint.value = null
            try {
                event.currentTarget.releasePointerCapture?.(event.pointerId)
            } catch {
                // ignore
            }
            return
        }

        isDrawing.value = false
        lastPoint.value = null
        dirty.value = true
        pushHistory()
        try {
            event.currentTarget.releasePointerCapture?.(event.pointerId)
        } catch {
            // ignore
        }
    }
}

function canvasToJpegBlob(canvas, quality = 0.92) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('画像の書き出しに失敗しました。'))
                    return
                }
                resolve(blob)
            },
            'image/jpeg',
            quality,
        )
    })
}

async function waitForCanvas(attempts = 8) {
    for (let i = 0; i < attempts; i++) {
        await nextTick()
        const canvas = canvasRef.value
        const ctx = canvas?.getContext('2d') ?? null
        if (canvas && ctx) {
            return { canvas, ctx }
        }
        await new Promise((resolve) => requestAnimationFrame(resolve))
    }
    return null
}

async function loadImage() {
    loadError.value = ''
    ready.value = false
    history.value = []
    dirty.value = false
    clearCrop()

    const url = props.image?.image_url
    if (!url) {
        loadError.value = '画像URLがありません。'
        return
    }

    // Ensure the canvas node is in the DOM (v-show keeps it mounted while !ready)
    const mounted = await waitForCanvas()
    if (!mounted) {
        loadError.value = 'Canvas を初期化できませんでした。'
        return
    }

    const img = new Image()
    img.decoding = 'async'
    // Same-origin gallery URLs; avoid crossOrigin so credentials cookies are not required
    try {
        await new Promise((resolve, reject) => {
            img.onload = () => resolve()
            img.onerror = () => reject(new Error('画像の読み込みに失敗しました。'))
            img.src = url
        })
    } catch (e) {
        loadError.value = e.message || '画像の読み込みに失敗しました。'
        return
    }

    const resolved = await waitForCanvas()
    if (!resolved) {
        loadError.value = 'Canvas を初期化できませんでした。'
        return
    }

    const { canvas, ctx } = resolved
    const width = img.naturalWidth || img.width
    const height = img.naturalHeight || img.height
    if (!width || !height) {
        loadError.value = '画像サイズを取得できませんでした。'
        return
    }

    canvas.width = width
    canvas.height = height
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(img, 0, 0)

    pushHistory()
    ready.value = true
    await nextTick()
    fitCanvasDisplay()
}

async function saveEditedImage() {
    if (!ready.value || busy.value || !dirty.value) return

    const canvas = canvasRef.value
    if (!canvas) return

    // Crop overlay must not be baked into the saved JPEG
    const exportCanvas = document.createElement('canvas')
    const base = history.value[history.value.length - 1]
    if (tool.value === 'crop' && cropRect.value && base) {
        exportCanvas.width = base.width
        exportCanvas.height = base.height
        const exportCtx = exportCanvas.getContext('2d')
        if (!exportCtx) {
            error.value = 'Canvas を初期化できませんでした。'
            return
        }
        exportCtx.putImageData(base, 0, 0)
    } else {
        exportCanvas.width = canvas.width
        exportCanvas.height = canvas.height
        const exportCtx = exportCanvas.getContext('2d')
        if (!exportCtx) {
            error.value = 'Canvas を初期化できませんでした。'
            return
        }
        exportCtx.drawImage(canvas, 0, 0)
    }

    busy.value = true
    error.value = ''
    success.value = ''

    try {
        const blob = await canvasToJpegBlob(exportCanvas, 0.92)
        const file = new File([blob], 'edited.jpg', {
            type: 'image/jpeg',
            lastModified: Date.now(),
        })

        const formData = new FormData()
        formData.append('file', file)
        formData.append('source_id', String(props.image.id ?? ''))

        const title = String(props.image.title || '').trim()
        if (title) {
            formData.append('title', title)
        }

        if (props.image.associatedID != null && props.image.associatedID !== '') {
            formData.append('associatedID', String(props.image.associatedID))
        }

        const editUrl = `${page.props.appBaseUrl}/servicerecord/camera/edit`
        const response = await fetch(editUrl, {
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
                `保存に失敗しました。（HTTP ${response.status}） ${rawText.slice(0, 240)}`,
            )
        }

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : null
            const detail = data.error ? ` ${data.error}` : ''
            throw new Error(
                validationMessage
                || `${data.message || `保存に失敗しました。（HTTP ${response.status}）`}${detail}`,
            )
        }

        success.value = data.message || '編集画像を保存しました。'
        emit('saved', data.image ?? null)
    } catch (e) {
        error.value = e.message || '保存に失敗しました。'
    } finally {
        busy.value = false
    }
}

function requestClose() {
    if (busy.value) return
    emit('close')
}

function onWindowResize() {
    if (ready.value) {
        fitCanvasDisplay()
        if (tool.value === 'crop' && cropRect.value) {
            redrawCropOverlay()
        }
    }
}

onMounted(async () => {
    window.addEventListener('resize', onWindowResize)
    await loadImage()
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', onWindowResize)
})
</script>

<style scoped>
.editor-overlay {
    position: fixed;
    inset: 0;
    z-index: 400;
    background: rgba(15, 23, 42, 0.78);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
}

.editor-panel {
    width: min(98vw, 1200px);
    max-height: 96vh;
    background: #0f172a;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    color: #fff;
}

.editor-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 16px;
}

.editor-header h3 {
    margin: 0 0 4px;
    font-size: 16px;
}

.editor-header p {
    margin: 0;
    font-size: 12px;
    color: #cbd5e1;
}

.close-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
}

.close-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    align-items: center;
    padding: 0 16px 10px;
    border-bottom: 1px solid #1e293b;
}

.tool-group,
.tool-options {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.tool-btn {
    padding: 7px 10px;
    border: 1px solid #475569;
    border-radius: 4px;
    background: #1e293b;
    color: #f8fafc;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.tool-btn:hover:not(:disabled) {
    background: #334155;
}

.tool-btn.active {
    border-color: #38bdf8;
    box-shadow: 0 0 0 1px #38bdf8;
}

.tool-btn-accent {
    background: #0369a1;
    border-color: #0284c7;
}

.tool-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.option-field {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #cbd5e1;
}

.option-input {
    width: 64px;
    padding: 5px 6px;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #fff;
    color: #0f172a;
}

.option-input-wide {
    width: min(220px, 42vw);
}

.option-color {
    width: 36px;
    height: 28px;
    padding: 0;
    border: 1px solid #64748b;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
}

.editor-body {
    flex: 1;
    min-height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 16px;
    overflow: hidden;
}

.canvas-wrap {
    width: 100%;
    height: min(62vh, 720px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1e293b;
    border-radius: 6px;
    overflow: auto;
}

.edit-canvas {
    display: block;
    max-width: none;
    background: #fff;
    touch-action: none;
    cursor: default;
}

.edit-canvas.cursor-cross {
    cursor: crosshair;
}

.edit-canvas.cursor-text {
    cursor: text;
}

.editor-footer {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-top: 1px solid #1e293b;
}

.footer-status {
    margin: 0;
    flex: 1 1 200px;
}

.footer-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}

.action-btn {
    padding: 8px 12px;
    border: none;
    border-radius: 4px;
    background: #64748b;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.action-btn-primary {
    background: #2563eb;
}

.status-message {
    margin: 0;
    font-size: 13px;
    color: #cbd5e1;
}

.status-message.error {
    color: #fca5a5;
}

.status-message.success {
    color: #86efac;
}
</style>

<template>
    <div class="preview-overlay" @click.self="$emit('close')">
        <div class="preview-panel" @click.stop>
            <div class="preview-header">
                <div>
                    <h3>メールプレビュー（HTML）</h3>
                    <p v-if="templateLabel" class="preview-sub">{{ templateLabel }}</p>
                </div>
                <button type="button" class="preview-close" @click="$emit('close')">×</button>
            </div>
            <div class="preview-body">
                <div class="preview-meta">
                    <div class="meta-row">
                        <span class="meta-label">To</span>
                        <span class="meta-value">{{ to || '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Subject</span>
                        <span class="meta-value">{{ subject || '—' }}</span>
                    </div>
                </div>

                <div class="html-preview-frame">
                    <div class="html-preview-content" v-html="composedPreviewHtml" />
                </div>

                <div v-if="attachedImages.length" class="preview-images">
                    <div
                        v-for="(image, index) in attachedImages"
                        :key="image.key"
                        class="preview-image-card"
                    >
                        <img :src="image.previewUrl" :alt="image.name">
                        <div class="preview-image-meta">
                            <strong>{{ image.name }}</strong>
                            <button type="button" class="link-btn" @click="removeImage(index)">外す</button>
                        </div>
                    </div>
                </div>
                <p v-else class="preview-images-empty">画像未挿入。Gallery から追加できます（ひな型の gallery-images 位置へ挿入）。</p>

                <p v-if="actionMessage" class="action-message" :class="{ error: actionError }">{{ actionMessage }}</p>
            </div>
            <div class="preview-actions">
                <button type="button" class="action-btn" @click="showGalleryPicker = true">Gallery</button>
                <button
                    type="button"
                    class="action-btn"
                    :disabled="busy"
                    @click="copyBodyToClipboard"
                >
                    本文コピー
                </button>
                <button
                    type="button"
                    class="action-btn action-btn-primary"
                    :disabled="busy"
                    @click="saveAsEml"
                >
                    {{ busyAction === 'eml' ? '保存中...' : 'eml保存' }}
                </button>
                <button type="button" class="action-btn" @click="$emit('close')">閉じる</button>
            </div>
        </div>

        <div
            v-if="showGalleryPicker"
            class="gallery-picker-overlay"
            @click.self="showGalleryPicker = false"
        >
            <div class="gallery-picker-panel" @click.stop>
                <header class="gallery-picker-header">
                    <div>
                        <h3>Gallery から画像を選択</h3>
                        <p>複数選択して「選択した画像を使う」を押してください</p>
                    </div>
                    <button type="button" class="preview-close" @click="showGalleryPicker = false">×</button>
                </header>
                <div class="gallery-picker-body">
                    <CapturedImageGallery
                        :associatedID="associatedId"
                        :associated-id="associatedId"
                        selection-only
                        @confirm-selection="onGalleryConfirm"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CapturedImageGallery from '@/components/ServiceRecord/CapturedImageGallery.vue'

const props = defineProps({
    to: { type: String, default: '' },
    subject: { type: String, default: '' },
    body: { type: String, default: '' },
    bodyHtml: { type: String, default: '' },
    bodyText: { type: String, default: '' },
    templateLabel: { type: String, default: '' },
    associatedId: { type: [Number, String], default: null },
})

defineEmits(['close'])

const localBodyHtml = ref(props.bodyHtml || props.body || '')
const localBodyText = ref(props.bodyText || stripHtmlToText(props.bodyHtml || props.body || ''))
const attachedImages = ref([])
const showGalleryPicker = ref(false)
const busy = ref(false)
const busyAction = ref('')
const actionMessage = ref('')
const actionError = ref(false)

watch(
    () => [props.bodyHtml, props.body, props.bodyText],
    () => {
        localBodyHtml.value = props.bodyHtml || props.body || ''
        localBodyText.value = props.bodyText || stripHtmlToText(localBodyHtml.value)
    },
)

const composedPreviewHtml = computed(() => buildHtmlBody({ useCid: false }))

function stripHtmlToText(html) {
    if (typeof document === 'undefined') {
        return String(html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
    }
    const el = document.createElement('div')
    el.innerHTML = html || ''
    return (el.innerText || el.textContent || '').trim()
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function extractBodyInner(html) {
    const source = String(html || '')
    const bodyMatch = source.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
    if (bodyMatch?.[1]) return bodyMatch[1].trim()
    return source
}

function buildGalleryImageHtml({ useCid = false } = {}) {
    return attachedImages.value.map((image, index) => {
        const src = useCid ? `cid:${image.cid}` : image.dataUrl
        const alt = escapeHtml(image.name || `image-${index + 1}`)
        return `<div style="margin:16px 0;"><img src="${src}" alt="${alt}" style="max-width:100%;height:auto;border:0;"></div>`
    }).join('\n')
}

/**
 * ひな型の目印位置へ画像を差し込む。
 * 対応: <div id="gallery-images"></div> / {galleryImages}
 * 目印が無い場合は末尾追加用に injected=false を返す。
 */
function injectGalleryImages(innerHtml, imageHtml) {
    const source = String(innerHtml || '')
    const divMarker = /<div\b[^>]*\bid\s*=\s*["']gallery-images["'][^>]*>[\s\S]*?<\/div>/i
    if (divMarker.test(source)) {
        const replacement = `<div id="gallery-images">${imageHtml || ''}</div>`
        return {
            html: source.replace(divMarker, replacement),
            injected: true,
        }
    }

    if (source.includes('{galleryImages}')) {
        return {
            html: source.split('{galleryImages}').join(imageHtml || ''),
            injected: true,
        }
    }

    return {
        html: source,
        injected: false,
    }
}

function buildHtmlBody({ useCid = false } = {}) {
    const imageHtml = buildGalleryImageHtml({ useCid })
    const inner = extractBodyInner(localBodyHtml.value)
    const { html: bodyInner, injected } = injectGalleryImages(inner, imageHtml)

    return [
        '<!DOCTYPE html>',
        '<html lang="ja"><head><meta charset="UTF-8"></head>',
        '<body style="margin:0;padding:0;background:#ffffff;">',
        '<div style="font-family:\'Segoe UI\',Meiryo,\'Hiragino Kaku Gothic ProN\',sans-serif;font-size:14px;line-height:1.7;color:#111827;padding:16px;">',
        bodyInner,
        injected ? '' : imageHtml,
        '</div>',
        '</body></html>',
    ].join('\n')
}

async function fetchImageAsData(image) {
    const sourceUrl = image.image_url || image.thumbnail_url
    if (!sourceUrl) {
        throw new Error(`画像 URL がありません（${image.file_name || image.id}）`)
    }
    const response = await fetch(sourceUrl, {
        credentials: 'same-origin',
        headers: {
            Accept: 'image/*,application/octet-stream',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    if (!response.ok) {
        throw new Error(`画像の取得に失敗しました。（HTTP ${response.status}）`)
    }
    const blob = await response.blob()
    const dataUrl = await blobToDataUrl(blob)
    const mime = blob.type || guessMimeFromName(image.file_name) || 'image/jpeg'
    const ext = mimeToExt(mime)
    const name = String(image.title || image.file_name || `image-${image.id}.${ext}`)
    return {
        key: `img-${image.id}-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        id: image.id,
        name,
        mime,
        ext,
        cid: `img${image.id}@servicerecord.local`,
        blob,
        dataUrl,
        previewUrl: image.thumbnail_url || dataUrl,
    }
}

function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(String(reader.result || ''))
        reader.onerror = () => reject(new Error('画像の読み込みに失敗しました。'))
        reader.readAsDataURL(blob)
    })
}

function guessMimeFromName(fileName) {
    const name = String(fileName || '').toLowerCase()
    if (name.endsWith('.png')) return 'image/png'
    if (name.endsWith('.gif')) return 'image/gif'
    if (name.endsWith('.webp')) return 'image/webp'
    if (name.endsWith('.bmp')) return 'image/bmp'
    return 'image/jpeg'
}

function mimeToExt(mime) {
    if (mime === 'image/png') return 'png'
    if (mime === 'image/gif') return 'gif'
    if (mime === 'image/webp') return 'webp'
    if (mime === 'image/bmp') return 'bmp'
    return 'jpg'
}

async function onGalleryConfirm(images) {
    const incoming = Array.isArray(images) ? images : []
    if (!incoming.length) {
        showGalleryPicker.value = false
        return
    }

    busy.value = true
    busyAction.value = 'gallery'
    actionMessage.value = ''
    actionError.value = false
    try {
        const next = [...attachedImages.value]
        for (const image of incoming) {
            if (!image?.id) continue
            if (next.some((item) => item.id === image.id)) continue
            next.push(await fetchImageAsData(image))
        }
        attachedImages.value = next
        actionMessage.value = `${incoming.length} 件の画像を HTML メールに挿入しました。`
        showGalleryPicker.value = false
    } catch (e) {
        actionError.value = true
        actionMessage.value = e.message || '画像の挿入に失敗しました。'
    } finally {
        busy.value = false
        busyAction.value = ''
    }
}

function removeImage(index) {
    attachedImages.value.splice(index, 1)
}

async function copyBodyToClipboard() {
    busy.value = true
    busyAction.value = 'copy'
    actionMessage.value = ''
    actionError.value = false
    try {
        const html = buildHtmlBody({ useCid: false })
        const plain = [
            localBodyText.value || stripHtmlToText(localBodyHtml.value),
            ...attachedImages.value.map((image, index) => `[画像${index + 1}: ${image.name}]`),
        ].filter(Boolean).join('\n\n')

        if (navigator.clipboard?.write && window.ClipboardItem) {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([plain], { type: 'text/plain' }),
                }),
            ])
        } else if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(plain)
        } else {
            throw new Error('このブラウザではクリップボードコピーに対応していません。')
        }
        actionMessage.value = attachedImages.value.length
            ? 'HTML 本文と画像をクリップボードにコピーしました。'
            : 'HTML 本文をクリップボードにコピーしました。'
    } catch (e) {
        actionError.value = true
        actionMessage.value = e.message || 'コピーに失敗しました。'
    } finally {
        busy.value = false
        busyAction.value = ''
    }
}

function encodeUtf8Base64(text) {
    const bytes = new TextEncoder().encode(text)
    let binary = ''
    bytes.forEach((b) => {
        binary += String.fromCharCode(b)
    })
    return btoa(binary)
}

function chunkBase64(value) {
    return String(value).replace(/(.{76})/g, '$1|$|').split('|$|').join('\r\n')
}

function encodeHeader(value) {
    const text = String(value ?? '')
    if (!text) return ''
    if (/^[\x20-\x7E]*$/.test(text)) return text
    const b64 = encodeUtf8Base64(text)
    return `=?UTF-8?B?${b64}?=`
}

function buildEmlContent() {
    const relatedBoundary = `----=_Related_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`
    const altBoundary = `----=_Alt_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`
    const html = buildHtmlBody({ useCid: true })
    const plain = [
        localBodyText.value || stripHtmlToText(localBodyHtml.value),
        ...attachedImages.value.map((image, index) => `[画像${index + 1}: ${image.name}]`),
    ].filter(Boolean).join('\r\n\r\n')

    const headers = [
        'X-Unsent: 1',
        'MIME-Version: 1.0',
        `To: ${encodeHeader(props.to || '')}`,
        `Subject: ${encodeHeader(props.subject || '')}`,
        `Date: ${new Date().toUTCString()}`,
        `Content-Type: multipart/related; type="multipart/alternative"; boundary="${relatedBoundary}"`,
    ]

    const altPart = [
        `--${relatedBoundary}`,
        `Content-Type: multipart/alternative; boundary="${altBoundary}"`,
        '',
        `--${altBoundary}`,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunkBase64(encodeUtf8Base64(plain)),
        `--${altBoundary}`,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunkBase64(encodeUtf8Base64(html)),
        `--${altBoundary}--`,
    ].join('\r\n')

    const imageParts = attachedImages.value.map((image) => {
        const base64 = (image.dataUrl.split(',')[1] || '').replace(/\s+/g, '')
        return [
            `--${relatedBoundary}`,
            `Content-Type: ${image.mime}; name="${image.name.replace(/"/g, '')}"`,
            'Content-Transfer-Encoding: base64',
            `Content-ID: <${image.cid}>`,
            `Content-Disposition: inline; filename="${image.name.replace(/"/g, '')}"`,
            '',
            chunkBase64(base64),
        ].join('\r\n')
    })

    return `${headers.join('\r\n')}\r\n\r\n${[altPart, ...imageParts, `--${relatedBoundary}--`].join('\r\n')}\r\n`
}

function safeFilename(value) {
    const raw = String(value || 'mail-draft').replace(/[\\/:*?"<>|]+/g, '_')
    return raw.slice(0, 80) || 'mail-draft'
}

async function saveAsEml() {
    busy.value = true
    busyAction.value = 'eml'
    actionMessage.value = ''
    actionError.value = false
    try {
        const eml = buildEmlContent()
        const blob = new Blob([eml], { type: 'message/rfc822' })
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = `${safeFilename(props.subject || 'mail-draft')}.eml`
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(url)
        actionMessage.value = 'HTML メールの eml ファイルを保存しました。'
    } catch (e) {
        actionError.value = true
        actionMessage.value = e.message || 'eml 保存に失敗しました。'
    } finally {
        busy.value = false
        busyAction.value = ''
    }
}
</script>

<style scoped>
.preview-overlay {
    position: fixed;
    inset: 0;
    z-index: 440;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
}

.preview-panel {
    width: min(860px, 100%);
    max-height: min(92vh, 920px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.preview-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: #1e293b;
    color: #fff;
}

.preview-header h3 {
    margin: 0;
    font-size: 16px;
}

.preview-sub {
    margin: 4px 0 0;
    font-size: 12px;
    color: #cbd5e1;
}

.preview-close {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.preview-body {
    padding: 16px;
    overflow: auto;
    min-height: 0;
    flex: 1 1 auto;
}

.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.meta-row {
    display: grid;
    grid-template-columns: 72px 1fr;
    gap: 8px;
    font-size: 13px;
}

.meta-label {
    color: #64748b;
    font-weight: 700;
}

.meta-value {
    color: #0f172a;
    word-break: break-word;
}

.html-preview-frame {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.html-preview-content {
    padding: 0;
    min-height: 180px;
    background: #fff;
}

.html-preview-content :deep(img) {
    max-width: 100%;
    height: auto;
}

.preview-images {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
}

.preview-image-card {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
}

.preview-image-card img {
    display: block;
    width: 100%;
    height: 110px;
    object-fit: cover;
    background: #e2e8f0;
}

.preview-image-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 6px 8px;
    font-size: 11px;
}

.preview-image-meta strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.preview-images-empty {
    margin: 12px 0 0;
    font-size: 12px;
    color: #64748b;
}

.link-btn {
    border: none;
    background: transparent;
    color: #2563eb;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}

.action-message {
    margin: 12px 0 0;
    font-size: 13px;
    color: #0f766e;
}

.action-message.error {
    color: #b91c1c;
}

.preview-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 16px 16px;
}

.action-btn {
    padding: 8px 14px;
    border: 1px solid #94a3b8;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.action-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.action-btn-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.gallery-picker-overlay {
    position: fixed;
    inset: 0;
    z-index: 460;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.5);
}

.gallery-picker-panel {
    width: min(1100px, 100%);
    max-height: min(92vh, 900px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.35);
}

.gallery-picker-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: #0f172a;
    color: #fff;
}

.gallery-picker-header h3 {
    margin: 0;
    font-size: 16px;
}

.gallery-picker-header p {
    margin: 4px 0 0;
    font-size: 12px;
    color: #cbd5e1;
}

.gallery-picker-body {
    min-height: 0;
    flex: 1 1 auto;
    overflow: auto;
    padding: 12px 16px 16px;
    background: #f8fafc;
}
</style>

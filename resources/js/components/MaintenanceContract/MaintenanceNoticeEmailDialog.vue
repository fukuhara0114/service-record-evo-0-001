<template>
    <div class="notice-overlay" @click.self="onCancel">
        <div
            class="notice-panel"
            :class="{ 'notice-panel-preview': step === 'preview' }"
            @click.stop
        >
            <div class="notice-header">
                <h3>{{ headerTitle }}</h3>
                <button type="button" class="notice-close" @click="onCancel">×</button>
            </div>

            <div v-if="step === 'choose'" class="notice-choose">
                <button type="button" class="btn btn-primary" @click="openPreview('recalibration')">
                    再校正案内
                </button>
                <button type="button" class="btn btn-primary" @click="openPreview('renewal')">
                    継続案内
                </button>
                <button type="button" class="btn btn-secondary" @click="onCancel">
                    キャンセル
                </button>
            </div>

            <div v-else class="notice-preview">
                <div class="preview-toolbar">
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="busy"
                        @click="saveAsEml"
                    >
                        {{ busy ? '保存中...' : 'emlを保存' }}
                    </button>
                    <p v-if="actionMessage" class="action-message" :class="{ error: actionError }">{{ actionMessage }}</p>
                </div>
                <div class="preview-meta">
                    <span class="meta-label">件名</span>
                    <p class="meta-subject">{{ composed.subject || '—' }}</p>
                </div>
                <div class="html-preview-frame">
                    <div class="html-preview-content" v-html="composed.bodyHtml" />
                </div>
                <div class="preview-actions">
                    <button type="button" class="btn btn-secondary" @click="step = 'choose'">戻る</button>
                    <button type="button" class="btn btn-secondary" @click="onCancel">閉じる</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import recalibrationTemplate from '@/email-templates/maintenance-recalibration-notice.txt?raw'
import renewalTemplate from '@/email-templates/maintenance-renewal-notice.txt?raw'
import {
    fillMaintenanceNoticeTemplate,
    parseMaintenanceNoticeEmail,
    maintenanceNoticeBodyToHtml,
    wrapMaintenanceNoticeHtml,
} from '@/utils/maintenanceNoticeEmail'

const props = defineProps({
    contract: {
        type: Object,
        required: true,
    },
    contractTypes: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])

const page = usePage()
const step = ref('choose')
const noticeType = ref(null)
const busy = ref(false)
const actionMessage = ref('')
const actionError = ref(false)

const headerTitle = computed(() => {
    if (step.value !== 'preview') return '案内E-メール'
    return noticeType.value === 'renewal' ? '継続案内プレビュー' : '再校正案内プレビュー'
})

const usernameKanji = computed(() => String(page.props.authUser?.kanji_name ?? '').trim())

const composed = computed(() => {
    const template = noticeType.value === 'renewal' ? renewalTemplate : recalibrationTemplate
    const filled = fillMaintenanceNoticeTemplate(template, {
        contract: props.contract,
        usernameKanji: usernameKanji.value,
        contractTypes: props.contractTypes,
    })
    const parsed = parseMaintenanceNoticeEmail(filled)
    const bodyHtml = maintenanceNoticeBodyToHtml(parsed.body)
    return {
        ...parsed,
        bodyHtml,
        htmlDocument: wrapMaintenanceNoticeHtml(bodyHtml),
    }
})

const toAddress = computed(() => String(props.contract?.email ?? '').trim())

function openPreview(type) {
    noticeType.value = type
    step.value = 'preview'
    actionMessage.value = ''
    actionError.value = false
}

function onCancel() {
    emit('close')
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
    return `=?UTF-8?B?${encodeUtf8Base64(text)}?=`
}

function safeFilename(value) {
    const raw = String(value || '案内メール').replace(/[\\/:*?"<>|]+/g, '_')
    return raw.slice(0, 80) || '案内メール'
}

function buildEmlContent() {
    const subject = composed.value.subject || '案内メール'
    const plain = String(composed.value.body ?? '').replace(/\r\n|\n|\r/g, '\r\n')
    const html = composed.value.htmlDocument
    const boundary = `----=_Alt_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`
    const headers = [
        'X-Unsent: 1',
        'MIME-Version: 1.0',
    ]
    if (toAddress.value) {
        headers.push(`To: ${toAddress.value}`)
    }
    headers.push(
        `Subject: ${encodeHeader(subject)}`,
        `Date: ${new Date().toUTCString()}`,
        `Content-Type: multipart/alternative; boundary="${boundary}"`,
    )
    const parts = [
        `--${boundary}`,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunkBase64(encodeUtf8Base64(plain)),
        `--${boundary}`,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunkBase64(encodeUtf8Base64(html)),
        `--${boundary}--`,
    ]
    return `${headers.join('\r\n')}\r\n\r\n${parts.join('\r\n')}\r\n`
}

async function saveAsEml() {
    if (busy.value) return
    busy.value = true
    actionMessage.value = ''
    actionError.value = false
    try {
        const eml = buildEmlContent()
        const blob = new Blob([eml], { type: 'message/rfc822' })
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = `${safeFilename(composed.value.subject)}.eml`
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        URL.revokeObjectURL(url)
        actionMessage.value = 'eml ファイルを保存しました。'
    } catch (e) {
        actionError.value = true
        actionMessage.value = e.message || 'eml 保存に失敗しました。'
    } finally {
        busy.value = false
    }
}
</script>

<style scoped>
.notice-overlay {
    position: fixed;
    inset: 0;
    z-index: 220;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.5);
    padding: 16px;
}

.notice-panel {
    width: min(420px, 100%);
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.notice-panel-preview {
    width: min(880px, 100%);
    height: min(90vh, 900px);
}

.notice-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: #1e293b;
    color: #fff;
    flex: 0 0 auto;
}

.notice-header h3 {
    margin: 0;
    font-size: 16px;
}

.notice-close {
    border: none;
    background: transparent;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.notice-choose {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 20px 16px;
}

.notice-preview {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.preview-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px 0;
    flex: 0 0 auto;
}

.action-message {
    margin: 0;
    font-size: 13px;
    color: #15803d;
}

.action-message.error {
    color: #b91c1c;
}

.preview-meta {
    flex: 0 0 auto;
    padding: 12px 16px 0;
}

.meta-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 4px;
}

.meta-subject {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.5;
}

.html-preview-frame {
    flex: 1 1 auto;
    min-height: 0;
    margin: 12px 16px;
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
}

.html-preview-content {
    padding: 16px;
    font-family: 'Segoe UI', Meiryo, 'Hiragino Kaku Gothic ProN', sans-serif;
    font-size: 14px;
    line-height: 1.7;
    color: #111827;
}

.html-preview-content :deep(a) {
    color: #1d4ed8;
}

.preview-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 0 16px 16px;
    flex: 0 0 auto;
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #64748b;
    color: #fff;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.notice-choose .btn {
    width: 100%;
}
</style>

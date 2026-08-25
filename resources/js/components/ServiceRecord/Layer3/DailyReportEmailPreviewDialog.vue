<template>
    <div class="preview-overlay" @click.self="$emit('close')">
        <div class="preview-panel" @click.stop>
            <div class="preview-header">
                <h3>メール・プレビュー</h3>
                <div class="header-actions">
                    <button
                        type="button"
                        class="eml-btn"
                        :disabled="busy || !rows.length"
                        @click="saveAsEml"
                    >
                        {{ busy ? '保存中...' : 'emlとして保存' }}
                    </button>
                    <button type="button" class="preview-close" @click="$emit('close')">×</button>
                </div>
            </div>

            <div class="preview-body">
                <div class="preview-meta">
                    <div class="meta-row">
                        <span class="meta-label">宛先</span>
                        <span class="meta-value">{{ toDisplay }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Cc</span>
                        <span class="meta-value">{{ ccDisplay }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Subject</span>
                        <span class="meta-value">{{ emailSubject }}</span>
                    </div>
                </div>

                <div class="html-preview-frame">
                    <div class="email-body" v-html="bodyInnerHtml" />
                    <p v-if="!rows.length" class="empty-message">プレビューする行がありません。</p>
                </div>

                <p v-if="actionMessage" class="action-message" :class="{ error: actionError }">{{ actionMessage }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const TO_ADDRESSES = ['emmachen@xrite.com', 'keihonda@xrite.com']
const CC_ADDRESSES = ['JapanServiceTeam@XRITE.com']
const TABLE_HEADERS = ['Date', 'RMA', 'Product', 'SN', '販売店', '対応内容', 'Service Type']

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    subject: {
        type: String,
        default: '',
    },
})

defineEmits(['close'])

const page = usePage()
const busy = ref(false)
const actionMessage = ref('')
const actionError = ref(false)

const signature = computed(() => String(page.props.authUser?.signature ?? '').trim())
const toDisplay = computed(() => TO_ADDRESSES.join('; '))
const ccDisplay = computed(() => CC_ADDRESSES.join('; '))
const emailSubject = computed(() => props.subject || 'Daily Report')

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function rowCells(row) {
    return [
        row.date,
        row.rma,
        row.product,
        row.sn,
        row.dealer,
        row.response,
        row.serviceType,
    ]
}

const tableHtml = computed(() => {
    const header = TABLE_HEADERS
        .map((label) => `<th style="background:#111111;color:#ffffff;border:1px solid #333333;padding:6px 8px;text-align:left;font-weight:700;">${escapeHtml(label)}</th>`)
        .join('')
    const body = props.rows.map((row) => {
        const cells = rowCells(row)
            .map((value) => `<td style="border:1px solid #333333;padding:6px 8px;text-align:left;white-space:nowrap;">${escapeHtml(value)}</td>`)
            .join('')
        return `<tr>${cells}</tr>`
    }).join('')
    return `<table style="border-collapse:collapse;font-family:sans-serif;font-size:13px;margin:16px 0;">`
        + `<thead><tr>${header}</tr></thead>`
        + `<tbody>${body}</tbody>`
        + `</table>`
})

function wrapEmailHtml(inner) {
    return [
        '<!DOCTYPE html>',
        '<html lang="en"><head><meta charset="UTF-8"></head>',
        '<body style="margin:0;padding:0;background:#ffffff;">',
        '<div style="font-family:\'Segoe UI\',Meiryo,\'Hiragino Kaku Gothic ProN\',sans-serif;font-size:14px;line-height:1.6;color:#111111;padding:8px 0;">',
        inner,
        '</div>',
        '</body></html>',
    ].join('\n')
}

const bodyInnerHtml = computed(() => {
    const sign = escapeHtml(signature.value)
    return [
        '<p style="margin:0 0 16px;">Dear Emma-san, Dear Honda-san,</p>',
        '<p style="margin:0 0 16px;">Please find today\'s completed device report attached.</p>',
        tableHtml.value,
        '<p style="margin:16px 0;">Could you please review it and let me know if you have any questions?</p>',
        '<p style="margin:16px 0 0;">Best regards,</p>',
        `<p style="margin:16px 0 0;">${sign}</p>`,
    ].join('\n')
})

const plainText = computed(() => {
    const header = TABLE_HEADERS.join('\t')
    const lines = props.rows.map((row) => rowCells(row).map((value) => String(value ?? '')).join('\t'))
    return [
        'Dear Emma-san, Dear Honda-san,',
        '',
        "Please find today's completed device report attached.",
        '',
        header,
        ...lines,
        '',
        'Could you please review it and let me know if you have any questions?',
        '',
        'Best regards,',
        '',
        signature.value,
    ].join('\r\n')
})

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
    const raw = String(value || 'Daily Report').replace(/[\\/:*?"<>|]+/g, '_')
    return raw.slice(0, 80) || 'Daily Report'
}

function buildEmlContent() {
    const boundary = `----=_Alt_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`
    const html = wrapEmailHtml(bodyInnerHtml.value)
    const headers = [
        'X-Unsent: 1',
        'MIME-Version: 1.0',
        `To: ${TO_ADDRESSES.join(', ')}`,
        `Cc: ${CC_ADDRESSES.join(', ')}`,
        `Subject: ${encodeHeader(emailSubject.value)}`,
        `Date: ${new Date().toUTCString()}`,
        `Content-Type: multipart/alternative; boundary="${boundary}"`,
    ]
    const parts = [
        `--${boundary}`,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunkBase64(encodeUtf8Base64(plainText.value)),
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
    if (!props.rows.length || busy.value) return
    busy.value = true
    actionMessage.value = ''
    actionError.value = false
    try {
        const eml = buildEmlContent()
        const blob = new Blob([eml], { type: 'message/rfc822' })
        const url = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.download = `${safeFilename(emailSubject.value)}.eml`
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
    width: min(920px, 100%);
    max-height: min(92vh, 900px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
    overflow: hidden;
}

.preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: #1e293b;
    color: #fff;
    flex-shrink: 0;
}

.preview-header h3 {
    margin: 0;
    font-size: 16px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.eml-btn {
    height: 32px;
    padding: 0 12px;
    border: none;
    border-radius: 6px;
    background: #0f766e;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.eml-btn:hover:not(:disabled) {
    background: #0d9488;
}

.eml-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.preview-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.preview-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    min-height: 0;
    overflow: hidden;
}

.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
}

.meta-row {
    display: flex;
    gap: 10px;
    font-size: 13px;
}

.meta-label {
    flex: 0 0 72px;
    font-weight: 700;
    color: #475569;
}

.meta-value {
    font-weight: 600;
    color: #0f172a;
    word-break: break-all;
}

.html-preview-frame {
    flex: 1 1 auto;
    min-height: 240px;
    overflow: auto;
    border: 1px solid #cbd5e1;
    background: #fff;
    padding: 16px 20px;
}

.email-body {
    color: #111;
    font-size: 14px;
    line-height: 1.6;
}

.empty-message {
    margin: 24px 0;
    text-align: center;
    color: #64748b;
}

.action-message {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #0f766e;
    flex-shrink: 0;
}

.action-message.error {
    color: #b91c1c;
}
</style>

const DATE_FIELDS = new Set([
    'startDate',
    'expireDate',
    'certificationExpireDate',
    'renewalInformation',
    'informedDate',
    'renewedDate',
    'shippingDate',
    'orderedDate',
])

const RED_DATE_MARK = '%%RED_DATE%%'

export function formatMaintenanceEmailDate(value) {
    const text = String(value ?? '').trim()
    if (!text) return ''
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (match) return `${match[1]}/${match[2]}/${match[3]}`
    return text
}

function textBeforeSpace(value) {
    const text = String(value ?? '').trim()
    if (!text) return ''
    return text.split(/[\s\u3000]+/, 2)[0] ?? ''
}

function contractFieldValue(contract, field) {
    const raw = contract?.[field]
    if (raw == null) return ''
    if (DATE_FIELDS.has(field)) return formatMaintenanceEmailDate(raw)
    const text = String(raw).trim()
    if (field === 'contact' || field === 'endUser_contact') {
        return textBeforeSpace(text)
    }
    return text
}

function resolveContractTypeName(contract, contractTypes) {
    const named = String(contract?.contractTypeName ?? '').trim()
    if (named) return named

    const typeId = String(contract?.contractType ?? '').trim()
    if (!typeId) return ''
    const found = (contractTypes ?? []).find((type) => String(type?.id) === typeId)
    return String(found?.contractType ?? '').trim()
}

export function fillMaintenanceNoticeTemplate(template, { contract, usernameKanji, contractTypes } = {}) {
    return String(template ?? '').replace(/【([^】]+)】/g, (_, rawKey) => {
        const key = String(rawKey ?? '').trim()
        if (key === 'username_kanji') {
            return String(usernameKanji ?? '').trim()
        }

        if (/^maintenance_contract_type\[(?:maintenanscontractmaster|maintenancecontractmaster)\.contractType\]$/i.test(key)) {
            return resolveContractTypeName(contract, contractTypes)
        }

        const match = key.match(/^(?:maintenanscontractmaster|maintenancecontractmaster)\.(.+)$/i)
        if (match) {
            return contractFieldValue(contract, match[1])
        }

        return ''
    })
}

export function parseMaintenanceNoticeEmail(filledText) {
    const source = String(filledText ?? '').replace(/^\uFEFF/, '')
    const lines = source.split(/\r?\n/)
    const first = lines[0] ?? ''
    const subjectMatch = first.match(/^件名[：:]\s*(.*)$/)
    const subject = subjectMatch ? subjectMatch[1].trim() : ''
    const bodyStart = subjectMatch ? 1 : 0
    const body = lines.slice(bodyStart).join('\n').replace(/^\s*\n/, '')
    return { subject, body }
}

export function escapeMaintenanceNoticeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
}

function linkifyHtml(html) {
    return html.replace(
        /(https?:\/\/[^\s<&]+)/g,
        '<a href="$1" style="color:#1d4ed8;text-decoration:underline;">$1</a>',
    )
}

export function maintenanceNoticeBodyToHtml(body) {
    const marked = String(body ?? '').replace(/20XX\/XX\/XX/g, RED_DATE_MARK)
    let html = escapeMaintenanceNoticeHtml(marked)
    html = html.replace(
        new RegExp(RED_DATE_MARK, 'g'),
        '<span style="color:#dc2626;font-weight:700;">20XX/XX/XX</span>',
    )
    html = linkifyHtml(html)
    return html.replace(/\r\n|\n|\r/g, '<br>\n')
}

export function wrapMaintenanceNoticeHtml(innerHtml) {
    return [
        '<!DOCTYPE html>',
        '<html lang="ja"><head><meta charset="UTF-8"></head>',
        '<body style="margin:0;padding:0;background:#ffffff;">',
        '<div style="font-family:\'Segoe UI\',Meiryo,\'Hiragino Kaku Gothic ProN\',sans-serif;font-size:14px;line-height:1.7;color:#111827;padding:16px;">',
        innerHtml,
        '</div>',
        '</body></html>',
    ].join('\n')
}

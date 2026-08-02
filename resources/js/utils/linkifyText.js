/**
 * Escape HTML then turn http(s) URLs into clickable anchors.
 */
export function linkifyText(value) {
    const text = value == null ? '' : String(value)
    if (!text) return ''

    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')

    return escaped.replace(
        /(https?:\/\/[^\s<]+)/g,
        (url) => {
            const href = url.replace(/[),.;:!?]+$/g, '')
            const trailing = url.slice(href.length)
            return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="note-autolink">${href}</a>${trailing}`
        },
    )
}

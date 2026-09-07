const COMPANY_MARKS = ['株式会社', '㈱', '（株）', '(株)']

function searchCharPrefix(value, length) {
    const text = String(value ?? '').trim()
    if (!text) return ''
    return Array.from(text).slice(0, length).join('')
}

export function stripDealerCompanyMarks(name) {
    let text = String(name ?? '').trim()
    let changed = true
    while (changed && text) {
        changed = false
        for (const mark of COMPANY_MARKS) {
            if (text.startsWith(mark)) {
                text = text.slice(mark.length).trim()
                changed = true
            }
            if (text.endsWith(mark)) {
                text = text.slice(0, -mark.length).trim()
                changed = true
            }
        }
    }
    return text
}

export function buildCaseSearchKeywords({
    productName = '',
    SN = '',
    dealer = '',
    contactPerson = '',
} = {}) {
    return {
        productName: searchCharPrefix(productName, 3),
        SN: String(SN ?? '').trim(),
        dealer: searchCharPrefix(stripDealerCompanyMarks(dealer), 3),
        contactPerson: searchCharPrefix(contactPerson, 1),
    }
}

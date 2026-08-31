/**
 * original_order_type と保存しようとしている order_type が
 * service グループ ↔ loaner/waiting_list グループを跨ぐとき、保存前確認を出す。
 *
 * - original_order_type 未設定（null/空）の既存行は判定不可のためスキップ
 * - 同一グループ内（loaner ↔ waiting_list、service のまま）は警告なし
 */

function normalizeOrderType(value) {
    if (value == null) return ''
    return String(value).trim().toLowerCase()
}

export function isLoanerOrderTypeGroup(orderType) {
    const t = normalizeOrderType(orderType)
    return t === 'loaner' || t === 'waiting_list'
}

export function isServiceOrderTypeGroup(orderType) {
    const t = normalizeOrderType(orderType)
    return t === '' || t === 'service'
}

/**
 * @returns {boolean} true=保存続行 / false=ユーザーがキャンセル
 */
export function confirmOrderTypeOriginalMismatch(originalOrderType, savingOrderType) {
    const original = normalizeOrderType(originalOrderType)
    if (!original) return true

    const savingRaw = normalizeOrderType(savingOrderType)
    const saving = savingRaw === '' ? 'service' : savingRaw

    const origLoaner = isLoanerOrderTypeGroup(original)
    const saveLoaner = isLoanerOrderTypeGroup(saving)
    const origService = original === 'service'
    const saveService = isServiceOrderTypeGroup(saving)

    if (origService && saveLoaner) {
        return window.confirm(
            `この案件の original_order_type は「service」ですが、`
            + `order_type を「${saving}」として保存しようとしています。\n\n`
            + `このまま保存しますか？`,
        )
    }

    if (origLoaner && saveService) {
        return window.confirm(
            `この案件の original_order_type は「${original}」ですが、`
            + `order_type を「service」として保存しようとしています。\n\n`
            + `このまま保存しますか？`,
        )
    }

    return true
}

/**
 * レコードから original / 保存 order_type を取り確認する。
 * @param {object|null|undefined} record
 * @param {unknown} [savingOrderType] 省略時は record.order_type
 */
export function confirmOrderTypeOriginalMismatchForRecord(record, savingOrderType) {
    if (!record) return true
    const saving = savingOrderType !== undefined
        ? savingOrderType
        : record.order_type
    return confirmOrderTypeOriginalMismatch(record.original_order_type, saving)
}

/**
 * マスタ価格版の共通解決。
 * - 受注日あり: validDateMin <= 受注日 <= validDateMax（なければ最新）
 * - 受注日未定: 最新版（validDateMin が最新）
 * - 期間未設定行は常に候補
 * - 製品選択など一覧は latestMastersByKey で最新版のみ表示
 */

function normalizeDate(value) {
    if (value == null || value === '') return null
    const text = String(value)
    return text.length >= 10 ? text.slice(0, 10) : text
}

function inDateRange(row, asOf) {
    const min = normalizeDate(row?.validDateMin)
    const max = normalizeDate(row?.validDateMax)
    if (!min && !max) return true
    if (min && asOf < min) return false
    if (max && asOf > max) return false
    return true
}

function compareVersionDesc(a, b) {
    const aMin = normalizeDate(a?.validDateMin) || ''
    const bMin = normalizeDate(b?.validDateMin) || ''
    if (aMin === bMin) return 0
    return aMin < bMin ? 1 : -1
}

/**
 * 同一キーの版リストから1件を返す。
 * asOfDate あり: 期間内版（なければ最新）。未指定: 最新版。
 */
export function pickMasterVersion(rows, asOfDate = null) {
    const list = Array.isArray(rows) ? rows.filter(Boolean) : []
    if (!list.length) return null

    const asOf = normalizeDate(asOfDate)
    if (asOf) {
        const matched = list.filter(row => inDateRange(row, asOf)).sort(compareVersionDesc)
        if (matched.length) return matched[0]
    }

    return [...list].sort(compareVersionDesc)[0]
}

/** 表示用: 常に最新版 */
export function pickLatestMasterVersion(rows) {
    return pickMasterVersion(rows, null)
}

/**
 * 一覧表示用: 業務キーごとに最新版だけ残す。
 */
export function latestMastersByKey(rows, key) {
    const list = Array.isArray(rows) ? rows.filter(Boolean) : []
    if (!list.length || !key) return []
    const sorted = [...list].sort(compareVersionDesc)
    const seen = new Set()
    const result = []
    sorted.forEach((row) => {
        const value = row?.[key]
        if (value == null || value === '') return
        const token = String(value)
        if (seen.has(token)) return
        seen.add(token)
        result.push(row)
    })
    return result
}

/**
 * 製品マスタと returnCode から作業価格を解決する。
 * 1, 9 → priceC_0 / 2 → priceR_0 / 12 → priceR_onSite / その他 → 0
 */
export function resolveServiceWorkPrice(master, returnCode) {
    if (!master) return 0

    const code = Number(returnCode)
    let raw = 0
    if (code === 1 || code === 9) {
        raw = master.priceC_0
    } else if (code === 2) {
        raw = master.priceR_0
    } else if (code === 12) {
        raw = master.priceR_onSite
    } else {
        return 0
    }

    const value = Number(raw)
    return Number.isFinite(value) ? value : 0
}

/**
 * ServiceMaster を特定する（価格版対応）。
 * 識別: productName → 非ゼロ serviceID → entityID（識別のみ。価格版キーは serviceID）
 * 版選択: asOfDate（受注日）。未指定なら最新版。
 */
export function findServiceMaster(servicesMaster, criteria = {}, asOfDate = null) {
    const list = servicesMaster ?? []
    if (!list.length) return null

    let identityRows = null

    const productName = criteria?.productName
    if (productName != null && productName !== '') {
        const byName = list.filter(item => String(item.productName) === String(productName))
        if (byName.length) identityRows = byName
    }

    if (!identityRows) {
        const serviceID = criteria?.serviceID
        if (serviceID != null && serviceID !== '' && Number(serviceID) !== 0) {
            const byServiceId = list.filter(item => String(item.serviceID) === String(serviceID))
            if (byServiceId.length) identityRows = byServiceId
        }
    }

    if (!identityRows) {
        const id = criteria?.id
        if (id != null && id !== '') {
            const byId = list.filter(item => String(item.id) === String(id))
            if (byId.length) identityRows = byId
        }
    }

    if (!identityRows) {
        const entityID = criteria?.entityID
        if (entityID != null && entityID !== '') {
            const byEntity = list.filter(item => String(item.entityID) === String(entityID))
            if (byEntity.length) identityRows = byEntity
        }
    }

    if (!identityRows?.length) return null

    // 価格版のキーは serviceID。同一 serviceID の版だけに絞る。
    const serviceID = identityRows[0]?.serviceID
    const versions = (serviceID != null && serviceID !== '' && Number(serviceID) !== 0)
        ? list.filter(item => String(item.serviceID) === String(serviceID))
        : identityRows

    return pickMasterVersion(versions, asOfDate)
}

/**
 * PartMaster を partID + 受注日で解決する（受注日未定なら最新版）。
 */
export function findPartMaster(partsMaster, partID, asOfDate = null) {
    if (partID == null || partID === '') return null
    const versions = (partsMaster ?? []).filter(item => String(item.partID) === String(partID))
    return pickMasterVersion(versions, asOfDate)
}

/**
 * LoanerMaster 価格版を loanerID + 受注日で解決する（受注日未定なら最新版）。
 */
export function findLoanerMasterPrice(versionsOrRows, loanerID, asOfDate = null) {
    const list = Array.isArray(versionsOrRows) ? versionsOrRows : []
    const versions = loanerID == null || loanerID === ''
        ? list
        : list.filter(item => String(item.loanerID) === String(loanerID) || String(item.id) === String(loanerID))
    const picked = pickMasterVersion(versions, asOfDate)
    const value = Number(picked?.price)
    return Number.isFinite(value) ? value : 0
}

export const PAID_LOANER_RETURN_CODES = [1, 2, 7, 13]

export function resolveLoanerMasterChargePrice(versionsOrRows, returnCode, loanerID, asOfDate = null) {
    if (!PAID_LOANER_RETURN_CODES.includes(Number(returnCode))) return 0
    return findLoanerMasterPrice(versionsOrRows, loanerID, asOfDate)
}

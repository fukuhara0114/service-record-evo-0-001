/**
 * マスタ価格版の共通解決（MySQL 5.7 / 8 共通）。
 * - 受注日あり（2001年以降）: validDateMin <= 受注日 <= validDateMax（なければ最新）
 * - 受注日未定 / 2000年以前: 最新版（validDateMin が最新）
 * - 期間未設定 / 0000-00-00 は常に候補
 * - 製品名は TRIM 一致（5.7 PAD SPACE と 8 NO PAD の差を吸収）
 * - 日付は Y-m-d（ISO UTC にしない）
 * - 製品選択など一覧は latestMastersByKey で最新版のみ表示
 */

function normalizeDate(value) {
    if (value == null || value === '') return null
    if (typeof value === 'object') {
        const nested = value.date ?? value.validDateMin ?? value.validDateMax
        if (nested != null && nested !== value) return normalizeDate(nested)
    }
    const text = String(value).trim()
    if (text === '' || text === '[object Object]') return null
    const match = text.match(/(\d{4}-\d{2}-\d{2})/)
    const ymd = match ? match[1] : (text.length >= 10 ? text.slice(0, 10) : text)
    if (!ymd || ymd.startsWith('0000-00-00') || Number(ymd.slice(0, 4)) < 1) return null
    return ymd
}

/** 受注日の暦日 Y-m-d。発送予定日は渡さない。 */
export function toOrderDateYmd(value) {
    return normalizeDate(value)
}

/** 価格版の起点日。未設定・2000年以前は最新版を使うため null。 */
export function normalizePriceAsOfDate(value) {
    const ymd = normalizeDate(value)
    if (!ymd) return null
    if (Number(ymd.slice(0, 4)) < 2001) return null
    return ymd
}

function orderDateYear(value) {
    const ymd = normalizeDate(value)
    if (!ymd) return null
    const year = Number(ymd.slice(0, 4))
    return Number.isFinite(year) ? year : null
}

/**
 * loaner 自身の受注日を価格版に使えるか。
 * 未定・2000年以前・2099年以降は使わず、親 service の受注日にフォールバックする。
 */
export function isLoanerOwnOrderDateUsable(value) {
    const year = orderDateYear(value)
    if (year == null) return false
    return year > 2000 && year < 2099
}

/** loaner 表示価格の as-of。範囲外なら親 service の受注日。 */
export function resolveLoanerPriceAsOfDate(loanerOrderDate, serviceOrderDate) {
    if (isLoanerOwnOrderDateUsable(loanerOrderDate)) {
        return normalizePriceAsOfDate(loanerOrderDate)
    }
    if (isLoanerOwnOrderDateUsable(serviceOrderDate)) {
        return normalizePriceAsOfDate(serviceOrderDate)
    }
    return null
}

/** 画面表示価格の as-of。loaner は上記ルール、それ以外は自身の受注日。 */
export function resolveDisplayPriceAsOfDate({ orderType, orderDate, parentOrderDate } = {}) {
    if (String(orderType ?? '').trim().toLowerCase() === 'loaner') {
        return resolveLoanerPriceAsOfDate(orderDate, parentOrderDate)
    }
    return normalizePriceAsOfDate(orderDate)
}

/**
 * 親 Service 画面に紐づく loaner 行の as-of。
 * 親の受注日ではなく、loaner 自身の受注日ルールを優先する。
 */
export function resolveLinkedLoanerPriceAsOfDate(loaner, parentOrderDate) {
    return resolveDisplayPriceAsOfDate({
        orderType: 'loaner',
        orderDate: loaner?.orderDate,
        parentOrderDate,
    })
}

/** 親 service の受注日のみ。発送予定日・出荷日・プレースホルダ日付は見ない。 */
export function parentOrderDateFromRecord(record) {
    if (!record || typeof record !== 'object') return null
    const candidates = [
        record.parentOrderDate,
        record.parentRecord?.orderDate,
        record.parent_record?.orderDate,
    ]
    for (const value of candidates) {
        if (isLoanerOwnOrderDateUsable(value)) {
            return normalizeDate(value)
        }
    }
    return null
}

export function firstValidPriceAsOf(...values) {
    for (const value of values) {
        const ymd = normalizePriceAsOfDate(value)
        if (ymd) return ymd
    }
    return null
}

function normalizeProductName(value) {
    return String(value ?? '').trim()
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

    const asOf = normalizePriceAsOfDate(asOfDate)
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
 * 1（再校正）, 9（新台/校正） → 受注日版の priceC_0
 * 2 → priceR_0 / 12 → priceR_onSite / その他 → 0
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

    const productName = normalizeProductName(criteria?.productName)
    if (productName !== '') {
        const byName = list.filter(item => normalizeProductName(item.productName) === productName)
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

    // 価格版のキーは serviceID。案件に非ゼロ serviceID があればそれを優先。
    const criteriaServiceID = criteria?.serviceID
    const rowServiceID = [...identityRows].sort(compareVersionDesc)[0]?.serviceID
    const serviceID = (criteriaServiceID != null && criteriaServiceID !== '' && Number(criteriaServiceID) !== 0)
        ? criteriaServiceID
        : rowServiceID
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

/**
 * 案件本体の作業価格。
 * service → servicemaster、loaner → loanermaster。版は必ず asOfDate（受注日）。
 */
export function resolveRecordWorkPriceFromMasters({
    orderType,
    returnCode,
    serviceMaster,
    loanerID,
    loanerPriceVersions = [],
    asOfDate = null,
} = {}) {
    if (String(orderType ?? '').trim() === 'loaner') {
        return findLoanerMasterPrice(loanerPriceVersions, loanerID, asOfDate)
    }
    return resolveServiceWorkPrice(serviceMaster, returnCode)
}

/**
 * 紐づく貸出行の価格。servicerecord.orderDate 版の loanermaster のみ参照する。
 */
export function resolveLoanerMasterLinePrice(loaner, returnCode, asOfDate = null) {
    if (!PAID_LOANER_RETURN_CODES.includes(Number(returnCode))) return 0
    if (Array.isArray(loaner?.priceVersions) && loaner.priceVersions.length) {
        return findLoanerMasterPrice(loaner.priceVersions, loaner.loanerID, asOfDate)
    }
    return 0
}

export function resolveLoanerMasterChargePrice(versionsOrRows, returnCode, loanerID, asOfDate = null) {
    if (!PAID_LOANER_RETURN_CODES.includes(Number(returnCode))) return 0
    return findLoanerMasterPrice(versionsOrRows, loanerID, asOfDate)
}

/** 受注日版の PartMaster を attached part に書き込む。 */
export function applyPartMasterAsOf(part, partsMaster, asOfDate = null) {
    if (!part) return null
    const versioned = findPartMaster(partsMaster, part.partID, asOfDate)
    if (versioned) {
        part.part_master = versioned
        part.partMaster = versioned
    }
    return versioned
}

/**
 * 貸出行の課金価格。
 * loaner 詳細の有償・無償で保存した price を優先し、未設定なら受注日版マスタ（有償 returnCode のみ）。
 */
export function resolveLoanerLinePrice(loaner, returnCode, asOfDate = null) {
    const storedRaw = loaner?.price
    if (storedRaw != null && storedRaw !== '') {
        const stored = Number(storedRaw)
        if (Number.isFinite(stored)) return stored
    }
    if (!PAID_LOANER_RETURN_CODES.includes(Number(returnCode))) return 0
    if (Array.isArray(loaner?.priceVersions) && loaner.priceVersions.length) {
        return findLoanerMasterPrice(loaner.priceVersions, loaner.loanerID, asOfDate)
    }
    const nested = Number(loaner?.loaner_master?.price ?? loaner?.loanerMaster?.price)
    if (Number.isFinite(nested)) return nested
    const master = Number(loaner?.masterPrice)
    return Number.isFinite(master) ? master : 0
}

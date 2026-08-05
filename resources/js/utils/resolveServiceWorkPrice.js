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
 * ServiceMaster を特定する。
 * この環境では serviceID が重複（多くが 0）のため、
 * productName → entityID → 非ゼロの serviceID の順で照合する。
 */
export function findServiceMaster(servicesMaster, criteria = {}) {
    const list = servicesMaster ?? []
    if (!list.length) return null

    const productName = criteria?.productName
    if (productName != null && productName !== '') {
        const byName = list.find(item => String(item.productName) === String(productName))
        if (byName) return byName
    }

    const entityID = criteria?.entityID
    if (entityID != null && entityID !== '') {
        const byEntity = list.find(item => String(item.entityID) === String(entityID))
        if (byEntity) return byEntity
    }

    const id = criteria?.id
    if (id != null && id !== '') {
        const byId = list.find(item => String(item.id) === String(id))
        if (byId) return byId
    }

    const serviceID = criteria?.serviceID
    if (serviceID != null && serviceID !== '' && Number(serviceID) !== 0) {
        const byServiceId = list.find(item => String(item.serviceID) === String(serviceID))
        if (byServiceId) return byServiceId
    }

    return null
}

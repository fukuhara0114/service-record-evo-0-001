export function loanerProductSelectionKey(item) {
    if (item?.loanerID != null && item.loanerID !== '') {
        return String(item.loanerID)
    }

    return ''
}

export function unitMatchesLoanerSelection(unit, selection) {
    const selectedId = selection?.loanerID
    if (selectedId == null || selectedId === '') return false

    return String(unit?.loanerID ?? '') === String(selectedId)
}

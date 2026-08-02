/**
 * Strip list-page suffixes so API paths resolve under /servicerecord.
 */
export function getServiceRecordBasePath() {
    return window.location.pathname.replace(/\/(administrator|engineer)\/?$/, '')
}

export function getServiceRecordApiOriginBase() {
    return `${window.location.origin}${getServiceRecordBasePath()}`
}

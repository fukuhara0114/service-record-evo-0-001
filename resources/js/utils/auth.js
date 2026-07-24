export function getAppBaseUrl() {
    const base = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') ?? ''
    return base.replace(/\/$/, '')
}

export function getLoginUrl() {
    return `${getAppBaseUrl()}/login`
}

export function redirectToLogin() {
    window.location.assign(getLoginUrl())
}

export function isUnauthorizedStatus(status) {
    return status === 401 || status === 419
}

export function handleUnauthorizedStatus(status) {
    if (isUnauthorizedStatus(status)) {
        redirectToLogin()
        return true
    }

    return false
}

export function handleUnauthorizedResponse(response) {
    return handleUnauthorizedStatus(response?.status)
}

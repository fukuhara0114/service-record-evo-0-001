let redirectingToLogin = false
let originalFetch = null
let lastSessionOkAt = 0
const SESSION_OK_TTL_MS = 3000

export function getAppBaseUrl() {
    const base = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') ?? ''
    return base.replace(/\/$/, '')
}

export function getLoginUrl() {
    return `${getAppBaseUrl()}/login`
}

export function redirectToLogin() {
    if (redirectingToLogin) {
        return
    }

    // 既にログイン画面にいる場合は再遷移しない
    if (isLoginUrl(window.location.href)) {
        return
    }

    redirectingToLogin = true
    window.location.assign(getLoginUrl())
}

export function isUnauthorizedStatus(status) {
    return status === 401 || status === 419
}

export function isLoginUrl(url) {
    if (!url) {
        return false
    }

    try {
        const resolved = new URL(url, window.location.origin)
        const loginPath = new URL(getLoginUrl(), window.location.origin).pathname
        return resolved.pathname === loginPath
    } catch {
        return false
    }
}

function getResponseUrl(response) {
    if (!response) {
        return ''
    }

    if (typeof response.url === 'string' && response.url) {
        return response.url
    }

    const axiosUrl = response.request?.responseURL
    if (typeof axiosUrl === 'string' && axiosUrl) {
        return axiosUrl
    }

    return ''
}

export function handleUnauthorizedStatus(status) {
    if (isUnauthorizedStatus(status)) {
        redirectToLogin()
        return true
    }

    return false
}

export function handleUnauthorizedResponse(response) {
    if (!response) {
        return false
    }

    if (handleUnauthorizedStatus(response.status)) {
        return true
    }

    // セッション切れで最終的に /login へ着地した場合（Blade ログイン等）
    if (isLoginUrl(getResponseUrl(response))) {
        redirectToLogin()
        return true
    }

    return false
}

function isMutatingMethod(method) {
    return ['POST', 'PUT', 'PATCH', 'DELETE'].includes((method || 'GET').toUpperCase())
}

/**
 * 更新系リクエスト前の軽いセッション確認。
 * 失効していればログインへ遷移し false を返す。
 */
export async function ensureSession() {
    if (redirectingToLogin || isLoginUrl(window.location.href)) {
        return false
    }

    if (Date.now() - lastSessionOkAt < SESSION_OK_TTL_MS) {
        return true
    }

    const fetchImpl = originalFetch ?? window.fetch.bind(window)

    try {
        const response = await fetchImpl(`${getAppBaseUrl()}/home`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            redirect: 'manual',
        })

        if (
            isUnauthorizedStatus(response.status)
            || response.status === 302
            || response.status === 301
            || response.type === 'opaqueredirect'
        ) {
            redirectToLogin()
            return false
        }

        lastSessionOkAt = Date.now()
        return true
    } catch {
        // ネットワーク一時障害では更新自体に進ませる（401/419 側で捕捉）
        return true
    }
}

function isSameOriginUrl(url) {
    try {
        return new URL(url, window.location.origin).origin === window.location.origin
    } catch {
        return false
    }
}

function resolveRequestUrl(input) {
    if (typeof input === 'string') {
        return input
    }
    if (input instanceof URL) {
        return input.toString()
    }
    return input?.url ?? ''
}

function resolveRequestMethod(input, init = {}) {
    if (init.method) {
        return init.method
    }
    if (typeof input !== 'string' && !(input instanceof URL) && input?.method) {
        return input.method
    }
    return 'GET'
}

/**
 * 生の fetch も含め、同一オリジンの更新前チェックと 401/419 を捕捉する。
 */
export function installFetchAuthGuard() {
    if (typeof window === 'undefined' || window.__authFetchGuardInstalled) {
        return
    }

    originalFetch = window.fetch.bind(window)
    window.__authFetchGuardInstalled = true

    window.fetch = async (input, init = {}) => {
        const url = resolveRequestUrl(input)
        const method = resolveRequestMethod(input, init)

        if (url && isSameOriginUrl(url) && isMutatingMethod(method)) {
            if (!(await ensureSession())) {
                return new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
                    status: 401,
                    statusText: 'Unauthorized',
                    headers: { 'Content-Type': 'application/json' },
                })
            }
        }

        const response = await originalFetch(input, init)

        if (url && isSameOriginUrl(url)) {
            handleUnauthorizedResponse(response)
        }

        return response
    }
}

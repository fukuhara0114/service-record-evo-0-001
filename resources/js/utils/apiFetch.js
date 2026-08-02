import { handleUnauthorizedResponse } from './auth'

function listModeHeaders() {
    if (typeof window === 'undefined') return {}
    if (/\/servicerecord\/engineer(?:\/|$)/.test(window.location.pathname)) {
        return { 'X-List-Mode': 'engineer' }
    }
    return {}
}

export async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...listModeHeaders(),
            ...options.headers,
        },
    })

    if (handleUnauthorizedResponse(response)) {
        return null
    }

    const data = await response.json().catch(() => ({}))

    return { response, data }
}

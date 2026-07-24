import { handleUnauthorizedResponse } from './auth'

export async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
    })

    if (handleUnauthorizedResponse(response)) {
        return null
    }

    const data = await response.json().catch(() => ({}))

    return { response, data }
}

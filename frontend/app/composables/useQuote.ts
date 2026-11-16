import type { Quote, QuoteResponse } from '~/types/quotes'

const storageKey = 'partsfinder.quote-id'

function readStoredId(): string | null {
    try {
        return localStorage.getItem(storageKey)
    } catch {
        return null
    }
}

/**
 * Remember the quote id across refreshes. Skipped when storage is unavailable.
 */
function storeId(id: number | null) {
    try {
        if (id === null) {
            localStorage.removeItem(storageKey)
        } else {
            localStorage.setItem(storageKey, String(id))
        }
    } catch {
        return
    }
}

/**
 * The workshop's open quote: shared state, its actions, and restoring it after a refresh.
 */
export function useQuote() {
    const quote = useState<Quote | null>('quote', () => null)
    const isOpen = useState('quote-drawer-open', () => false)
    const pending = useState('quote-pending', () => false)

    async function send(path: string, method: 'GET' | 'POST' | 'PATCH' | 'DELETE', body?: Record<string, unknown>) {
        pending.value = true
        try {
            const response = await $fetch<QuoteResponse>(path, { method, body })
            quote.value = response.data
            storeId(response.data.id)

            return response.data
        } finally {
            pending.value = false
        }
    }

    async function restore() {
        const id = readStoredId()

        if (!id) {
            return
        }

        try {
            await send(`/api/quotes/${id}`, 'GET')
        } catch {
            storeId(null)
            quote.value = null
        }
    }

    async function startNew() {
        return await send('/api/quotes', 'POST')
    }

    async function addLine(partId: number, quantity: number, colour?: string) {
        if (!quote.value || quote.value.status === 'submitted') {
            await startNew()
        }

        return await send(`/api/quotes/${quote.value!.id}/lines`, 'POST', { partId, quantity, colour })
    }

    async function changeQuantity(lineId: number, quantity: number) {
        return await send(`/api/quotes/${quote.value!.id}/lines/${lineId}`, 'PATCH', { quantity })
    }

    async function removeLine(lineId: number) {
        return await send(`/api/quotes/${quote.value!.id}/lines/${lineId}`, 'DELETE')
    }

    async function submit() {
        return await send(`/api/quotes/${quote.value!.id}/submit`, 'POST')
    }

    return { quote, isOpen, pending, restore, startNew, addLine, changeQuantity, removeLine, submit }
}

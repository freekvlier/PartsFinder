import type { Quote, QuoteResponse } from '~/types/quotes'

/**
 * The workshop's open quote: shared state, its actions, and restoring it after a refresh.
 */
export function useQuote() {
    const quote = useState<Quote | null>('quote', () => null)
    const isOpen = useState('quote-drawer-open', () => false)
    const pending = useState('quote-pending', () => false)
    const storedId = useCookie<number | null>('quote-id', { maxAge: 60 * 60 * 24 * 365, sameSite: 'lax' })

    async function send(path: string, method: 'GET' | 'POST' | 'PATCH' | 'DELETE', body?: Record<string, unknown>) {
        pending.value = true
        try {
            const response = await $fetch<QuoteResponse>(path, { method, body })
            quote.value = response.data
            storedId.value = response.data.id

            return response.data
        } finally {
            pending.value = false
        }
    }

    /**
     * Load the quote the cookie remembers, on the server or the client; an id the API no longer knows is forgotten.
     */
    async function restore() {
        if (quote.value || !storedId.value) {
            return
        }

        try {
            await send(`/api/quotes/${storedId.value}`, 'GET')
        } catch {
            storedId.value = null
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

        return await send(`/api/quotes/${quote.value!.id}/lines`, 'POST', { part_id: partId, quantity, colour })
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

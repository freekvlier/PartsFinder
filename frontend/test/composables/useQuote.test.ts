import { beforeEach, describe, expect, it, vi } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, readBody, setResponseStatus } from 'h3'
import type { HTTPMethod } from 'h3'
import { makeLine, makeQuote } from '../fixtures'

interface Call { method: string, url: string, body?: unknown }

let calls: Call[]
let missing: Set<string>

function endpoint(url: string, method: HTTPMethod, respond: () => unknown, status = 200) {
    registerEndpoint(url, {
        method,
        handler: async (event) => {
            const body = method === 'GET' ? getQuery(event) : await readBody(event).catch(() => undefined)
            calls.push({ method, url, body })
            if (missing.has(url)) {
                setResponseStatus(event, 404)
                return { message: 'Not found.' }
            }
            setResponseStatus(event, status)
            return respond()
        }
    })
}

endpoint('/api/quotes', 'POST', () => ({ data: makeQuote({ id: 8, lines: [] }) }), 201)
endpoint('/api/quotes/7', 'GET', () => ({ data: makeQuote({ id: 7 }) }))
endpoint('/api/quotes/404', 'GET', () => ({}))
const lineAdded = makeQuote({ id: 8, lines: [makeLine({ part_id: 3, colour: 'red', quantity: 2 })] })
endpoint('/api/quotes/8/lines', 'POST', () => ({ data: lineAdded }), 201)

beforeEach(async () => {
    calls = []
    missing = new Set(['/api/quotes/404'])
    useCookie('quote-id').value = null
    useState('quote').value = null
    useState('quote-drawer-open').value = false
    await nextTick()
})

describe('useQuote', () => {
    it('restores the open quote of a refresh from the API using the id in the cookie', async () => {
        useCookie<number | null>('quote-id').value = 7
        await nextTick()
        const { quote, restore } = useQuote()

        await restore()

        expect(calls).toEqual([{ method: 'GET', url: '/api/quotes/7', body: {} }])
        expect(quote.value?.id).toBe(7)
    })

    it('does not call the API when the cookie holds no quote id', async () => {
        const { quote, restore } = useQuote()

        await restore()

        expect(calls).toEqual([])
        expect(quote.value).toBeNull()
    })

    it('forgets a quote id the API no longer knows', async () => {
        useCookie<number | null>('quote-id').value = 404
        await nextTick()
        const { quote, restore } = useQuote()

        await restore()

        expect(quote.value).toBeNull()
        await vi.waitFor(() => expect(document.cookie).not.toContain('quote-id='))
    })

    it('creates a quote when the first part is added and stores its id', async () => {
        const { quote, addLine } = useQuote()

        await addLine(3, 2, 'red')

        expect(calls.map(call => `${call.method} ${call.url}`))
            .toEqual(['POST /api/quotes', 'POST /api/quotes/8/lines'])
        expect(calls[1]!.body).toEqual({ part_id: 3, quantity: 2, colour: 'red' })
        expect(quote.value?.lines).toHaveLength(1)
        await vi.waitFor(() => expect(useCookie('quote-id').value).toBe(8))
    })

    it('adds to the existing draft instead of creating another quote', async () => {
        useState('quote').value = makeQuote({ id: 8, lines: [] })
        const { addLine } = useQuote()

        await addLine(3, 1)

        expect(calls.map(call => `${call.method} ${call.url}`)).toEqual(['POST /api/quotes/8/lines'])
    })

    it('starts a new quote when the current one has been submitted', async () => {
        useState('quote').value = makeQuote({ id: 5, status: 'submitted' })
        const { addLine } = useQuote()

        await addLine(3, 1)

        expect(calls.map(call => `${call.method} ${call.url}`))
            .toEqual(['POST /api/quotes', 'POST /api/quotes/8/lines'])
    })

    it('starts a new quote on request and remembers it', async () => {
        useState('quote').value = makeQuote({ id: 5, status: 'submitted' })
        const { quote, startNew } = useQuote()

        await startNew()

        expect(quote.value?.id).toBe(8)
        expect(quote.value?.lines).toEqual([])
        await vi.waitFor(() => expect(useCookie('quote-id').value).toBe(8))
    })

    it('is not pending once a request has finished', async () => {
        const { pending, startNew } = useQuote()

        const request = startNew()
        expect(pending.value).toBe(true)
        await request

        await vi.waitFor(() => expect(pending.value).toBe(false))
    })
})

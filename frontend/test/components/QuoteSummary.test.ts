import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, readBody, setResponseStatus } from 'h3'
import QuoteSummary from '~/components/QuoteSummary.vue'
import { makeLine, makeQuote, noTotals } from '../fixtures'

interface Call { method: string, body?: unknown }

let calls: Call[]
let response: { status?: number, body: unknown }

function endpoint(url: string, method: string) {
    registerEndpoint(url, {
        method,
        handler: async (event) => {
            const hasBody = ['PATCH', 'POST'].includes(method)
            calls.push({ method, body: hasBody ? await readBody(event).catch(() => undefined) : getQuery(event) })
            if (response.status) {
                setResponseStatus(event, response.status)
            }
            return response.body
        }
    })
}

endpoint('/api/quotes/5/lines/11', 'DELETE')
endpoint('/api/quotes/5/lines/11', 'PATCH')
endpoint('/api/quotes/5/submit', 'POST')
endpoint('/api/quotes', 'POST')

beforeEach(() => {
    calls = []
    response = { body: { data: makeQuote() } }
    useState('quote').value = makeQuote()
})

describe('quote drawer content', () => {
    it('renders the lines and the totals exactly as the API returned them', async () => {
        const summary = await mountSuspended(QuoteSummary)

        const line = summary.get('[data-testid="quote-line"]')
        expect(line.text()).toContain('DAF XF 106 Sideskirt set')
        expect(line.text()).toContain('OEM 2119711')
        expect(line.text()).toContain('1.590,00')

        const totals = summary.get('[data-testid="quote-totals"]').text()
        expect(totals).toContain('1.590,00')
        expect(totals).toContain('Discount (10%)')
        expect(totals).toContain('VAT (21%)')
        expect(totals).toContain('159,00')
        expect(totals).toContain('300,51')
        expect(totals).toContain('1.731,51')
    })

    it('hides the discount line when the workshop has no discount', async () => {
        useState('quote').value = makeQuote({
            discountPercentage: 0,
            totals: { subtotalCents: 159000, discountCents: 0, vatCents: 33390, totalCents: 192390 }
        })

        const summary = await mountSuspended(QuoteSummary)

        expect(summary.find('[data-testid="quote-discount"]').exists()).toBe(false)
        expect(summary.get('[data-testid="quote-totals"]').text()).not.toContain('Discount')
    })

    it('shows the colour of a line and the backorder flag with its warning', async () => {
        useState('quote').value = makeQuote({
            lines: [makeLine({ colour: 'white', backorder: true })],
            warnings: [
                { code: 'backorder', message: 'Some lines are out of stock and will be backordered.', lineIds: [11] }
            ]
        })

        const summary = await mountSuspended(QuoteSummary)

        expect(summary.get('[data-testid="quote-line"]').text()).toContain('white')
        expect(summary.get('[data-testid="quote-line"]').text()).toContain('Backorder')
        expect(summary.get('[data-testid="quote-warning"]').text()).toContain('will be backordered')
    })

    it('shows an empty state without lines', async () => {
        useState('quote').value = makeQuote({ lines: [] })

        const summary = await mountSuspended(QuoteSummary)

        expect(summary.find('[data-testid="quote-empty"]').exists()).toBe(true)
        expect(summary.text()).not.toContain('Submit quote')
    })

    it('removes a line through the API and shows the quote it returns', async () => {
        response = { body: { data: makeQuote({ lines: [], totals: noTotals }) } }
        const summary = await mountSuspended(QuoteSummary)

        await summary.get('button[aria-label="Remove DAF XF 106 Sideskirt set"]').trigger('click')
        await vi.waitFor(() => expect(calls).toHaveLength(1))

        expect(calls[0]!.method).toBe('DELETE')
        await vi.waitFor(() => expect(summary.find('[data-testid="quote-empty"]').exists()).toBe(true))
    })

    it('changes a quantity through the API with the new quantity', async () => {
        response = { body: { data: makeQuote({ lines: [makeLine({ quantity: 3, lineTotalCents: 238500 })] }) } }
        const summary = await mountSuspended(QuoteSummary)

        const quantity = summary.get('input[aria-label="Quantity of DAF XF 106 Sideskirt set"]')
        await quantity.setValue('3')
        await quantity.trigger('blur')
        await vi.waitFor(() => expect(calls).toHaveLength(1))

        expect(calls[0]).toEqual({ method: 'PATCH', body: { quantity: 3 } })
    })

    it('submits the quote through the API and then shows it read-only', async () => {
        response = { body: { data: makeQuote({ status: 'submitted', submittedAt: '2026-11-16T10:00:00+00:00' }) } }
        const summary = await mountSuspended(QuoteSummary)

        await summary.get('button:not([aria-label])').trigger('click')
        await vi.waitFor(() => expect(calls).toHaveLength(1))
        await vi.waitFor(() => expect(summary.find('[data-testid="quote-submitted"]').exists()).toBe(true))

        expect(calls[0]!.method).toBe('POST')
        expect(summary.find('button[aria-label^="Remove"]').exists()).toBe(false)
        expect(summary.find('button[aria-label="Increment"]').exists()).toBe(false)
        expect(summary.text()).not.toContain('Submit quote')
        expect(summary.text()).toContain('Start new quote')
    })

    it('shows a submitted quote without any edit controls', async () => {
        useState('quote').value = makeQuote({ status: 'submitted' })

        const summary = await mountSuspended(QuoteSummary)

        expect(summary.find('[data-testid="quote-submitted"]').exists()).toBe(true)
        expect(summary.find('button[aria-label^="Remove"]').exists()).toBe(false)
        expect(summary.find('input').exists()).toBe(false)
        expect(summary.text()).toContain('2 × ')
    })

    it('starts a new quote from a submitted one', async () => {
        useState('quote').value = makeQuote({ status: 'submitted' })
        response = { body: { data: makeQuote({ id: 6, lines: [], totals: noTotals }) } }
        const summary = await mountSuspended(QuoteSummary)

        await summary.get('button:not([aria-label])').trigger('click')
        await vi.waitFor(() => expect(calls).toHaveLength(1))

        expect(calls[0]!.method).toBe('POST')
        await vi.waitFor(() => expect(summary.find('[data-testid="quote-empty"]').exists()).toBe(true))
    })

    it('shows the validation messages when the API refuses a change', async () => {
        const message = 'Only 3 in stock for DAF XF 106 Sideskirt set; the quote has 4.'
        response = { status: 422, body: { message: 'x', errors: { lines: [message] } } }
        const summary = await mountSuspended(QuoteSummary)

        await summary.get('button:not([aria-label])').trigger('click')

        await vi.waitFor(() => expect(summary.find('[data-testid="quote-errors"]').exists()).toBe(true))
        expect(summary.get('[data-testid="quote-errors"]').text()).toContain('Only 3 in stock')
        expect(useState('quote').value).toMatchObject({ status: 'draft' })
    })
})

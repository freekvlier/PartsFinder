import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import PartPage from '~/pages/parts/[id].vue'
import type { Part } from '~/types/parts'
import { makeLine, makePart, makeQuote } from '../fixtures'

interface Call { method: string, url: string, body?: unknown }

let calls: Call[]
let parts: Record<string, { status: number, body: unknown }>
let lineResponse: { status: number, body: unknown }

for (const id of ['9', '10', '11', '12']) {
  registerEndpoint(`/api/parts/${id}`, () => parts[id]!.body)
}
registerEndpoint('/api/parts/404', { handler: (event) => {
  setResponseStatus(event, 404)
  return { message: 'Not found.' }
} })
registerEndpoint('/api/quotes', { method: 'POST', handler: async (event) => {
  calls.push({ method: 'POST', url: '/api/quotes' })
  setResponseStatus(event, 201)
  return { data: makeQuote({ id: 8, lines: [] }) }
} })
registerEndpoint('/api/quotes/8/lines', { method: 'POST', handler: async (event) => {
  calls.push({ method: 'POST', url: '/api/quotes/8/lines', body: await readBody(event) })
  setResponseStatus(event, lineResponse.status)
  return lineResponse.body
} })

function show(id: string, part: Part) {
  parts[id] = { status: 200, body: { data: part } }
}

beforeEach(() => {
  clearNuxtData()
  calls = []
  parts = {}
  lineResponse = { status: 201, body: { data: makeQuote({ id: 8, lines: [makeLine({ partId: 9 })] }) } }
  useState('quote').value = null
  useState('quote-drawer-open').value = false
  localStorage.clear()
})

describe('part detail page', () => {
  it('shows the details of the part', async () => {
    show('9', makePart({
      id: 9,
      name: 'Volvo FH 4 Headlamp left',
      oem: '3141592',
      brand: 'Volvo',
      category: 'Lighting',
      condition: 'new',
      priceCents: 32000,
      stockQuantity: 5
    }))

    const page = await mountSuspended(PartPage, { route: '/parts/9' })

    expect(page.text()).toContain('Volvo FH 4 Headlamp left')
    expect(page.text()).toContain('OEM 3141592')
    expect(page.text()).toContain('Volvo')
    expect(page.text()).toContain('Lighting')
    expect(page.text()).toContain('New')
    expect(page.text()).toContain('320,00')
    expect(page.get('[data-testid="part-stock"]').text()).toBe('In stock (5)')
  })

  it('explains that a part without stock is backordered', async () => {
    show('9', makePart({ id: 9, stockQuantity: 0, inStock: false }))

    const page = await mountSuspended(PartPage, { route: '/parts/9' })

    expect(page.get('[data-testid="part-stock"]').text()).toBe('Out of stock')
    expect(page.text()).toContain('backordered')
  })

  it('shows a not found state for an unknown part', async () => {
    const page = await mountSuspended(PartPage, { route: '/parts/404' })

    expect(page.find('[data-testid="part-error"]').exists()).toBe(true)
  })

  it('does not ask for a colour for a part without colours', async () => {
    show('9', makePart({ id: 9 }))

    const page = await mountSuspended(PartPage, { route: '/parts/9' })

    expect(page.find('[data-testid="part-colour"]').exists()).toBe(false)
    expect(page.get('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('asks for a colour for a part with colours and blocks adding until one is chosen', async () => {
    show('10', makePart({ id: 10, colours: ['white', 'red'] }))

    const page = await mountSuspended(PartPage, { route: '/parts/10' })

    expect(page.find('[data-testid="part-colour"]').exists()).toBe(true)
    expect(page.get('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('adds the part to a new quote and opens the quote', async () => {
    show('9', makePart({ id: 9 }))
    const page = await mountSuspended(PartPage, { route: '/parts/9' })

    await page.get('form').trigger('submit')
    await vi.waitFor(() => expect(calls).toHaveLength(2))

    expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/quotes' })
    expect(calls[1]).toMatchObject({ method: 'POST', url: '/api/quotes/8/lines', body: { partId: 9, quantity: 1 } })
    expect(useState('quote-drawer-open').value).toBe(true)
  })

  it('sends the chosen quantity', async () => {
    show('9', makePart({ id: 9 }))
    const page = await mountSuspended(PartPage, { route: '/parts/9' })

    const quantity = page.get('input[role="spinbutton"]')
    await quantity.setValue('3')
    await quantity.trigger('blur')
    await page.get('form').trigger('submit')
    await vi.waitFor(() => expect(calls).toHaveLength(2))

    expect(calls[1]!.body).toMatchObject({ partId: 9, quantity: 3 })
  })

  it('shows the message of the API when the part cannot be added', async () => {
    show('9', makePart({ id: 9 }))
    const message = 'Only 3 in stock for DAF XF 106 Sideskirt set; this quote would hold 4.'
    lineResponse = { status: 422, body: { message: 'x', errors: { quantity: [message] } } }
    const page = await mountSuspended(PartPage, { route: '/parts/9' })

    await page.get('form').trigger('submit')

    await vi.waitFor(() => expect(page.find('[data-testid="part-add-errors"]').exists()).toBe(true))
    expect(page.get('[data-testid="part-add-errors"]').text()).toContain('Only 3 in stock')
    expect(useState('quote-drawer-open').value).toBe(false)
  })
})

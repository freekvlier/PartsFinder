import { beforeEach, describe, expect, it } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError, getQuery } from 'h3'
import IndexPage from '~/pages/index.vue'
import type { Part, PartsResponse } from '~/types/parts'

function part(overrides: Partial<Part> = {}): Part {
  return {
    id: 1,
    name: 'DAF XF 106 Sideskirt set',
    oem: '2119711',
    brand: 'DAF',
    category: 'Body',
    condition: 'used',
    priceCents: 79500,
    stockQuantity: 3,
    inStock: true,
    image: '/img/placeholder-part.svg',
    colours: [],
    ...overrides
  }
}

function listing(parts: Part[], meta: Partial<PartsResponse['meta']> = {}): PartsResponse {
  return {
    data: parts,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: 1,
      last_page: 1,
      links: [],
      path: null,
      per_page: 12,
      to: parts.length,
      total: parts.length,
      ...meta
    }
  }
}

let response: PartsResponse
let failWith: number | null
let requests: Record<string, unknown>[]

registerEndpoint('/api/parts', (event) => {
  requests.push(getQuery(event))
  if (failWith) {
    throw createError({ statusCode: failWith })
  }
  return response
})

beforeEach(() => {
  clearNuxtData()
  response = listing([part()])
  failWith = null
  requests = []
})

describe('parts overview', () => {
  it('renders a card for every part with its details', async () => {
    response = listing([
      part(),
      part({ id: 2, name: 'Volvo FH 4 Headlamp left', oem: '3141592', brand: 'Volvo', condition: 'new', priceCents: 32000, stockQuantity: 5 })
    ])

    const page = await mountSuspended(IndexPage)
    const cards = page.findAll('[data-testid="part-card"]')

    expect(cards).toHaveLength(2)
    expect(cards[0]!.text()).toContain('DAF XF 106 Sideskirt set')
    expect(cards[0]!.text()).toContain('OEM 2119711')
    expect(cards[0]!.text()).toContain('DAF')
    expect(cards[0]!.text()).toContain('Used')
    expect(cards[0]!.text()).toContain('795,00')
    expect(cards[0]!.text()).toContain('In stock (3)')
    expect(cards[1]!.text()).toContain('New')
    expect(cards[1]!.text()).toContain('320,00')
  })

  it('shows an out of stock indicator for a part without stock', async () => {
    response = listing([part({ stockQuantity: 0, inStock: false })])

    const page = await mountSuspended(IndexPage)

    expect(page.get('[data-testid="part-card"]').text()).toContain('Out of stock')
    expect(page.text()).not.toContain('In stock')
  })

  it('shows the total number of parts', async () => {
    response = listing([part()], { total: 54 })

    const page = await mountSuspended(IndexPage)

    expect(page.text()).toContain('54 parts')
  })

  it('shows an empty state when there are no parts', async () => {
    response = listing([])

    const page = await mountSuspended(IndexPage)

    expect(page.find('[data-testid="parts-empty"]').exists()).toBe(true)
    expect(page.find('[data-testid="part-card"]').exists()).toBe(false)
  })

  it('shows an error state when the API fails', async () => {
    failWith = 500

    const page = await mountSuspended(IndexPage)

    expect(page.find('[data-testid="parts-error"]').exists()).toBe(true)
    expect(page.find('[data-testid="part-card"]').exists()).toBe(false)
  })
})

describe('parts overview URL state', () => {
  it('requests page 1 when the URL has no page', async () => {
    await mountSuspended(IndexPage, { route: '/' })

    expect(requests.at(-1)).toMatchObject({ page: '1' })
  })

  it('requests the page that is in the URL', async () => {
    await mountSuspended(IndexPage, { route: '/?page=3' })

    expect(requests.at(-1)).toMatchObject({ page: '3' })
  })

  it('falls back to page 1 for an invalid page in the URL', async () => {
    await mountSuspended(IndexPage, { route: '/?page=abc' })

    expect(requests.at(-1)).toMatchObject({ page: '1' })
  })

  it('links the pagination to the other pages through the URL', async () => {
    response = listing([part()], { current_page: 1, last_page: 3, total: 30 })

    const page = await mountSuspended(IndexPage, { route: '/' })
    const hrefs = page.findAll('a').map(link => link.attributes('href'))

    expect(hrefs.some(href => href?.includes('page=2'))).toBe(true)
  })

  it('does not show pagination when everything fits on one page', async () => {
    response = listing([part()], { last_page: 1 })

    const page = await mountSuspended(IndexPage, { route: '/' })

    expect(page.findAll('a').some(link => link.attributes('href')?.includes('page='))).toBe(false)
  })
})

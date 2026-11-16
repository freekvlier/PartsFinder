import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError, getQuery } from 'h3'
import IndexPage from '~/pages/index.vue'
import type { BrandsResponse, PartsResponse } from '~/types/parts'
import { makeListing, makePart } from '../fixtures'

let response: PartsResponse
let brands: BrandsResponse
let failWith: number | null
let requests: Record<string, unknown>[]
let requestUrls: string[]
let brandRequests: Record<string, unknown>[]

registerEndpoint('/api/parts', (event) => {
    requests.push(getQuery(event))
    requestUrls.push(decodeURIComponent(event.path))
    if (failWith) {
        throw createError({ statusCode: failWith })
    }
    return response
})

registerEndpoint('/api/brands', (event) => {
    brandRequests.push(getQuery(event))
    return brands
})

beforeEach(() => {
    clearNuxtData()
    response = makeListing([makePart()])
    brands = { data: [{ name: 'DAF', count: 4 }, { name: 'MAN', count: 2 }, { name: 'Volvo', count: 0 }] }
    failWith = null
    requests = []
    requestUrls = []
    brandRequests = []
})

describe('parts overview', () => {
    it('renders a card for every part with its details', async () => {
        response = makeListing([
            makePart(),
            makePart({
                id: 2,
                name: 'Volvo FH 4 Headlamp left',
                oem: '3141592',
                brand: 'Volvo',
                condition: 'new',
                price_cents: 32000,
                stock_quantity: 5
            })
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
        response = makeListing([makePart({ stock_quantity: 0, in_stock: false })])

        const page = await mountSuspended(IndexPage)

        expect(page.get('[data-testid="part-card"]').text()).toContain('Out of stock')
        expect(page.text()).not.toContain('In stock')
    })

    it('shows the total number of parts', async () => {
        response = makeListing([makePart()], { total: 54 })

        const page = await mountSuspended(IndexPage)

        expect(page.text()).toContain('54 parts')
    })

    it('shows an empty state when there are no parts', async () => {
        response = makeListing([])

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
        response = makeListing([makePart()], { current_page: 1, last_page: 3, total: 30 })

        const page = await mountSuspended(IndexPage, { route: '/' })
        const hrefs = page.findAll('a').map(link => link.attributes('href'))

        expect(hrefs.some(href => href?.includes('page=2'))).toBe(true)
    })

    it('does not show pagination when everything fits on one page', async () => {
        response = makeListing([makePart()], { last_page: 1 })

        const page = await mountSuspended(IndexPage, { route: '/' })

        expect(page.findAll('a').some(link => link.attributes('href')?.includes('page='))).toBe(false)
    })
})

describe('parts overview search, filters and sort', () => {
    it('passes the search, brands, condition, sort and page from the URL to the API', async () => {
        await mountSuspended(IndexPage, {
            route: '/?q=brake&brand=DAF&brand=MAN&condition=used&sort=price_asc&page=2'
        })

        expect(requests.at(-1)).toMatchObject({
            'q': 'brake',
            'brand[]': ['DAF', 'MAN'],
            'condition': 'used',
            'sort': 'price_asc',
            'page': '2'
        })
    })

    it('sends a single brand as a list too', async () => {
        await mountSuspended(IndexPage, { route: '/?brand=DAF' })

        expect(requestUrls.at(-1)).toContain('brand[]=DAF')
    })

    it('sends nothing for filters that are not in the URL', async () => {
        await mountSuspended(IndexPage, { route: '/' })

        expect(Object.keys(requests.at(-1)!)).toEqual(['page'])
    })

    it('ignores an unknown condition or sort in the URL', async () => {
        await mountSuspended(IndexPage, { route: '/?condition=broken&sort=name_desc' })

        expect(Object.keys(requests.at(-1)!)).toEqual(['page'])
    })

    it('asks for the brand counts with the search and condition, but not the brand or the page', async () => {
        await mountSuspended(IndexPage, { route: '/?q=brake&brand=DAF&condition=new&sort=price_desc&page=3' })

        expect(brandRequests.at(-1)).toEqual({ q: 'brake', condition: 'new' })
    })

    it('shows a pill per brand with its count, and marks the selected brands', async () => {
        const page = await mountSuspended(IndexPage, { route: '/?brand=MAN' })
        const pills = page.findAll('[data-testid="filter-brand"]')

        expect(pills.map(pill => pill.text())).toEqual(['DAF 4', 'MAN 2', 'Volvo 0'])
        expect(pills.map(pill => pill.attributes('aria-pressed'))).toEqual(['false', 'true', 'false'])
    })

    it('selecting a brand pill adds it to the URL and keeps the other filters', async () => {
        const page = await mountSuspended(IndexPage, { route: '/?q=brake&brand=MAN&page=2' })

        await page.get('[data-brand="DAF"]').trigger('click')
        await vi.waitFor(() => expect(useRoute().query).toEqual({ q: 'brake', brand: ['MAN', 'DAF'] }))
    })

    it('selecting a selected brand pill again removes it', async () => {
        const page = await mountSuspended(IndexPage, { route: '/?brand=MAN&brand=DAF' })

        await page.get('[data-brand="MAN"]').trigger('click')
        await vi.waitFor(() => expect(useRoute().query).toEqual({ brand: ['DAF'] }))
    })

    it('the condition control marks the active condition and changes it through the URL', async () => {
        const page = await mountSuspended(IndexPage, { route: '/?condition=used&page=2' })
        const pressed = () => page.findAll('[data-condition]').filter(b => b.attributes('aria-pressed') === 'true')

        expect(pressed().map(button => button.text())).toEqual(['Used'])

        await page.get('[data-condition="new"]').trigger('click')
        await vi.waitFor(() => expect(useRoute().query).toEqual({ condition: 'new' }))

        await page.get('[data-condition="all"]').trigger('click')
        await vi.waitFor(() => expect(useRoute().query).toEqual({}))
    })

    it('shows the total next to the filters', async () => {
        response = makeListing([makePart()], { total: 54 })

        const page = await mountSuspended(IndexPage, { route: '/' })

        expect(page.get('[data-testid="filter-total"]').text()).toBe('54 parts')
    })

    it('fills the search box from the URL', async () => {
        const page = await mountSuspended(IndexPage, { route: '/?q=brake' })

        expect(page.get<HTMLInputElement>('[data-testid="filter-search"]').element.value).toBe('brake')
    })

    it('puts a typed search in the URL after a pause and goes back to page 1', async () => {
        const page = await mountSuspended(IndexPage, { route: '/?page=3' })

        await page.get('[data-testid="filter-search"]').setValue('brake')

        await vi.waitFor(() => expect(useRoute().query).toEqual({ q: 'brake' }))
        await vi.waitFor(() => expect(requests.at(-1)).toMatchObject({ q: 'brake', page: '1' }))
    })

    it('shows the reset button only when something is filtered, not for a sort alone', async () => {
        expect((await mountSuspended(IndexPage, { route: '/' }))
            .find('[data-testid="filter-reset"]').exists()).toBe(false)
        expect((await mountSuspended(IndexPage, { route: '/?sort=price_asc' }))
            .find('[data-testid="filter-reset"]').exists()).toBe(false)
        expect((await mountSuspended(IndexPage, { route: '/?brand=DAF' }))
            .find('[data-testid="filter-reset"]').exists()).toBe(true)
    })

    it('reset clears the search and filters but keeps the sort', async () => {
        const page = await mountSuspended(IndexPage, {
            route: '/?q=brake&brand=DAF&condition=used&sort=price_desc&page=2'
        })

        await page.get('[data-testid="filter-reset"]').trigger('click')
        await vi.waitFor(() => expect(useRoute().query).toEqual({ sort: 'price_desc' }))
    })

    it('explains an empty result when filters are active, and offers to reset them', async () => {
        response = makeListing([])

        const page = await mountSuspended(IndexPage, { route: '/?q=nothing' })

        expect(page.get('[data-testid="parts-empty"]').text()).toContain('No parts match your search and filters.')
        expect(page.text()).toContain('Reset filters')
    })
})

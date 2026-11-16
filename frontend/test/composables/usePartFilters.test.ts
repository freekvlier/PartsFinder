import { describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent } from 'vue'

async function mountFilters(route: string) {
    let filters!: ReturnType<typeof usePartFilters>
    await mountSuspended(defineComponent({
        setup() {
            filters = usePartFilters()
            return () => null
        }
    }), { route })

    return filters
}

describe('usePartFilters', () => {
    it('reads the filters from the URL', async () => {
        const filters = await mountFilters('/?q=%20brake%20&brand=DAF&brand=MAN&condition=new&sort=price_desc&page=4')

        expect(filters.q.value).toBe('brake')
        expect(filters.brands.value).toEqual(['DAF', 'MAN'])
        expect(filters.condition.value).toBe('new')
        expect(filters.sort.value).toBe('price_desc')
        expect(filters.page.value).toBe(4)
        expect(filters.hasFilters.value).toBe(true)
    })

    it('does not count the sort as a filter', async () => {
        const filters = await mountFilters('/?sort=price_asc')

        expect(filters.hasFilters.value).toBe(false)
    })

    it('reset clears search, brands and condition but keeps the sort', async () => {
        const filters = await mountFilters('/?q=brake&brand=DAF&condition=new&sort=price_asc&page=2')

        await filters.reset()
        await vi.waitFor(() => expect(useRoute().query).toEqual({ sort: 'price_asc' }))
    })

    it('has no filters and page 1 on a clean URL', async () => {
        const filters = await mountFilters('/')

        expect(filters.hasFilters.value).toBe(false)
        expect(filters.page.value).toBe(1)
        expect(filters.brands.value).toEqual([])
    })

    it('changing a filter keeps the others and goes back to page 1', async () => {
        const filters = await mountFilters('/?q=brake&condition=used&page=3')

        await filters.change({ sort: 'price_asc' })
        await vi.waitFor(() => expect(useRoute().query).toEqual({ q: 'brake', condition: 'used', sort: 'price_asc' }))
    })

    it('clearing a filter removes it from the URL', async () => {
        const filters = await mountFilters('/?q=brake&condition=used&brand=DAF')

        await filters.change({ condition: undefined, brand: [] })
        await vi.waitFor(() => expect(useRoute().query).toEqual({ q: 'brake' }))
    })

    it('keeps several brands as a list in the URL', async () => {
        const filters = await mountFilters('/')

        await filters.change({ brand: ['DAF', 'Volvo'] })
        await vi.waitFor(() => expect(useRoute().query).toEqual({ brand: ['DAF', 'Volvo'] }))
    })
})

import type { LocationQueryValue } from 'vue-router'
import type { PartCondition, PartSort } from '~/types/parts'

const conditions: PartCondition[] = ['new', 'used']
const sorts: NonNullable<PartSort>[] = ['price_asc', 'price_desc']

type Filters = {
    q?: string
    brand?: string[]
    condition?: PartCondition
    sort?: NonNullable<PartSort>
}

function first(value: LocationQueryValue | LocationQueryValue[] | undefined) {
    return (Array.isArray(value) ? value[0] : value) ?? undefined
}

function oneOf<T extends string>(value: string | undefined, allowed: T[]) {
    return allowed.find(option => option === value)
}

/**
 * The search, filters, sort and page of the parts overview, kept in the URL.
 */
export function usePartFilters() {
    const route = useRoute()

    const q = computed(() => first(route.query.q)?.trim() || undefined)
    const brands = computed(() => [route.query.brand].flat().filter((name): name is string => !!name))
    const condition = computed(() => oneOf(first(route.query.condition), conditions))
    const sort = computed(() => oneOf(first(route.query.sort), sorts))
    const page = computed(() => Math.max(1, Number(first(route.query.page)) || 1))

    const partsQuery = computed(() => ({
        'q': q.value,
        'brand[]': brands.value.length ? brands.value : undefined,
        'condition': condition.value,
        'sort': sort.value,
        'page': page.value
    }))
    const brandsQuery = computed(() => ({ q: q.value, condition: condition.value }))
    const hasFilters = computed(() => !!(q.value || brands.value.length || condition.value))

    /**
     * Change filters in the URL: empty values are dropped and the page goes back to 1.
     */
    function change(changes: Filters, { replace = false } = {}) {
        const query = { ...route.query, ...changes, page: undefined }

        return navigateTo({
            query: Object.fromEntries(Object.entries(query).filter(([, value]) => value?.length)),
            replace
        })
    }

    /** Clear the search and the filters, but keep the chosen sort. */
    function reset() {
        return change({ q: undefined, brand: [], condition: undefined })
    }

    return { q, brands, condition, sort, page, partsQuery, brandsQuery, hasFilters, change, reset }
}

import type { Part, PartsResponse } from '~/types/parts'
import type { Quote, QuoteLine } from '~/types/quotes'

export function makePart(overrides: Partial<Part> = {}): Part {
    return {
        id: 1,
        name: 'DAF XF 106 Sideskirt set',
        oem: '2119711',
        brand: 'DAF',
        category: 'Body',
        condition: 'used',
        price_cents: 79500,
        stock_quantity: 3,
        in_stock: true,
        image: '/img/placeholder-part.svg',
        colours: [],
        ...overrides
    }
}

export function makeListing(parts: Part[], meta: Partial<PartsResponse['meta']> = {}): PartsResponse {
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

export function makeLine(overrides: Partial<QuoteLine> = {}): QuoteLine {
    return {
        id: 11,
        part_id: 1,
        name: 'DAF XF 106 Sideskirt set',
        oem: '2119711',
        image: '/img/placeholder-part.svg',
        colour: null,
        quantity: 2,
        backorder: false,
        unit_price_cents: 79500,
        line_total_cents: 159000,
        ...overrides
    }
}

export const noTotals = { subtotal_cents: 0, discount_cents: 0, vat_cents: 0, total_cents: 0 }

export function makeQuote(overrides: Partial<Quote> = {}): Quote {
    return {
        id: 5,
        status: 'draft',
        submitted_at: null,
        discount_percentage: 10,
        vat_percentage: 21,
        lines: [makeLine()],
        warnings: [],
        totals: { subtotal_cents: 159000, discount_cents: 15900, vat_cents: 30051, total_cents: 173151 },
        ...overrides
    }
}

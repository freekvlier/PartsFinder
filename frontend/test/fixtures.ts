import type { Part } from '~/types/parts'
import type { Quote, QuoteLine } from '~/types/quotes'

export function makePart(overrides: Partial<Part> = {}): Part {
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

export function makeLine(overrides: Partial<QuoteLine> = {}): QuoteLine {
  return {
    id: 11,
    partId: 1,
    name: 'DAF XF 106 Sideskirt set',
    oem: '2119711',
    image: '/img/placeholder-part.svg',
    colour: null,
    quantity: 2,
    backorder: false,
    unitPriceCents: 79500,
    lineTotalCents: 159000,
    ...overrides
  }
}

export const noTotals = { subtotalCents: 0, discountCents: 0, vatCents: 0, totalCents: 0 }

export function makeQuote(overrides: Partial<Quote> = {}): Quote {
  return {
    id: 5,
    status: 'draft',
    submittedAt: null,
    discountPercentage: 10,
    vatPercentage: 21,
    lines: [makeLine()],
    warnings: [],
    totals: { subtotalCents: 159000, discountCents: 15900, vatCents: 30051, totalCents: 173151 },
    ...overrides
  }
}

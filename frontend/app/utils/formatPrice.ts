const euro = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' })

/**
 * Format an amount in integer cents for display, for example 79500 as "€ 795,00".
 */
export function formatPrice(cents: number): string {
    return euro.format(cents / 100)
}

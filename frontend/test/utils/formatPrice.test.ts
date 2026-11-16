import { describe, expect, it } from 'vitest'

describe('formatPrice', () => {
    it('formats integer cents as euros with two decimals', () => {
        expect(formatPrice(79500)).toContain('795,00')
        expect(formatPrice(5)).toContain('0,05')
        expect(formatPrice(1999)).toContain('19,99')
    })

    it('uses the euro currency', () => {
        expect(formatPrice(100)).toContain('€')
    })
})

import { beforeEach, describe, expect, it } from 'vitest'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError } from 'h3'
import App from '~/app.vue'
import type { WorkshopResponse } from '~/types/workshop'
import { makeListing } from './fixtures'

let response: WorkshopResponse
let failWith: number | null

registerEndpoint('/api/me', () => {
    if (failWith) {
        throw createError({ statusCode: failWith })
    }
    return response
})

registerEndpoint('/api/parts', () => makeListing([]))
registerEndpoint('/api/brands', () => ({ data: [] }))

beforeEach(() => {
    clearNuxtData()
    response = { data: { id: 1, name: 'Van Dijk Trucks', discount_percentage: 10 } }
    failWith = null
})

describe('app header', () => {
    it('shows the name of the workshop the app acts as', async () => {
        const app = await mountSuspended(App, { route: '/' })

        expect(app.get('[data-testid="workshop-name"]').text()).toBe('Van Dijk Trucks')
    })

    it('shows no name, and nothing breaks, when the workshop cannot be loaded', async () => {
        failWith = 500

        const app = await mountSuspended(App, { route: '/' })

        expect(app.find('[data-testid="workshop-name"]').exists()).toBe(false)
        expect(app.text()).toContain('Parts Finder')
    })
})

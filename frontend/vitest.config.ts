import { defineVitestConfig } from '@nuxt/test-utils/config'

export default defineVitestConfig({
    test: {
        environment: 'nuxt',
        include: ['test/**/*.test.ts'],
        environmentOptions: {
            nuxt: {
                overrides: {
                    runtimeConfig: { public: { apiBase: '/api', apiToken: 'test-token' } }
                }
            }
        }
    }
})

export default defineNuxtConfig({
    modules: [
        '@nuxt/eslint',
        '@nuxt/ui',
        '@vueuse/nuxt'
    ],

    devtools: {
        enabled: true
    },

    css: ['~/assets/css/main.css'],

    runtimeConfig: {
        apiBase: 'http://localhost/api',
        apiToken: ''
    },

    compatibilityDate: '2026-06-30',

    typescript: {
        tsConfig: {
            include: ['../test/**/*']
        }
    },

    eslint: {
        config: {
            stylistic: {
                indent: 4,
                commaDangle: 'never',
                braceStyle: '1tbs'
            }
        }
    }
})

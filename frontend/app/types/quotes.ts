import type { components, paths } from '~/types/api'

export type Quote = components['schemas']['QuoteResource']

export type QuoteLine = components['schemas']['QuoteLineResource']

export type QuoteResponse = paths['/quotes/{quote}']['get']['responses'][200]['content']['application/json']

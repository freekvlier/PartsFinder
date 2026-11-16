import type { components, paths } from '~/types/api'

export type Workshop = components['schemas']['WorkshopResource']

export type WorkshopResponse = paths['/me']['get']['responses'][200]['content']['application/json']

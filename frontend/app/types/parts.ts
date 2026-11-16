import type { components, paths } from '~/types/api'

export type Part = components['schemas']['PartResource']

export type PartsResponse = paths['/parts']['get']['responses'][200]['content']['application/json']

export type PartResponse = paths['/parts/{part}']['get']['responses'][200]['content']['application/json']

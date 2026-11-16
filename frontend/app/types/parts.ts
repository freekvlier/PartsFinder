import type { components, paths } from '~/types/api'

export type Part = components['schemas']['PartResource']

export type PartsResponse = paths['/parts']['get']['responses'][200]['content']['application/json']

export type PartResponse = paths['/parts/{part}']['get']['responses'][200]['content']['application/json']

export type Brand = components['schemas']['BrandResource']

export type BrandsResponse = paths['/brands']['get']['responses'][200]['content']['application/json']

export type PartCondition = components['schemas']['Condition']

export type PartSort = NonNullable<paths['/parts']['get']['parameters']['query']>['sort']

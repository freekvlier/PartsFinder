import type { WorkshopResponse } from '~/types/workshop'

/**
 * The workshop the app acts as (the owner of the API token). Only an identity hint for the header:
 * when the request fails there is simply no name, and nothing else breaks.
 */
export async function useWorkshop() {
    const { data } = await useFetch<WorkshopResponse>('/api/me', { key: 'workshop' })

    return { workshop: computed(() => data.value?.data ?? null) }
}

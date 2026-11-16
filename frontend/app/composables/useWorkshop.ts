import type { WorkshopResponse } from '~/types/workshop'

/**
 * The workshop that owns the API token, or null when it cannot be loaded.
 */
export async function useWorkshop() {
    const { data } = await useFetch<WorkshopResponse>('/api/me', { key: 'workshop' })

    return { workshop: computed(() => data.value?.data ?? null) }
}

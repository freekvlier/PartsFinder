<script setup lang="ts">
import type { BrandsResponse, PartsResponse } from '~/types/parts'

useHead({ title: 'Parts' })

const route = useRoute()
const { partsQuery, brandsQuery, hasFilters, reset } = usePartFilters()

const { data, status, error, refresh } = await useFetch<PartsResponse>('/api/parts', {
    query: partsQuery
})
const { data: brandData } = await useFetch<BrandsResponse>('/api/brands', {
    query: brandsQuery
})

const emptyActions = computed(() => hasFilters.value
    ? [{ label: 'Reset filters', color: 'neutral' as const, variant: 'outline' as const, onClick: () => reset() }]
    : [])

const pageLink = (target: number) => ({ query: { ...route.query, page: target } })
</script>

<template>
    <UContainer class="py-8">
        <div class="mb-6 flex items-baseline justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                Parts
            </h1>
        </div>

        <PartFilters
            :brands="brandData?.data ?? []"
            :total="data?.meta.total"
        />

        <UAlert
            v-if="error"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Could not load parts"
            description="Something went wrong while fetching the parts. Please try again."
            :actions="[{ label: 'Try again', color: 'error', variant: 'outline', onClick: () => refresh() }]"
            data-testid="parts-error"
        />

        <div
            v-else-if="status === 'pending' && !data"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            data-testid="parts-loading"
        >
            <USkeleton
                v-for="n in 8"
                :key="n"
                class="h-80 w-full"
            />
        </div>

        <UEmpty
            v-else-if="!data?.data.length"
            icon="i-lucide-package-search"
            title="No parts found"
            :description="hasFilters ? 'No parts match your search and filters.' : 'There are no parts on this page.'"
            :actions="emptyActions"
            data-testid="parts-empty"
        />

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <PartCard
                    v-for="part in data.data"
                    :key="part.id"
                    :part="part"
                />
            </div>

            <div
                v-if="data.meta.last_page > 1"
                class="mt-8 flex justify-center"
            >
                <UPagination
                    :page="data.meta.current_page"
                    :total="data.meta.total"
                    :items-per-page="data.meta.per_page"
                    :to="pageLink"
                />
            </div>
        </template>
    </UContainer>
</template>

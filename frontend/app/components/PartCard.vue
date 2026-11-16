<script setup lang="ts">
import type { Part } from '~/types/parts'

defineProps<{ part: Part }>()
</script>

<template>
    <UCard
        class="flex h-full flex-col overflow-hidden"
        :ui="{ header: 'p-0 sm:p-0', body: 'flex flex-1 flex-col gap-3' }"
        data-testid="part-card"
    >
        <template #header>
            <NuxtLink :to="`/parts/${part.id}`">
                <img
                    :src="part.image"
                    :alt="part.name"
                    class="aspect-4/3 w-full object-cover"
                    loading="lazy"
                >
            </NuxtLink>
        </template>

        <div class="flex flex-wrap items-center gap-2">
            <UBadge
                :label="part.brand"
                color="neutral"
                variant="subtle"
            />
            <UBadge
                :label="part.condition === 'new' ? 'New' : 'Used'"
                :color="part.condition === 'new' ? 'primary' : 'warning'"
                variant="subtle"
            />
        </div>

        <h2 class="font-semibold">
            <NuxtLink
                :to="`/parts/${part.id}`"
                class="hover:underline"
            >
                {{ part.name }}
            </NuxtLink>
        </h2>
        <p class="text-sm text-muted">
            OEM {{ part.oem }}
        </p>

        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
            <div>
                <p class="text-lg font-semibold">
                    {{ formatPrice(part.priceCents) }}
                </p>
                <p class="text-xs text-muted">
                    excl. VAT
                </p>
            </div>
            <UBadge
                :label="part.inStock ? `In stock (${part.stockQuantity})` : 'Out of stock'"
                :color="part.inStock ? 'success' : 'error'"
                variant="subtle"
            />
        </div>
    </UCard>
</template>

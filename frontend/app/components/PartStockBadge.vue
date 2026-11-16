<script setup lang="ts">
import type { Part } from '~/types/parts'

defineProps<{ part: Pick<Part, 'in_stock' | 'stock_quantity'> }>()
</script>

<template>
    <UBadge
        :label="part.in_stock ? `In stock (${part.stock_quantity})` : 'Out of stock'"
        :color="part.in_stock ? 'success' : 'error'"
        variant="subtle"
    />
</template>

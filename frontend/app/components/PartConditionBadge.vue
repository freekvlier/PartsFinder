<script setup lang="ts">
import type { PartCondition } from '~/types/parts'

defineProps<{ condition: PartCondition }>()
</script>

<template>
    <UBadge
        :label="condition === 'new' ? 'New' : 'Used'"
        :color="condition === 'new' ? 'primary' : 'warning'"
        variant="subtle"
    />
</template>

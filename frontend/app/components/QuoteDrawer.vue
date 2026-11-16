<script setup lang="ts">
const { isOpen } = useQuote()
</script>

<template>
    <USlideover
        v-model:open="isOpen"
        title="Quote"
    >
        <template #body>
            <QuoteSummary />
        </template>
    </USlideover>
</template>

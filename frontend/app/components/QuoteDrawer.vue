<script setup lang="ts">
const { isOpen } = useQuote()
</script>

<template>
    <USlideover
        v-model:open="isOpen"
        title="Quote"
        description="Prices exclude VAT; totals are calculated by the server."
    >
        <template #body>
            <QuoteSummary />
        </template>
    </USlideover>
</template>

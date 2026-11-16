<script setup lang="ts">
useHead({
  titleTemplate: title => (title ? `${title} · Parts Finder` : 'Parts Finder'),
  htmlAttrs: { lang: 'en' }
})

const { quote, isOpen, restore } = useQuote()

onMounted(restore)
</script>

<template>
  <UApp>
    <UHeader title="Parts Finder">
      <template #right>
        <UChip
          :text="quote?.lines.length"
          :show="!!quote?.lines.length"
          size="3xl"
        >
          <UButton
            icon="i-lucide-file-text"
            label="Quote"
            color="neutral"
            variant="outline"
            @click="isOpen = true"
          />
        </UChip>
        <UColorModeButton />
      </template>
    </UHeader>

    <UMain>
      <NuxtPage />
    </UMain>

    <QuoteDrawer />
  </UApp>
</template>

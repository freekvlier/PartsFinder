<script setup lang="ts">
useHead({
  titleTemplate: title => (title ? `${title} · Parts Finder` : 'Parts Finder'),
  htmlAttrs: { lang: 'en' }
})

const { quote, isOpen, restore } = useQuote()
const { workshop } = await useWorkshop()

onMounted(restore)
</script>

<template>
  <UApp>
    <UHeader title="Parts Finder">
      <template #right>
        <span
          v-if="workshop"
          class="hidden items-center gap-1.5 text-sm text-muted sm:flex"
          data-testid="workshop-name"
        >
          <UIcon
            name="i-lucide-building-2"
            class="size-4"
          />
          {{ workshop.name }}
        </span>
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

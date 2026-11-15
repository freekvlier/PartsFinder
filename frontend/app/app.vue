<script setup lang="ts">
useHead({
  titleTemplate: title => (title ? `${title} · Parts Finder` : 'Parts Finder'),
  htmlAttrs: { lang: 'en' }
})
</script>

<template>
  <UApp>
    <UHeader title="Parts Finder">
      <template #right>
        <UColorModeButton />
      </template>
    </UHeader>

    <UMain>
      <NuxtPage />
    </UMain>
  </UApp>
</template>

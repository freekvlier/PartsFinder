<script setup lang="ts">
useHead({
    titleTemplate: title => (title ? `${title} · Parts Finder` : 'Parts Finder'),
    htmlAttrs: { lang: 'en' }
})
</script>

<template>
    <UApp>
        <NuxtLayout name="workshop">
            <NuxtPage />
        </NuxtLayout>
    </UApp>
</template>

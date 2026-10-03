<script setup lang="ts">
import type { NuxtError } from '#app'

const nuxtApp = useNuxtApp()

defineProps<{
  error: NuxtError
}>()

useSeoMeta({
  title: 'Page not found',
  description: 'We are sorry but this page could not be found.',
})

useHead({
  htmlAttrs: {
    lang: computed(() => unref(nuxtApp.$i18n?.locale) || 'bs'),
  },
})
</script>

<template>
  <UApp>
    <UError :error="error" />
  </UApp>
</template>

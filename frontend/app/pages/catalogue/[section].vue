<script setup lang="ts">
const route = useRoute()
const { t } = useI18n()

const navigationKey = computed(() => {
  const sections = ['products', 'categories', 'manufacturers', 'attributes']
  return sections.includes(String(route.params.section)) ? String(route.params.section) : 'products'
})
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="text-lg font-semibold text-highlighted">
        {{ t('nav.' + navigationKey) }}
      </h2>
    </template>

    <p class="text-muted">
      {{ t('catalogue.comingSoon') }}
    </p>
  </UCard>
</template>

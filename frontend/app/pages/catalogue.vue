<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()
const { t } = useI18n()
const route = useRoute()
const routeBaseName = useRouteBaseName()
const isFullWidthListing = computed(() =>
  !route.params.id
  && [
    'catalogue-products',
    'catalogue-manufacturers',
    'catalogue-attributes',
    'catalogue-custom-fields'
  ].includes(String(routeBaseName(route) || ''))
)

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.products'),
          icon: 'i-lucide-package',
          to: localePath('/catalogue/products')
        },
        {
          label: t('nav.categories'),
          icon: 'i-lucide-folder-tree',
          to: localePath('/catalogue/categories')
        },
        {
          label: t('nav.manufacturers'),
          icon: 'i-lucide-factory',
          to: localePath('/catalogue/manufacturers')
        },
        {
          label: t('nav.attributes'),
          icon: 'i-lucide-list-tree',
          to: localePath('/catalogue/attributes')
        }
      ]
    ] satisfies NavigationMenuItem[][]
)
</script>

<template>
  <UDashboardPanel
    id="catalogue"
    :ui="{ body: isFullWidthListing ? 'min-h-0 !p-0' : 'lg:py-12' }"
  >
    <template #header>
      <UDashboardNavbar :title="t('catalogue.title')">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <div class="w-full">
          <UNavigationMenu :items="links" highlight class="-mx-1" />
          <div id="detail-page-header" class="-mx-6" />
        </div>
      </UDashboardToolbar>
    </template>

    <template #body>
      <div :class="isFullWidthListing ? 'h-full min-h-0 w-full' : 'mx-auto w-full lg:max-w-7xl'">
        <NuxtPage :key="route.path" />
      </div>
    </template>
  </UDashboardPanel>
</template>

<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const { t } = useI18n()

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.taxes'),
          icon: 'i-lucide-percent',
          to: '/shop/taxes'
        },
        {
          label: t('nav.units'),
          icon: 'i-lucide-ruler',
          to: '/shop/units'
        },
        {
          label: t('nav.deliveryTimes'),
          icon: 'i-lucide-truck',
          to: '/shop/delivery-times'
        },
        {
          label: t('nav.customFields'),
          icon: 'i-lucide-panels-top-left',
          to: '/shop/custom-fields'
        }
      ]
    ] satisfies NavigationMenuItem[][]
)
</script>

<template>
  <UDashboardPanel id="shop" :ui="{ body: 'min-h-0 !p-0' }">
    <template #header>
      <UDashboardNavbar :title="t('nav.shop')">
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
      <div class="h-full min-h-0 w-full">
        <NuxtPage />
      </div>
    </template>
  </UDashboardPanel>
</template>

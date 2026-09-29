<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const { t } = useI18n()

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.suppliers'),
          icon: 'i-lucide-truck',
          to: '/inventory/suppliers'
        },
        {
          label: t('purchasing.offers'),
          icon: 'i-lucide-tag',
          to: '/inventory/supplier-offers'
        },
        {
          label: t('purchasing.orders'),
          icon: 'i-lucide-clipboard-list',
          to: '/inventory/purchase-orders'
        },
        {
          label: t('nav.stock'),
          icon: 'i-lucide-boxes',
          to: '/inventory/stock'
        },
        {
          label: t('nav.warehouses'),
          icon: 'i-lucide-warehouse',
          to: '/inventory/warehouses'
        },
        {
          label: t('nav.transfers'),
          icon: 'i-lucide-arrow-left-right',
          to: '/inventory/transfers'
        },
        {
          label: t('nav.stockCounts'),
          icon: 'i-lucide-clipboard-check',
          to: '/inventory/counts'
        }
      ]
    ] satisfies NavigationMenuItem[][]
)
</script>

<template>
  <UDashboardPanel id="inventory" :ui="{ body: 'min-h-0 !p-0' }">
    <template #header>
      <UDashboardNavbar :title="t('nav.inventory')">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar class="overflow-x-auto">
        <UNavigationMenu :items="links" highlight class="-mx-1 min-w-max flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="h-full min-h-0 w-full">
        <NuxtPage />
      </div>
    </template>
  </UDashboardPanel>
</template>

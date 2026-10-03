<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()
const { t } = useI18n()

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.suppliers'),
          icon: 'i-lucide-truck',
          to: localePath('/inventory/suppliers')
        },
        {
          label: t('purchasing.offers'),
          icon: 'i-lucide-tag',
          to: localePath('/inventory/supplier-offers')
        },
        {
          label: t('purchasing.orders'),
          icon: 'i-lucide-clipboard-list',
          to: localePath('/inventory/purchase-orders')
        },
        {
          label: t('supplierInvoices.title'),
          icon: 'i-lucide-file-check-2',
          to: localePath('/inventory/supplier-invoices')
        },
        {
          label: t('replenishment.title'),
          icon: 'i-lucide-chart-no-axes-combined',
          to: localePath('/inventory/replenishment')
        },
        {
          label: t('nav.stock'),
          icon: 'i-lucide-boxes',
          to: localePath('/inventory/stock')
        },
        {
          label: t('nav.warehouses'),
          icon: 'i-lucide-warehouse',
          to: localePath('/inventory/warehouses')
        },
        {
          label: t('nav.transfers'),
          icon: 'i-lucide-arrow-left-right',
          to: localePath('/inventory/transfers')
        },
        {
          label: t('nav.stockCounts'),
          icon: 'i-lucide-clipboard-check',
          to: localePath('/inventory/counts')
        },
        {
          label: t('operationsExceptions.title'),
          icon: 'i-lucide-triangle-alert',
          to: localePath('/inventory/exceptions')
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

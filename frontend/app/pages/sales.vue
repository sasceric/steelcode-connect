<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const { t } = useI18n()

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.orders'),
          icon: 'i-lucide-receipt-text',
          to: '/sales/orders'
        },
        {
          label: t('nav.pickLists'),
          icon: 'i-lucide-clipboard-list',
          to: '/sales/picklists'
        },
        {
          label: t('nav.customers'),
          icon: 'i-lucide-users',
          to: '/sales/customers'
        }
      ]
    ] satisfies NavigationMenuItem[][]
)
</script>

<template>
  <UDashboardPanel id="sales" :ui="{ body: 'min-h-0 !p-0' }">
    <template #header>
      <UDashboardNavbar :title="t('nav.sales')">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UNavigationMenu :items="links" highlight class="-mx-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="h-full min-h-0 w-full">
        <NuxtPage />
      </div>
    </template>
  </UDashboardPanel>
</template>

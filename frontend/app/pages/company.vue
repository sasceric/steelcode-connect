<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()
const { t } = useI18n()

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.details'),
          icon: 'i-lucide-building',
          to: localePath('/company'),
          exact: true,
        },
        {
          label: t('nav.addresses'),
          icon: 'i-lucide-map-pin',
          to: localePath('/company/addresses'),
        },
        {
          label: t('nav.paymentMethods'),
          icon: 'i-lucide-credit-card',
          to: localePath('/company/payment-methods'),
        },
        {
          label: t('nav.billing'),
          icon: 'i-lucide-receipt-text',
          to: localePath('/company/billing'),
        },
        {
          label: t('nav.pricingPlans'),
          icon: 'i-lucide-badge-dollar-sign',
          to: localePath('/company/pricing-plans'),
        },
      ],
    ] satisfies NavigationMenuItem[][],
)
</script>

<template>
  <UDashboardPanel id="company" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar title="Company">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UNavigationMenu :items="links" highlight class="-mx-1 flex-1" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="flex flex-col gap-4 sm:gap-6 lg:gap-12 w-full lg:max-w-5xl mx-auto">
        <NuxtPage />
      </div>
    </template>
  </UDashboardPanel>
</template>

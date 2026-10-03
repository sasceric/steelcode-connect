<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

const localePath = useLocalePath()
const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const open = ref(false)

const links = computed(
  () =>
    [
      [
        {
          label: t('nav.home'),
          icon: 'i-lucide-house',
          to: localePath('/'),
          onSelect: () => {
            open.value = false
          },
        },
        {
          label: t('nav.integrations'),
          icon: 'i-lucide-plug-zap',
          to: localePath('/integrations'),
          onSelect: () => {
            open.value = false
          },
        },
        {
          label: t('nav.catalogue'),
          icon: 'i-lucide-package-search',
          defaultOpen: true,
          type: 'trigger',
          children: [
            {
              label: t('nav.products'),
              icon: 'i-lucide-package',
              to: localePath('/catalogue/products'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.categories'),
              icon: 'i-lucide-folder-tree',
              to: localePath('/catalogue/categories'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.manufacturers'),
              icon: 'i-lucide-factory',
              to: localePath('/catalogue/manufacturers'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.attributes'),
              icon: 'i-lucide-list-tree',
              to: localePath('/catalogue/attributes'),
              onSelect: () => {
                open.value = false
              },
            },
          ],
        },
        {
          label: t('nav.inventory'),
          icon: 'i-lucide-boxes',
          defaultOpen: true,
          type: 'trigger',
          children: [
            {
              label: t('nav.suppliers'),
              icon: 'i-lucide-truck',
              to: localePath('/inventory/suppliers'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('purchasing.offers'),
              icon: 'i-lucide-tag',
              to: localePath('/inventory/supplier-offers'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('purchasing.orders'),
              icon: 'i-lucide-clipboard-list',
              to: localePath('/inventory/purchase-orders'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('supplierInvoices.title'),
              icon: 'i-lucide-file-check-2',
              to: localePath('/inventory/supplier-invoices'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('replenishment.title'),
              icon: 'i-lucide-chart-no-axes-combined',
              to: localePath('/inventory/replenishment'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.stock'),
              icon: 'i-lucide-boxes',
              to: localePath('/inventory/stock'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.warehouses'),
              icon: 'i-lucide-warehouse',
              to: localePath('/inventory/warehouses'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.transfers'),
              icon: 'i-lucide-arrow-left-right',
              to: localePath('/inventory/transfers'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.stockCounts'),
              icon: 'i-lucide-clipboard-check',
              to: localePath('/inventory/counts'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('operationsExceptions.title'),
              icon: 'i-lucide-triangle-alert',
              to: localePath('/inventory/exceptions'),
              onSelect: () => {
                open.value = false
              },
            },
          ],
        },
        {
          label: t('nav.sales'),
          icon: 'i-lucide-shopping-bag',
          defaultOpen: true,
          type: 'trigger',
          children: [
            {
              label: t('nav.orders'),
              icon: 'i-lucide-receipt-text',
              to: localePath('/sales/orders'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.pickLists'),
              icon: 'i-lucide-clipboard-list',
              to: localePath('/sales/picklists'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.customers'),
              icon: 'i-lucide-users',
              to: localePath('/sales/customers'),
              onSelect: () => {
                open.value = false
              },
            },
          ],
        },
        {
          label: t('nav.company'),
          icon: 'i-lucide-building-2',
          defaultOpen: true,
          type: 'trigger',
          children: [
            {
              label: t('nav.details'),
              icon: 'i-lucide-building',
              to: localePath('/company'),
              exact: true,
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.addresses'),
              icon: 'i-lucide-map-pin',
              to: localePath('/company/addresses'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.paymentMethods'),
              icon: 'i-lucide-credit-card',
              to: localePath('/company/payment-methods'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.billing'),
              icon: 'i-lucide-receipt-text',
              to: localePath('/company/billing'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.pricingPlans'),
              icon: 'i-lucide-badge-dollar-sign',
              to: localePath('/company/pricing-plans'),
              onSelect: () => {
                open.value = false
              },
            },
          ],
        },
        {
          label: t('nav.shop'),
          icon: 'i-lucide-store',
          defaultOpen: true,
          type: 'trigger',
          children: [
            {
              label: t('nav.taxes'),
              icon: 'i-lucide-percent',
              to: localePath('/shop/taxes'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.units'),
              icon: 'i-lucide-ruler',
              to: localePath('/shop/units'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.deliveryTimes'),
              icon: 'i-lucide-truck',
              to: localePath('/shop/delivery-times'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.customFields'),
              icon: 'i-lucide-panels-top-left',
              to: localePath('/shop/custom-fields'),
              onSelect: () => {
                open.value = false
              },
            },
          ],
        },
        {
          label: t('nav.settings'),
          to: localePath('/settings'),
          icon: 'i-lucide-settings',
          defaultOpen: true,
          type: 'trigger',
          children: [
            {
              label: t('nav.general'),
              icon: 'i-lucide-settings-2',
              to: localePath('/settings'),
              exact: true,
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.profile'),
              icon: 'i-lucide-user',
              to: localePath('/settings/profile'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.translations'),
              icon: 'i-lucide-languages',
              to: localePath('/settings/translations'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.members'),
              icon: 'i-lucide-users',
              to: localePath('/settings/members'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.notifications'),
              icon: 'i-lucide-bell',
              to: localePath('/settings/notifications'),
              onSelect: () => {
                open.value = false
              },
            },
            {
              label: t('nav.security'),
              icon: 'i-lucide-shield',
              to: localePath('/settings/security'),
              onSelect: () => {
                open.value = false
              },
            },
          ],
        },
      ],
      [
        {
          label: t('nav.help'),
          icon: 'i-lucide-circle-help',
        },
      ],
    ] satisfies NavigationMenuItem[][],
)

const groups = computed(() => [
  {
    id: 'links',
    label: 'Go to',
    items: links.value.flat(),
  },
  {
    id: 'code',
    label: 'Code',
    items: [
      {
        id: 'source',
        label: 'View page source',
        icon: 'i-simple-icons-github',
        to: `https://github.com/nuxt-ui-templates/dashboard/blob/main/app/pages${route.path === '/' ? '/index' : route.path}.vue`,
        target: '_blank',
      },
    ],
  },
])

onMounted(async () => {
  const cookie = useCookie('cookie-consent')
  if (cookie.value === 'accepted') {
    return
  }

  toast.add({
    title: 'We use first-party cookies to enhance your experience on our website.',
    duration: 0,
    close: false,
    actions: [
      {
        label: 'Accept',
        color: 'neutral',
        variant: 'outline',
        onClick: () => {
          cookie.value = 'accepted'
        },
      },
      {
        label: 'Opt out',
        color: 'neutral',
        variant: 'ghost',
      },
    ],
  })
})
</script>

<template>
  <UDashboardGroup unit="rem">
    <UDashboardSidebar
      id="default"
      v-model:open="open"
      collapsible
      resizable
      class="bg-elevated/25"
      :ui="{ footer: 'lg:border-t lg:border-default' }"
    >
      <template #header="{ collapsed }">
        <TeamsMenu :collapsed="collapsed" />
      </template>

      <template #default="{ collapsed }">
        <UDashboardSearchButton :collapsed="collapsed" class="bg-transparent ring-default" />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[0]"
          orientation="vertical"
          tooltip
          popover
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[1]"
          orientation="vertical"
          tooltip
          class="mt-auto"
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <UDashboardSearch :groups="groups" />

    <slot />

    <IntegrationImportProgress />

    <NotificationsSlideover />
  </UDashboardGroup>
</template>

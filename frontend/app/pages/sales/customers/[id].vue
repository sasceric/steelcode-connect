<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type CustomerAddress = {
  id: string
  name: string
  title: string | null
  company: string | null
  department: string | null
  street: string | null
  additionalAddressLine1: string | null
  additionalAddressLine2: string | null
  zipcode: string | null
  city: string | null
  countryCode: string | null
  countryState: string | null
  phone: string | null
  billingDefault: boolean
  shippingDefault: boolean
  customFields: Record<string, unknown>
  sourceFields: Record<string, unknown>
}

type Customer = {
  name: string
  email: string | null
  phone: string | null
  connection: string | null
  customerNumber: string | null
  accountType: string | null
  title: string | null
  active: boolean | null
  vatIds: string[]
  profileImported: boolean
  affiliateCode: string | null
  campaignCode: string | null
  customFields: Record<string, unknown>
  sourceFields: Record<string, unknown>
  addresses: CustomerAddress[]
  orders: { id: string, number: string, status: string, orderedAt: string | null }[]
}

const localePath = useLocalePath()
const route = useRoute()
const { t } = useI18n()
const selectedTab = useRouteTab('overview')
const { data, status } = await useAsyncData(
  () => `sales-customer-${route.params.id}`,
  () => apiFetch<{ customer: Customer }>(`/sales/customers/${route.params.id}`)
)
const customer = computed(() => data.value?.customer)
const source = computed(() => customer.value?.sourceFields || {})

function sourceAssociation(name: string): Record<string, unknown> {
  const associations = source.value._associations
  if (!associations || typeof associations !== 'object' || Array.isArray(associations)) {
    return {}
  }

  const value = (associations as Record<string, unknown>)[name]
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {}
}

function sourceText(value: unknown): string {
  return typeof value === 'string' && value.trim() ? value : '—'
}

const language = computed(() => {
  const item = sourceAssociation('language')
  return [item.name, item.localeCode].filter(value => typeof value === 'string' && value).join(' · ') || '—'
})
const group = computed(() => sourceText(sourceAssociation('group').name))
const salutation = computed(() => sourceText(sourceAssociation('salutation').displayName))
const selectedAddress = ref<CustomerAddress | null>(null)
const addressDetailOpen = ref(false)

function showAddress(address: CustomerAddress) {
  selectedAddress.value = address
  addressDetailOpen.value = true
}

const tabs = computed(() => [
  { label: t('sales.overview'), value: 'overview' },
  { label: t('nav.addresses'), value: 'addresses' },
  { label: t('nav.orders'), value: 'orders' }
])
const addressColumns = computed<TableColumn<CustomerAddress>[]>(() => [
  {
    accessorKey: 'name',
    header: t('sales.customer'),
    cell: ({ row }) => h(
      'button',
      {
        class: 'cursor-pointer font-medium text-primary hover:underline',
        onClick: () => showAddress(row.original)
      },
      row.original.name || row.original.company || '—'
    )
  },
  {
    id: 'address',
    header: t('sales.address'),
    cell: ({ row }) => [
      row.original.street,
      row.original.additionalAddressLine1,
      row.original.additionalAddressLine2,
      row.original.zipcode,
      row.original.city,
      row.original.countryState,
      row.original.countryCode
    ].filter(Boolean).join(', ') || '—'
  },
  {
    id: 'defaults',
    header: t('sales.addressUse'),
    cell: ({ row }) => [
      row.original.billingDefault ? t('sales.billingAddress') : null,
      row.original.shippingDefault ? t('sales.shippingAddress') : null
    ].filter(Boolean).join(' · ') || '—'
  },
  {
    accessorKey: 'phone',
    header: t('common.phone'),
    cell: ({ row }) => row.original.phone || '—'
  }
])
const orderColumns = computed<TableColumn<Customer['orders'][number]>[]>(() => [
  {
    accessorKey: 'number',
    header: t('sales.orderNumber'),
    cell: ({ row }) => h(
      'button',
      {
        class: 'cursor-pointer font-medium text-primary hover:underline',
        onClick: () => navigateTo(localePath(`/sales/orders/${row.original.id}`))
      },
      row.original.number
    )
  },
  { accessorKey: 'status', header: t('sales.status') },
  { accessorKey: 'orderedAt', header: t('sales.orderedAt') }
])
</script>

<template>
  <UDashboardPanel id="sales-customer-detail">
    <template #header>
      <UDashboardNavbar :title="customer?.name || t('nav.customers')">
        <template #leading>
          <UButton
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="ghost"
            :to="localePath('/sales/customers')"
          />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="status === 'pending'" class="p-6 text-sm text-muted">
        {{ t('common.loading') }}
      </div>
      <template v-else-if="customer">
        <UTabs
          v-model="selectedTab"
          :items="tabs"
          :content="false"
        />
        <div v-if="selectedTab === 'overview'" class="grid gap-4 p-4 md:grid-cols-2">
          <UCard>
            <template #header>
              {{ t('sales.customer') }}
            </template>
            <div class="space-y-2 text-sm">
              <p v-if="!customer.profileImported" class="text-warning">
                {{ t('sales.orderOnlyCustomer') }}
              </p>
              <p>{{ customer.email || '—' }}</p>
              <p>{{ customer.phone || '—' }}</p>
              <p class="text-muted">
                {{ customer.connection || '—' }}
              </p>
            </div>
          </UCard>
          <UCard>
            <template #header>
              {{ t('sales.profile') }}
            </template>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
              <dt class="text-muted">
                {{ t('sales.customerNumber') }}
              </dt>
              <dd>{{ customer.customerNumber || '—' }}</dd>
              <dt class="text-muted">
                {{ t('sales.accountType') }}
              </dt>
              <dd>{{ customer.accountType || '—' }}</dd>
              <dt class="text-muted">
                {{ t('sales.title') }}
              </dt>
              <dd>{{ customer.title || '—' }}</dd>
              <dt class="text-muted">
                {{ t('sales.status') }}
              </dt>
              <dd>{{ customer.active === null ? '—' : customer.active ? t('sales.active') : t('sales.inactive') }}</dd>
              <dt class="text-muted">
                {{ t('sales.vatIds') }}
              </dt>
              <dd>{{ customer.vatIds.join(', ') || '—' }}</dd>
              <dt class="text-muted">
                {{ t('sales.affiliateCode') }}
              </dt>
              <dd>{{ customer.affiliateCode || '—' }}</dd>
              <dt class="text-muted">
                {{ t('sales.campaignCode') }}
              </dt>
              <dd>{{ customer.campaignCode || '—' }}</dd>
              <dt class="text-muted">
                {{ t('sales.language') }}
              </dt>
              <dd>{{ language }}</dd>
              <dt class="text-muted">
                {{ t('sales.customerGroup') }}
              </dt>
              <dd>{{ group }}</dd>
              <dt class="text-muted">
                {{ t('sales.salutation') }}
              </dt>
              <dd>{{ salutation }}</dd>
              <dt class="text-muted">
                {{ t('sales.birthday') }}
              </dt>
              <dd>{{ sourceText(source.birthday) }}</dd>
            </dl>
          </UCard>
          <CustomFieldSetsCard
            :title="t('nav.customFields')"
            entity-type="customer"
            :fields="customer.customFields"
          />
          <SourceFieldsCard
            :title="t('sales.sourceFields')"
            :fields="customer.sourceFields"
          />
        </div>
        <AppDataTable
          v-else-if="selectedTab === 'addresses'"
          :data="customer.addresses"
          :columns="addressColumns"
          table-key="sales-customer-addresses"
          max-height="h-auto"
        >
          <template #empty>
            <AppEmptyState
              :title="t('sales.noSavedAddresses')"
              :description="t('sales.noSavedAddressesDescription')"
              icon="i-lucide-map-pin"
            />
          </template>
        </AppDataTable>
        <AppDataTable
          v-else
          :data="customer.orders"
          :columns="orderColumns"
          table-key="sales-customer-orders"
          max-height="h-auto"
        >
          <template #empty>
            <AppEmptyState
              :title="t('sales.noCustomerOrders')"
              :description="t('sales.noCustomerOrdersDescription')"
              icon="i-lucide-shopping-bag"
            />
          </template>
        </AppDataTable>
      </template>
    </template>
  </UDashboardPanel>
  <UModal
    v-model:open="addressDetailOpen"
    :title="selectedAddress?.name || t('sales.address')"
    :ui="{ content: 'sm:max-w-3xl' }"
  >
    <template #body>
      <div v-if="selectedAddress" class="space-y-4">
        <SourceFieldsCard
          :title="t('nav.customFields')"
          :fields="selectedAddress.customFields"
        />
        <SourceFieldsCard
          :title="t('sales.sourceFields')"
          :fields="selectedAddress.sourceFields"
        />
      </div>
    </template>
  </UModal>
</template>

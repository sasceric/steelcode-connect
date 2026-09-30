<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type OrderDetail = {
  number: string
  status: string
  sourceStatus: string | null
  unresolvedSkus: string[]
  shipmentReconciliationRequired: boolean
  manualShipmentTargets: Record<string, string>
  pickList: { itemId: string, warehouseId: string, warehouse: string, sku: string, name: string, quantity: string }[]
  customer: string
  customerId: string | null
  primaryPaymentExternalId: string | null
  primaryDeliveryExternalId: string | null
  connection: string
  currency: string | null
  orderedAt: string | null
  customerSnapshot: Record<string, unknown>
  customFields: Record<string, unknown>
  sourceFields: Record<string, unknown>
  billingAddress: Record<string, unknown>
  shippingAddress: Record<string, unknown>
  totals: {
    taxStatus: string | null
    amountNet: string | null
    amountTax: string | null
    amountGross: string | null
    shippingNet: string | null
    shippingTax: string | null
    shippingGross: string | null
  }
  items: { id: string, externalLineId: string, sku: string | null, type: string, name: string, quantity: string, productResolved: boolean, reservedQuantity: string, fulfilledQuantity: string, unitGross: string | null, totalTax: string | null, totalGross: string | null, sourceFields: Record<string, unknown> }[]
  payments: { externalId: string, method: string | null, state: string | null, reference: string | null, amount: string | null, currency: string | null, sourceFields: Record<string, unknown> }[]
  deliveries: { externalId: string, method: string | null, state: string | null, trackingNumber: string | null, trackingCodes: string[], shippingGross: string | null, sourceFields: Record<string, unknown> }[]
}

const route = useRoute()
const { t } = useI18n()
const notify = useAppToast()
const selectedTab = useRouteTab('general')
if (!['general', 'details', 'returns'].includes(selectedTab.value)) {
  selectedTab.value = selectedTab.value === 'overview' ? 'general' : 'details'
}
const { data, status, refresh } = await useAsyncData(
  () => `sales-order-${route.params.id}`,
  () => apiFetch<{ order: OrderDetail }>(`/sales/orders/${route.params.id}`)
)
const order = computed(() => data.value?.order)
const shipmentTargets = ref<Record<string, string>>({})
const savingShipment = ref(false)
const productItems = computed(() => order.value?.items.filter(item => item.type === 'product') || [])
watch(order, (value) => {
  if (!value) {
    return
  }

  shipmentTargets.value = Object.fromEntries(
    value.items
      .filter(item => item.type === 'product')
      .map(item => [
        item.externalLineId,
        value.manualShipmentTargets[item.externalLineId] ?? item.fulfilledQuantity
      ])
  )
}, { immediate: true })

async function saveShipmentReconciliation() {
  if (!order.value || savingShipment.value) {
    return
  }

  savingShipment.value = true
  try {
    await apiFetch(`/sales/orders/${route.params.id}/shipment-reconciliation`, {
      method: 'POST',
      body: { targets: shipmentTargets.value }
    })
    await refresh()
    notify.success(t('common.changesSaved'), t('sales.shipmentReconciliationSaved'))
  } catch (error: unknown) {
    notify.error(
      t('common.tryAgain'),
      error instanceof Error ? error.message : t('common.tryAgain')
    )
  } finally {
    savingShipment.value = false
  }
}
const sourceDetailOpen = ref(false)
const sourceDetailTitle = ref('')
const selectedSourceFields = ref<Record<string, unknown>>({})

function showSourceFields(title: string, fields: Record<string, unknown>) {
  sourceDetailTitle.value = title
  selectedSourceFields.value = fields
  sourceDetailOpen.value = true
}

const tabs = computed(() => [
  { label: t('sales.general'), value: 'general' },
  { label: t('sales.details'), value: 'details' },
  { label: t('salesReturns.title'), value: 'returns' }
])
const textValue = (value: unknown) => typeof value === 'string' ? value.trim() : ''
const fullName = (value: Record<string, unknown>) =>
  [textValue(value.firstName), textValue(value.lastName)].filter(Boolean).join(' ')
const customerSummary = (value: Record<string, unknown>) =>
  textValue(value.email) || textValue(value.company) || fullName(value) || '—'
const address = (value: Record<string, unknown>) =>
  [
    textValue(value.company) || fullName(value),
    textValue(value.street),
    [textValue(value.zipcode), textValue(value.city)].filter(Boolean).join(' '),
    textValue(value.countryCode)
  ].filter(Boolean).join(', ') || '—'
const money = (value: string | null | undefined, currency: string | null | undefined) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  const amount = Number(value)
  if (!Number.isFinite(amount)) {
    return '—'
  }

  if (currency && /^[A-Z]{3}$/.test(currency)) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
    } catch {
      // An unknown source currency still displays its recorded numeric amount.
    }
  }

  return amount.toFixed(2)
}
const itemsGross = computed(() => {
  const gross = order.value?.totals.amountGross
  const shipping = order.value?.totals.shippingGross
  return gross !== null && gross !== undefined && shipping !== null && shipping !== undefined
    ? String(Number(gross) - Number(shipping))
    : null
})
const shippingNet = computed(() => {
  if (order.value?.totals.shippingNet !== null && order.value?.totals.shippingNet !== undefined) {
    return order.value.totals.shippingNet
  }

  const gross = order.value?.totals.shippingGross
  const tax = order.value?.totals.shippingTax
  return gross !== null && gross !== undefined && tax !== null && tax !== undefined
    ? String(Number(gross) - Number(tax))
    : null
})
const orderedAt = computed(() => order.value?.orderedAt
  ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(order.value.orderedAt))
  : '—')
const currentPayment = computed(() => order.value?.payments.find(
  payment => payment.externalId === order.value?.primaryPaymentExternalId
) || order.value?.payments.at(-1))
const currentDelivery = computed(() => order.value?.deliveries.find(
  delivery => delivery.externalId === order.value?.primaryDeliveryExternalId
) || order.value?.deliveries.at(-1))
const displayStatus = computed(() => {
  if (!order.value) {
    return '—'
  }

  return order.value.status === 'historical'
    ? `${t('sales.historical')} · ${order.value.sourceStatus?.replaceAll('_', ' ') || t('sales.unknownSourceState')}`
    : order.value.status.replaceAll('_', ' ')
})
const itemColumns = computed<TableColumn<OrderDetail['items'][number]>[]>(() => [
  {
    accessorKey: 'name',
    header: t('products.product'),
    cell: ({ row }) => h('div', { class: 'flex items-center gap-2' }, [
      h('button', {
        class: 'cursor-pointer text-left font-medium text-primary hover:underline',
        onClick: () => showSourceFields(row.original.name, row.original.sourceFields)
      }, row.original.name),
      !row.original.productResolved
        ? h(resolveComponent('UBadge'), {
            label: t('sales.unmatchedProduct'),
            color: 'warning',
            variant: 'subtle',
            size: 'xs'
          })
        : null
    ])
  },
  { accessorKey: 'type', header: t('sales.lineType'), cell: ({ row }) => row.original.type.replaceAll('_', ' ') },
  { accessorKey: 'sku', header: t('products.productNumber'), cell: ({ row }) => row.original.sku || '—' },
  { accessorKey: 'quantity', header: t('sales.quantity') },
  { accessorKey: 'unitGross', header: t('sales.unitGross'), cell: ({ row }) => money(row.original.unitGross, order.value?.currency) },
  { accessorKey: 'totalGross', header: t('sales.lineTotal'), cell: ({ row }) => money(row.original.totalGross, order.value?.currency) },
  { accessorKey: 'reservedQuantity', header: t('sales.reserved') },
  { accessorKey: 'fulfilledQuantity', header: t('sales.fulfilled') }
])
const paymentColumns = computed<TableColumn<OrderDetail['payments'][number]>[]>(() => [
  {
    accessorKey: 'method',
    header: t('sales.method'),
    cell: ({ row }) => h('button', {
      class: 'cursor-pointer text-left font-medium text-primary hover:underline',
      onClick: () => showSourceFields(row.original.method || row.original.externalId, row.original.sourceFields)
    }, row.original.method || '—')
  },
  { accessorKey: 'state', header: t('sales.status') },
  { accessorKey: 'reference', header: t('sales.reference') },
  { accessorKey: 'amount', header: t('sales.amount'), cell: ({ row }) => money(row.original.amount, row.original.currency) }
])
const deliveryColumns = computed<TableColumn<OrderDetail['deliveries'][number]>[]>(() => [
  {
    accessorKey: 'method',
    header: t('sales.method'),
    cell: ({ row }) => h('button', {
      class: 'cursor-pointer text-left font-medium text-primary hover:underline',
      onClick: () => showSourceFields(row.original.method || row.original.externalId, row.original.sourceFields)
    }, row.original.method || '—')
  },
  { accessorKey: 'state', header: t('sales.status') },
  { accessorKey: 'trackingCodes', header: t('sales.trackingNumber'), cell: ({ row }) => row.original.trackingCodes.join(', ') || '—' },
  { accessorKey: 'shippingGross', header: t('sales.shippingGross'), cell: ({ row }) => money(row.original.shippingGross, order.value?.currency) }
])
</script>

<template>
  <UDashboardPanel id="sales-order-detail">
    <template #header>
      <UDashboardNavbar :title="order ? `${t('nav.orders')} ${order.number}` : t('nav.orders')">
        <template #leading>
          <UButton
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="ghost"
            to="/sales/orders"
          />
        </template>
        <template #right>
          <UButton
            v-if="order?.pickList.length"
            icon="i-lucide-clipboard-list"
            color="neutral"
            variant="outline"
            :to="`/sales/picklists/${route.params.id}`"
          >
            {{ t('sales.viewPickList') }}
          </UButton>
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="status === 'pending'" class="p-6 text-sm text-muted">
        {{ t('common.loading') }}
      </div>
      <template v-else-if="order">
        <UTabs
          v-model="selectedTab"
          :items="tabs"
          :content="false"
          class="w-full"
        />
        <div v-if="selectedTab === 'general'" class="space-y-4 p-4">
          <div class="grid gap-4 md:grid-cols-2">
            <UCard>
              <template #header>
                {{ t('sales.customer') }}
              </template>
              <div class="space-y-2 text-sm">
                <NuxtLink
                  v-if="order.customerId"
                  :to="`/sales/customers/${order.customerId}`"
                  class="font-medium text-primary hover:underline"
                >
                  {{ order.customer }}
                </NuxtLink>
                <p v-else class="font-medium">
                  {{ order.customer }}
                </p>
                <p class="text-muted">
                  {{ customerSummary(order.customerSnapshot) }}
                </p>
                <p v-if="textValue(order.customerSnapshot.customerNumber)" class="text-muted">
                  {{ t('sales.customerNumber') }}: {{ order.customerSnapshot.customerNumber }}
                </p>
              </div>
            </UCard>
            <UCard>
              <template #header>
                {{ t('sales.orderInformation') }}
              </template>
              <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted">
                  {{ t('sales.status') }}
                </dt>
                <dd>{{ displayStatus }}</dd>
                <dt class="text-muted">
                  {{ t('sales.orderedAt') }}
                </dt>
                <dd>{{ orderedAt }}</dd>
                <dt class="text-muted">
                  {{ t('sales.source') }}
                </dt>
                <dd>{{ order.connection }}</dd>
                <dt class="text-muted">
                  {{ t('sales.currency') }}
                </dt>
                <dd>{{ order.currency || '—' }}</dd>
              </dl>
            </UCard>
            <UCard>
              <template #header>
                {{ t('sales.billingAddress') }}
              </template>
              <p class="text-sm">
                {{ address(order.billingAddress) }}
              </p>
            </UCard>
            <UCard>
              <template #header>
                {{ t('sales.shippingAddress') }}
              </template>
              <p class="text-sm">
                {{ address(order.shippingAddress) }}
              </p>
            </UCard>
            <UCard>
              <template #header>
                {{ t('sales.paymentAndShipping') }}
              </template>
              <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted">
                  {{ t('sales.paymentMethod') }}
                </dt>
                <dd>{{ currentPayment?.method || '—' }}</dd>
                <dt class="text-muted">
                  {{ t('sales.paymentStatus') }}
                </dt>
                <dd>{{ currentPayment?.state || '—' }}</dd>
                <dt class="text-muted">
                  {{ t('sales.shippingMethod') }}
                </dt>
                <dd>{{ currentDelivery?.method || '—' }}</dd>
                <dt class="text-muted">
                  {{ t('sales.deliveryStatus') }}
                </dt>
                <dd>{{ currentDelivery?.state || '—' }}</dd>
              </dl>
            </UCard>
            <UCard>
              <template #header>
                {{ t('sales.orderTotals') }}
              </template>
              <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted">
                  {{ t('sales.itemsGross') }}
                </dt>
                <dd>{{ money(itemsGross, order.currency) }}</dd>
                <dt class="text-muted">
                  {{ t('sales.shippingNet') }}
                </dt>
                <dd>{{ money(shippingNet, order.currency) }}</dd>
                <dt class="text-muted">
                  {{ t('sales.shippingTax') }}
                </dt>
                <dd>{{ money(order.totals.shippingTax, order.currency) }}</dd>
                <dt class="text-muted">
                  {{ t('sales.shippingGross') }}
                </dt>
                <dd>{{ money(order.totals.shippingGross, order.currency) }}</dd>
                <dt class="text-muted">
                  {{ t('sales.netTotal') }}
                </dt>
                <dd>{{ money(order.totals.amountNet, order.currency) }}</dd>
                <dt class="text-muted">
                  {{ t('sales.taxTotal') }}
                </dt>
                <dd>{{ money(order.totals.amountTax, order.currency) }}</dd>
                <dt class="border-t border-default pt-2 font-medium">
                  {{ t('sales.grandTotal') }}
                </dt>
                <dd class="border-t border-default pt-2 font-semibold">
                  {{ money(order.totals.amountGross, order.currency) }}
                </dd>
              </dl>
            </UCard>
          </div>
          <UAlert
            v-if="order.unresolvedSkus.length"
            icon="i-lucide-triangle-alert"
            color="warning"
            variant="subtle"
            :title="t('sales.unmatchedProducts', { count: order.unresolvedSkus.length })"
            :description="t('sales.unmatchedProductsDescription', { skus: order.unresolvedSkus.join(', ') })"
          />
          <div class="grid gap-4 md:grid-cols-2">
            <SourceFieldsCard
              :title="t('nav.customFields')"
              :fields="order.customFields"
            />
            <SourceFieldsCard
              :title="t('sales.sourceFields')"
              :fields="order.sourceFields"
            />
          </div>
        </div>
        <SalesOrderReturnsPanel
          v-else-if="selectedTab === 'returns'"
          :order-id="String(route.params.id)"
        />
        <div v-else class="space-y-6 p-4">
          <UCard v-if="order.shipmentReconciliationRequired && order.status !== 'historical' && order.status !== 'cancelled'">
            <template #header>
              {{ t('sales.shipmentReconciliation') }}
            </template>
            <p class="mb-4 text-sm text-muted">
              {{ t('sales.shipmentReconciliationDescription') }}
            </p>
            <div class="grid gap-4 md:grid-cols-2">
              <UFormField
                v-for="item in productItems"
                :key="item.externalLineId"
                :label="`${item.name} (${item.sku || item.externalLineId})`"
                :description="t('sales.shipmentReconciliationRange', { fulfilled: item.fulfilledQuantity, ordered: item.quantity })"
              >
                <UInput
                  v-model="shipmentTargets[item.externalLineId]"
                  type="number"
                  min="0"
                  :max="item.quantity"
                  step="1"
                />
              </UFormField>
            </div>
            <UButton
              class="mt-4"
              icon="i-lucide-package-check"
              :loading="savingShipment"
              @click="saveShipmentReconciliation"
            >
              {{ t('sales.saveShipmentReconciliation') }}
            </UButton>
          </UCard>
          <section class="space-y-2">
            <h2 class="text-sm font-semibold text-highlighted">
              {{ t('sales.items') }}
            </h2>
            <AppDataTable
              table-key="sales-order-items"
              :data="order.items"
              :columns="itemColumns"
              max-height="h-auto"
            />
          </section>
          <section class="space-y-2">
            <h2 class="text-sm font-semibold text-highlighted">
              {{ t('sales.payments') }}
            </h2>
            <AppDataTable
              table-key="sales-order-payments"
              :data="order.payments"
              :columns="paymentColumns"
              max-height="h-auto"
            >
              <template #empty>
                <AppEmptyState
                  :title="t('sales.noPayments')"
                  :description="t('sales.noPaymentsDescription')"
                  icon="i-lucide-credit-card"
                />
              </template>
            </AppDataTable>
          </section>
          <section class="space-y-2">
            <h2 class="text-sm font-semibold text-highlighted">
              {{ t('sales.deliveries') }}
            </h2>
            <AppDataTable
              table-key="sales-order-deliveries"
              :data="order.deliveries"
              :columns="deliveryColumns"
              max-height="h-auto"
            >
              <template #empty>
                <AppEmptyState
                  :title="t('sales.noDeliveries')"
                  :description="t('sales.noDeliveriesDescription')"
                  icon="i-lucide-truck"
                />
              </template>
            </AppDataTable>
          </section>
        </div>
      </template>
    </template>
  </UDashboardPanel>
  <UModal
    v-model:open="sourceDetailOpen"
    :title="sourceDetailTitle"
    :ui="{ content: 'sm:max-w-3xl' }"
  >
    <template #body>
      <SourceFieldsCard
        :title="t('sales.sourceFields')"
        :fields="selectedSourceFields"
      />
    </template>
  </UModal>
</template>

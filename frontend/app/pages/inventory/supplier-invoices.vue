<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type InvoiceLine = {
  purchaseOrderItemId: string
  sku: string | null
  quantity: number
  unitCost: number
}
type Invoice = {
  id: string
  invoiceNumber: string
  invoiceDate: string
  status: 'draft' | 'matched' | 'disputed' | 'void'
  supplierName: string
  purchaseOrderId: string
  purchaseOrderReference: string
  currency: string
  declaredTotal: number
  tax?: number
  shipping?: number
  discount?: number
  note?: string | null
  matchIssues?: string[]
  voidReason?: string | null
  items?: InvoiceLine[]
}
type OrderOption = { id: string, reference: string, supplierName: string, currency: string }
type AvailableLine = {
  id: string
  sku: string | null
  purchaseUnit: string
  receivedGood: number
  receivedDamaged: number
  matchedBilled: number
  availableToInvoice: number
  poUnitCost: number
}
type Availability = {
  id: string
  reference: string
  supplierName: string
  currency: string
  items: AvailableLine[]
}

const { t } = useI18n()
const notify = useAppToast()
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const page = ref(1)
const pageSize = ref(25)
const search = ref('')
const debouncedSearch = ref('')
const modalOpen = ref(false)
const current = ref<Invoice | null>(null)
const availability = ref<Availability | null>(null)
const saving = ref(false)
const matchOpen = ref(false)
const voidOpen = ref(false)
const voidReason = ref('')
const orderSearch = ref('')
const debouncedOrderSearch = ref('')
const orderPage = ref(1)
const orderOptions = ref<OrderOption[]>([])
const orderHasMore = ref(false)
const ordersLoading = ref(false)
let orderRequestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined
let orderSearchTimer: ReturnType<typeof setTimeout> | undefined
const form = reactive({
  purchaseOrderId: '',
  invoiceNumber: '',
  invoiceDate: '',
  tax: 0,
  shipping: 0,
  discount: 0,
  declaredTotal: 0,
  note: '',
  items: [] as InvoiceLine[]
})
const listUrl = computed(() => {
  const params = new URLSearchParams({ page: String(page.value), limit: String(pageSize.value) })
  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  return `/inventory/supplier-invoices?${params}`
})
const { data, status, refresh } = await useAsyncData('supplier-invoices-page', () =>
  apiFetch<{ items: Invoice[], pagination: { total: number } }>(listUrl.value)
)
const orderItems = computed(() => {
  const selected = availability.value && !orderOptions.value.some(order => order.id === availability.value?.id)
    ? [{ id: availability.value.id, reference: availability.value.reference, supplierName: availability.value.supplierName, currency: availability.value.currency }, ...orderOptions.value]
    : orderOptions.value
  return selected.map(order => ({ label: `${order.reference} · ${order.supplierName}`, value: order.id }))
})
const additionalLineItems = computed(() => (availability.value?.items ?? [])
  .filter(item => item.availableToInvoice > 0 && !form.items.some(line => line.purchaseOrderItemId === item.id))
  .map(item => ({ label: `${item.sku ?? item.id} · ${item.availableToInvoice} ${item.purchaseUnit}`, value: item.id })))
const isEditable = computed(() => !current.value || ['draft', 'disputed'].includes(current.value.status))
const computedTotal = computed(() => Math.round((
  form.items.reduce((sum, item) => sum + item.quantity * item.unitCost, 0)
  + Number(form.tax || 0)
  + Number(form.shipping || 0)
  - Number(form.discount || 0)
) * 100) / 100)
const money = (amount: number, currency: string) =>
  new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
const loadOrders = async (reset = false) => {
  if (ordersLoading.value && !reset) return
  if (reset) {
    orderRequestId += 1
    orderPage.value = 1
    orderOptions.value = []
  }
  const requestId = orderRequestId
  ordersLoading.value = true
  try {
    const params = new URLSearchParams({ page: String(orderPage.value), limit: '25' })
    if (debouncedOrderSearch.value) params.set('search', debouncedOrderSearch.value)
    const response = await apiFetch<{ items: OrderOption[], hasMore: boolean }>(`/inventory/supplier-invoices/orders?${params}`)
    if (requestId !== orderRequestId) return
    orderOptions.value = [...orderOptions.value, ...response.items]
    orderHasMore.value = response.hasMore
    orderPage.value += 1
  } finally {
    if (requestId === orderRequestId) ordersLoading.value = false
  }
}
const loadAvailability = async (id: string) => {
  if (!id) {
    availability.value = null
    form.items = []
    return
  }
  const response = await apiFetch<{ order: Availability }>(`/inventory/supplier-invoices/orders/${id}/availability`)
  if (form.purchaseOrderId !== id) return
  availability.value = response.order
  if (!current.value) {
    form.items = response.order.items.filter(item => item.availableToInvoice > 0).map(item => ({
      purchaseOrderItemId: item.id,
      sku: item.sku,
      quantity: item.availableToInvoice,
      unitCost: item.poUnitCost
    }))
    form.declaredTotal = computedTotal.value
  }
}
const chooseOrder = (value: string | string[]) => {
  if (typeof value !== 'string') return
  form.purchaseOrderId = value
  void loadAvailability(value)
}
const addLine = (value: string | string[]) => {
  if (typeof value !== 'string') return
  const item = availability.value?.items.find(candidate => candidate.id === value)
  if (!item || form.items.some(line => line.purchaseOrderItemId === value)) return
  form.items.push({
    purchaseOrderItemId: item.id,
    sku: item.sku,
    quantity: item.availableToInvoice,
    unitCost: item.poUnitCost
  })
}
const removeLine = (id: string) => {
  form.items = form.items.filter(item => item.purchaseOrderItemId !== id)
}
const startCreate = () => {
  current.value = null
  availability.value = null
  Object.assign(form, {
    purchaseOrderId: '', invoiceNumber: '', invoiceDate: new Date().toISOString().slice(0, 10),
    tax: 0, shipping: 0, discount: 0, declaredTotal: 0, note: '', items: []
  })
  void loadOrders(true)
  modalOpen.value = true
}
const openInvoice = async (invoice: Invoice) => {
  try {
    const response = await apiFetch<{ invoice: Invoice }>(`/inventory/supplier-invoices/${invoice.id}`)
    current.value = response.invoice
    Object.assign(form, {
      purchaseOrderId: response.invoice.purchaseOrderId,
      invoiceNumber: response.invoice.invoiceNumber,
      invoiceDate: response.invoice.invoiceDate,
      tax: response.invoice.tax ?? 0,
      shipping: response.invoice.shipping ?? 0,
      discount: response.invoice.discount ?? 0,
      declaredTotal: response.invoice.declaredTotal,
      note: response.invoice.note ?? '',
      items: response.invoice.items ?? []
    })
    void loadOrders(true)
    await loadAvailability(response.invoice.purchaseOrderId)
    modalOpen.value = true
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  }
}
const saveInvoice = async () => {
  if (!form.purchaseOrderId || !form.invoiceNumber.trim() || !form.invoiceDate || form.items.length === 0
    || form.items.some(item => item.quantity <= 0 || item.unitCost < 0)
    || [form.tax, form.shipping, form.discount, form.declaredTotal].some(value => !Number.isFinite(value) || value < 0)) {
    notify.error(t('common.tryAgain'), t('supplierInvoices.validation'))
    return
  }
  saving.value = true
  try {
    const response = await apiFetch<{ invoice: Invoice }>(
      current.value ? `/inventory/supplier-invoices/${current.value.id}` : '/inventory/supplier-invoices',
      { method: current.value ? 'PUT' : 'POST', body: form }
    )
    current.value = response.invoice
    await refresh()
    notify.success(t('common.changesSaved'), t('supplierInvoices.saved'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const askMatch = () => {
  matchOpen.value = true
}
const matchInvoice = async () => {
  if (!current.value) return
  saving.value = true
  try {
    const response = await apiFetch<{ invoice: Invoice }>(`/inventory/supplier-invoices/${current.value.id}/match`, { method: 'POST' })
    current.value = response.invoice
    matchOpen.value = false
    await refresh()
    notify.success(t('common.changesSaved'), t(response.invoice.status === 'matched' ? 'supplierInvoices.matched' : 'supplierInvoices.disputed'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const askVoid = () => {
  voidReason.value = ''
  voidOpen.value = true
}
const closeVoid = () => {
  voidOpen.value = false
}
const voidInvoice = async () => {
  if (!current.value || !voidReason.value.trim()) {
    notify.error(t('common.tryAgain'), t('supplierInvoices.voidReasonRequired'))
    return
  }
  saving.value = true
  try {
    const response = await apiFetch<{ invoice: Invoice }>(`/inventory/supplier-invoices/${current.value.id}/void`, {
      method: 'POST', body: { reason: voidReason.value.trim() }
    })
    current.value = response.invoice
    voidOpen.value = false
    await refresh()
    notify.success(t('common.changesSaved'), t('supplierInvoices.voided'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const columns: TableColumn<Invoice>[] = [
  { id: 'number', header: () => t('supplierInvoices.number'), cell: ({ row }) => h('button', { class: 'cursor-pointer text-left font-medium text-highlighted hover:text-primary', onClick: () => openInvoice(row.original) }, row.original.invoiceNumber) },
  { accessorKey: 'supplierName', header: () => t('suppliers.name') },
  { accessorKey: 'purchaseOrderReference', header: () => t('purchasing.reference') },
  { accessorKey: 'invoiceDate', header: () => t('supplierInvoices.date') },
  { id: 'total', header: () => t('purchasing.total'), cell: ({ row }) => money(row.original.declaredTotal, row.original.currency) },
  { id: 'status', header: () => t('inventory.status'), cell: ({ row }) => h(UBadge, { color: row.original.status === 'matched' ? 'success' : row.original.status === 'disputed' ? 'warning' : 'neutral', variant: 'subtle' }, () => t(`supplierInvoices.status.${row.original.status}`)) },
  { id: 'actions', header: '', enableHiding: false, enableSorting: false, cell: ({ row }) => h('div', { class: 'flex justify-end' }, h(UDropdownMenu, { items: [[{ label: t('purchasing.view'), icon: 'i-lucide-eye', onSelect: () => openInvoice(row.original) }]], content: { align: 'end' } }, () => h(UButton, { icon: 'i-lucide-ellipsis-vertical', color: 'neutral', variant: 'ghost' }))) }
]
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { debouncedSearch.value = value.trim() }, 250)
})
watch(orderSearch, (value) => {
  clearTimeout(orderSearchTimer)
  orderSearchTimer = setTimeout(() => { debouncedOrderSearch.value = value.trim() }, 250)
})
watch(debouncedOrderSearch, () => { void loadOrders(true) })
watch(listUrl, () => { void refresh() })
watch([pageSize, debouncedSearch], () => { page.value = 1 })
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  clearTimeout(orderSearchTimer)
})
</script>

<template>
  <AppDataTable
    :data="data?.items ?? []"
    :columns="columns"
    :get-row-id="row => row.id"
    :loading="status === 'pending'"
    table-key="supplier-invoices"
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <p class="text-sm font-medium text-highlighted">
            {{ t('supplierInvoices.title') }} ({{ data?.pagination.total ?? 0 }})
          </p>
          <UInput v-model="search" icon="i-lucide-search" :placeholder="t('supplierInvoices.search')" />
        </div>
        <UButton :label="t('supplierInvoices.add')" @click="startCreate" />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :title="t('supplierInvoices.empty')"
        :description="t('supplierInvoices.emptyDescription')"
        icon="i-lucide-file-check-2"
      />
    </template>
    <template #footer>
      <TablePaginationFooter
        v-model:page="page"
        v-model:page-size="pageSize"
        :total="data?.pagination.total ?? 0"
        class="border-t-0 pt-0"
      />
    </template>
  </AppDataTable>

  <UModal v-model:open="modalOpen" :title="current ? current.invoiceNumber : t('supplierInvoices.add')" :ui="{ content: 'sm:max-w-4xl' }">
    <template #body>
      <div class="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <UFormField :label="t('purchasing.reference')">
            <SearchableSelect
              :model-value="form.purchaseOrderId"
              v-model:search-term="orderSearch"
              :items="orderItems"
              :disabled="!!current"
              :has-more="orderHasMore"
              :loading="ordersLoading"
              :load-more="loadOrders"
              :placeholder="t('supplierInvoices.selectOrder')"
              :search-placeholder="t('supplierInvoices.searchOrder')"
              @update:model-value="chooseOrder"
            />
          </UFormField>
          <UFormField :label="t('suppliers.name')">
            <UInput :model-value="availability?.supplierName ?? current?.supplierName ?? ''" disabled class="w-full" />
          </UFormField>
          <UFormField :label="t('supplierInvoices.number')">
            <UInput v-model="form.invoiceNumber" :disabled="!isEditable" class="w-full" />
          </UFormField>
          <UFormField :label="t('supplierInvoices.date')">
            <CustomFieldDateInput v-model="form.invoiceDate" :disabled="!isEditable" :with-time="false" />
          </UFormField>
        </div>
        <div v-if="availability" class="space-y-2">
          <p class="text-sm font-medium text-highlighted">{{ t('supplierInvoices.lines') }}</p>
          <SearchableSelect
            v-if="isEditable && additionalLineItems.length"
            model-value=""
            :items="additionalLineItems"
            :placeholder="t('supplierInvoices.addLine')"
            :search-placeholder="t('products.searchProducts')"
            class="w-full"
            @update:model-value="addLine"
          />
          <div
            v-for="line in form.items"
            :key="line.purchaseOrderItemId"
            class="grid grid-cols-2 items-end gap-3 rounded-lg border border-default p-3 sm:grid-cols-5"
          >
            <div class="text-sm">
              <p class="font-medium text-highlighted">{{ line.sku ?? line.purchaseOrderItemId }}</p>
              <p class="text-xs text-muted">
                {{ t('supplierInvoices.goodAvailable') }}: {{ availability.items.find(item => item.id === line.purchaseOrderItemId)?.availableToInvoice ?? 0 }}
              </p>
            </div>
            <UFormField :label="t('supplierInvoices.billedQuantity')">
              <UInput v-model.number="line.quantity" type="number" min="0.0001" step="0.0001" :disabled="!isEditable" class="w-full" />
            </UFormField>
            <UFormField :label="t('purchasing.unitCost')">
              <UInput v-model.number="line.unitCost" type="number" min="0" step="0.0001" :disabled="!isEditable" class="w-full" />
            </UFormField>
            <p class="pb-2 text-right text-sm font-medium">{{ money(line.quantity * line.unitCost, availability.currency) }}</p>
            <UButton
              v-if="isEditable"
              icon="i-lucide-x"
              color="neutral"
              variant="ghost"
              :aria-label="t('supplierInvoices.removeLine')"
              @click="removeLine(line.purchaseOrderItemId)"
            />
          </div>
          <p v-if="!form.items.length" class="text-sm text-muted">{{ t('supplierInvoices.noReceivedGoods') }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <UFormField :label="t('supplierInvoices.tax')">
            <UInput v-model.number="form.tax" type="number" min="0" step="0.0001" :disabled="!isEditable" class="w-full" />
          </UFormField>
          <UFormField :label="t('supplierInvoices.shipping')">
            <UInput v-model.number="form.shipping" type="number" min="0" step="0.0001" :disabled="!isEditable" class="w-full" />
          </UFormField>
          <UFormField :label="t('supplierInvoices.discount')">
            <UInput v-model.number="form.discount" type="number" min="0" step="0.0001" :disabled="!isEditable" class="w-full" />
          </UFormField>
          <UFormField :label="t('supplierInvoices.declaredTotal')">
            <UInput v-model.number="form.declaredTotal" type="number" min="0" step="0.0001" :disabled="!isEditable" class="w-full" />
          </UFormField>
        </div>
        <p class="text-sm text-muted">
          {{ t('supplierInvoices.calculatedTotal') }}: {{ money(computedTotal, availability?.currency ?? current?.currency ?? 'EUR') }}
        </p>
        <UFormField :label="t('supplierInvoices.note')">
          <UTextarea v-model="form.note" :disabled="!isEditable" class="w-full" />
        </UFormField>
        <div v-if="current?.matchIssues?.length" class="rounded-lg border border-warning/40 bg-warning/5 p-3 text-sm">
          <p class="font-medium">{{ t('supplierInvoices.disputed') }}</p>
          <ul class="mt-2 list-disc pl-5">
            <li v-for="issue in current.matchIssues" :key="issue">{{ issue }}</li>
          </ul>
        </div>
        <p v-if="current?.voidReason" class="text-sm text-muted">
          {{ t('supplierInvoices.voidReason') }}: {{ current.voidReason }}
        </p>
        <div class="flex flex-wrap justify-end gap-2">
          <UButton v-if="current && current.status !== 'void'" :label="t('supplierInvoices.void')" color="error" variant="subtle" @click="askVoid" />
          <UButton v-if="isEditable" :label="t('common.save')" :loading="saving" @click="saveInvoice" />
          <UButton v-if="current && isEditable" :label="t('supplierInvoices.match')" :loading="saving" color="primary" variant="outline" @click="askMatch" />
        </div>
      </div>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="matchOpen"
    :title="t('supplierInvoices.match')"
    :description="t('supplierInvoices.matchDescription')"
    :confirm-label="t('supplierInvoices.match')"
    :loading="saving"
    confirm-color="primary"
    @confirm="matchInvoice"
  />
  <UModal v-model:open="voidOpen" :title="t('supplierInvoices.void')" :description="t('supplierInvoices.voidDescription')">
    <template #body>
      <UFormField :label="t('supplierInvoices.voidReason')">
        <UTextarea v-model="voidReason" class="w-full" />
      </UFormField>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closeVoid" />
        <UButton :label="t('supplierInvoices.void')" color="error" :loading="saving" @click="voidInvoice" />
      </div>
    </template>
  </UModal>
</template>

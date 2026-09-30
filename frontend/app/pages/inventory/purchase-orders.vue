<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type OrderLine = {
  id: string
  productId: string
  sku: string | null
  supplierSku: string | null
  quantity: number
  receivedQuantity: number
  damagedQuantity: number
  openQuantity: number
  unitCost: number
  purchaseUnit: string
  stockUnitsPerPurchaseUnit: number
}
type Order = {
  id: string
  reference: string
  supplierId: string
  supplierName: string
  warehouseId: string
  warehouseName: string
  status: 'draft' | 'sent' | 'partially_received' | 'received' | 'cancelled'
  currency: string
  note: string | null
  createdAt: string
  supplierSnapshot: Record<string, string | null> | null
  lastEmailedAt: string | null
  lastEmailedTo: string | null
  items: OrderLine[]
}
type Receipt = {
  id: string
  sku: string | null
  goodQuantity: number
  damagedQuantity: number
  damageResolution: 'open' | 'returned' | 'credited' | 'written_off' | 'replaced' | null
  damageResolutionNote: string | null
  damageHistory: { kind?: 'claim' | 'physical', from: string | null, to: string, note: string | null, at: string, userId: string | null }[]
  quarantineQuantity: number
  quarantineStatus: 'held' | 'released' | 'returned' | 'scrapped' | 'legacy_untracked' | null
  note: string | null
  createdAt: string
}
type Offer = {
  id: string
  productId: string
  productSku: string | null
  productName: string | null
  variantCombination?: string | null
  supplierSku: string | null
  unitCost: number | null
  currency: string
  minimumQuantity: number
  minimumOrderQuantity: number
  prices: {
    minimumQuantity: number
    unitCost: number
    currency: string
    validFrom: string | null
    validUntil: string | null
  }[]
  purchaseUnit: string
  stockUnitsPerPurchaseUnit: number
  active: boolean
}
type Warehouse = { id: string, name: string, active: boolean }

const { t } = useI18n()
const notify = useAppToast()
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const page = ref(1)
const pageSize = ref(25)
const createOpen = ref(false)
const editingOrder = ref<Order | null>(null)
const detailOpen = ref(false)
const receiveOpen = ref(false)
const confirmOpen = ref(false)
const confirmAction = ref<'send' | 'cancel' | 'email' | null>(null)
const currentOrder = ref<Order | null>(null)
const receipts = ref<Receipt[]>([])
const saving = ref(false)
const actingId = ref<string | null>(null)
const offerSearch = ref('')
const debouncedOfferSearch = ref('')
const offerPage = ref(1)
const offerOptions = ref<Offer[]>([])
const chosenOffers = reactive<Record<string, Offer>>({})
const offerHasMore = ref(false)
const offersLoading = ref(false)
let offerRequestId = 0
const form = reactive({
  supplierId: '',
  warehouseId: '',
  currency: 'EUR',
  productIds: [] as string[],
  note: ''
})
const quantities = reactive<Record<string, number>>({})
const receiptQuantities = reactive<Record<string, { good: number, damaged: number }>>({})
const receiptNote = ref('')
const receiptKey = ref('')
const resolvingReceipt = ref<Receipt | null>(null)
const damageResolution = ref<'open' | 'returned' | 'credited' | 'written_off' | 'replaced'>('open')
const damageNote = ref('')
const resolving = ref(false)
const quarantineReceipt = ref<Receipt | null>(null)
const quarantineDisposition = ref<'released' | 'returned' | 'scrapped'>('returned')
const quarantineNote = ref('')
const quarantineSaving = ref(false)
const quarantineOptions = computed(() => (
  ['returned', 'scrapped', 'released'] as const
).map(value => ({ value, label: t(`purchasing.quarantineStatus.${value}`) })))
const closeQuarantine = () => {
  quarantineReceipt.value = null
}
const historyLabel = (event: Receipt['damageHistory'][number]) => t(
  event.kind === 'physical'
    ? `purchasing.quarantineStatus.${event.to}`
    : `purchasing.damageStatus.${event.to}`
)
const damageResolutionItems = computed(() => (
  ['open', 'returned', 'credited', 'written_off', 'replaced'] as const
).map(value => ({ value, label: t(`purchasing.damageStatus.${value}`) })))
const closeDamageResolution = () => {
  resolvingReceipt.value = null
}
const listUrl = computed(() => `/inventory/purchase-orders?page=${page.value}&limit=${pageSize.value}`)
const { data, status, refresh } = await useAsyncData('purchase-orders-page', () =>
  apiFetch<{ orders: Order[], pagination: { total: number } }>(listUrl.value)
)
const { data: warehousesData } = await useAsyncData('purchase-orders-warehouses', () =>
  apiFetch<{ warehouses: Warehouse[] }>('/inventory/warehouses')
)
const { data: currenciesData } = await useAsyncData('purchase-orders-currencies', () =>
  apiFetch<{ currencies: { code: string, symbol: string }[] }>('/products/currencies')
)
const currencyItems = computed(() => (currenciesData.value?.currencies ?? []).map(currency => ({
  label: `${currency.code} (${currency.symbol})`,
  value: currency.code
})))
const warehouseItems = computed(() =>
  (warehousesData.value?.warehouses ?? []).filter(item => item.active).map(item => ({ label: item.name, value: item.id }))
)
const offerItems = computed(() => {
  const all = [...Object.values(chosenOffers), ...offerOptions.value.filter(offer => !chosenOffers[offer.productId])]
  return all.map(offer => ({
    value: offer.productId,
    label: offer.productName ?? offer.productSku ?? offer.productId,
    productName: offer.productName ?? offer.productSku ?? offer.productId,
    productNumber: offer.productSku,
    variantCombination: offer.variantCombination
  }))
})
const currentLocalDate = () => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
const selectedOffers = computed(() => form.productIds.map(id => chosenOffers[id]).filter((offer): offer is Offer => !!offer))
const tierFor = (offer: Offer) => {
  const quantity = quantities[offer.productId] ?? 0
  const today = currentLocalDate()
  return offer.prices
    .filter(price => price.currency === form.currency
      && price.minimumQuantity <= quantity
      && (!price.validFrom || price.validFrom <= today)
      && (!price.validUntil || price.validUntil >= today))
    .sort((a, b) => b.minimumQuantity - a.minimumQuantity)[0] ?? null
}
const totalCost = computed(() => selectedOffers.value.reduce((total, offer) =>
  total + (tierFor(offer)?.unitCost ?? 0) * (quantities[offer.productId] ?? 0), 0))
const loadOffers = async (reset = false) => {
  if ((offersLoading.value && !reset) || !form.supplierId) return
  if (reset) {
    offerRequestId += 1
    offerPage.value = 1
    offerOptions.value = []
  }
  const currentRequest = offerRequestId
  offersLoading.value = true
  try {
    const params = new URLSearchParams({ supplierId: form.supplierId, page: String(offerPage.value), limit: '25', active: '1' })
    if (debouncedOfferSearch.value) params.set('search', debouncedOfferSearch.value)
    const response = await apiFetch<{ offers: Offer[], pagination: { hasMore: boolean } }>(`/inventory/supplier-offers?${params}`)
    if (currentRequest !== offerRequestId) return
    offerOptions.value = [...offerOptions.value, ...response.offers]
    response.offers.forEach((offer) => {
      if (chosenOffers[offer.productId]) chosenOffers[offer.productId] = offer
    })
    offerHasMore.value = response.pagination.hasMore
    offerPage.value += 1
  } finally {
    if (currentRequest === offerRequestId) offersLoading.value = false
  }
}
const resetSelectedOffers = () => {
  offerRequestId += 1
  offerOptions.value = []
  offerHasMore.value = false
  offersLoading.value = false
  form.productIds = []
  Object.keys(chosenOffers).forEach(key => delete chosenOffers[key])
  Object.keys(quantities).forEach(key => delete quantities[key])
  void loadOffers(true)
}
const syncSelectedOffers = (productIds: string[]) => {
  productIds.forEach((id) => {
    const offer = offerOptions.value.find(item => item.productId === id)
    if (offer) {
      chosenOffers[id] = offer
      if (Object.keys(chosenOffers).length === 1 && !editingOrder.value) form.currency = offer.currency
      const today = currentLocalDate()
      const firstCurrentTier = offer.prices
        .filter(price => price.currency === form.currency
          && (!price.validFrom || price.validFrom <= today)
          && (!price.validUntil || price.validUntil >= today))
        .sort((a, b) => a.minimumQuantity - b.minimumQuantity)[0]
      quantities[id] ??= Math.max(offer.minimumOrderQuantity, firstCurrentTier?.minimumQuantity ?? 0)
    }
  })
}
const removeProduct = (productId: string) => {
  form.productIds = form.productIds.filter(id => id !== productId)
  delete chosenOffers[productId]
  delete quantities[productId]
}
const openCreate = () => {
  editingOrder.value = null
  Object.assign(form, { supplierId: '', warehouseId: '', currency: 'EUR', productIds: [], note: '' })
  Object.keys(chosenOffers).forEach(key => delete chosenOffers[key])
  Object.keys(quantities).forEach(key => delete quantities[key])
  offerOptions.value = []
  createOpen.value = true
}
const closeCreate = () => {
  createOpen.value = false
}
const closeReceive = () => {
  receiveOpen.value = false
}
const openEdit = (order: Order) => {
  editingOrder.value = order
  Object.assign(form, {
    supplierId: order.supplierId,
    warehouseId: order.warehouseId,
    currency: order.currency,
    productIds: order.items.map(item => item.productId),
    note: order.note ?? ''
  })
  Object.keys(chosenOffers).forEach(key => delete chosenOffers[key])
  Object.keys(quantities).forEach(key => delete quantities[key])
  order.items.forEach((item) => {
    chosenOffers[item.productId] = {
      id: item.id,
      productId: item.productId,
      productSku: item.sku,
      productName: null,
      supplierSku: item.supplierSku,
      unitCost: item.unitCost,
      currency: order.currency,
      minimumQuantity: 0,
      minimumOrderQuantity: 0,
      prices: [{ minimumQuantity: 0, unitCost: item.unitCost, currency: order.currency, validFrom: null, validUntil: null }],
      purchaseUnit: item.purchaseUnit,
      stockUnitsPerPurchaseUnit: item.stockUnitsPerPurchaseUnit,
      active: true
    }
    quantities[item.productId] = item.quantity
  })
  void loadOffers(true)
  createOpen.value = true
}
const createOrder = async () => {
  if (!form.supplierId || !form.warehouseId || selectedOffers.value.length === 0 || selectedOffers.value.some(offer =>
    (quantities[offer.productId] ?? 0) < offer.minimumOrderQuantity || !tierFor(offer))) {
    notify.error(t('common.tryAgain'), t('purchasing.orderValidation'))
    return
  }
  saving.value = true
  try {
    const response = await apiFetch<{ order: Order }>(editingOrder.value
      ? `/inventory/purchase-orders/${editingOrder.value.id}`
      : '/inventory/purchase-orders', {
      method: editingOrder.value ? 'PATCH' : 'POST',
      body: {
        supplierId: form.supplierId,
        warehouseId: form.warehouseId,
        currency: form.currency,
        note: form.note,
        items: selectedOffers.value.map(offer => ({ productId: offer.productId, quantity: quantities[offer.productId] }))
      }
    })
    createOpen.value = false
    if (!editingOrder.value) page.value = 1
    await refresh()
    await openDetail(response.order)
    notify.success(t('common.changesSaved'), t(editingOrder.value ? 'purchasing.orderUpdated' : 'purchasing.orderCreated'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const openDetail = async (order: Order) => {
  const response = await apiFetch<{ order: Order, receipts: Receipt[] }>(`/inventory/purchase-orders/${order.id}`)
  currentOrder.value = response.order
  receipts.value = response.receipts
  detailOpen.value = true
}
const downloadPdf = async (order: Order) => {
  try {
    const blob = await apiFetch<Blob>(`/inventory/purchase-orders/${order.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${order.reference}.pdf`
    link.click()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  }
}
const openDamageResolution = (receipt: Receipt) => {
  resolvingReceipt.value = receipt
  damageResolution.value = receipt.damageResolution ?? 'open'
  damageNote.value = receipt.damageResolutionNote ?? ''
}
const saveDamageResolution = async () => {
  if (!currentOrder.value || !resolvingReceipt.value) return
  resolving.value = true
  try {
    await apiFetch(`/inventory/purchase-orders/${currentOrder.value.id}/receipts/${resolvingReceipt.value.id}/damage`, {
      method: 'PATCH',
      body: { resolution: damageResolution.value, note: damageNote.value }
    })
    resolvingReceipt.value = null
    await openDetail(currentOrder.value)
    notify.success(t('common.changesSaved'), t('purchasing.orderUpdated'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    resolving.value = false
  }
}
const openQuarantine = (receipt: Receipt) => {
  quarantineReceipt.value = receipt
  quarantineDisposition.value = 'returned'
  quarantineNote.value = ''
}
const saveQuarantine = async () => {
  if (!currentOrder.value || !quarantineReceipt.value) return
  quarantineSaving.value = true
  try {
    await apiFetch(`/inventory/purchase-orders/${currentOrder.value.id}/receipts/${quarantineReceipt.value.id}/quarantine`, {
      method: 'POST',
      body: { disposition: quarantineDisposition.value, note: quarantineNote.value }
    })
    quarantineReceipt.value = null
    await openDetail(currentOrder.value)
    notify.success(t('common.changesSaved'), t('purchasing.quarantineSaved'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    quarantineSaving.value = false
  }
}
const requestAction = (order: Order, action: 'send' | 'cancel' | 'email') => {
  currentOrder.value = order
  confirmAction.value = action
  confirmOpen.value = true
}
const runAction = async () => {
  if (!currentOrder.value || !confirmAction.value) return
  actingId.value = currentOrder.value.id
  try {
    const response = await apiFetch<{ order: Order }>(`/inventory/purchase-orders/${currentOrder.value.id}/${confirmAction.value}`, { method: 'POST' })
    currentOrder.value = response.order
    confirmOpen.value = false
    await refresh()
    notify.success(t('common.changesSaved'), t('purchasing.orderUpdated'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    actingId.value = null
  }
}
const openReceive = (order: Order) => {
  currentOrder.value = order
  Object.keys(receiptQuantities).forEach(key => delete receiptQuantities[key])
  order.items.filter(item => item.openQuantity > 0).forEach((item) => {
    receiptQuantities[item.id] = { good: 0, damaged: 0 }
  })
  receiptNote.value = ''
  receiptKey.value = crypto.randomUUID()
  receiveOpen.value = true
}
const receiveOrder = async () => {
  if (!currentOrder.value) return
  const items = Object.entries(receiptQuantities)
    .filter(([, value]) => value.good > 0 || value.damaged > 0)
    .map(([itemId, value]) => ({ itemId, goodQuantity: value.good, damagedQuantity: value.damaged }))
  if (!items.length || currentOrder.value.items.some(item => {
    const value = receiptQuantities[item.id]
    return value && (value.good < 0 || value.damaged < 0 || value.good + value.damaged > item.openQuantity)
  })) {
    notify.error(t('common.tryAgain'), t('purchasing.receiptValidation'))
    return
  }
  saving.value = true
  try {
    await apiFetch(`/inventory/purchase-orders/${currentOrder.value.id}/receive`, {
      method: 'POST',
      body: { receiptKey: receiptKey.value, items, note: receiptNote.value }
    })
    receiveOpen.value = false
    await refresh()
    await openDetail(currentOrder.value)
    notify.success(t('common.changesSaved'), t('purchasing.receiptSaved'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const orderActions = (order: Order) => [[
  { label: t('purchasing.view'), icon: 'i-lucide-eye', onSelect: () => openDetail(order) },
  { label: t('purchasing.downloadPdf'), icon: 'i-lucide-file-down', onSelect: () => downloadPdf(order) },
  ...(order.status === 'draft' ? [{ label: t('purchasing.editOrder'), icon: 'i-lucide-pencil', onSelect: () => openEdit(order) }] : []),
  ...(order.status === 'draft' ? [{ label: t('purchasing.send'), icon: 'i-lucide-send', onSelect: () => requestAction(order, 'send') }] : []),
  ...(['sent', 'partially_received', 'received'].includes(order.status) ? [{ label: t('purchasing.emailOrder'), icon: 'i-lucide-mail', disabled: !order.supplierSnapshot?.email, onSelect: () => requestAction(order, 'email') }] : []),
  ...(['sent', 'partially_received'].includes(order.status) ? [{ label: t('purchasing.receive'), icon: 'i-lucide-package-check', onSelect: () => openReceive(order) }] : []),
  ...(['draft', 'sent', 'partially_received'].includes(order.status) ? [{ label: t('purchasing.cancel'), icon: 'i-lucide-x', onSelect: () => requestAction(order, 'cancel') }] : [])
]]
const columns: TableColumn<Order>[] = [
  { accessorKey: 'reference', header: () => t('purchasing.reference'), cell: ({ row }) => h('button', { class: 'max-w-44 cursor-pointer truncate text-left font-medium text-highlighted hover:text-primary', title: row.original.reference, onClick: () => openDetail(row.original) }, row.original.reference) },
  { accessorKey: 'createdAt', header: () => t('inventoryTransfers.date'), cell: ({ row }) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(row.original.createdAt)) },
  { accessorKey: 'supplierName', header: () => t('suppliers.name'), cell: ({ row }) => h('button', { class: 'cursor-pointer font-medium text-highlighted hover:text-primary', onClick: () => openDetail(row.original) }, row.original.supplierName) },
  { accessorKey: 'warehouseName', header: () => t('inventory.warehouse') },
  { id: 'items', header: () => t('inventoryTransfers.items'), cell: ({ row }) => row.original.items.length },
  { id: 'total', header: () => t('purchasing.total'), cell: ({ row }) => `${row.original.items.reduce((sum, item) => sum + item.quantity * item.unitCost, 0).toFixed(2)} ${row.original.currency}` },
  { accessorKey: 'status', header: () => t('inventory.status'), cell: ({ row }) => h(UBadge, { color: row.original.status === 'received' ? 'success' : row.original.status === 'partially_received' ? 'warning' : 'neutral', variant: 'subtle' }, () => t(`purchasing.status.${row.original.status}`)) },
  { id: 'actions', header: '', enableHiding: false, enableSorting: false, cell: ({ row }) => h('div', { class: 'flex justify-end' }, h(UDropdownMenu, { items: orderActions(row.original), content: { align: 'end' } }, () => h(UButton, { icon: 'i-lucide-ellipsis-vertical', color: 'neutral', variant: 'ghost', loading: actingId.value === row.original.id }))) }
]
const lineColumns: TableColumn<OrderLine>[] = [
  { accessorKey: 'sku', header: () => t('purchasing.productNumber') },
  { accessorKey: 'quantity', header: () => t('purchasing.ordered'), cell: ({ row }) => `${row.original.quantity} ${row.original.purchaseUnit}` },
  { accessorKey: 'stockUnitsPerPurchaseUnit', header: () => t('purchasing.stockUnitsPerPurchaseUnit') },
  { accessorKey: 'receivedQuantity', header: () => t('purchasing.received') },
  { accessorKey: 'damagedQuantity', header: () => t('purchasing.damaged') },
  { accessorKey: 'openQuantity', header: () => t('purchasing.outstanding') },
  { accessorKey: 'unitCost', header: () => t('purchasing.unitCost') }
]
const receiptColumns: TableColumn<Receipt>[] = [
  { accessorKey: 'createdAt', header: () => t('inventoryTransfers.date'), cell: ({ row }) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(row.original.createdAt)) },
  { accessorKey: 'sku', header: () => t('purchasing.productNumber') },
  { accessorKey: 'goodQuantity', header: () => t('purchasing.received') },
  { accessorKey: 'damagedQuantity', header: () => t('purchasing.damaged') },
  { accessorKey: 'damageResolution', header: () => t('purchasing.damageResolution'), cell: ({ row }) => row.original.damageResolution ? h('button', { class: 'cursor-pointer text-primary', onClick: () => openDamageResolution(row.original) }, t(`purchasing.damageStatus.${row.original.damageResolution}`)) : '—' },
  { accessorKey: 'quarantineStatus', header: () => t('purchasing.physicalStock'), cell: ({ row }) => row.original.quarantineStatus === 'held' ? h('button', { class: 'cursor-pointer text-primary', onClick: () => openQuarantine(row.original) }, `${t('purchasing.quarantineStatus.held')} · ${row.original.quarantineQuantity}`) : row.original.quarantineStatus ? t(`purchasing.quarantineStatus.${row.original.quarantineStatus}`) : '—' },
  { accessorKey: 'note', header: () => t('inventory.note') }
]
let offerSearchTimer: ReturnType<typeof setTimeout> | undefined
watch(offerSearch, (value) => {
  clearTimeout(offerSearchTimer)
  offerSearchTimer = setTimeout(() => { debouncedOfferSearch.value = value.trim() }, 250)
})
watch(listUrl, () => { void refresh() })
watch(() => form.supplierId, (supplierId) => {
  if (editingOrder.value?.supplierId === supplierId) return
  resetSelectedOffers()
})
watch(debouncedOfferSearch, () => { void loadOffers(true) })
watch(() => form.productIds, syncSelectedOffers)
watch(pageSize, () => { page.value = 1 })
onBeforeUnmount(() => clearTimeout(offerSearchTimer))
</script>

<template>
  <AppDataTable
    :data="data?.orders ?? []"
    :columns="columns"
    :get-row-id="row => row.id"
    :loading="status === 'pending'"
    table-key="purchase-orders"
  >
    <template #header>
      <div class="flex w-full items-center justify-between gap-3">
        <p class="text-sm font-medium text-highlighted">
          {{ t('purchasing.orders') }} ({{ data?.pagination.total ?? 0 }})
        </p>
        <UButton :label="t('purchasing.createOrder')" @click="openCreate" />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :title="t('purchasing.noOrders')"
        :description="t('purchasing.noOrdersDescription')"
        icon="i-lucide-clipboard-list"
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

  <UModal v-model:open="createOpen" :title="t(editingOrder ? 'purchasing.editOrder' : 'purchasing.createOrder')" :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="createOrder">
        <div class="grid grid-cols-2 gap-3">
          <UFormField :label="t('suppliers.name')">
            <SupplierPicker
              v-model="form.supplierId"
            />
          </UFormField>
          <UFormField :label="t('inventory.warehouse')">
            <SearchableSelect
              v-model="form.warehouseId"
              :items="warehouseItems"
              :placeholder="t('inventoryCounts.selectWarehouse')"
              :search-placeholder="t('inventoryTransfers.searchWarehouses')"
            />
          </UFormField>
          <UFormField :label="t('purchasing.currency')">
            <USelect
              v-model="form.currency"
              :items="currencyItems"
              value-key="value"
              class="w-full"
            />
          </UFormField>
        </div>
        <UFormField :label="t('inventoryTransfers.products')">
          <SearchableSelect
            v-model="form.productIds"
            v-model:search-term="offerSearch"
            :items="offerItems"
            :disabled="!form.supplierId"
            :has-more="offerHasMore"
            :loading="offersLoading"
            :load-more="loadOffers"
            :placeholder="t('inventoryTransfers.selectProducts')"
            :search-placeholder="t('products.searchProducts')"
            multiple
            show-placeholder-when-selected
          />
        </UFormField>
        <div v-for="offer in selectedOffers" :key="offer.id" class="grid grid-cols-[minmax(0,1fr)_7rem_7rem_auto] items-center gap-2 rounded-md border border-default p-2">
          <span class="truncate text-sm">{{ offer.productSku ?? offer.supplierSku }} · {{ offer.purchaseUnit }} × {{ offer.stockUnitsPerPurchaseUnit }}</span>
          <UInput v-model.number="quantities[offer.productId]" type="number" :min="offer.minimumOrderQuantity" step="0.0001" />
          <span class="text-right text-sm">{{ tierFor(offer) ? `${tierFor(offer)?.unitCost.toFixed(2)} ${form.currency}` : '—' }}</span>
          <UButton icon="i-lucide-x" color="neutral" variant="ghost" @click="removeProduct(offer.productId)" />
        </div>
        <p v-if="selectedOffers.length" class="text-right text-sm font-medium">
          {{ t('purchasing.total') }}: {{ totalCost.toFixed(2) }} {{ form.currency }}
        </p>
        <UFormField :label="t('inventory.note')">
          <UTextarea v-model="form.note" class="w-full" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closeCreate" />
          <UButton :label="t(editingOrder ? 'common.save' : 'common.create')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal v-model:open="detailOpen" :title="t('purchasing.orderDetail')" :ui="{ content: 'sm:max-w-4xl' }">
    <template #body>
      <div v-if="currentOrder" class="space-y-4">
        <p class="text-sm font-semibold text-highlighted">{{ currentOrder.reference }}</p>
        <p class="text-sm text-muted">
          {{ currentOrder.supplierName }} · {{ currentOrder.warehouseName }} · {{ currentOrder.currency }}
        </p>
        <p v-if="currentOrder.note" class="text-sm">{{ currentOrder.note }}</p>
        <p v-if="currentOrder.lastEmailedAt" class="text-sm text-muted">
          {{ t('purchasing.emailedAt') }} {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(currentOrder.lastEmailedAt)) }} · {{ currentOrder.lastEmailedTo }}
        </p>
        <AppDataTable
          :data="currentOrder.items"
          :columns="lineColumns"
          :get-row-id="row => row.id"
          :max-height="null"
          table-key="purchase-order-lines"
        />
        <div v-if="receipts.length" class="space-y-2">
          <h3 class="text-sm font-semibold">{{ t('purchasing.receipts') }}</h3>
          <AppDataTable
            :data="receipts"
            :columns="receiptColumns"
            :get-row-id="row => row.id"
            :max-height="null"
            table-key="purchase-receipts"
          />
        </div>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('purchasing.downloadPdf')"
            color="neutral"
            variant="subtle"
            icon="i-lucide-file-down"
            @click="downloadPdf(currentOrder)"
          />
          <UButton
            v-if="currentOrder.status === 'draft'"
            :label="t('purchasing.editOrder')"
            color="neutral"
            variant="subtle"
            @click="openEdit(currentOrder)"
          />
          <UButton
            v-if="['sent', 'partially_received', 'received'].includes(currentOrder.status)"
            :label="t('purchasing.emailOrder')"
            color="neutral"
            variant="subtle"
            icon="i-lucide-mail"
            :disabled="!currentOrder.supplierSnapshot?.email"
            @click="requestAction(currentOrder, 'email')"
          />
          <UButton
            v-if="currentOrder.status === 'draft'"
            :label="t('purchasing.send')"
            @click="requestAction(currentOrder, 'send')"
          />
          <UButton
            v-if="['sent', 'partially_received'].includes(currentOrder.status)"
            :label="t('purchasing.receive')"
            @click="openReceive(currentOrder)"
          />
        </div>
      </div>
    </template>
  </UModal>

  <UModal
    :open="!!resolvingReceipt"
    :title="t('purchasing.damageResolution')"
    @update:open="closeDamageResolution"
  >
    <template #body>
      <UForm class="space-y-4" @submit.prevent="saveDamageResolution">
        <p class="text-sm text-muted">
          {{ resolvingReceipt?.sku }} · {{ resolvingReceipt?.damagedQuantity }} {{ t('purchasing.damaged') }}
        </p>
        <UFormField :label="t('purchasing.damageResolution')">
          <SearchableSelect
            v-model="damageResolution"
            :items="damageResolutionItems"
            :search-placeholder="t('purchasing.damageResolution')"
          />
        </UFormField>
        <UFormField :label="t('purchasing.damageNote')">
          <UTextarea v-model="damageNote" class="w-full" />
        </UFormField>
        <div v-if="resolvingReceipt?.damageHistory.length" class="space-y-1 text-xs text-muted">
          <p
            v-for="(event, index) in resolvingReceipt.damageHistory"
            :key="index"
          >
            {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(event.at)) }} · {{ historyLabel(event) }}{{ event.note ? ` — ${event.note}` : '' }}
          </p>
        </div>
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closeDamageResolution" />
          <UButton :label="t('purchasing.saveResolution')" type="submit" :loading="resolving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal
    :open="!!quarantineReceipt"
    :title="t('purchasing.resolveQuarantine')"
    @update:open="closeQuarantine"
  >
    <template #body>
      <UForm class="space-y-4" @submit.prevent="saveQuarantine">
        <p class="text-sm text-muted">
          {{ quarantineReceipt?.sku }} · {{ quarantineReceipt?.quarantineQuantity }} {{ t('purchasing.stockUnits') }}
        </p>
        <p class="text-sm text-muted">{{ t('purchasing.quarantineDescription') }}</p>
        <UFormField :label="t('purchasing.physicalStock')">
          <SearchableSelect
            v-model="quarantineDisposition"
            :items="quarantineOptions"
            :search-placeholder="t('purchasing.physicalStock')"
          />
        </UFormField>
        <UFormField :label="t('inventory.note')">
          <UTextarea v-model="quarantineNote" class="w-full" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closeQuarantine" />
          <UButton :label="t('purchasing.saveDisposition')" type="submit" :loading="quarantineSaving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal v-model:open="receiveOpen" :title="t('purchasing.receive')" :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="receiveOrder">
        <div v-for="item in currentOrder?.items.filter(line => line.openQuantity > 0) ?? []" :key="item.id" class="grid grid-cols-[minmax(0,1fr)_7rem_7rem] items-center gap-2">
          <span class="truncate text-sm">{{ item.sku }} · {{ t('purchasing.outstanding') }} {{ item.openQuantity }}</span>
          <UFormField :label="t('purchasing.received')">
            <UInput v-model.number="receiptQuantities[item.id]!.good" type="number" min="0" :max="item.openQuantity" step="0.0001" />
          </UFormField>
          <UFormField :label="t('purchasing.damaged')">
            <UInput v-model.number="receiptQuantities[item.id]!.damaged" type="number" min="0" :max="item.openQuantity" step="0.0001" />
          </UFormField>
        </div>
        <UFormField :label="t('inventory.note')">
          <UTextarea v-model="receiptNote" class="w-full" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closeReceive" />
          <UButton :label="t('purchasing.receive')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="confirmOpen"
    :title="confirmAction === 'send' ? t('purchasing.send') : confirmAction === 'email' ? t('purchasing.emailOrder') : t('purchasing.cancel')"
    :description="confirmAction === 'send' ? t('purchasing.sendDescription') : confirmAction === 'email' ? t('purchasing.emailDescription', { email: currentOrder?.supplierSnapshot?.email ?? '—' }) : t('purchasing.cancelDescription')"
    :confirm-label="confirmAction === 'send' ? t('purchasing.send') : confirmAction === 'email' ? t('purchasing.emailOrder') : t('purchasing.cancel')"
    :confirm-color="confirmAction === 'cancel' ? 'error' : 'primary'"
    :loading="!!actingId"
    @confirm="runAction"
  />
</template>

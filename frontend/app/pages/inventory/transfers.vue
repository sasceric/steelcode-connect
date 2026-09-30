<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type Transfer = {
  id: string
  status: 'draft' | 'in_transit' | 'received' | 'cancelled'
  sourceWarehouseId: string
  sourceWarehouse: string
  destinationWarehouseId: string
  destinationWarehouse: string
  note: string | null
  createdAt: string
  items: { productId: string, sku: string | null, quantity: number }[]
}
type Warehouse = { id: string, name: string, active: boolean }
type ProductOption = { id: string, name: string, sku: string | null, variantCombination?: string | null }
type ProductStock = {
  levels: { warehouseId: string, availableStock: number }[]
}

const { t } = useI18n()
const notify = useAppToast()
const UBadge = resolveComponent('UBadge')
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const actingId = ref<string | null>(null)
const detailOpen = ref(false)
const confirmOpen = ref(false)
const selectedTransfer = ref<Transfer | null>(null)
const pendingAction = ref<'send' | 'receive' | 'cancel' | null>(null)
const createOpen = ref(false)
const creating = ref(false)
const transferForm = reactive({
  sourceWarehouseId: '',
  destinationWarehouseId: '',
  productIds: [] as string[],
  note: ''
})
const quantities = reactive<Record<string, number>>({})
const productSearch = ref('')
const productPage = ref(1)
const productOptions = ref<ProductOption[]>([])
const selectedOptions = reactive<Record<string, ProductOption>>({})
const productHasMore = ref(false)
const productsLoading = ref(false)
let productRequestId = 0
const availabilityLoading = ref(false)
const availableByProductId = ref<Record<string, number>>({})
const page = ref(1)
const pageSize = ref(25)
const listUrl = computed(() => `/inventory/transfers?page=${page.value}&limit=${pageSize.value}`)
const { data, status, refresh } = await useAsyncData('inventory-transfers', () =>
  apiFetch<{ transfers: Transfer[], pagination: { total: number } }>(listUrl.value)
)
const transfers = computed(() => data.value?.transfers ?? [])
const { data: warehousesData } = await useAsyncData('inventory-transfer-warehouses', () =>
  apiFetch<{ warehouses: Warehouse[] }>('/inventory/warehouses')
)
const warehouseItems = computed(() =>
  (warehousesData.value?.warehouses ?? []).filter(warehouse => warehouse.active).map(warehouse => ({ label: warehouse.name, value: warehouse.id }))
)
const productItems = computed(() =>
  [...Object.values(selectedOptions), ...productOptions.value.filter(product => !selectedOptions[product.id])]
    .map(product => ({
      label: product.name,
      productName: product.name,
      productNumber: product.sku,
      variantCombination: product.variantCombination,
      value: product.id
    }))
)
const selectedProducts = computed(() => transferForm.productIds.map(id => selectedOptions[id]).filter((product): product is ProductOption => !!product))
const exceedsAvailableStock = (productId: string, quantity: number) => {
  const available = availableByProductId.value[productId]

  return available !== undefined && quantity > available
}
const insufficientSelectedProducts = computed(() => selectedProducts.value.filter(product =>
  exceedsAvailableStock(product.id, quantities[product.id] || 1)
))
const loadProducts = async (reset = false) => {
  if (productsLoading.value && !reset) return
  if (reset) {
    productRequestId += 1
    productPage.value = 1
    productOptions.value = []
  }
  const currentRequest = productRequestId
  productsLoading.value = true
  try {
    const params = new URLSearchParams({ view: 'options', includeVariants: '1', limit: '25', page: String(productPage.value), sort: 'name', direction: 'ASC' })
    if (productSearch.value) params.set('search', productSearch.value)
    const response = await apiFetch<{ products: ProductOption[], pagination?: { hasMore: boolean } }>(`/products?${params}`)
    if (currentRequest !== productRequestId) return
    productOptions.value = [...productOptions.value, ...response.products]
    productHasMore.value = response.pagination?.hasMore ?? false
    productPage.value += 1
  } finally {
    if (currentRequest === productRequestId) productsLoading.value = false
  }
}
const removeProduct = (productId: string) => {
  transferForm.productIds = transferForm.productIds.filter(id => id !== productId)
  delete quantities[productId]
  delete selectedOptions[productId]
}
const loadAvailableStock = async (warehouseId: string, productIds: string[]) => {
  if (!warehouseId || !productIds.length) {
    availableByProductId.value = {}
    return
  }

  availabilityLoading.value = true
  try {
    const stock = await Promise.all(productIds.map(async (productId) => {
      const response = await apiFetch<ProductStock>(`/inventory/products/${productId}`)
      const level = response.levels.find(item => item.warehouseId === warehouseId)

      return [productId, level?.availableStock ?? 0] as const
    }))
    availableByProductId.value = Object.fromEntries(stock)
  } finally {
    availabilityLoading.value = false
  }
}
const transferErrorMessage = (error: unknown) => {
  if (typeof error === 'object' && error !== null && 'data' in error) {
    const data = (error as { data?: { message?: unknown } }).data
    if (typeof data?.message === 'string') return data.message
  }

  return error instanceof Error ? error.message : t('common.tryAgain')
}
const openCreate = () => {
  Object.assign(transferForm, { sourceWarehouseId: '', destinationWarehouseId: '', productIds: [], note: '' })
  Object.keys(quantities).forEach(key => delete quantities[key])
  Object.keys(selectedOptions).forEach(key => delete selectedOptions[key])
  void loadProducts(true)
  createOpen.value = true
}
const createTransfer = async () => {
  if (!transferForm.sourceWarehouseId || !transferForm.destinationWarehouseId || !transferForm.productIds.length || transferForm.sourceWarehouseId === transferForm.destinationWarehouseId) {
    notify.error(t('common.tryAgain'), t('inventoryTransfers.createValidation'))
    return
  }
  creating.value = true
  try {
    await loadAvailableStock(transferForm.sourceWarehouseId, transferForm.productIds)
    if (insufficientSelectedProducts.value.length) {
      const products = insufficientSelectedProducts.value
        .map(product => `${product.sku || product.name} (${availableByProductId.value[product.id] ?? 0} available, ${quantities[product.id] || 1} requested)`)
        .join(', ')
      notify.error(t('common.tryAgain'), `Insufficient available stock: ${products}.`)
      return
    }

    await apiFetch('/inventory/transfers', {
      method: 'POST',
      body: {
        sourceWarehouseId: transferForm.sourceWarehouseId,
        destinationWarehouseId: transferForm.destinationWarehouseId,
        note: transferForm.note,
        items: transferForm.productIds.map(productId => ({ productId, quantity: quantities[productId] || 1 }))
      }
    })
    page.value = 1
    await refresh()
    createOpen.value = false
    notify.success(t('common.changesSaved'), t('inventoryTransfers.created'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), transferErrorMessage(error))
  } finally {
    creating.value = false
  }
}
watch(productSearch, () => { void loadProducts(true) })
watch(
  [
    () => transferForm.sourceWarehouseId,
    () => transferForm.productIds
  ],
  ([warehouseId, productIds]) => {
    void loadAvailableStock(warehouseId, productIds)
  },
)
watch(
  () => transferForm.productIds,
  (productIds) => {
    productIds.forEach((productId) => {
      const option = productOptions.value.find(product => product.id === productId)
      if (option) selectedOptions[productId] = option
      if (quantities[productId] === undefined) quantities[productId] = 1
    })
  }
)
const statusLabel = (status: Transfer['status']) => t(`inventoryTransfers.status.${status}`)
const statusColor = (status: Transfer['status']) =>
  status === 'received' ? 'success' : status === 'in_transit' ? 'warning' : 'neutral'
const openDetail = (transfer: Transfer) => {
  selectedTransfer.value = transfer
  detailOpen.value = true
}
const requestAction = (transfer: Transfer, action: 'send' | 'receive' | 'cancel') => {
  selectedTransfer.value = transfer
  pendingAction.value = action
  confirmOpen.value = true
}
const runAction = async (transfer: Transfer, action: 'send' | 'receive' | 'cancel') => {
  actingId.value = transfer.id
  try {
    if (action === 'send') {
      const stock = await Promise.all(transfer.items.map(async (item) => {
        const response = await apiFetch<ProductStock>(`/inventory/products/${item.productId}`)
        const level = response.levels.find(current => current.warehouseId === transfer.sourceWarehouseId)

        return {
          sku: item.sku || item.productId,
          requested: item.quantity,
          available: level?.availableStock ?? 0
        }
      }))
      const insufficient = stock.filter(item => item.available < item.requested)
      if (insufficient.length) {
        const products = insufficient.map(item => `${item.sku} (${item.available} available, ${item.requested} requested)`).join(', ')
        notify.error(t('common.tryAgain'), `Insufficient available stock in ${transfer.sourceWarehouse}: ${products}.`)
        return
      }
    }
    await apiFetch(`/inventory/transfers/${transfer.id}/${action}`, { method: 'POST' })
    await refresh()
    selectedTransfer.value = transfers.value.find(item => item.id === transfer.id) ?? null
    confirmOpen.value = false
    notify.success(t('common.changesSaved'), t(`inventoryTransfers.${action}Success`))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), transferErrorMessage(error))
  } finally {
    actingId.value = null
  }
}
const confirmAction = () => {
  if (selectedTransfer.value && pendingAction.value) void runAction(selectedTransfer.value, pendingAction.value)
}
const actions = (transfer: Transfer) => [
  [{
    label: t('purchasing.view'),
    icon: 'i-lucide-eye',
    onSelect: () => openDetail(transfer)
  }],
  ...(transfer.status === 'draft' ? [[{
    label: t('inventoryTransfers.send'),
    icon: 'i-lucide-send',
    onSelect: () => requestAction(transfer, 'send')
  }, {
    label: t('inventoryTransfers.cancel'),
    icon: 'i-lucide-x',
    onSelect: () => requestAction(transfer, 'cancel')
  }]] : []),
  ...(transfer.status === 'in_transit' ? [[{
    label: t('inventoryTransfers.receive'),
    icon: 'i-lucide-package-check',
    onSelect: () => requestAction(transfer, 'receive')
  }]] : [])
]
const columns: TableColumn<Transfer>[] = [
  {
    accessorKey: 'createdAt',
    header: () => t('inventoryTransfers.date'),
    cell: ({ row }) => h('button', { class: 'cursor-pointer font-medium text-highlighted hover:text-primary', onClick: () => openDetail(row.original) }, new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(row.original.createdAt)))
  },
  { accessorKey: 'sourceWarehouse', header: () => t('inventoryTransfers.source') },
  { accessorKey: 'destinationWarehouse', header: () => t('inventoryTransfers.destination') },
  { id: 'items', header: () => t('inventoryTransfers.items'), cell: ({ row }) => row.original.items.length },
  {
    accessorKey: 'status',
    header: () => t('inventory.status'),
    cell: ({ row }) => h(UBadge, { color: statusColor(row.original.status), variant: 'subtle' }, () => statusLabel(row.original.status))
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h('div', { class: 'flex justify-end' }, h(UDropdownMenu, { items: actions(row.original), content: { align: 'end' } }, () => h(UButton, { icon: 'i-lucide-ellipsis-vertical', color: 'neutral', variant: 'ghost', loading: actingId.value === row.original.id })))
  }
]
const detailColumns: TableColumn<Transfer['items'][number]>[] = [
  { accessorKey: 'sku', header: () => t('purchasing.productNumber') },
  { accessorKey: 'quantity', header: () => t('purchasing.ordered') }
]
watch(listUrl, () => { void refresh() })
watch(pageSize, () => { page.value = 1 })
</script>

<template>
  <AppDataTable
    :data="transfers"
    :columns="columns"
    :get-row-id="(row) => row.id"
    :loading="status === 'pending'"
    table-key="inventory-transfers"
    :column-labels="{
      createdAt: t('inventoryTransfers.date'),
      sourceWarehouse: t('inventoryTransfers.source'),
      destinationWarehouse: t('inventoryTransfers.destination'),
      items: t('inventoryTransfers.items'),
      status: t('inventory.status')
    }"
  >
    <template #header>
      <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-highlighted">
          {{ t('inventoryTransfers.title') }} ({{ data?.pagination.total ?? 0 }})
        </p>
        <UButton :label="t('inventoryTransfers.create')" @click="openCreate" />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :title="t('inventoryTransfers.empty')"
        :description="t('inventoryTransfers.emptyDescription')"
        icon="i-lucide-arrow-left-right"
      />
    </template>
    <template #footer>
      <TablePaginationFooter
        v-model:page="page"
        v-model:page-size="pageSize"
        :total="data?.pagination.total ?? 0"
      />
    </template>
  </AppDataTable>

  <UModal v-model:open="createOpen" :title="t('inventoryTransfers.create')">
    <template #body>
      <UForm class="space-y-5" @submit.prevent="createTransfer">
        <UFormField :label="t('inventoryTransfers.source')">
          <SearchableSelect v-model="transferForm.sourceWarehouseId" :items="warehouseItems" :placeholder="t('inventoryTransfers.selectWarehouse')" :search-placeholder="t('inventoryTransfers.searchWarehouses')" />
        </UFormField>
        <UFormField :label="t('inventoryTransfers.destination')">
          <SearchableSelect v-model="transferForm.destinationWarehouseId" :items="warehouseItems" :placeholder="t('inventoryTransfers.selectWarehouse')" :search-placeholder="t('inventoryTransfers.searchWarehouses')" />
        </UFormField>
        <UFormField :label="t('inventoryTransfers.products')">
          <SearchableSelect
            v-model="transferForm.productIds"
            v-model:search-term="productSearch"
            :items="productItems"
            :has-more="productHasMore"
            :loading="productsLoading"
            :load-more="loadProducts"
            multiple
            show-placeholder-when-selected
            :placeholder="t('inventoryTransfers.selectProducts')"
            :search-placeholder="t('products.searchProducts')"
          />
        </UFormField>
        <div v-if="selectedProducts.length" class="space-y-2">
          <div
            v-for="product in selectedProducts"
            :key="product.id"
            class="grid grid-cols-[minmax(0,1fr)_7rem_auto] items-center gap-2 rounded-md border border-default p-2"
          >
            <div class="min-w-0">
              <p class="truncate text-sm text-highlighted">{{ product.name }}</p>
              <p
                v-if="transferForm.sourceWarehouseId"
                class="text-xs"
                :class="exceedsAvailableStock(product.id, quantities[product.id] || 1) ? 'text-error' : 'text-muted'"
              >
                {{ availabilityLoading ? 'Checking available stock…' : `Available: ${availableByProductId[product.id] ?? 0}` }}
              </p>
            </div>
            <UInput v-model.number="quantities[product.id]" type="number" min="0.0001" step="0.0001" />
            <UButton icon="i-lucide-x" color="neutral" variant="ghost" @click="removeProduct(product.id)" />
          </div>
        </div>
        <UFormField :label="t('inventory.note')">
          <UTextarea v-model="transferForm.note" class="w-full" />
        </UFormField>
        <p v-if="insufficientSelectedProducts.length" class="text-sm text-error">
          Requested quantity is greater than the available stock in the source warehouse. Reduce the quantity or add stock before creating this transfer.
        </p>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="createOpen = false"
          />
          <UButton
            :label="t('common.create')"
            type="submit"
            :disabled="availabilityLoading || insufficientSelectedProducts.length > 0"
            :loading="creating"
          />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal v-model:open="detailOpen" :title="t('inventoryTransfers.title')" :ui="{ content: 'sm:max-w-3xl' }">
    <template #body>
      <div v-if="selectedTransfer" class="space-y-4">
        <p class="text-sm text-muted">
          {{ selectedTransfer.sourceWarehouse }} → {{ selectedTransfer.destinationWarehouse }}
        </p>
        <p v-if="selectedTransfer.note" class="text-sm">{{ selectedTransfer.note }}</p>
        <AppDataTable
          :data="selectedTransfer.items"
          :columns="detailColumns"
          :get-row-id="row => row.productId"
          max-height="h-auto"
          table-key="inventory-transfer-lines"
        />
        <div class="flex justify-end gap-2">
          <UButton
            v-if="selectedTransfer.status === 'draft'"
            :label="t('inventoryTransfers.send')"
            @click="requestAction(selectedTransfer, 'send')"
          />
          <UButton
            v-if="selectedTransfer.status === 'in_transit'"
            :label="t('inventoryTransfers.receive')"
            @click="requestAction(selectedTransfer, 'receive')"
          />
        </div>
      </div>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="confirmOpen"
    :title="pendingAction ? t(`inventoryTransfers.${pendingAction}`) : ''"
    :description="pendingAction ? t(`inventoryTransfers.${pendingAction}Description`) : ''"
    :confirm-label="pendingAction ? t(`inventoryTransfers.${pendingAction}`) : ''"
    :confirm-color="pendingAction === 'cancel' ? 'error' : 'primary'"
    :loading="!!actingId"
    @confirm="confirmAction"
  />
</template>

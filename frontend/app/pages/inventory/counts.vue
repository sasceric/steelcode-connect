<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type Count = {
  id: string
  warehouse: string
  status: 'draft' | 'posted'
  createdAt: string
  note: string | null
  items: { productId: string, sku: string | null, expectedQuantity: number, countedQuantity: number, variance: number }[]
}
type Warehouse = { id: string, name: string, active: boolean }
type Product = { id: string, name: string, sku: string | null, variantCombination?: string | null }

const { t } = useI18n()
const notify = useAppToast()
const UBadge = resolveComponent('UBadge')
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const postingId = ref<string | null>(null)
const detailOpen = ref(false)
const confirmOpen = ref(false)
const selectedCount = ref<Count | null>(null)
const createOpen = ref(false)
const creating = ref(false)
const countForm = reactive({ warehouseId: '', productIds: [] as string[], note: '' })
const countedQuantities = reactive<Record<string, number>>({})
const productSearch = ref('')
const productPage = ref(1)
const productOptions = ref<Product[]>([])
const selectedOptions = reactive<Record<string, Product>>({})
const productHasMore = ref(false)
const productsLoading = ref(false)
let productRequestId = 0
const page = ref(1)
const pageSize = ref(25)
const listUrl = computed(() => `/inventory/counts?page=${page.value}&limit=${pageSize.value}`)
const data = ref<{ counts: Count[], pagination: { total: number } }>({ counts: [], pagination: { total: 0 } })
const status = ref<'pending' | 'success'>('pending')
const refresh = async () => {
  status.value = 'pending'
  try {
    data.value = await apiFetch<{ counts: Count[], pagination: { total: number } }>(listUrl.value)
  } finally {
    status.value = 'success'
  }
}
const counts = computed(() => data.value.counts)
onMounted(() => { void refresh() })
watch(listUrl, () => { void refresh() })
watch(pageSize, () => { page.value = 1 })
const { data: warehousesData } = await useAsyncData('inventory-count-warehouses', () => apiFetch<{ warehouses: Warehouse[] }>('/inventory/warehouses'))
const warehouseItems = computed(() => (warehousesData.value?.warehouses ?? []).filter(warehouse => warehouse.active).map(warehouse => ({ label: warehouse.name, value: warehouse.id })))
const productItems = computed(() => {
  const options = [...Object.values(selectedOptions), ...productOptions.value.filter(product => !selectedOptions[product.id])]
  return options.map(product => ({
    label: product.name,
    productName: product.name,
    productNumber: product.sku,
    variantCombination: product.variantCombination,
    value: product.id
  }))
})
const selectedProducts = computed(() => countForm.productIds.map(id => selectedOptions[id]).filter((product): product is Product => !!product))
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
    if (productSearch.value.trim()) params.set('search', productSearch.value.trim())
    const response = await apiFetch<{ products: Product[], pagination?: { hasMore: boolean } }>(`/products?${params}`)
    if (currentRequest !== productRequestId) return
    productOptions.value = [...productOptions.value, ...response.products]
    productHasMore.value = response.pagination?.hasMore ?? false
    productPage.value += 1
  } finally {
    if (currentRequest === productRequestId) productsLoading.value = false
  }
}
const openCreate = () => {
  Object.assign(countForm, { warehouseId: '', productIds: [], note: '' })
  Object.keys(countedQuantities).forEach(key => delete countedQuantities[key])
  Object.keys(selectedOptions).forEach(key => delete selectedOptions[key])
  void loadProducts(true)
  createOpen.value = true
}
const createCount = async () => {
  if (!countForm.warehouseId || !countForm.productIds.length) {
    notify.error(t('common.tryAgain'), t('inventoryCounts.createValidation'))
    return
  }
  creating.value = true
  try {
    await apiFetch('/inventory/counts', { method: 'POST', body: { warehouseId: countForm.warehouseId, note: countForm.note, items: countForm.productIds.map(productId => ({ productId, countedQuantity: countedQuantities[productId] ?? 0 })) } })
    page.value = 1
    await refresh()
    createOpen.value = false
    notify.success(t('common.changesSaved'), t('inventoryCounts.created'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    creating.value = false
  }
}
const removeProduct = (productId: string) => {
  countForm.productIds = countForm.productIds.filter(id => id !== productId)
  delete countedQuantities[productId]
  delete selectedOptions[productId]
}
watch(productSearch, () => { void loadProducts(true) })
watch(() => countForm.productIds, (productIds) => productIds.forEach((productId) => {
  const option = productOptions.value.find(product => product.id === productId)
  if (option) selectedOptions[productId] = option
  if (countedQuantities[productId] === undefined) countedQuantities[productId] = 0
}))
const openDetail = (count: Count) => {
  selectedCount.value = count
  detailOpen.value = true
}
const requestPost = (count: Count) => {
  selectedCount.value = count
  confirmOpen.value = true
}
const post = async (count: Count) => {
  postingId.value = count.id
  try {
    await apiFetch(`/inventory/counts/${count.id}/post`, { method: 'POST' })
    await refresh()
    confirmOpen.value = false
    selectedCount.value = data.value.counts.find(item => item.id === count.id) ?? null
    notify.success(t('common.changesSaved'), t('inventoryCounts.posted'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    postingId.value = null
  }
}
const confirmPost = () => {
  if (selectedCount.value) void post(selectedCount.value)
}
const detailColumns: TableColumn<Count['items'][number]>[] = [
  { accessorKey: 'sku', header: () => t('purchasing.productNumber') },
  { accessorKey: 'expectedQuantity', header: () => t('inventoryCounts.expected') },
  { accessorKey: 'countedQuantity', header: () => t('inventoryCounts.counted') },
  { accessorKey: 'variance', header: () => t('inventoryCounts.variance') }
]
const columns: TableColumn<Count>[] = [
  { accessorKey: 'createdAt', header: () => t('inventoryCounts.date'), cell: ({ row }) => h('button', { class: 'cursor-pointer font-medium text-highlighted hover:text-primary', onClick: () => openDetail(row.original) }, new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(row.original.createdAt))) },
  { accessorKey: 'warehouse', header: () => t('inventory.warehouse') },
  { id: 'items', header: () => t('inventoryTransfers.items'), cell: ({ row }) => row.original.items.length },
  { id: 'variance', header: () => t('inventoryCounts.variance'), cell: ({ row }) => row.original.items.reduce((total, item) => total + item.variance, 0) },
  { accessorKey: 'status', header: () => t('inventory.status'), cell: ({ row }) => h(UBadge, { color: row.original.status === 'posted' ? 'success' : 'neutral', variant: 'subtle' }, () => t(`inventoryCounts.status.${row.original.status}`)) },
  { id: 'actions', header: '', enableHiding: false, enableSorting: false, cell: ({ row }) => h('div', { class: 'flex justify-end' }, h(UDropdownMenu, { items: [[{ label: t('purchasing.view'), icon: 'i-lucide-eye', onSelect: () => openDetail(row.original) }, ...(row.original.status === 'draft' ? [{ label: t('inventoryCounts.post'), icon: 'i-lucide-check', onSelect: () => requestPost(row.original) }] : [])]], content: { align: 'end' } }, () => h(UButton, { icon: 'i-lucide-ellipsis-vertical', color: 'neutral', variant: 'ghost', loading: postingId.value === row.original.id }))) }
]
</script>

<template>
  <AppDataTable
    :data="counts"
    :columns="columns"
    :get-row-id="(row) => row.id"
    :loading="status === 'pending'"
    table-key="inventory-counts"
  >
    <template #header>
      <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-highlighted">{{ t('inventoryCounts.title') }} ({{ data.pagination.total }})</p>
        <UButton :label="t('inventoryCounts.create')" @click="openCreate" />
      </div>
    </template>
    <template #empty><AppEmptyState :title="t('inventoryCounts.empty')" :description="t('inventoryCounts.emptyDescription')" icon="i-lucide-clipboard-check" /></template>
    <template #footer>
      <TablePaginationFooter
        v-model:page="page"
        v-model:page-size="pageSize"
        :total="data.pagination.total"
      />
    </template>
  </AppDataTable>
  <UModal v-model:open="createOpen" :title="t('inventoryCounts.create')">
    <template #body>
      <UForm class="space-y-5" @submit.prevent="createCount">
        <UFormField :label="t('inventory.warehouse')">
          <SearchableSelect v-model="countForm.warehouseId" :items="warehouseItems" :placeholder="t('inventoryCounts.selectWarehouse')" :search-placeholder="t('inventoryTransfers.searchWarehouses')" />
        </UFormField>
        <UFormField :label="t('inventoryTransfers.products')">
          <SearchableSelect
            v-model="countForm.productIds"
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
        <div v-for="product in selectedProducts" :key="product.id" class="grid grid-cols-[minmax(0,1fr)_7rem_auto] items-center gap-2 rounded-md border border-default p-2">
          <span class="truncate text-sm text-highlighted">{{ product.name }}</span>
          <UInput v-model.number="countedQuantities[product.id]" type="number" min="0" step="0.0001" />
          <UButton icon="i-lucide-x" color="neutral" variant="ghost" @click="removeProduct(product.id)" />
        </div>
        <UFormField :label="t('inventory.note')"><UTextarea v-model="countForm.note" class="w-full" /></UFormField>
        <div class="flex justify-end gap-2"><UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="createOpen = false" /><UButton :label="t('common.create')" type="submit" :loading="creating" /></div>
      </UForm>
    </template>
  </UModal>

  <UModal v-model:open="detailOpen" :title="t('inventoryCounts.title')" :ui="{ content: 'sm:max-w-3xl' }">
    <template #body>
      <div v-if="selectedCount" class="space-y-4">
        <p class="text-sm text-muted">
          {{ selectedCount.warehouse }} · {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(selectedCount.createdAt)) }}
        </p>
        <p v-if="selectedCount.note" class="text-sm">{{ selectedCount.note }}</p>
        <AppDataTable
          :data="selectedCount.items"
          :columns="detailColumns"
          :get-row-id="row => row.productId"
          max-height="h-auto"
          table-key="inventory-count-lines"
        />
        <div v-if="selectedCount.status === 'draft'" class="flex justify-end">
          <UButton :label="t('inventoryCounts.post')" @click="requestPost(selectedCount)" />
        </div>
      </div>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="confirmOpen"
    :title="t('inventoryCounts.post')"
    :description="t('inventoryCounts.postDescription')"
    :confirm-label="t('inventoryCounts.post')"
    confirm-color="primary"
    :loading="!!postingId"
    @confirm="confirmPost"
  />
</template>

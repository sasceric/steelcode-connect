<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { RowSelectionState } from '@tanstack/table-core'

type Policy = {
  id: string
  productId: string
  productName: string | null
  productSku: string | null
  warehouseId: string
  warehouseName: string
  available: number
  incoming: number
  position: number
  threshold: number
  target: number
  leadDays: number | null
  needsOrder: boolean
  supplierName: string | null
  offerId: string | null
  purchaseUnit: string | null
  stockUnitsPerPurchaseUnit: number | null
  suggestedPurchaseQuantity: number | null
  currency: string | null
  estimatedCost: number | null
  expectedDays: number | null
}
type ProductOption = { id: string, name: string, sku: string | null, variantCombination?: string | null }
type WarehouseOption = { id: string, name: string, active: boolean }

const { t } = useI18n()
const notify = useAppToast()
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const page = ref(1)
const pageSize = ref(25)
const suggestionsOnly = ref(true)
const search = ref('')
const debouncedSearch = ref('')
const warehouseFilter = ref('')
const selection = ref<RowSelectionState>({})
const modalOpen = ref(false)
const confirmRemoveOpen = ref(false)
const confirmDraftsOpen = ref(false)
const editing = ref<Policy | null>(null)
const removing = ref<Policy | null>(null)
const saving = ref(false)
const generating = ref(false)
const form = reactive({
  warehouseId: '',
  productId: '',
  threshold: 0,
  target: 1,
  leadDays: null as number | null
})
const productSearch = ref('')
const debouncedProductSearch = ref('')
const productPage = ref(1)
const products = ref<ProductOption[]>([])
const selectedProduct = ref<ProductOption | null>(null)
const productHasMore = ref(false)
const productsLoading = ref(false)
let productRequestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined
let productSearchTimer: ReturnType<typeof setTimeout> | undefined

const listUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(pageSize.value),
    suggestions: suggestionsOnly.value ? '1' : '0'
  })
  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  if (warehouseFilter.value) params.set('warehouseId', warehouseFilter.value)
  return `/inventory/replenishment?${params}`
})
const { data, status, refresh } = await useAsyncData('inventory-replenishment', () =>
  apiFetch<{ items: Policy[], pagination: { total: number } }>(listUrl.value)
)
const { data: warehouseData } = await useAsyncData('replenishment-warehouses', () =>
  apiFetch<{ warehouses: WarehouseOption[] }>('/inventory/warehouses')
)
const warehouseItems = computed(() =>
  (warehouseData.value?.warehouses ?? []).filter(item => item.active).map(item => ({ label: item.name, value: item.id }))
)
const productItems = computed(() => {
  const options = selectedProduct.value && !products.value.some(item => item.id === selectedProduct.value?.id)
    ? [selectedProduct.value, ...products.value]
    : products.value
  return options.map(item => ({
    label: item.name,
    productName: item.name,
    productNumber: item.sku,
    variantCombination: item.variantCombination,
    value: item.id
  }))
})
const selectedPolicies = computed(() =>
  (data.value?.items ?? []).filter(item => selection.value[item.id])
)
const showSuggestions = () => {
  suggestionsOnly.value = true
}
const showAllPolicies = () => {
  suggestionsOnly.value = false
}
const closePolicy = () => {
  modalOpen.value = false
}
const loadProducts = async (reset = false) => {
  if (productsLoading.value && !reset) return
  if (reset) {
    productRequestId += 1
    productPage.value = 1
    products.value = []
  }
  const requestId = productRequestId
  productsLoading.value = true
  try {
    const params = new URLSearchParams({ view: 'options', includeVariants: '1', page: String(productPage.value), limit: '25', sort: 'name', direction: 'ASC' })
    if (debouncedProductSearch.value) params.set('search', debouncedProductSearch.value)
    const response = await apiFetch<{ products: ProductOption[], pagination?: { hasMore: boolean } }>(`/products?${params}`)
    if (requestId !== productRequestId) return
    products.value = [...products.value, ...response.products]
    productHasMore.value = response.pagination?.hasMore ?? false
    productPage.value += 1
  } finally {
    if (requestId === productRequestId) productsLoading.value = false
  }
}
const openPolicy = (policy?: Policy) => {
  editing.value = policy ?? null
  Object.assign(form, {
    warehouseId: policy?.warehouseId ?? '',
    productId: policy?.productId ?? '',
    threshold: policy?.threshold ?? 0,
    target: policy?.target ?? 1,
    leadDays: policy?.leadDays ?? null
  })
  selectedProduct.value = policy
    ? { id: policy.productId, name: policy.productName ?? policy.productSku ?? policy.productId, sku: policy.productSku }
    : null
  void loadProducts(true)
  modalOpen.value = true
}
const savePolicy = async () => {
  if (!form.warehouseId || !form.productId || !Number.isFinite(form.threshold) || !Number.isFinite(form.target) || form.threshold < 0 || form.target <= form.threshold) {
    notify.error(t('common.tryAgain'), t('replenishment.validation'))
    return
  }
  saving.value = true
  try {
    await apiFetch('/inventory/replenishment/policies', {
      method: 'POST',
      body: {
        ...form,
        leadDays: form.leadDays === null || String(form.leadDays).trim() === '' ? null : form.leadDays
      }
    })
    modalOpen.value = false
    selection.value = {}
    await refresh()
    notify.success(t('common.changesSaved'), t('replenishment.saved'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const askRemove = (policy: Policy) => {
  removing.value = policy
  confirmRemoveOpen.value = true
}
const removePolicy = async () => {
  if (!removing.value) return
  saving.value = true
  try {
    await apiFetch(`/inventory/replenishment/policies/${removing.value.id}`, { method: 'DELETE' })
    confirmRemoveOpen.value = false
    removing.value = null
    selection.value = {}
    await refresh()
    notify.success(t('common.changesSaved'), t('replenishment.removed'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const askCreateDrafts = () => {
  if (selectedPolicies.value.length === 0 || selectedPolicies.value.some(item => !item.needsOrder || !item.offerId)) {
    notify.error(t('common.tryAgain'), t('replenishment.selectValid'))
    return
  }
  confirmDraftsOpen.value = true
}
const createDrafts = async () => {
  generating.value = true
  try {
    const response = await apiFetch<{ orders: { id: string }[] }>('/inventory/replenishment/drafts', {
      method: 'POST',
      body: { policyIds: selectedPolicies.value.map(item => item.id) }
    })
    confirmDraftsOpen.value = false
    selection.value = {}
    await refresh()
    notify.success(t('common.changesSaved'), t('replenishment.draftsCreated', { count: response.orders.length }))
    await navigateTo('/inventory/purchase-orders')
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    generating.value = false
  }
}
const actions = (policy: Policy) => [[
  { label: t('common.edit'), icon: 'i-lucide-pencil', onSelect: () => openPolicy(policy) },
  { label: t('replenishment.removePolicy'), icon: 'i-lucide-trash-2', onSelect: () => askRemove(policy) }
]]
const columns: TableColumn<Policy>[] = [
  { accessorKey: 'productName', header: () => t('inventoryTransfers.products'), cell: ({ row }) => h('button', { class: 'cursor-pointer text-left font-medium text-highlighted hover:text-primary', onClick: () => openPolicy(row.original) }, row.original.productName ?? row.original.productSku ?? row.original.productId) },
  { accessorKey: 'productSku', header: () => t('purchasing.productNumber') },
  { accessorKey: 'warehouseName', header: () => t('inventory.warehouse') },
  { accessorKey: 'available', header: () => t('replenishment.available') },
  { accessorKey: 'incoming', header: () => t('replenishment.incoming') },
  { accessorKey: 'threshold', header: () => t('replenishment.threshold') },
  { accessorKey: 'target', header: () => t('replenishment.target') },
  { accessorKey: 'supplierName', header: () => t('suppliers.name'), cell: ({ row }) => row.original.supplierName ?? t('replenishment.noOffer') },
  { id: 'suggested', header: () => t('replenishment.suggested'), cell: ({ row }) => row.original.suggestedPurchaseQuantity === null ? '—' : `${row.original.suggestedPurchaseQuantity} ${row.original.purchaseUnit}` },
  { id: 'estimatedCost', header: () => t('replenishment.estimatedCost'), cell: ({ row }) => row.original.estimatedCost === null ? '—' : `${row.original.estimatedCost.toFixed(2)} ${row.original.currency}` },
  { accessorKey: 'needsOrder', header: () => t('inventory.status'), cell: ({ row }) => h(UBadge, { color: row.original.needsOrder ? 'warning' : 'success', variant: 'subtle' }, () => row.original.needsOrder ? t('replenishment.needsOrder') : t('replenishment.covered')) },
  { id: 'actions', header: '', enableHiding: false, enableSorting: false, cell: ({ row }) => h('div', { class: 'flex justify-end' }, h(UDropdownMenu, { items: actions(row.original), content: { align: 'end' } }, () => h(UButton, { icon: 'i-lucide-ellipsis-vertical', color: 'neutral', variant: 'ghost' }))) }
]
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { debouncedSearch.value = value.trim() }, 250)
})
watch(productSearch, (value) => {
  clearTimeout(productSearchTimer)
  productSearchTimer = setTimeout(() => { debouncedProductSearch.value = value.trim() }, 250)
})
watch(debouncedProductSearch, () => { void loadProducts(true) })
watch(listUrl, () => { selection.value = {}; void refresh() })
watch([suggestionsOnly, warehouseFilter, debouncedSearch, pageSize], () => { page.value = 1 })
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  clearTimeout(productSearchTimer)
})
</script>

<template>
  <AppDataTable
    v-model:row-selection="selection"
    :data="data?.items ?? []"
    :columns="columns"
    :get-row-id="row => row.id"
    :loading="status === 'pending'"
    :selectable="suggestionsOnly"
    table-key="inventory-replenishment"
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-sm font-medium text-highlighted">{{ t('replenishment.title') }} ({{ data?.pagination.total ?? 0 }})</p>
          <UButton :label="t('replenishment.suggestions')" :variant="suggestionsOnly ? 'solid' : 'ghost'" size="sm" @click="showSuggestions" />
          <UButton :label="t('replenishment.allPolicies')" :variant="suggestionsOnly ? 'ghost' : 'solid'" size="sm" @click="showAllPolicies" />
          <UInput v-model="search" icon="i-lucide-search" :placeholder="t('replenishment.search')" class="w-full sm:w-56" />
          <SearchableSelect
            v-model="warehouseFilter"
            :items="warehouseItems"
            :placeholder="t('replenishment.allWarehouses')"
            :search-placeholder="t('inventoryTransfers.searchWarehouses')"
            class="w-48"
          />
        </div>
        <div class="flex items-center gap-2">
          <UButton
            v-if="suggestionsOnly"
            :label="t('replenishment.createDrafts')"
            :disabled="selectedPolicies.length === 0"
            @click="askCreateDrafts"
          />
          <UButton :label="t('replenishment.addPolicy')" color="neutral" variant="subtle" @click="openPolicy()" />
        </div>
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :title="t(suggestionsOnly ? 'replenishment.noSuggestions' : 'replenishment.noPolicies')"
        :description="t(suggestionsOnly ? 'replenishment.noSuggestionsDescription' : 'replenishment.noPoliciesDescription')"
        icon="i-lucide-chart-no-axes-combined"
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

  <UModal v-model:open="modalOpen" :title="t(editing ? 'replenishment.editPolicy' : 'replenishment.addPolicy')">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="savePolicy">
        <UFormField :label="t('inventory.warehouse')">
          <SearchableSelect
            v-model="form.warehouseId"
            :items="warehouseItems"
            :disabled="!!editing"
            :placeholder="t('inventoryCounts.selectWarehouse')"
            :search-placeholder="t('inventoryTransfers.searchWarehouses')"
          />
        </UFormField>
        <UFormField :label="t('inventoryTransfers.products')">
          <SearchableSelect
            v-model="form.productId"
            v-model:search-term="productSearch"
            :items="productItems"
            :disabled="!!editing"
            :loading="productsLoading"
            :has-more="productHasMore"
            :load-more="loadProducts"
            :placeholder="t('purchasing.selectProduct')"
            :search-placeholder="t('products.searchProducts')"
          />
        </UFormField>
        <div class="grid grid-cols-2 gap-3">
          <UFormField :label="t('replenishment.threshold')">
            <UInput v-model.number="form.threshold" type="number" min="0" step="0.0001" class="w-full" />
          </UFormField>
          <UFormField :label="t('replenishment.target')">
            <UInput v-model.number="form.target" type="number" min="0.0001" step="0.0001" class="w-full" />
          </UFormField>
        </div>
        <UFormField :label="t('replenishment.leadDays')">
          <UInput v-model.number="form.leadDays" type="number" min="0" max="3650" step="1" class="w-full" />
        </UFormField>
        <p class="text-xs text-muted">{{ t('replenishment.policyHelp') }}</p>
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closePolicy" />
          <UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="confirmRemoveOpen"
    :title="t('replenishment.removePolicy')"
    :description="t('replenishment.removeDescription')"
    :confirm-label="t('replenishment.removePolicy')"
    confirm-color="error"
    :loading="saving"
    @confirm="removePolicy"
  />
  <ConfirmationModal
    v-model:open="confirmDraftsOpen"
    :title="t('replenishment.createDrafts')"
    :description="t('replenishment.draftDescription', { count: selectedPolicies.length })"
    :confirm-label="t('replenishment.createDrafts')"
    :loading="generating"
    @confirm="createDrafts"
  />
</template>

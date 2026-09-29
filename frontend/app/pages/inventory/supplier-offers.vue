<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type Offer = {
  id: string
  supplierId: string
  supplierName: string
  productId: string
  productSku: string | null
  productName: string | null
  supplierSku: string | null
  unitCost: number
  currency: string
  minimumQuantity: number
  purchaseUnit: string
  stockUnitsPerPurchaseUnit: number
  validFrom: string | null
  validUntil: string | null
  leadTimeDays: number | null
  preferred: boolean
  active: boolean
}
type Product = { id: string, name: string, sku: string | null }

const { t } = useI18n()
const notify = useAppToast()
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const UBadge = resolveComponent('UBadge')
const page = ref(1)
const pageSize = ref(25)
const search = ref('')
const debouncedSearch = ref('')
const modalOpen = ref(false)
const saving = ref(false)
const editing = ref<Offer | null>(null)
const form = reactive({
  supplierId: '',
  productId: '',
  supplierSku: '',
  unitCost: 0,
  currency: 'EUR',
  minimumQuantity: 1,
  purchaseUnit: 'unit',
  stockUnitsPerPurchaseUnit: 1,
  validFrom: '',
  validUntil: '',
  leadTimeDays: null as number | null,
  preferred: false,
  active: true
})
const productSearch = ref('')
const debouncedProductSearch = ref('')
const productPage = ref(1)
const products = ref<Product[]>([])
const selectedProduct = ref<Product | null>(null)
const productHasMore = ref(false)
const productLoading = ref(false)
let productRequestId = 0
const listUrl = computed(() => {
  const params = new URLSearchParams({ page: String(page.value), limit: String(pageSize.value) })
  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  return `/inventory/supplier-offers?${params}`
})
const { data, status, refresh } = await useAsyncData('supplier-offers-page', () =>
  apiFetch<{ offers: Offer[], pagination: { total: number } }>(listUrl.value)
)
const productItems = computed(() => {
  const all = selectedProduct.value && !products.value.some(product => product.id === selectedProduct.value?.id)
    ? [selectedProduct.value, ...products.value]
    : products.value
  return all.map(product => ({
    label: product.sku ? `${product.name} · ${product.sku}` : product.name,
    value: product.id
  }))
})
const loadProducts = async (reset = false) => {
  if (productLoading.value && !reset) return
  if (reset) {
    productRequestId += 1
    productPage.value = 1
    products.value = []
  }
  const currentRequest = productRequestId
  productLoading.value = true
  try {
    const params = new URLSearchParams({
      view: 'options',
      limit: '25',
      page: String(productPage.value),
      sort: 'name',
      direction: 'ASC'
    })
    if (debouncedProductSearch.value) params.set('search', debouncedProductSearch.value)
    const response = await apiFetch<{ products: Product[], pagination?: { hasMore: boolean } }>(`/products?${params}`)
    if (currentRequest !== productRequestId) return
    products.value = [...products.value, ...response.products]
    productHasMore.value = response.pagination?.hasMore ?? false
    productPage.value += 1
  } finally {
    if (currentRequest === productRequestId) productLoading.value = false
  }
}
const openOffer = (offer?: Offer) => {
  editing.value = offer ?? null
  Object.assign(form, {
    supplierId: offer?.supplierId ?? '',
    productId: offer?.productId ?? '',
    supplierSku: offer?.supplierSku ?? '',
    unitCost: offer?.unitCost ?? 0,
    currency: offer?.currency ?? 'EUR',
    minimumQuantity: offer?.minimumQuantity ?? 1,
    purchaseUnit: offer?.purchaseUnit ?? 'unit',
    stockUnitsPerPurchaseUnit: offer?.stockUnitsPerPurchaseUnit ?? 1,
    validFrom: offer?.validFrom ?? '',
    validUntil: offer?.validUntil ?? '',
    leadTimeDays: offer?.leadTimeDays ?? null,
    preferred: offer?.preferred ?? false,
    active: offer?.active ?? true
  })
  selectedProduct.value = offer
    ? { id: offer.productId, name: offer.productName ?? offer.productSku ?? offer.productId, sku: offer.productSku }
    : null
  void loadProducts(true)
  modalOpen.value = true
}
const saveOffer = async () => {
  if (!form.supplierId || !form.productId || form.unitCost < 0 || form.minimumQuantity <= 0 || !form.purchaseUnit.trim() || !Number.isInteger(form.stockUnitsPerPurchaseUnit) || form.stockUnitsPerPurchaseUnit < 1 || (form.validFrom && form.validUntil && form.validFrom > form.validUntil)) {
    notify.error(t('common.tryAgain'), t('purchasing.offerValidation'))
    return
  }
  saving.value = true
  try {
    await apiFetch(editing.value ? `/inventory/supplier-offers/${editing.value.id}` : '/inventory/supplier-offers', {
      method: editing.value ? 'PATCH' : 'POST',
      body: form
    })
    modalOpen.value = false
    await refresh()
    notify.success(t('common.changesSaved'), t('purchasing.offerSaved'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const clearValidFrom = () => {
  form.validFrom = ''
}
const clearValidUntil = () => {
  form.validUntil = ''
}
const columns: TableColumn<Offer>[] = [
  { accessorKey: 'supplierName', header: () => t('suppliers.name') },
  { accessorKey: 'productName', header: () => t('inventoryTransfers.products'), cell: ({ row }) => row.original.productName ?? '—' },
  { accessorKey: 'productSku', header: () => t('purchasing.productNumber'), cell: ({ row }) => row.original.productSku ?? '—' },
  { accessorKey: 'supplierSku', header: () => t('purchasing.supplierSku'), cell: ({ row }) => row.original.supplierSku ?? '—' },
  { id: 'cost', header: () => t('purchasing.unitCost'), cell: ({ row }) => `${row.original.unitCost.toFixed(2)} ${row.original.currency}` },
  { accessorKey: 'minimumQuantity', header: () => t('purchasing.minimumQuantity') },
  { id: 'purchaseUnit', header: () => t('purchasing.purchaseUnit'), cell: ({ row }) => `${row.original.purchaseUnit} · ${row.original.stockUnitsPerPurchaseUnit} ${t('purchasing.stockUnits')}` },
  { accessorKey: 'leadTimeDays', header: () => t('purchasing.leadTime'), cell: ({ row }) => row.original.leadTimeDays ?? '—' },
  { accessorKey: 'validUntil', header: () => t('purchasing.validUntil'), cell: ({ row }) => row.original.validUntil ?? '—' },
  { accessorKey: 'active', header: () => t('inventory.status'), cell: ({ row }) => h(UBadge, { color: row.original.active ? 'success' : 'neutral', variant: 'subtle' }, () => row.original.active ? t('inventory.active') : t('inventory.inactive')) },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h('div', { class: 'flex justify-end' }, h(UDropdownMenu, {
      items: [[{ label: t('common.edit'), icon: 'i-lucide-pencil', onSelect: () => openOffer(row.original) }]],
      content: { align: 'end' }
    }, () => h(UButton, { icon: 'i-lucide-ellipsis-vertical', color: 'neutral', variant: 'ghost' })))
  }
]
let searchTimer: ReturnType<typeof setTimeout> | undefined
let productSearchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { debouncedSearch.value = value.trim() }, 250)
})
watch(productSearch, (value) => {
  clearTimeout(productSearchTimer)
  productSearchTimer = setTimeout(() => { debouncedProductSearch.value = value.trim() }, 250)
})
watch(listUrl, () => { void refresh() })
watch(debouncedProductSearch, () => { void loadProducts(true) })
watch([pageSize, debouncedSearch], () => { page.value = 1 })
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  clearTimeout(productSearchTimer)
})
</script>

<template>
  <AppDataTable
    :data="data?.offers ?? []"
    :columns="columns"
    :get-row-id="row => row.id"
    :loading="status === 'pending'"
    table-key="supplier-offers"
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <p class="text-sm font-medium text-highlighted">
            {{ t('purchasing.offers') }} ({{ data?.pagination.total ?? 0 }})
          </p>
          <UInput
            v-model="search"
            icon="i-lucide-search"
            :placeholder="t('purchasing.searchOffers')"
          />
        </div>
        <UButton :label="t('purchasing.addOffer')" @click="openOffer()" />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :title="t('purchasing.noOffers')"
        :description="t('purchasing.noOffersDescription')"
        icon="i-lucide-tag"
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

  <UModal v-model:open="modalOpen" :title="editing ? t('purchasing.editOffer') : t('purchasing.addOffer')">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="saveOffer">
        <UFormField :label="t('suppliers.name')">
          <SupplierPicker
            v-model="form.supplierId"
            :disabled="!!editing"
            :initial-label="editing?.supplierName ?? ''"
          />
        </UFormField>
        <UFormField :label="t('inventoryTransfers.products')">
          <SearchableSelect
            v-model="form.productId"
            v-model:search-term="productSearch"
            :items="productItems"
            :disabled="!!editing"
            :has-more="productHasMore"
            :loading="productLoading"
            :load-more="loadProducts"
            :placeholder="t('purchasing.selectProduct')"
            :search-placeholder="t('products.searchProducts')"
          />
        </UFormField>
        <UFormField :label="t('purchasing.supplierSku')">
          <UInput v-model="form.supplierSku" class="w-full" />
        </UFormField>
        <div class="grid grid-cols-2 gap-3">
          <UFormField :label="t('purchasing.unitCost')">
            <UInput v-model.number="form.unitCost" type="number" min="0" step="0.0001" class="w-full" />
          </UFormField>
          <UFormField :label="t('purchasing.currency')">
            <UInput v-model="form.currency" maxlength="3" class="w-full" />
          </UFormField>
          <UFormField :label="t('purchasing.minimumQuantity')">
            <UInput v-model.number="form.minimumQuantity" type="number" min="0.0001" step="0.0001" class="w-full" />
          </UFormField>
          <UFormField :label="t('purchasing.purchaseUnit')">
            <UInput v-model="form.purchaseUnit" maxlength="64" class="w-full" />
          </UFormField>
          <UFormField :label="t('purchasing.stockUnitsPerPurchaseUnit')">
            <UInput v-model.number="form.stockUnitsPerPurchaseUnit" type="number" min="1" step="1" class="w-full" />
          </UFormField>
          <UFormField :label="t('purchasing.validFrom')">
            <div class="flex items-center gap-1">
              <CustomFieldDateInput v-model="form.validFrom" :with-time="false" :placeholder="t('purchasing.noValidityDate')" />
              <UButton icon="i-lucide-x" color="neutral" variant="ghost" :aria-label="t('purchasing.clearDate')" @click="clearValidFrom" />
            </div>
          </UFormField>
          <UFormField :label="t('purchasing.validUntil')">
            <div class="flex items-center gap-1">
              <CustomFieldDateInput v-model="form.validUntil" :with-time="false" :placeholder="t('purchasing.noValidityDate')" />
              <UButton icon="i-lucide-x" color="neutral" variant="ghost" :aria-label="t('purchasing.clearDate')" @click="clearValidUntil" />
            </div>
          </UFormField>
          <UFormField :label="t('purchasing.leadTime')">
            <UInput v-model.number="form.leadTimeDays" type="number" min="0" class="w-full" />
          </UFormField>
        </div>
        <UCheckbox v-model="form.preferred" :label="t('purchasing.preferred')" />
        <UCheckbox v-model="form.active" :label="t('inventory.active')" />
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="modalOpen = false" />
          <UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
</template>

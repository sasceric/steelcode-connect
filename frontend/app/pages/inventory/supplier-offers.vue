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
  variantCombination?: string | null
  unitCost: number | null
  currency: string
  preferredCurrency: string
  minimumQuantity: number
  minimumOrderQuantity: number
  prices: PriceInput[]
  purchaseUnit: string
  stockUnitsPerPurchaseUnit: number
  validFrom: string | null
  validUntil: string | null
  leadTimeDays: number | null
  preferred: boolean
  active: boolean
}
type Product = { id: string, name: string, sku: string | null, unitCode?: string | null, variantCombination?: string | null }
type PriceInput = {
  minimumQuantity: number
  unitCost: number
  currency: string
  validFrom: string
  validUntil: string
}
type OfferInput = {
  supplierSku: string
  preferredCurrency: string
  minimumOrderQuantity: number
  prices: PriceInput[]
  purchaseUnit: string
  stockUnitsPerPurchaseUnit: number
  leadTimeDays: number | null
  preferred: boolean
  active: boolean
}
type BatchRow = OfferInput & { productId: string, productName: string, productNumber: string | null, variantCombination?: string | null }

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
const selectedProductIds = ref<string[]>([])
const batchRows = ref<BatchRow[]>([])
const form = reactive({
  supplierId: '',
  productId: '',
  supplierSku: '',
  preferredCurrency: 'EUR',
  minimumOrderQuantity: 1,
  prices: [] as PriceInput[],
  purchaseUnit: 'unit',
  stockUnitsPerPurchaseUnit: 1,
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
const { data: currenciesData } = await useAsyncData('supplier-offer-currencies', () =>
  apiFetch<{ currencies: { code: string, symbol: string }[] }>('/products/currencies')
)
const { data: unitsData } = await useAsyncData('supplier-offer-units', () =>
  apiFetch<{ units: { code: string, symbol: string, labels: Record<string, string> }[] }>('/catalogue/references')
)
const currencyItems = computed(() => (currenciesData.value?.currencies ?? []).map(currency => ({
  label: `${currency.code} (${currency.symbol})`,
  value: currency.code
})))
const defaultCurrency = computed(() => currencyItems.value.some(item => item.value === 'EUR')
  ? 'EUR'
  : currencyItems.value[0]?.value ?? '')
const unitItems = computed(() => {
  const items = (unitsData.value?.units ?? []).map(unit => ({
    label: `${Object.values(unit.labels)[0] ?? unit.code} (${unit.symbol})`,
    value: unit.code
  }))
  if (editing.value?.purchaseUnit && !items.some(item => item.value === editing.value?.purchaseUnit)) {
    items.push({ label: `${editing.value.purchaseUnit} (${t('purchasing.legacyUnit')})`, value: editing.value.purchaseUnit })
  }
  return items
})
const unitLabel = (code: string) => {
  const label = unitItems.value.find(item => item.value === code)?.label
  return label?.split(' (')[0] ?? code
}
const productItems = computed(() => {
  const selected: Product[] = batchRows.value.map(row => ({
    id: row.productId,
    name: row.productName,
    sku: row.productNumber,
    variantCombination: row.variantCombination
  }))
  if (selectedProduct.value) selected.push(selectedProduct.value)
  const all = [...selected, ...products.value].filter((product, index, allProducts) =>
    allProducts.findIndex(candidate => candidate.id === product.id) === index
  )
  return all.map(product => ({
    label: product.name,
    productName: product.name,
    productNumber: product.sku,
    variantCombination: product.variantCombination,
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
      includeVariants: '1',
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
  selectedProductIds.value = []
  batchRows.value = []
  Object.assign(form, {
    supplierId: offer?.supplierId ?? '',
    productId: offer?.productId ?? '',
    supplierSku: offer?.supplierSku ?? '',
    preferredCurrency: offer?.preferredCurrency ?? defaultCurrency.value,
    minimumOrderQuantity: offer?.minimumOrderQuantity ?? 1,
    prices: offer?.prices.map(price => ({
      minimumQuantity: price.minimumQuantity,
      unitCost: price.unitCost,
      currency: price.currency,
      validFrom: price.validFrom ?? '',
      validUntil: price.validUntil ?? ''
    })) ?? [{ minimumQuantity: 1, unitCost: 0, currency: defaultCurrency.value, validFrom: '', validUntil: '' }],
    purchaseUnit: offer?.purchaseUnit ?? '',
    stockUnitsPerPurchaseUnit: offer?.stockUnitsPerPurchaseUnit ?? 1,
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
const closeModal = () => {
  modalOpen.value = false
}
const saveOffer = async () => {
  const rows = editing.value ? [form] : batchRows.value
  if (!form.supplierId || rows.length === 0 || rows.some(row =>
    row.minimumOrderQuantity <= 0
    || !currencyItems.value.some(item => item.value === row.preferredCurrency)
    || !unitItems.value.some(item => item.value === row.purchaseUnit)
    || row.stockUnitsPerPurchaseUnit <= 0
    || row.prices.some(price => price.minimumQuantity <= 0
      || price.unitCost < 0
      || !currencyItems.value.some(item => item.value === price.currency)
      || !!(price.validFrom && price.validUntil && price.validFrom > price.validUntil))
  )) {
    notify.error(t('common.tryAgain'), t('purchasing.offerValidation'))
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await apiFetch(`/inventory/supplier-offers/${editing.value.id}`, {
        method: 'PATCH',
        body: form
      })
    } else {
      await apiFetch('/inventory/supplier-offers/batch', {
        method: 'POST',
        body: { supplierId: form.supplierId, offers: batchRows.value }
      })
    }
    modalOpen.value = false
    await refresh()
    notify.success(t('common.changesSaved'), t('purchasing.offerSaved'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const onProductSelection = (ids: string | string[]) => {
  if (!Array.isArray(ids)) return
  selectedProductIds.value = ids
  batchRows.value = ids.map(id => {
    const existing = batchRows.value.find(row => row.productId === id)
    if (existing) return existing
    const product = products.value.find(item => item.id === id)
    return {
      productId: id,
      productName: product?.name ?? id,
      productNumber: product?.sku ?? null,
      variantCombination: product?.variantCombination,
      supplierSku: '',
      preferredCurrency: defaultCurrency.value,
      minimumOrderQuantity: 1,
      prices: [{ minimumQuantity: 1, unitCost: 0, currency: defaultCurrency.value, validFrom: '', validUntil: '' }],
      purchaseUnit: unitItems.value.some(item => item.value === product?.unitCode) ? product?.unitCode ?? '' : '',
      stockUnitsPerPurchaseUnit: 1,
      leadTimeDays: null,
      preferred: false,
      active: true
    }
  })
}
const removeBatchRow = (id: string) => {
  onProductSelection(selectedProductIds.value.filter(productId => productId !== id))
}
const columns: TableColumn<Offer>[] = [
  { accessorKey: 'supplierName', header: () => t('suppliers.name') },
  { accessorKey: 'productName', header: () => t('inventoryTransfers.products'), cell: ({ row }) => row.original.productName ?? '—' },
  { accessorKey: 'productSku', header: () => t('purchasing.productNumber'), cell: ({ row }) => row.original.productSku ?? '—' },
  { accessorKey: 'supplierSku', header: () => t('purchasing.supplierSku'), cell: ({ row }) => row.original.supplierSku ?? '—' },
  { id: 'cost', header: () => t('purchasing.unitCost'), cell: ({ row }) => row.original.unitCost === null ? '—' : `${row.original.unitCost.toFixed(2)} ${row.original.currency}` },
  { accessorKey: 'minimumOrderQuantity', header: () => t('purchasing.minimumOrderQuantity') },
  { id: 'purchaseUnit', header: () => t('purchasing.purchaseUnit'), cell: ({ row }) => `${unitLabel(row.original.purchaseUnit)} · ${row.original.stockUnitsPerPurchaseUnit} ${t('purchasing.stockUnits')}` },
  { accessorKey: 'leadTimeDays', header: () => t('purchasing.leadTime'), cell: ({ row }) => row.original.leadTimeDays ?? '—' },
  { id: 'priceTiers', header: () => t('purchasing.priceTiers'), cell: ({ row }) => row.original.prices.length },
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

  <UModal v-model:open="modalOpen" :title="editing ? t('purchasing.editOffer') : t('purchasing.addOffer')" :ui="{ content: 'sm:max-w-5xl' }">
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
            :model-value="editing ? form.productId : selectedProductIds"
            v-model:search-term="productSearch"
            :items="productItems"
            :disabled="!!editing"
            :multiple="!editing"
            :show-placeholder-when-selected="!editing"
            :has-more="productHasMore"
            :loading="productLoading"
            :load-more="loadProducts"
            :placeholder="t('purchasing.selectProduct')"
            :search-placeholder="t('products.searchProducts')"
            @update:model-value="onProductSelection"
          />
        </UFormField>
        <SupplierOfferFields
          v-if="editing"
          :model-value="form"
          :currencies="currencyItems"
          :units="unitItems"
        />
        <div v-else class="max-h-[55vh] space-y-4 overflow-y-auto pr-1">
          <div
            v-for="row in batchRows"
            :key="row.productId"
            class="rounded-lg border border-default p-4"
          >
            <div class="mb-3 flex items-center justify-between gap-2">
              <p class="font-medium text-highlighted">
                {{ row.productName }}
              </p>
              <UButton
                icon="i-lucide-x"
                color="neutral"
                variant="ghost"
                :aria-label="t('purchasing.removeItem')"
                @click="removeBatchRow(row.productId)"
              />
            </div>
            <SupplierOfferFields
              :model-value="row"
              :currencies="currencyItems"
              :units="unitItems"
              compact
            />
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <UButton :label="t('common.cancel')" color="neutral" variant="subtle" @click="closeModal" />
          <UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
</template>

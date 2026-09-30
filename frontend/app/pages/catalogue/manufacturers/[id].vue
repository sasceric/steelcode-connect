<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Row, SortingState } from '@tanstack/table-core'

type Translation = {
  name: string
  description: string | null
  metaTitle: string | null
  metaDescription: string | null
  metaKeywords: string | null
  seoUrl: string | null
  customFields: Record<string, unknown>
}
type Manufacturer = {
  id: string
  name: string
  seoUrl: string
  website: string | null
  mediaId: string | null
  translations: Record<string, Translation>
  productIds: string[]
}
type CustomField = {
  id: string
  technicalName: string
  type: string
  labels: Record<string, string>
  config: Record<string, unknown>
  options: { technicalValue: string, labels: Record<string, string> }[]
}
type CustomFieldSet = {
  id: string
  technicalName: string
  labels: Record<string, string>
  relations: string[]
  fields: CustomField[]
}
type ProductReference = {
  id: string
  name: string
  sku: string | null
}
type ProductPage = {
  products: ProductReference[]
  pagination: {
    page: number
    total: number
    hasMore: boolean
  }
}

const route = useRoute()
const { t } = useI18n()
const auth = useAuth()
const toast = useAppToast()
const saving = ref(false)
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const validation = useFormValidation()
const validationErrors = validation.errors
const tab = useRouteTab('general')
const selectedCustomFieldSet = ref('uncategorized')
const selectedProductRowSelection = ref<Record<string, boolean>>({})
const selectedProductSearch = ref('')
const debouncedSelectedProductSearch = ref('')
const productSearch = ref('')
const debouncedProductSearch = ref('')
const selectedProductSorting = ref<SortingState>([])
const selectedProductsPage = ref(1)
const selectedProductsPagination = reactive({ pageSize: 25 })
const persistedProductIds = ref<string[]>([])
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const state = reactive({
  name: '',
  description: '',
  seoUrl: '',
  metaTitle: '',
  metaDescription: '',
  metaKeywords: '',
  website: '',
  mediaId: '',
  productIds: [] as string[],
  customFields: {} as Record<string, unknown>
})

const { data, refresh } = await useAsyncData(`manufacturer-${route.params.id}`, () =>
  apiFetch<{ manufacturer: Manufacturer }>(`/manufacturers/${route.params.id}`)
)
const { data: localesData } = await useAsyncData('manufacturer-detail-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/products/locales')
)
const { data: productsData } = await useAsyncData('manufacturer-products', () =>
  apiFetch<ProductPage>('/products?view=options&limit=25&page=1')
)
const { data: customFieldSetsData } = await useAsyncData('manufacturer-custom-field-sets', () =>
  apiFetch<{ sets: CustomFieldSet[], unassignedFields: CustomField[] }>('/custom-field-sets')
)
const manufacturer = computed(() => data.value?.manufacturer)
const locales = computed(() => localesData.value?.locales ?? [])
const products = ref<ProductReference[]>([])
const nextProductsPage = ref(2)
const hasMoreProducts = ref(false)
const loadingMoreProducts = ref(false)
let productSearchDebounce: ReturnType<typeof setTimeout> | undefined
let selectedProductSearchDebounce: ReturnType<typeof setTimeout> | undefined

watch(
  productsData,
  (value) => {
    products.value = value?.products ?? []
    nextProductsPage.value = (value?.pagination.page ?? 0) + 1
    hasMoreProducts.value = value?.pagination.hasMore ?? false
  },
  { immediate: true }
)

const loadMoreProducts = async () => {
  if (!hasMoreProducts.value || loadingMoreProducts.value) {
    return
  }

  loadingMoreProducts.value = true
  try {
    const response = await apiFetch<ProductPage>(
      `/products?view=options&limit=25&page=${nextProductsPage.value}${
        debouncedProductSearch.value
          ? `&search=${encodeURIComponent(debouncedProductSearch.value)}`
          : ''
      }`
    )
    const productIds = new Set(products.value.map(product => product.id))

    products.value.push(
      ...response.products.filter(product => !productIds.has(product.id))
    )
    nextProductsPage.value = response.pagination.page + 1
    hasMoreProducts.value = response.pagination.hasMore
  } finally {
    loadingMoreProducts.value = false
  }
}
const productItems = computed(() =>
  products.value.map(product => ({
    label: product.name,
    productName: product.name,
    productNumber: product.sku,
    value: product.id
  }))
)
const selectedProductServerSorting = computed(() => {
  const sorting = selectedProductSorting.value[0]

  return ['name', 'sku'].includes(sorting?.id || '') ? sorting : undefined
})
const selectedProductsUrl = computed(() => {
  const params = new URLSearchParams({
    view: 'options',
    manufacturerId: String(route.params.id),
    page: String(selectedProductsPage.value),
    limit: String(selectedProductsPagination.pageSize)
  })

  if (debouncedSelectedProductSearch.value) {
    params.set('search', debouncedSelectedProductSearch.value)
  }
  if (selectedProductServerSorting.value) {
    params.set('sort', selectedProductServerSorting.value.id)
    params.set('direction', selectedProductServerSorting.value.desc ? 'DESC' : 'ASC')
  }

  return `/products?${params.toString()}`
})
const { data: selectedProductsData, refresh: refreshSelectedProducts } = await useAsyncData(
  `manufacturer-assigned-products-${route.params.id}`,
  () => apiFetch<ProductPage>(selectedProductsUrl.value)
)
const selectedProducts = computed(() => {
  const persistedIds = new Set(persistedProductIds.value)
  const addedProducts = products.value.filter(product =>
    state.productIds.includes(product.id) && !persistedIds.has(product.id)
  )

  return [
    ...(selectedProductsData.value?.products ?? []).filter(product => state.productIds.includes(product.id)),
    ...addedProducts
  ]
})
const selectedProductsTotal = computed(() =>
  debouncedSelectedProductSearch.value
    ? selectedProductsData.value?.pagination.total ?? 0
    : state.productIds.length
)
const selectedProductIdsForRemoval = computed(() =>
  Object.entries(selectedProductRowSelection.value)
    .filter(([, selected]) => selected)
    .map(([productId]) => productId)
)
const customFieldSets = computed(() =>
  (customFieldSetsData.value?.sets ?? []).filter(set => set.relations.includes('manufacturer'))
)
const customFieldTabs = computed(() => [
  ...customFieldSets.value.map(set => ({
    label: set.labels[selectedLocale.value] || set.labels[defaultLocale.value] || set.technicalName,
    value: set.id
  })),
  { label: t('productCustomFields.uncategorized'), value: 'uncategorized' }
])
const activeCustomFields = computed(() =>
  selectedCustomFieldSet.value === 'uncategorized'
    ? customFieldSetsData.value?.unassignedFields ?? []
    : customFieldSets.value.find(set => set.id === selectedCustomFieldSet.value)?.fields ?? []
)
const selectedProductColumns: TableColumn<ProductReference>[] = [
  {
    accessorKey: 'name',
    header: () => t('products.name')
  },
  {
    accessorKey: 'sku',
    header: () => t('products.productNumber'),
    cell: ({ row }) => row.original.sku || '—'
  },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'text-right' },
        h(
          UDropdownMenu,
          { items: getSelectedProductActions(row), content: { align: 'end' } },
          () =>
            h(UButton, {
              icon: 'i-lucide-ellipsis-vertical',
              color: 'neutral',
              variant: 'ghost',
              class: 'ml-auto'
            })
        )
      )
  }
]

const removeProducts = (productIds: string[]) => {
  const productIdsToRemove = new Set(productIds)

  state.productIds = state.productIds.filter(productId => !productIdsToRemove.has(productId))
  selectedProductRowSelection.value = {}
}

const removeSelectedProducts = () => {
  removeProducts(selectedProductIdsForRemoval.value)
}

const getSelectedProductActions = (row: Row<ProductReference>) => [
  [
    {
      label: t('manufacturers.removeProduct'),
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
      onSelect: () => removeProducts([row.original.id])
    }
  ]
]
const tabs = computed(() => [
  { label: t('products.general'), value: 'general' },
  { label: t('productSeo.title'), value: 'seo' },
  { label: t('productCustomFields.title'), value: 'custom-fields' },
  { label: t('categories.products'), value: 'products' }
])

const slugify = (value: string) =>
  value
    .toLocaleLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

const updateName = (value: string) => {
  state.name = value
  state.seoUrl = slugify(value)
  validation.clear('name')
}

const load = () => {
  const value = manufacturer.value
  if (!value) return
  const translation = value.translations[selectedLocale.value] || value.translations[defaultLocale.value]
  state.name = translation?.name || value.name
  state.description = translation?.description || ''
  state.seoUrl = translation?.seoUrl || value.seoUrl
  state.metaTitle = translation?.metaTitle || ''
  state.metaDescription = translation?.metaDescription || ''
  state.metaKeywords = translation?.metaKeywords || ''
  state.customFields = { ...(translation?.customFields ?? {}) }
  state.website = value.website || ''
  state.mediaId = value.mediaId || ''
  state.productIds = [...value.productIds]
  persistedProductIds.value = [...value.productIds]
  selectedProductRowSelection.value = {}
}

watch([manufacturer, selectedLocale], load, { immediate: true })
watch(productSearch, (value) => {
  clearTimeout(productSearchDebounce)
  productSearchDebounce = setTimeout(() => {
    debouncedProductSearch.value = value.trim()
  }, 300)
})
watch(debouncedProductSearch, async (search) => {
  const response = await apiFetch<ProductPage>(
    `/products?view=options&limit=25&page=1${
      search ? `&search=${encodeURIComponent(search)}` : ''
    }`
  )

  products.value = response.products
  nextProductsPage.value = response.pagination.page + 1
  hasMoreProducts.value = response.pagination.hasMore
})
watch(
  selectedProductSearch,
  (value) => {
    selectedProductsPage.value = 1
    clearTimeout(selectedProductSearchDebounce)
    selectedProductSearchDebounce = setTimeout(() => {
      debouncedSelectedProductSearch.value = value.trim()
    }, 300)
  }
)
watch(selectedProductSorting, () => {
  selectedProductsPage.value = 1
})
watch(
  () => selectedProductsPagination.pageSize,
  () => {
    selectedProductsPage.value = 1
  }
)
watch(selectedProductsUrl, () => {
  void refreshSelectedProducts()
})

const save = async () => {
  if (
    !validation.requireFields(
      [
        {
          field: 'name',
          value: state.name,
          label: t('manufacturers.name'),
          message: t('common.requiredField')
        }
      ],
      toast,
      t('common.error'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    await apiFetch(`/manufacturers/${route.params.id}`, {
      method: 'PATCH',
      body: { website: state.website, mediaId: state.mediaId || null }
    })
    await apiFetch(`/manufacturers/${route.params.id}/translations/${selectedLocale.value}`, {
      method: 'PUT',
      body: {
        name: state.name,
        description: state.description,
        seoUrl: state.seoUrl,
        metaTitle: state.metaTitle,
        metaDescription: state.metaDescription,
        metaKeywords: state.metaKeywords,
        customFields: state.customFields
      }
    })
    await apiFetch(`/manufacturers/${route.params.id}/products`, {
      method: 'PUT',
      body: { productIds: state.productIds }
    })
    await refresh()
    await refreshSelectedProducts()
    toast.success(t('common.saved'), t('common.changesSaved'))
  } catch (error: unknown) {
    validation.notifyApiError(error, toast, t('common.error'), t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="manufacturer" class="space-y-6">
    <DetailPageHeader :title="state.name || manufacturer.name" :subtitle="state.seoUrl" back-to="/catalogue/manufacturers">
      <template #actions>
        <LocaleSelect
          v-model="selectedLocale"
          :options="locales"
          class="w-full sm:w-56"
        />
        <UButton :label="t('common.save')" :loading="saving" @click="save" />
      </template>
    </DetailPageHeader>

    <UTabs v-model="tab" :items="tabs" />

    <UPageCard v-if="tab === 'general'" :title="t('manufacturers.general')">
      <div class="grid gap-6 sm:grid-cols-2">
        <UFormField
          :label="t('manufacturers.name')"
          :error="validationErrors.name"
          required
        >
          <UInput
            :model-value="state.name"
            class="w-full"
            @update:model-value="updateName"
          />
        </UFormField>
        <UFormField :label="t('common.website')">
          <UInput v-model="state.website" type="url" class="w-full" />
        </UFormField>
        <UFormField :label="t('manufacturers.logo')" class="sm:col-span-2">
          <MediaSelectionField v-model="state.mediaId" />
        </UFormField>
      </div>
      <UFormField :label="t('products.description')" class="mt-6">
        <RichTextEditor v-model="state.description" />
      </UFormField>
    </UPageCard>
    <UPageCard v-else-if="tab === 'seo'" :title="t('productSeo.title')">
      <div class="grid gap-6 sm:grid-cols-2">
        <UFormField :label="t('productSeo.url')">
          <UInput v-model="state.seoUrl" class="w-full" />
        </UFormField>
        <UFormField :label="t('productSeo.metaTitle')">
          <UInput v-model="state.metaTitle" class="w-full" />
        </UFormField>
      </div>
      <UFormField :label="t('productSeo.metaDescription')" class="mt-6">
        <UTextarea v-model="state.metaDescription" class="w-full" :rows="4" />
      </UFormField>
      <UFormField :label="t('productSeo.keywords')" class="mt-6">
        <UInput v-model="state.metaKeywords" class="w-full" />
      </UFormField>
    </UPageCard>
    <template v-else-if="tab === 'custom-fields'">
      <div class="overflow-x-auto border-b border-default">
        <UTabs
          v-model="selectedCustomFieldSet"
          :items="customFieldTabs"
          :content="false"
          class="min-w-max"
        />
      </div>
      <UPageCard :title="t('productCustomFields.title')">
        <div v-if="!activeCustomFields.length" class="py-10 text-center text-muted">
          {{ t('productCustomFields.emptySet') }}
        </div>
        <CustomFieldValuesForm
          v-else
          v-model="state.customFields"
          :fields="activeCustomFields"
          :locale="selectedLocale"
          :fallback-locale="defaultLocale"
          :date-placeholder="t('productExtra.releaseDatePlaceholder')"
        />
      </UPageCard>
    </template>
    <UPageCard v-else :title="t('manufacturers.products')">
      <SearchableSelect
        v-model="state.productIds"
        v-model:search-term="productSearch"
        :items="productItems"
        :placeholder="t('manufacturers.selectProducts')"
        :search-placeholder="t('manufacturers.searchProducts')"
        :has-more="hasMoreProducts"
        :loading="loadingMoreProducts"
        :load-more="loadMoreProducts"
        multiple
        show-placeholder-when-selected
        class="w-full"
      />

      <div class="mt-6 space-y-4">
        <div v-if="!state.productIds.length" class="py-16 text-center">
          <UIcon name="i-lucide-package-search" class="mx-auto size-10 text-primary" />
          <p class="mt-4 font-medium text-highlighted">
            {{ t('manufacturers.noProductsSelected') }}
          </p>
          <p class="mx-auto mt-1 max-w-md text-sm text-muted">
            {{ t('manufacturers.noProductsSelectedDescription') }}
          </p>
        </div>
        <template v-else>
          <AppDataTable
            v-model:row-selection="selectedProductRowSelection"
            v-model:sorting="selectedProductSorting"
            :data="selectedProducts"
            :columns="selectedProductColumns"
            :get-row-id="product => product.id"
            :max-height="null"
            server-sorting
            selectable
            table-key="manufacturer-products"
            :column-labels="{
              name: t('products.name'),
              sku: t('products.productNumber')
            }"
          >
            <template #header>
              <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                  <p class="shrink-0 text-sm font-medium text-highlighted">
                    {{ t('manufacturers.products') }} ({{ selectedProductsTotal }})
                  </p>
                  <UInput
                    v-model="selectedProductSearch"
                    :placeholder="t('manufacturers.searchProducts')"
                    icon="i-lucide-search"
                    class="w-56"
                  />
                </div>
                <UButton
                  v-if="selectedProductIdsForRemoval.length"
                  :label="t('manufacturers.removeSelectedProducts', { count: selectedProductIdsForRemoval.length })"
                  color="error"
                  variant="soft"
                  icon="i-lucide-trash-2"
                  @click="removeSelectedProducts"
                />
              </div>
            </template>

            <template #footer>
              <TablePaginationFooter
                v-model:page="selectedProductsPage"
                v-model:page-size="selectedProductsPagination.pageSize"
                :total="selectedProductsTotal"
                class="border-t-0 pt-0"
              />
            </template>
          </AppDataTable>
        </template>
      </div>
    </UPageCard>
  </div>
</template>

<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'
import { CalendarDate } from '@internationalized/date'

type Product = {
  id: string
  parentId?: string | null
  name: string
  seoUrl: string
  sku?: string | null
  ean?: string | null
  shortDescription: string | null
  description: string | null
  status: 'draft' | 'active' | 'archived'
  productType?: string
  manufacturerNumber?: string | null
  packUnit?: string | null
  packUnitPlural?: string | null
  shippingClass?: string | null
  deliveryTime?: string | null
  releaseDate?: string | null
  isFeatured?: boolean
  minPurchaseQuantity?: string
  purchaseSteps?: string
  maxPurchaseQuantity?: string | null
  restockTimeDays?: number | null
  clearanceSale?: boolean
  freeShipping?: boolean
  searchKeywords?: string | null
  weightGrams?: number | null
  lengthMillimeters?: number | null
  widthMillimeters?: number | null
  heightMillimeters?: number | null
  regularPrice?: {
    currency: string | null
    taxRate: string | null
    grossAmount: number | null
    netAmount: number | null
    purchaseGrossAmount: number | null
    purchaseNetAmount: number | null
    listGrossAmount: number | null
    listNetAmount: number | null
    linked?: boolean
    purchaseLinked?: boolean
    listLinked?: boolean
  }
}
type Variant = {
  id: string
  sku: string | null
  ean: string | null
  name: string | null
  price: number | null
  currency: string | null
  optionValues: Record<string, string>
  status: string
}
type OptionGroup = {
  id: string
  name: string
  values: { id: string, value: string }[]
}
type Locale = { id: string, code: string, label: string }
type Price = {
  id: string
  currency: string
  currencySymbol: string
  priceType: string
  netAmount: number
  grossAmount: number
  taxRate: string
  quantityStart: string
  quantityEnd: string | null
  pricingContext: string
  listNetAmount: number | null
  listGrossAmount: number | null
  cheapestNetAmount: number | null
  cheapestGrossAmount: number | null
  validFrom: string | null
  validUntil: string | null
}
type Currency = { code: string, symbol: string, decimalPrecision: number }
type ProductMedia = {
  id: string
  type: 'image'
  url: string
  fileName: string
  fileExtension: string | null
  fileSize: number | null
  mimeType: string | null
  altText: string | null
  position: number
  createdAt: string
}
type ProductDownload = {
  id: string
  title: string | null
  position: number
  fileName: string
  mimeType: string | null
  url: string
}
type ProductCrossSelling = {
  id: string
  name: string
  type: string
  active: boolean
  position: number
  sourceProductStreamId: string | null
  translations: Record<string, string>
  products: {
    id: string
    name: string
    position: number
  }[]
}
type CatalogueProperty = {
  id: string
  name: string
  code: string
  colorHex: string | null
  position: number
}
type CataloguePropertyGroup = {
  id: string
  name: string
  code: string
  displayType: 'text' | 'color' | 'image'
  isFilterable: boolean
  position: number
  properties: CatalogueProperty[]
}
type ProductPropertyRow = {
  id: string
  name: string
  properties: CatalogueProperty[]
}
type PropertyGroupTranslation = {
  name: string | null
  properties: { id: string, name: string | null }[]
}
type InventoryLevel = {
  warehouseId: string
  warehouse: string
  active: boolean
  fulfillmentEnabled: boolean
  priority: number
  stock: number
  reservedStock: number
  unavailableStock: number
  incomingStock: number
  availableStock: number
}
type ProductInventory = {
  stock: number
  availableStock: number
  unavailableStock: number
  incomingStock: number
  levels: InventoryLevel[]
}
type Warehouse = {
  id: string
  code: string
  name: string
  active: boolean
  default: boolean
}
type InventoryMovement = {
  id: string
  warehouse: string
  type: string
  quantityDelta: number
  quantityAfter: number
  note: string | null
  createdAt: string
}
type Tag = { id: string, name: string }
type CustomFieldOption = {
  id: string
  technicalValue: string
  labels: Record<string, string>
  position: number
}
type CustomField = {
  id: string
  technicalName: string
  type:
    | 'text'
    | 'editor'
    | 'number'
    | 'date'
    | 'checkbox'
    | 'switch'
    | 'select'
    | 'entity'
    | 'media'
    | 'color'
    | 'price'
  labels: Record<string, string>
  config: Record<string, any>
  position: number
  options: CustomFieldOption[]
}
type CustomFieldSet = {
  id: string
  technicalName: string
  labels: Record<string, string>
  relations: string[]
  position: number
  fields: CustomField[]
}
type ProductSalesChannel = {
  id: string
  name: string
  connectionName: string
  connectorKey: string
  visibility: number
}
type CatalogueReferences = {
  taxes: { id: string, name: string, rate: string }[]
  units: { id: string, code: string, symbol: string, labels: Record<string, string> }[]
  deliveryTimes: {
    id: string
    labels: Record<string, string>
    min: number
    max: number
    unit: string
  }[]
}
type ProductReferences = {
  taxId: string | null
  manufacturerId: string | null
  unitId: string | null
  purchaseUnit: string | null
  referenceUnit: string | null
  deliveryTimeId: string | null
}
type ProductCategory = {
  id: string
  parentId: string | null
  name: string
  position: number
  translations: Record<string, { name: string }>
}

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const notify = useAppToast()
const selectedTab = useRouteTab('general')
const saving = ref(false)
const productValidation = useFormValidation()
const validationErrors = productValidation.errors
const stockValidation = useFormValidation()
const stockValidationErrors = stockValidation.errors
const priceValidation = useFormValidation()
const priceValidationErrors = priceValidation.errors
const optionOpen = ref(false)
const activeVariantGroupId = ref('')
const variantSearch = ref('')
const variantsTableSearch = ref('')
const debouncedVariantsTableSearch = ref('')
const variantSorting = ref<SortingState>([])
const variantsPage = ref(1)
const variantsPagination = reactive({ pageSize: 25 })
const variantRowSelection = ref<Record<string, boolean>>({})
const variantIdsToDelete = ref<string[]>([])
const variantsDeleting = ref(false)
const stockAdjustmentOpen = ref(false)
const stockSaving = ref(false)
const stockForm = reactive({ warehouseId: '', quantity: '0', note: '' })
const movementsPage = ref(1)
const movementsPagination = reactive({ pageSize: 25 })
const tagInput = ref('')
const productTags = ref<Tag[]>([])
const keywordInput = ref('')
const productKeywords = ref<string[]>([])
const optionSaving = ref(false)
const generating = ref(false)
const priceOpen = ref(false)
const priceSaving = ref(false)
const mediaUploading = ref(false)
const mediaUpdating = ref(false)
const mediaDeleting = ref<string | null>(null)
const mediaInput = ref<HTMLInputElement | null>(null)
const duplicateMediaOpen = ref(false)
const duplicateMediaNames = ref<string[]>([])
const pendingMediaFiles = ref<File[]>([])
let variantsSearchDebounce: ReturnType<typeof setTimeout> | undefined
const newAdvancedRow = () => ({
  from: '1',
  to: '',
  gross: '',
  net: '',
  listGross: '',
  listNet: '',
  cheapestGross: '',
  cheapestNet: '',
  priceChained: true,
  listChained: true,
  cheapestChained: true
})
type AdvancedRow = ReturnType<typeof newAdvancedRow>
type AdvancedPriceRule = { id: number, rows: AdvancedRow[] }
const advancedRules = ref<AdvancedPriceRule[]>([])
const auth = useAuth()
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const selectedLocale = ref(defaultLocale.value)
const selectedPropertyIds = ref<string[]>([])
const specificationsOpen = ref(false)
const specificationsSaving = ref(false)
const activeSpecificationGroupId = ref('')
const specificationSearch = ref('')
const specificationPropertyIds = ref<string[]>([])
const addedPropertiesSearch = ref('')
const propertyRowSelection = ref<Record<string, boolean>>({})
const propertyIdsToRemove = ref<string[]>([])
const variantPropertyIds = ref<Record<string, string[]>>({})
const customFieldValues = ref<Record<string, any>>({})
const selectedCustomFieldSet = ref('uncategorized')
const salesChannelVisibility = ref<Record<string, string>>({})
const categoryPickerOpen = ref(false)
const selectedCategoryIds = ref<string[]>([])
const { data, status, refresh } = await useAsyncData(`product-${route.params.id}`, () =>
  apiFetch<{ product: Product }>(`/products/${route.params.id}`)
)
const product = computed(() => data.value?.product)
const isVariant = computed(() => Boolean(product.value?.parentId))
const state = reactive({
  name: '',
  sku: '',
  shortDescription: '',
  description: '',
  seoUrl: '',
  metaTitle: '',
  metaDescription: '',
  metaKeywords: '',
  status: 'draft' as Product['status'],
  productType: 'physical',
  manufacturerNumber: '',
  manufacturerId: '',
  taxId: '',
  unitId: '',
  purchaseUnit: '',
  referenceUnit: '',
  packUnit: '',
  packUnitPlural: '',
  shippingClass: '',
  deliveryTime: '',
  deliveryTimeId: '',
  releaseDate: '',
  isFeatured: false,
  minPurchaseQuantity: '1',
  purchaseSteps: '1',
  maxPurchaseQuantity: '',
  restockTimeDays: '',
  clearanceSale: false,
  freeShipping: false,
  searchKeywords: '',
  weightGrams: '',
  lengthMillimeters: '',
  widthMillimeters: '',
  heightMillimeters: ''
})

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
  productValidation.clear('name')
}

const { data: localesData } = await useAsyncData('product-locales', () =>
  apiFetch<{ locales: Locale[] }>('/products/locales')
)
const { data: catalogueReferencesData } = await useAsyncData('catalogue-references', () =>
  apiFetch<CatalogueReferences>('/catalogue/references')
)
const { data: manufacturersData } = await useAsyncData('product-manufacturers', () =>
  apiFetch<{ manufacturers: { id: string, name: string, translations: Record<string, { name: string }> }[] }>('/manufacturers')
)
const { data: productReferencesData } = await useAsyncData(
  `product-references-${route.params.id}`,
  () => apiFetch<ProductReferences>(`/products/${route.params.id}/references`)
)
const catalogueReferences = computed(() => catalogueReferencesData.value)
const manufacturers = computed(() => manufacturersData.value?.manufacturers ?? [])
const manufacturerItems = computed(() =>
  manufacturers.value.map(manufacturer => ({
    label:
      manufacturer.translations[selectedLocale.value]?.name
      || manufacturer.translations[defaultLocale.value]?.name
      || manufacturer.name,
    value: manufacturer.id
  }))
)
const locales = computed(() => localesData.value?.locales ?? [])
const optionForm = reactive({ name: '', values: '' })
const priceForm = reactive({
  currency: 'BAM',
  netAmount: '',
  grossAmount: '',
  taxRate: '17',
  quantityStart: '1',
  quantityEnd: '',
  listNetAmount: '',
  listGrossAmount: '',
  validFrom: '',
  validUntil: ''
})
const regularPrice = reactive({
  currency: 'BAM',
  taxRate: '17',
  sellingId: '',
  sellingGross: '',
  sellingNet: '',
  purchaseId: '',
  purchaseGross: '',
  purchaseNet: '',
  listId: '',
  listGross: '',
  listNet: '',
  sellingChained: true,
  purchaseChained: true,
  listChained: true,
  cheapestChained: true
})
const variantServerSorting = computed(() => {
  const sorting = variantSorting.value[0]

  return ['name', 'sku', 'status'].includes(sorting?.id || '') ? sorting : undefined
})
const variantsUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(variantsPage.value),
    limit: String(variantsPagination.pageSize)
  })

  if (debouncedVariantsTableSearch.value) {
    params.set('search', debouncedVariantsTableSearch.value)
  }
  if (variantServerSorting.value) {
    params.set('sort', variantServerSorting.value.id)
    params.set('direction', variantServerSorting.value.desc ? 'DESC' : 'ASC')
  }

  return `/products/${route.params.id}/variants?${params.toString()}`
})
const {
  data: variantsData,
  status: variantsStatus,
  refresh: refreshVariants
} = await useAsyncData(`product-variants-${route.params.id}`, () =>
  apiFetch<{
    variants: Variant[]
    pagination: { total: number }
  }>(variantsUrl.value),
  { immediate: false }
)
const variants = computed(() => variantsData.value?.variants ?? [])
const variantsTotal = computed(() => variantsData.value?.pagination.total ?? 0)
const { data: optionGroupsData, refresh: refreshOptionGroups } = await useAsyncData(
  `product-options-${route.params.id}`,
  () => apiFetch<{ optionGroups: OptionGroup[] }>(`/products/${route.params.id}/option-groups`)
)
const optionGroups = computed(() => optionGroupsData.value?.optionGroups ?? [])
const { data: customFieldSetsData } = await useAsyncData('product-custom-field-sets', () =>
  apiFetch<{ sets: CustomFieldSet[], unassignedFields: CustomField[] }>('/custom-field-sets')
)
const customFieldSets = computed(() =>
  (customFieldSetsData.value?.sets ?? []).filter(set => set.relations.includes('product'))
)
const customFieldSetTabs = computed(() => [
  ...customFieldSets.value.map(set => ({
    label: setLabel(set),
    value: set.id
  })),
  { label: t('productCustomFields.uncategorized'), value: 'uncategorized' }
])
const activeCustomFieldSet = computed(() =>
  customFieldSets.value.find(set => set.id === selectedCustomFieldSet.value)
)
const unassignedCustomFields = computed(() => customFieldSetsData.value?.unassignedFields ?? [])
const activeCustomFields = computed(() =>
  selectedCustomFieldSet.value === 'uncategorized'
    ? unassignedCustomFields.value
    : (activeCustomFieldSet.value?.fields ?? [])
)
const {
  data: productSalesChannelsData,
  refresh: refreshProductSalesChannels
} = await useAsyncData(`product-sales-channels-${route.params.id}`, () =>
  apiFetch<{ channels: ProductSalesChannel[] }>(
    `/products/${route.params.id}/channel-publications`
  )
)
const productSalesChannels = computed(() => productSalesChannelsData.value?.channels ?? [])
const salesChannelVisibilityOptions = computed(() => [
  { label: t('productSalesChannels.hidden'), value: '0' },
  { label: t('productSalesChannels.link'), value: '10' },
  { label: t('productSalesChannels.search'), value: '20' },
  { label: t('productSalesChannels.all'), value: '30' }
])
const setLabel = (set: CustomFieldSet) =>
  set.labels[selectedLocale.value] || set.labels[defaultLocale.value] || set.technicalName
const crossSellingLabel = (group: ProductCrossSelling) =>
  group.translations[selectedLocale.value]
  || group.translations[defaultLocale.value]
  || group.name
const {
  data: pricesData,
  status: pricesStatus,
  refresh: refreshPrices
} = await useAsyncData(`product-prices-${route.params.id}`, () =>
  apiFetch<{ prices: Price[] }>(`/products/${route.params.id}/prices`),
  { immediate: false }
)
const prices = computed(() => pricesData.value?.prices ?? [])
const {
  data: mediaData,
  status: mediaStatus,
  refresh: refreshMedia
} = await useAsyncData(`product-media-${route.params.id}`, () =>
  apiFetch<{ media: ProductMedia[] }>(`/products/${route.params.id}/media`),
  { immediate: false }
)
const media = computed(() => mediaData.value?.media ?? [])
const {
  data: downloadsData,
  status: downloadsStatus,
  refresh: refreshDownloads
} = await useAsyncData(`product-downloads-${route.params.id}`, () =>
  apiFetch<{ downloads: ProductDownload[] }>(`/products/${route.params.id}/downloads`),
  { immediate: false }
)
const downloads = computed(() => downloadsData.value?.downloads ?? [])
const {
  data: crossSellingsData,
  status: crossSellingsStatus,
  refresh: refreshCrossSellings
} = await useAsyncData(`product-cross-sellings-${route.params.id}`, () =>
  apiFetch<{ crossSellings: ProductCrossSelling[] }>(
    `/products/${route.params.id}/cross-sellings`
  ),
  { immediate: false }
)
const crossSellings = computed(() => crossSellingsData.value?.crossSellings ?? [])
const { data: tagsData, refresh: refreshTags } = await useAsyncData(
  `product-tags-${route.params.id}`,
  () => apiFetch<{ tags: Tag[] }>(`/products/${route.params.id}/tags`)
)
const { data: inventoryData, refresh: refreshInventory } = await useAsyncData(
  `product-inventory-${route.params.id}`,
  () => apiFetch<ProductInventory>(`/inventory/products/${route.params.id}`)
)
const inventory = computed(() =>
  inventoryData.value ?? {
    stock: 0,
    availableStock: 0,
    unavailableStock: 0,
    incomingStock: 0,
    levels: []
  }
)
const movementsUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(movementsPage.value),
    limit: String(movementsPagination.pageSize)
  })

  return `/inventory/products/${route.params.id}/movements?${params.toString()}`
})
const { data: inventoryMovementsData, refresh: refreshInventoryMovements } = await useAsyncData(
  `product-inventory-movements-${route.params.id}`,
  () =>
    apiFetch<{
      movements: InventoryMovement[]
      pagination: { total: number }
    }>(
      movementsUrl.value
    ),
  { immediate: false }
)
const inventoryMovements = computed(() => inventoryMovementsData.value?.movements ?? [])
const movementsTotal = computed(() => inventoryMovementsData.value?.pagination.total ?? 0)
const { data: warehousesData } = await useAsyncData('inventory-warehouses', () =>
  apiFetch<{ warehouses: Warehouse[] }>('/inventory/warehouses')
)
const warehouses = computed(
  () => warehousesData.value?.warehouses?.filter(warehouse => warehouse.active) ?? []
)
const { data: propertyGroupsData } = await useAsyncData('catalogue-property-groups', () =>
  apiFetch<{ propertyGroups: CataloguePropertyGroup[] }>('/property-groups')
)
const propertyGroups = computed(() => propertyGroupsData.value?.propertyGroups ?? [])
const propertyGroupTranslations = ref<Record<string, PropertyGroupTranslation>>({})
const localizedPropertyGroups = computed(() =>
  propertyGroups.value.map((group) => {
    if (selectedLocale.value === defaultLocale.value) return group
    const translation = propertyGroupTranslations.value[group.id]
    if (!translation) return group
    const names = new Map(translation.properties.map(property => [property.id, property.name]))
    return {
      ...group,
      name: translation.name || group.name,
      properties: group.properties.map(property => ({
        ...property,
        name: names.get(property.id) || property.name
      }))
    }
  })
)
const { data: selectedPropertiesData, refresh: refreshSelectedProperties } = await useAsyncData(
  `product-properties-${route.params.id}`,
  () => apiFetch<{ propertyIds: string[] }>(`/products/${route.params.id}/properties`)
)
const { data: categoriesData } = await useAsyncData('product-category-tree', () =>
  apiFetch<{ categories: ProductCategory[] }>('/categories')
)
const { data: selectedCategoriesData, refresh: refreshSelectedCategories } = await useAsyncData(
  `product-categories-${route.params.id}`,
  () => apiFetch<{ categoryIds: string[] }>(`/products/${route.params.id}/categories`)
)
const selectedCategoryLabels = computed(() => {
  const names = new Map(
    (categoriesData.value?.categories ?? []).map(category => [
      category.id,
      category.translations[selectedLocale.value]?.name
      || category.translations[defaultLocale.value]?.name
      || category.name
    ])
  )
  return selectedCategoryIds.value.map(id => ({ id, name: names.get(id) || id }))
})
watch(
  selectedCategoriesData,
  (value) => {
    selectedCategoryIds.value = value?.categoryIds ?? []
  },
  { immediate: true }
)
const { data: variantOptionsData, refresh: refreshVariantOptions } = await useAsyncData(
  `product-variant-options-${route.params.id}`,
  () =>
    apiFetch<{
      optionGroups: { propertyGroupId: string, propertyIds: string[] }[]
    }>(`/products/${route.params.id}/variant-options`)
)
const { data: currenciesData } = await useAsyncData('product-currencies', () =>
  apiFetch<{ currencies: Currency[] }>('/products/currencies')
)
const currencies = computed(() =>
  currenciesData.value?.currencies?.length
    ? currenciesData.value.currencies
    : [{ code: 'BAM', symbol: 'KM', decimalPrecision: 2 }]
)
const combinations = computed(() => {
  const selected = Object.values(variantPropertyIds.value).filter(ids => ids.length)
  return selected.length ? selected.reduce((count, ids) => count * ids.length, 1) : 0
})
const activeVariantGroup = computed(
  () =>
    localizedPropertyGroups.value.find(group => group.id === activeVariantGroupId.value)
    || localizedPropertyGroups.value[0]
)
const visibleVariantProperties = computed(() =>
  (activeVariantGroup.value?.properties ?? []).filter(property =>
    property.name.toLowerCase().includes(variantSearch.value.toLowerCase())
  )
)
const activeSpecificationGroup = computed(
  () =>
    localizedPropertyGroups.value.find(group => group.id === activeSpecificationGroupId.value)
    || localizedPropertyGroups.value[0]
)
const visibleSpecificationProperties = computed(() => {
  const query = specificationSearch.value.trim().toLocaleLowerCase()
  return (activeSpecificationGroup.value?.properties ?? []).filter(
    property => !query || property.name.toLocaleLowerCase().includes(query)
  )
})
const selectedSpecificationGroups = computed(() =>
  localizedPropertyGroups.value
    .map(group => ({
      ...group,
      properties: group.properties.filter(property =>
        selectedPropertyIds.value.includes(property.id)
      )
    }))
    .filter(group => group.properties.length)
)
const releaseCalendar = computed<CalendarDate | undefined>({
  get: () => {
    const date = state.releaseDate.split('T')[0]
    if (!date) return undefined
    const [year = 0, month = 0, day = 0] = date.split('-').map(Number)
    return new CalendarDate(year, month, day)
  },
  set: (value) => {
    if (!value) {
      state.releaseDate = ''
      return
    }
    state.releaseDate = `${value.year.toString().padStart(4, '0')}-${value.month.toString().padStart(2, '0')}-${value.day.toString().padStart(2, '0')}T${releaseTime.value || '00:00'}`
  }
})
const releaseTime = computed({
  get: () => state.releaseDate.split('T')[1] || '',
  set: (value: string) => {
    if (releaseCalendar.value) state.releaseDate = `${state.releaseDate.split('T')[0]}T${value}`
  }
})
const releaseDateLabel = computed(() =>
  state.releaseDate
    ? new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short'
      }).format(new Date(state.releaseDate))
    : t('productExtra.releaseDatePlaceholder')
)
const filteredProductPropertyRows = computed<ProductPropertyRow[]>(() => {
  const query = addedPropertiesSearch.value.trim().toLocaleLowerCase()
  return selectedSpecificationGroups.value.filter(
    group =>
      !query
      || group.name.toLocaleLowerCase().includes(query)
      || group.properties.some(property => property.name.toLocaleLowerCase().includes(query))
  )
})
const {
  page: propertiesPage,
  paginatedItems: paginatedProperties,
  pagination: propertiesPagination,
  reset: resetPropertiesPagination,
  total: propertiesTotal
} = useClientPagination(() => filteredProductPropertyRows.value)
const UBadge = resolveComponent('UBadge')
const UButton = resolveComponent('UButton')
const UCheckbox = resolveComponent('UCheckbox')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const productPropertyColumns: TableColumn<ProductPropertyRow>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(UCheckbox, {
        'modelValue': table.getIsSomePageRowsSelected()
          ? 'indeterminate'
          : table.getIsAllPageRowsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(!!value),
        'ariaLabel': t('productProperties.selectAll')
      }),
    cell: ({ row }) =>
      h(UCheckbox, {
        'modelValue': row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'ariaLabel': t('productProperties.selectRow')
      })
  },
  {
    accessorKey: 'name',
    header: () => t('productProperties.property'),
    cell: ({ row }) => h('span', { class: 'font-medium text-highlighted' }, row.original.name)
  },
  {
    id: 'values',
    header: () => t('productProperties.propertyValues'),
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex flex-wrap gap-2' },
        row.original.properties.map(property =>
          h(UBadge, { color: 'neutral', variant: 'subtle', class: 'gap-1.5' }, () => [
            property.colorHex
              ? h('span', {
                  class: 'size-3 rounded-full border border-default',
                  style: { backgroundColor: property.colorHex }
                })
              : null,
            property.name
          ])
        )
      )
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex justify-end' },
        h(
          UDropdownMenu,
          {
            content: { align: 'end' },
            items: [
              [
                {
                  label: t('common.delete'),
                  icon: 'i-lucide-trash-2',
                  color: 'error' as const,
                  onSelect: () => {
                    propertyIdsToRemove.value = row.original.properties.map(
                      property => property.id
                    )
                  }
                }
              ]
            ]
          },
          () =>
            h(UButton, {
              icon: 'i-lucide-ellipsis-vertical',
              color: 'neutral',
              variant: 'ghost',
              ariaLabel: t('common.edit')
            })
        )
      )
  }
]

const variantColumns: TableColumn<Variant>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(UCheckbox, {
        'modelValue': table.getIsSomePageRowsSelected()
          ? 'indeterminate'
          : table.getIsAllPageRowsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
          table.toggleAllPageRowsSelected(!!value),
        'ariaLabel': t('productProperties.selectAll')
      }),
    cell: ({ row }) =>
      h(UCheckbox, {
        'modelValue': row.getIsSelected(),
        'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
        'ariaLabel': t('productProperties.selectRow')
      })
  },
  {
    accessorKey: 'name',
    header: () => t('products.variantName'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer text-left font-medium text-highlighted hover:text-primary',
          onClick: () => navigateTo(`/catalogue/products/${row.original.id}`)
        },
        row.original.name || row.original.sku || '—'
      )
  },
  {
    accessorKey: 'sku',
    header: () => t('products.productNumber'),
    cell: ({ row }) => row.original.sku || '—'
  },
  {
    accessorKey: 'price',
    header: () => t('products.price'),
    cell: ({ row }) =>
      row.original.price === null ? '—' : money(row.original.price, row.original.currency || 'BAM')
  },
  {
    id: 'actions',
    header: '',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'flex justify-end' },
        h(
          UDropdownMenu,
          {
            content: { align: 'end' },
            items: [
              [
                {
                  label: t('common.edit'),
                  icon: 'i-lucide-pencil',
                  onSelect: () => navigateTo(`/catalogue/products/${row.original.id}`)
                }
              ],
              [
                {
                  label: t('common.delete'),
                  icon: 'i-lucide-trash-2',
                  color: 'error' as const,
                  onSelect: () => {
                    variantIdsToDelete.value = [row.original.id]
                  }
                }
              ]
            ]
          },
          () =>
            h(UButton, {
              icon: 'i-lucide-ellipsis-vertical',
              color: 'neutral',
              variant: 'ghost',
              ariaLabel: t('common.edit')
            })
        )
      )
  }
]
const inventoryMovementColumns: TableColumn<InventoryMovement>[] = [
  {
    accessorKey: 'createdAt',
    header: () => t('inventoryMovements.date'),
    cell: ({ row }) =>
      new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short'
      }).format(new Date(row.original.createdAt))
  },
  { accessorKey: 'warehouse', header: () => t('inventory.warehouse') },
  {
    accessorKey: 'quantityDelta',
    header: () => t('inventoryMovements.change'),
    cell: ({ row }) => `${row.original.quantityDelta > 0 ? '+' : ''}${row.original.quantityDelta}`
  },
  { accessorKey: 'quantityAfter', header: () => t('inventory.stock') },
  {
    accessorKey: 'note',
    header: () => t('inventory.note'),
    cell: ({ row }) => row.original.note || '—'
  }
]
const inventoryLevelColumns: TableColumn<InventoryLevel>[] = [
  {
    accessorKey: 'warehouse',
    header: () => t('inventory.warehouse'),
    cell: ({ row }) => h('span', { class: 'font-medium text-highlighted' }, row.original.warehouse)
  },
  { accessorKey: 'stock', header: () => t('inventory.stock') },
  { accessorKey: 'reservedStock', header: () => t('inventory.reservedStock') },
  { accessorKey: 'unavailableStock', header: () => t('inventory.unavailableStock') },
  { accessorKey: 'availableStock', header: () => t('inventory.availableStock') },
  { accessorKey: 'incomingStock', header: () => t('inventory.incomingStock') }
]
const priceColumns: TableColumn<Price>[] = [
  {
    accessorKey: 'quantityStart',
    header: () => t('productAdvancedPrice.quantity'),
    cell: ({ row }) =>
      row.original.quantityEnd
        ? `${row.original.quantityStart}–${row.original.quantityEnd}`
        : `${row.original.quantityStart}+`
  },
  {
    accessorKey: 'grossAmount',
    header: () => t('productAdvancedPrice.price'),
    cell: ({ row }) => money(row.original.grossAmount, row.original.currency)
  },
  {
    accessorKey: 'listGrossAmount',
    header: () => t('productAdvancedPrice.listPrice'),
    cell: ({ row }) =>
      row.original.listGrossAmount === null
        ? '—'
        : money(row.original.listGrossAmount, row.original.currency)
  },
  {
    accessorKey: 'validFrom',
    header: () => t('productAdvancedPrice.validity'),
    cell: ({ row }) =>
      row.original.validFrom || row.original.validUntil
        ? `${row.original.validFrom?.slice(0, 10) || '—'} – ${row.original.validUntil?.slice(0, 10) || '—'}`
        : '—'
  }
]

const money = (amount: number, currency: string) =>
  new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount / 100)
const advancedCurrency = computed(() => regularPrice.currency || 'BAM')

async function loadTranslation() {
  const locale = selectedLocale.value
  const response = await apiFetch<{
    translation: (Partial<Product> & { customFields?: Record<string, unknown> }) | null
  }>(`/products/${route.params.id}/translations/${locale}`)
  if (locale !== selectedLocale.value) return
  if (response.translation) {
    Object.assign(state, response.translation)
    customFieldValues.value = { ...(response.translation.customFields || {}) }
  } else if (product.value) {
    Object.assign(state, {
      name: product.value.name,
      shortDescription: product.value.shortDescription || '',
      description: product.value.description || ''
    })
    customFieldValues.value = {}
  }
}

watch(
  product,
  (value) => {
    if (!value) return
    if (selectedLocale.value === defaultLocale.value) {
      state.name = value.name
      state.shortDescription = value.shortDescription || ''
      state.description = value.description || ''
    }
    state.status = value.status
    state.sku = value.sku || ''
    state.productType = value.productType || 'physical'
    state.manufacturerNumber = value.manufacturerNumber || ''
    state.packUnit = value.packUnit || ''
    state.packUnitPlural = value.packUnitPlural || ''
    state.shippingClass = value.shippingClass || ''
    state.deliveryTime = value.deliveryTime || ''
    state.releaseDate = value.releaseDate ? value.releaseDate.slice(0, 16) : ''
    state.isFeatured = value.isFeatured || false
    state.minPurchaseQuantity = value.minPurchaseQuantity || '1'
    state.purchaseSteps = value.purchaseSteps || '1'
    state.maxPurchaseQuantity = value.maxPurchaseQuantity || ''
    state.restockTimeDays = value.restockTimeDays?.toString() || ''
    state.clearanceSale = value.clearanceSale || false
    state.freeShipping = value.freeShipping || false
    state.searchKeywords = value.searchKeywords || ''
    productKeywords.value = value.searchKeywords
      ? value.searchKeywords
          .split(',')
          .map(keyword => keyword.trim())
          .filter(Boolean)
      : []
    state.weightGrams = value.weightGrams?.toString() || ''
    state.lengthMillimeters = value.lengthMillimeters?.toString() || ''
    state.widthMillimeters = value.widthMillimeters?.toString() || ''
    state.heightMillimeters = value.heightMillimeters?.toString() || ''
    if (value.regularPrice) {
      regularPrice.currency = value.regularPrice.currency || 'BAM'
      regularPrice.taxRate = value.regularPrice.taxRate || '17'
      regularPrice.sellingGross
        = value.regularPrice.grossAmount === null
          ? ''
          : (value.regularPrice.grossAmount / 100).toFixed(2)
      regularPrice.sellingNet
        = value.regularPrice.netAmount === null ? '' : (value.regularPrice.netAmount / 100).toFixed(2)
      regularPrice.purchaseGross
        = value.regularPrice.purchaseGrossAmount === null
          ? ''
          : (value.regularPrice.purchaseGrossAmount / 100).toFixed(2)
      regularPrice.purchaseNet
        = value.regularPrice.purchaseNetAmount === null
          ? ''
          : (value.regularPrice.purchaseNetAmount / 100).toFixed(2)
      regularPrice.listGross
        = value.regularPrice.listGrossAmount === null
          ? ''
          : (value.regularPrice.listGrossAmount / 100).toFixed(2)
      regularPrice.listNet
        = value.regularPrice.listNetAmount === null
          ? ''
          : (value.regularPrice.listNetAmount / 100).toFixed(2)
      regularPrice.sellingChained = value.regularPrice.linked ?? true
      regularPrice.purchaseChained = value.regularPrice.purchaseLinked ?? true
      regularPrice.listChained = value.regularPrice.listLinked ?? true
    }
    void loadTranslation()
  },
  { immediate: true }
)

const tabs = computed(() => [
  { label: t('products.general'), value: 'general' },
  ...(!isVariant.value ? [{ label: t('products.variants'), value: 'variants' }] : []),
  { label: t('productSpecifications.title'), value: 'specifications' },
  { label: t('productSeo.title'), value: 'seo' },
  { label: t('inventoryMovements.title'), value: 'stock-movements' },
  { label: t('productGallery.title'), value: 'gallery' },
  { label: t('productRelations.title'), value: 'related' },
  { label: t('productAdvancedPriceTab.title'), value: 'prices' },
  { label: t('productCustomFields.title'), value: 'extensions' }
])

watch(
  selectedPropertiesData,
  (value) => {
    selectedPropertyIds.value = value?.propertyIds ?? []
  },
  { immediate: true }
)
watch(
  productReferencesData,
  (references) => {
    state.taxId = references?.taxId || ''
    state.manufacturerId = references?.manufacturerId || ''
    state.unitId = references?.unitId || ''
    state.purchaseUnit = references?.purchaseUnit || ''
    state.referenceUnit = references?.referenceUnit || ''
    state.deliveryTimeId = references?.deliveryTimeId || ''
  },
  { immediate: true }
)
watch(
  customFieldSets,
  (value) => {
    const firstSet = value[0]
    if (selectedCustomFieldSet.value === 'uncategorized' && firstSet)
      selectedCustomFieldSet.value = firstSet.id
  },
  { immediate: true }
)
watch(
  productSalesChannels,
  (channels) => {
    salesChannelVisibility.value = Object.fromEntries(
      channels.map(channel => [channel.id, String(channel.visibility)])
    )
  },
  { immediate: true }
)
watch(
  tagsData,
  (value) => {
    productTags.value = value?.tags ?? []
  },
  { immediate: true }
)
watch(
  variantOptionsData,
  (value) => {
    variantPropertyIds.value = Object.fromEntries(
      (value?.optionGroups ?? []).map(group => [group.propertyGroupId, group.propertyIds])
    )
  },
  { immediate: true }
)
const loadPropertyGroupTranslations = async () => {
  const locale = selectedLocale.value
  if (locale === defaultLocale.value) {
    propertyGroupTranslations.value = {}
    return
  }
  const groups = propertyGroups.value
  const translations = await Promise.all(
    groups.map(
      async group =>
        [
          group.id,
          (
            await apiFetch<{ translation: PropertyGroupTranslation }>(
              `/property-groups/${group.id}/translations/${locale}`
            )
          ).translation
        ] as const
    )
  )
  if (locale === selectedLocale.value)
    propertyGroupTranslations.value = Object.fromEntries(translations)
}

watch(
  propertyGroups,
  (groups) => {
    if (!activeVariantGroupId.value && groups[0]) activeVariantGroupId.value = groups[0].id
    if (!activeSpecificationGroupId.value && groups[0])
      activeSpecificationGroupId.value = groups[0].id
    void loadPropertyGroupTranslations()
  },
  { immediate: true }
)

const save = async () => {
  saving.value = true
  try {
    await apiFetch(`/products/${route.params.id}`, {
      method: 'PATCH',
      body: state
    })
    await refresh()
    notify.success(t('products.updatedSuccess'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('products.updateFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}

watch(selectedLocale, () => {
  productValidation.clear()
  void loadTranslation()
  void loadPropertyGroupTranslations()
})
watch(
  selectedTab,
  (tab) => {
    if (tab === 'variants') {
      void refreshVariants()
    }
    if (tab === 'stock-movements') {
      void refreshInventoryMovements()
    }
    if (tab === 'gallery') {
      void refreshMedia()
    }
    if (tab === 'related') {
      void refreshDownloads()
      void refreshCrossSellings()
    }
    if (tab === 'prices') {
      void refreshPrices()
    }
  },
  { immediate: true }
)
watch(variantsTableSearch, (value) => {
  variantsPage.value = 1
  clearTimeout(variantsSearchDebounce)
  variantsSearchDebounce = setTimeout(() => {
    debouncedVariantsTableSearch.value = value.trim()
  }, 300)
})
watch(variantSorting, () => {
  variantsPage.value = 1
})
watch(
  () => variantsPagination.pageSize,
  () => {
    variantsPage.value = 1
  }
)
watch(variantsUrl, () => {
  void refreshVariants()
})
watch(
  () => movementsPagination.pageSize,
  () => {
    movementsPage.value = 1
  }
)
watch(movementsUrl, () => {
  void refreshInventoryMovements()
})
watch(addedPropertiesSearch, resetPropertiesPagination)
const productValidationLabels = computed(() => ({
  name: t('products.name'),
  taxId: t('productPrices.tax'),
  sellingGross: t('productRegularPriceFields.gross'),
  sellingNet: t('productRegularPriceFields.net')
}))
const validateDefaultProduct = () => {
  const missingLabels = productValidation.validateRequired([
    {
      field: 'name',
      value: state.name,
      label: productValidationLabels.value.name,
      message: t('common.requiredField')
    },
    {
      field: 'taxId',
      value: state.taxId,
      label: productValidationLabels.value.taxId,
      message: t('common.requiredField')
    },
    {
      field: 'regularPrice.sellingGross',
      value: regularPrice.sellingGross,
      label: productValidationLabels.value.sellingGross,
      message: t('common.requiredField')
    },
    {
      field: 'regularPrice.sellingNet',
      value: regularPrice.sellingNet,
      label: productValidationLabels.value.sellingNet,
      message: t('common.requiredField')
    }
  ])

  if (missingLabels.length === 0) return true

  notify.error(
    t('products.updateFailed'),
    t('common.requiredFields', { fields: missingLabels.join(', ') })
  )

  return false
}
const validateTranslation = () => {
  const missingLabels = productValidation.validateRequired([
    {
      field: 'name',
      value: state.name,
      label: productValidationLabels.value.name,
      message: t('common.requiredField')
    }
  ])
  if (missingLabels.length === 0) return true

  notify.error(
    t('products.updateFailed'),
    t('common.requiredFields', {
      fields: missingLabels.join(', ')
    })
  )

  return false
}
const translationPayload = () => ({
  name: state.name,
  shortDescription: state.shortDescription,
  description: state.description,
  seoUrl: state.seoUrl,
  metaTitle: state.metaTitle,
  metaDescription: state.metaDescription,
  metaKeywords: state.metaKeywords,
  customFields: customFieldValues.value
})
const saveProductTranslation = async () => {
  if (!validateTranslation()) return

  saving.value = true
  try {
    await apiFetch(`/products/${route.params.id}/translations/${selectedLocale.value}`, {
      method: 'PUT',
      body: translationPayload()
    })
    await refresh()
    notify.success(t('products.updatedSuccess'), t('common.changesSaved'))
  } catch (error: any) {
    productValidation.applyApiError(error)
    notify.error(t('products.updateFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}
const saveProduct = async () => {
  if (selectedLocale.value !== defaultLocale.value) {
    await saveProductTranslation()
    return
  }

  if (!validateDefaultProduct()) return

  saving.value = true
  try {
    await apiFetch(`/products/${route.params.id}`, {
      method: 'PATCH',
      body: {
        status: state.status,
        sku: state.sku,
        productType: state.productType,
        manufacturerNumber: state.manufacturerNumber,
        packUnit: state.packUnit,
        packUnitPlural: state.packUnitPlural,
        shippingClass: state.shippingClass,
        deliveryTime: state.deliveryTime,
        releaseDate: state.releaseDate || null,
        isFeatured: state.isFeatured,
        regularPrice: {
          currency: regularPrice.currency,
          taxRate: regularPrice.taxRate,
          grossAmount: regularPrice.sellingGross,
          netAmount: regularPrice.sellingNet,
          purchaseGrossAmount: regularPrice.purchaseGross,
          purchaseNetAmount: regularPrice.purchaseNet,
          listGrossAmount: regularPrice.listGross,
          listNetAmount: regularPrice.listNet,
          linked: regularPrice.sellingChained,
          purchaseLinked: regularPrice.purchaseChained,
          listLinked: regularPrice.listChained
        },
        minPurchaseQuantity: state.minPurchaseQuantity,
        purchaseSteps: state.purchaseSteps,
        maxPurchaseQuantity: state.maxPurchaseQuantity,
        restockTimeDays: state.restockTimeDays,
        clearanceSale: state.clearanceSale,
        freeShipping: state.freeShipping,
        searchKeywords: productKeywords.value.join(', '),
        weightGrams: state.weightGrams,
        lengthMillimeters: state.lengthMillimeters,
        widthMillimeters: state.widthMillimeters,
        heightMillimeters: state.heightMillimeters
      }
    })
    await apiFetch(`/products/${route.params.id}/translations/${selectedLocale.value}`, {
      method: 'PUT',
      body: translationPayload()
    })
    await apiFetch(`/products/${route.params.id}/tags`, {
      method: 'PUT',
      body: { names: productTags.value.map(tag => tag.name) }
    })
    await refreshTags()
    await apiFetch(`/products/${route.params.id}/properties`, {
      method: 'PUT',
      body: { propertyIds: selectedPropertyIds.value }
    })
    await apiFetch(`/products/${route.params.id}/categories`, {
      method: 'PUT',
      body: { categoryIds: selectedCategoryIds.value }
    })
    await apiFetch(`/products/${route.params.id}/references`, {
      method: 'PATCH',
      body: {
        taxId: state.taxId,
        manufacturerId: state.manufacturerId,
        unitId: state.unitId,
        purchaseUnit: state.purchaseUnit,
        referenceUnit: state.referenceUnit,
        deliveryTimeId: state.deliveryTimeId
      }
    })
    await apiFetch(`/products/${route.params.id}/channel-publications`, {
      method: 'PUT',
      body: {
        publications: productSalesChannels.value.map(channel => ({
          salesChannelId: channel.id,
          visibility: Number(salesChannelVisibility.value[channel.id] || 0)
        }))
      }
    })
    await refreshSelectedProperties()
    await refreshSelectedCategories()
    await refreshProductSalesChannels()
    await refresh()
    notify.success(t('products.updatedSuccess'), t('common.changesSaved'))
  } catch (error: any) {
    productValidation.applyApiError(error)
    notify.error(t('products.updateFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    saving.value = false
  }
}

const addProductTag = () => {
  const name = tagInput.value.trim()
  if (
    !name
    || productTags.value.some(tag => tag.name.toLocaleLowerCase() === name.toLocaleLowerCase())
  )
    return
  productTags.value = [...productTags.value, { id: name, name }]
  tagInput.value = ''
}
const removeProductTag = (name: string) => {
  productTags.value = productTags.value.filter(tag => tag.name !== name)
}
const addProductKeyword = () => {
  const keyword = keywordInput.value.trim()
  if (
    !keyword
    || productKeywords.value.some(item => item.toLocaleLowerCase() === keyword.toLocaleLowerCase())
  )
    return
  productKeywords.value = [...productKeywords.value, keyword]
  keywordInput.value = ''
}
const removeProductKeyword = (keyword: string) => {
  productKeywords.value = productKeywords.value.filter(item => item !== keyword)
}

const addOptionGroup = async () => {
  const values = optionForm.values
    .split(',')
    .map(value => value.trim())
    .filter(Boolean)
  if (!optionForm.name.trim() || !values.length) return
  optionSaving.value = true
  try {
    await apiFetch(`/products/${route.params.id}/option-groups`, {
      method: 'POST',
      body: { name: optionForm.name, values }
    })
    await refreshOptionGroups()
    optionForm.name = ''
    optionForm.values = ''
    notify.success(t('catalogue.optionCreated'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('catalogue.optionCreateFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    optionSaving.value = false
  }
}

const generateVariants = async () => {
  generating.value = true
  try {
    await apiFetch(`/products/${route.params.id}/variant-options`, {
      method: 'PUT',
      body: {
        optionGroups: Object.entries(variantPropertyIds.value)
          .filter(([, propertyIds]) => propertyIds.length)
          .map(([propertyGroupId, propertyIds]) => ({
            propertyGroupId,
            propertyIds
          }))
      }
    })
    await refreshVariantOptions()
    const response = await apiFetch<{ message: string }>(
      `/products/${route.params.id}/variants/generate`,
      { method: 'POST' }
    )
    await refreshVariants()
    optionOpen.value = false
    notify.success(t('products.updatedSuccess'), response.message)
  } catch (error: any) {
    notify.error(t('catalogue.variantGenerateFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    generating.value = false
  }
}

const uploadMedia = async (event: Event) => {
  const input = event.target as HTMLInputElement
  if (!input.files?.length) return
  pendingMediaFiles.value = Array.from(input.files)
  input.value = ''
  await uploadMediaFiles()
}

const uploadMediaFiles = async (duplicateAction?: 'replace' | 'rename') => {
  if (!pendingMediaFiles.value.length) return
  mediaUploading.value = true
  try {
    const body = new FormData()
    for (const file of pendingMediaFiles.value) body.append('files[]', file)
    if (duplicateAction) body.append('duplicateAction', duplicateAction)
    await apiFetch(`/products/${route.params.id}/media`, {
      method: 'POST',
      body
    })
    await refreshMedia()
    duplicateMediaOpen.value = false
    duplicateMediaNames.value = []
    pendingMediaFiles.value = []
    notify.success(t('productGallery.uploaded'), t('common.changesSaved'))
  } catch (error: any) {
    if (error?.status === 409 && Array.isArray(error?.data?.duplicates)) {
      duplicateMediaNames.value = error.data.duplicates
      duplicateMediaOpen.value = true
      return
    }
    notify.error(t('productGallery.uploadFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    mediaUploading.value = false
  }
}

const cancelDuplicateMediaUpload = () => {
  duplicateMediaOpen.value = false
  pendingMediaFiles.value = []
}

const saveMediaOrder = async (ids: string[]) => {
  mediaUpdating.value = true
  try {
    await apiFetch(`/products/${route.params.id}/media/order`, {
      method: 'PUT',
      body: { mediaIds: ids }
    })
    await refreshMedia()
  } catch (error: any) {
    notify.error(t('productGallery.orderFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    mediaUpdating.value = false
  }
}

const moveMedia = async (index: number, direction: -1 | 1) => {
  const nextIndex = index + direction
  if (nextIndex < 0 || nextIndex >= media.value.length) return
  const ids = media.value.map(item => item.id)
  const currentId = ids[index]
  const nextId = ids[nextIndex]
  if (!currentId || !nextId) return
  ids[index] = nextId
  ids[nextIndex] = currentId
  await saveMediaOrder(ids)
}

const setCoverMedia = async (id: string) => {
  await saveMediaOrder([id, ...media.value.filter(item => item.id !== id).map(item => item.id)])
}

const deleteMedia = async (id: string) => {
  mediaDeleting.value = id
  try {
    await apiFetch(`/products/${route.params.id}/media/${id}`, {
      method: 'DELETE'
    })
    await refreshMedia()
    notify.success(t('productGallery.deleted'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('productGallery.deleteFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    mediaDeleting.value = null
  }
}

const toggleProductProperty = (id: string, selected: boolean) => {
  selectedPropertyIds.value = selected
    ? [...new Set([...selectedPropertyIds.value, id])]
    : selectedPropertyIds.value.filter(propertyId => propertyId !== id)
}

const openSpecifications = () => {
  specificationPropertyIds.value = [...selectedPropertyIds.value]
  specificationSearch.value = ''
  specificationsOpen.value = true
}

const toggleSpecificationProperty = (id: string, selected: boolean) => {
  specificationPropertyIds.value = selected
    ? [...new Set([...specificationPropertyIds.value, id])]
    : specificationPropertyIds.value.filter(propertyId => propertyId !== id)
}

const saveSpecifications = async () => {
  specificationsSaving.value = true
  try {
    await apiFetch(`/products/${route.params.id}/properties`, {
      method: 'PUT',
      body: { propertyIds: specificationPropertyIds.value }
    })
    await refreshSelectedProperties()
    specificationsOpen.value = false
    notify.success(t('productProperties.saved'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('productProperties.saveFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    specificationsSaving.value = false
  }
}

const openStockAdjustment = () => {
  stockValidation.clear()
  const defaultWarehouse
    = warehouses.value.find(warehouse => warehouse.default) || warehouses.value[0]
  stockForm.warehouseId = defaultWarehouse?.id || ''
  const level = inventory.value.levels.find(item => item.warehouseId === stockForm.warehouseId)
  stockForm.quantity = String(level?.stock ?? 0)
  stockForm.note = ''
  stockAdjustmentOpen.value = true
}

const updateStockWarehouse = (warehouseId: string) => {
  stockForm.warehouseId = warehouseId
  stockForm.quantity = String(
    inventory.value.levels.find(level => level.warehouseId === warehouseId)?.stock ?? 0
  )
  stockValidation.clear('warehouseId')
}

const saveStockAdjustment = async () => {
  if (
    !stockValidation.requireFields(
      [
        {
          field: 'warehouseId',
          value: stockForm.warehouseId,
          label: t('inventory.warehouse'),
          message: t('common.requiredField')
        },
        {
          field: 'quantity',
          value: stockForm.quantity,
          label: t('inventory.stock'),
          message: t('common.requiredField')
        }
      ],
      notify,
      t('inventory.stockUpdateFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }
  if (!Number.isFinite(Number(stockForm.quantity))) {
    stockValidation.set({ quantity: t('common.invalidField') })
    notify.error(t('inventory.stockUpdateFailed'), t('common.invalidFields', { fields: t('inventory.stock') }))
    return
  }

  stockSaving.value = true
  try {
    await apiFetch(`/inventory/products/${route.params.id}`, {
      method: 'PUT',
      body: stockForm
    })
    await refreshInventory()
    await refreshInventoryMovements()
    stockAdjustmentOpen.value = false
    notify.success(t('inventory.stockUpdated'), t('common.changesSaved'))
  } catch (error: any) {
    stockValidation.notifyApiError(
      error,
      notify,
      t('inventory.stockUpdateFailed'),
      t('common.tryAgain')
    )
  } finally {
    stockSaving.value = false
  }
}

const selectedPropertyRowIds = computed(() =>
  Object.entries(propertyRowSelection.value)
    .filter(([, selected]) => selected)
    .flatMap(
      ([groupId]) =>
        selectedSpecificationGroups.value
          .find(group => group.id === groupId)
          ?.properties.map(property => property.id) ?? []
    )
)
const propertyRemovalOpen = computed({
  get: () => propertyIdsToRemove.value.length > 0,
  set: (value) => {
    if (!value) propertyIdsToRemove.value = []
  }
})

const removeProductProperties = async () => {
  if (!propertyIdsToRemove.value.length) return
  const ids = new Set(propertyIdsToRemove.value)
  specificationsSaving.value = true
  try {
    const remaining = selectedPropertyIds.value.filter(id => !ids.has(id))
    await apiFetch(`/products/${route.params.id}/properties`, {
      method: 'PUT',
      body: { propertyIds: remaining }
    })
    propertyRowSelection.value = {}
    propertyIdsToRemove.value = []
    await refreshSelectedProperties()
    notify.success(t('productProperties.removed'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('productProperties.removeFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    specificationsSaving.value = false
  }
}

const selectedVariantIds = computed(() =>
  Object.entries(variantRowSelection.value)
    .filter(([, selected]) => selected)
    .map(([id]) => id)
)
const variantRemovalOpen = computed({
  get: () => variantIdsToDelete.value.length > 0,
  set: (value) => {
    if (!value) variantIdsToDelete.value = []
  }
})
const removeVariants = async () => {
  if (!variantIdsToDelete.value.length) return
  variantsDeleting.value = true
  try {
    await apiFetch(`/products/${route.params.id}/variants`, {
      method: 'DELETE',
      body: { variantIds: variantIdsToDelete.value }
    })
    variantRowSelection.value = {}
    variantIdsToDelete.value = []
    await refreshVariants()
    notify.success(t('products.variantsDeleted'), t('common.changesSaved'))
  } catch (error: any) {
    notify.error(t('products.variantsDeleteFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    variantsDeleting.value = false
  }
}

const addPrice = async () => {
  if (
    !priceValidation.requireFields(
      [
        {
          field: 'currency',
          value: priceForm.currency,
          label: t('productPrices.currency'),
          message: t('common.requiredField')
        },
        {
          field: 'taxRate',
          value: priceForm.taxRate,
          label: t('productPrices.tax'),
          message: t('common.requiredField')
        },
        {
          field: 'netAmount',
          value: priceForm.netAmount,
          label: t('productPriceLabels.net'),
          message: t('common.requiredField')
        },
        {
          field: 'grossAmount',
          value: priceForm.grossAmount,
          label: t('productPriceLabels.gross'),
          message: t('common.requiredField')
        },
        {
          field: 'quantityStart',
          value: priceForm.quantityStart,
          label: t('productAdvancedPrice.quantityFrom'),
          message: t('common.requiredField')
        }
      ],
      notify,
      t('productPrices.createFailed'),
      fields => t('common.requiredFields', { fields: fields.join(', ') })
    )
  ) {
    return
  }

  priceSaving.value = true
  try {
    await apiFetch(`/products/${route.params.id}/prices`, {
      method: 'POST',
      body: {
        ...priceForm,
        priceType: 'default',
        pricingContext: 'advanced',
        listNetAmount: priceForm.listNetAmount || null,
        listGrossAmount: priceForm.listGrossAmount || null,
        validFrom: priceForm.validFrom || null,
        validUntil: priceForm.validUntil || null
      }
    })
    await refreshPrices()
    priceOpen.value = false
    Object.assign(priceForm, {
      currency: regularPrice.currency || 'BAM',
      netAmount: '',
      grossAmount: '',
      taxRate: regularPrice.taxRate || '17',
      quantityStart: '1',
      quantityEnd: '',
      listNetAmount: '',
      listGrossAmount: '',
      validFrom: '',
      validUntil: ''
    })
    notify.success(t('productPrices.created'), t('common.changesSaved'))
  } catch (error: any) {
    priceValidation.notifyApiError(
      error,
      notify,
      t('productPrices.createFailed'),
      t('common.tryAgain')
    )
  } finally {
    priceSaving.value = false
  }
}

const saveRegularPrices = async () => {
  if (
    regularPrice.sellingGross === ''
    || regularPrice.sellingNet === ''
    || regularPrice.taxRate === ''
  )
    throw new Error(t('productPriceValidation.required'))
  const records = [
    {
      id: regularPrice.sellingId,
      priceType: 'default',
      gross: regularPrice.sellingGross,
      net: regularPrice.sellingNet
    },
    {
      id: regularPrice.purchaseId,
      priceType: 'purchase',
      gross: regularPrice.purchaseGross,
      net: regularPrice.purchaseNet
    },
    {
      id: regularPrice.listId,
      priceType: 'list',
      gross: regularPrice.listGross,
      net: regularPrice.listNet
    }
  ]
  for (const record of records) {
    if (record.gross === '' && record.net === '') continue
    const body = {
      currency: regularPrice.currency,
      netAmount: record.net,
      grossAmount: record.gross,
      taxRate: regularPrice.taxRate,
      priceType: record.priceType,
      quantityStart: '1',
      pricingContext: 'default'
    }
    if (record.id)
      await apiFetch(`/products/${route.params.id}/prices/${record.id}`, {
        method: 'PATCH',
        body
      })
    else
      await apiFetch(`/products/${route.params.id}/prices`, {
        method: 'POST',
        body
      })
  }
  await refreshPrices()
}

const priceValue = (value: string | number | null | undefined) =>
  Number.parseFloat(String(value ?? '').replace(',', '.'))
const formattedPrice = (value: number) => (Number.isFinite(value) ? value.toFixed(2) : '')
const updatePricePair = (
  type: 'selling' | 'purchase' | 'list',
  field: 'gross' | 'net',
  value: string | number | null | undefined
) => {
  const normalizedValue = String(value ?? '')
  const amount = priceValue(normalizedValue)
  const multiplier = 1 + (priceValue(regularPrice.taxRate) || 0) / 100
  const grossKey = `${type}Gross` as 'sellingGross' | 'purchaseGross' | 'listGross'
  const netKey = `${type}Net` as 'sellingNet' | 'purchaseNet' | 'listNet'
  regularPrice[field === 'gross' ? grossKey : netKey] = normalizedValue
  productValidation.clear(
    `regularPrice.${field === 'gross' ? grossKey : netKey}`
  )
  if (!regularPrice[`${type}Chained`]) return
  if (!Number.isFinite(amount) || multiplier <= 0) return
  regularPrice[field === 'gross' ? netKey : grossKey] = formattedPrice(
    field === 'gross' ? amount / multiplier : amount * multiplier
  )
}

const updateTaxRate = (value: string) => {
  regularPrice.taxRate = value
  for (const type of ['selling', 'purchase', 'list'] as const) {
    const gross = regularPrice[`${type}Gross`]
    if (gross !== '') updatePricePair(type, 'gross', gross)
  }
}

const updateTax = (taxId: string) => {
  state.taxId = taxId
  productValidation.clear('taxId')
  const tax = catalogueReferences.value?.taxes.find(item => item.id === taxId)
  if (tax) updateTaxRate(tax.rate)
}

const toggleRegularPriceChain = (type: 'selling' | 'purchase' | 'list') => {
  const chainedKey = `${type}Chained` as 'sellingChained' | 'purchaseChained' | 'listChained'
  regularPrice[chainedKey] = !regularPrice[chainedKey]
  if (!regularPrice[chainedKey]) return

  const grossKey = `${type}Gross` as 'sellingGross' | 'purchaseGross' | 'listGross'
  const netKey = `${type}Net` as 'sellingNet' | 'purchaseNet' | 'listNet'
  if (regularPrice[grossKey] !== '') updatePricePair(type, 'gross', regularPrice[grossKey])
  else if (regularPrice[netKey] !== '') updatePricePair(type, 'net', regularPrice[netKey])
}

const addAdvancedScale = (rule: AdvancedPriceRule, index: number) => {
  const current = rule.rows[index]
  const quantityEnd = String(current?.to ?? '').trim()
  if (!quantityEnd) return

  const nextFrom = Number.parseFloat(quantityEnd.replace(',', '.')) + 1
  if (!Number.isFinite(nextFrom)) return

  const next = rule.rows[index + 1]
  if (next) {
    next.from = String(nextFrom)
    return
  }

  rule.rows.push({ ...newAdvancedRow(), from: String(nextFrom) })
}

const removeAdvancedRule = (id: number) => {
  advancedRules.value = advancedRules.value.filter(rule => rule.id !== id)
}

const removeAdvancedScale = (rule: AdvancedPriceRule, index: number) => {
  rule.rows.splice(index, 1)
}

const tierActions = (rule: AdvancedPriceRule, index: number) => [
  [
    {
      label: t('common.delete'),
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
      onSelect: () => removeAdvancedScale(rule, index)
    }
  ]
]

const updateAdvancedPair = (
  row: AdvancedRow,
  kind: 'price' | 'list' | 'cheapest',
  field: 'gross' | 'net',
  value: string | number | null | undefined
) => {
  const keys
    = kind === 'price'
      ? ['gross', 'net', 'priceChained']
      : kind === 'list'
        ? ['listGross', 'listNet', 'listChained']
        : ['cheapestGross', 'cheapestNet', 'cheapestChained']
  const [grossKey, netKey, chainedKey] = keys as [
    'gross' | 'listGross' | 'cheapestGross',
    'net' | 'listNet' | 'cheapestNet',
    'priceChained' | 'listChained' | 'cheapestChained'
  ]
  const normalizedValue = String(value ?? '')
  row[field === 'gross' ? grossKey : netKey] = normalizedValue
  if (!row[chainedKey]) return
  const amount = priceValue(normalizedValue)
  const multiplier = 1 + (priceValue(regularPrice.taxRate) || 0) / 100
  if (!Number.isFinite(amount) || multiplier <= 0) return
  row[field === 'gross' ? netKey : grossKey] = formattedPrice(
    field === 'gross' ? amount / multiplier : amount * multiplier
  )
}

const toggleAdvancedPriceChain = (row: AdvancedRow, kind: 'price' | 'list' | 'cheapest') => {
  const keys
    = kind === 'price'
      ? ['gross', 'net', 'priceChained']
      : kind === 'list'
        ? ['listGross', 'listNet', 'listChained']
        : ['cheapestGross', 'cheapestNet', 'cheapestChained']
  const [grossKey, netKey, chainedKey] = keys as [
    'gross' | 'listGross' | 'cheapestGross',
    'net' | 'listNet' | 'cheapestNet',
    'priceChained' | 'listChained' | 'cheapestChained'
  ]
  row[chainedKey] = !row[chainedKey]
  if (!row[chainedKey]) return

  if (row[grossKey] !== '') updateAdvancedPair(row, kind, 'gross', row[grossKey])
  else if (row[netKey] !== '') updateAdvancedPair(row, kind, 'net', row[netKey])
}
</script>

<template>
  <div v-if="status === 'pending'" class="py-12 text-center text-muted">
    {{ t('common.loading') }}
  </div>
  <div v-else-if="product" class="space-y-6">
    <DetailPageHeader
      :title="state.name || product.name"
      :subtitle="product.seoUrl"
      :back-to="
        isVariant && product.parentId
          ? `/catalogue/products/${product.parentId}?tab=variants`
          : '/catalogue/products'
      "
      :back-label="isVariant ? t('productNavigation.backToParent') : t('products.back')"
    >
      <template #actions>
        <LocaleSelect
          v-model="selectedLocale"
          :options="locales"
          class="w-full sm:w-56"
        /><UButton :label="t('common.saveChanges')" :loading="saving" @click="saveProduct" />
      </template>
    </DetailPageHeader>

    <UTabs v-model="selectedTab" :items="tabs" :content="false" />

    <UForm v-if="selectedTab === 'general'" class="space-y-6" @submit.prevent="saveProduct">
      <UPageCard :title="t('products.general')" :description="t('products.generalDescription')">
        <div class="grid gap-6 sm:grid-cols-2">
          <UFormField
            :label="t('products.name')"
            :error="validationErrors.name"
            required
          >
            <UInput
              :model-value="state.name"
              class="w-full"
              @update:model-value="updateName"
            />
          </UFormField>
          <UFormField :label="t('products.status')">
            <USelect
              v-model="state.status"
              :items="[
                { label: t('products.active'), value: 'active' },
                { label: t('products.draft'), value: 'draft' },
                { label: t('products.archived'), value: 'archived' }
              ]"
              class="w-full"
            />
          </UFormField>
          <UFormField :label="t('products.productNumber')">
            <UInput v-model="state.sku" class="w-full" autocomplete="off" />
          </UFormField>
          <UFormField :label="t('products.manufacturer')">
            <SearchableSelect
              v-model="state.manufacturerId"
              :items="manufacturerItems"
              :placeholder="t('products.selectManufacturer')"
              :search-placeholder="t('products.searchManufacturers')"
              class="w-full"
            />
          </UFormField>
          <UFormField :label="t('productEditor.productType')">
            <USelect
              v-model="state.productType"
              :items="[
                { label: t('productEditor.physical'), value: 'physical' },
                { label: t('productEditor.digital'), value: 'digital' },
                { label: t('productEditor.service'), value: 'service' }
              ]"
              class="w-full"
            />
          </UFormField>
          <UFormField :label="t('productEditor.shippingClass')">
            <UInput v-model="state.shippingClass" class="w-full" />
          </UFormField>
        </div>
        <UFormField :label="t('products.shortDescription')" class="mt-6">
          <UTextarea v-model="state.shortDescription" class="w-full" :rows="3" />
        </UFormField>
        <UFormField :label="t('products.description')" class="mt-6">
          <RichTextEditor v-model="state.description" />
        </UFormField>
      </UPageCard>

      <UPageCard
        :title="t('productRegularPrice.title')"
        :description="t('productRegularPrice.description')"
      >
        <div class="space-y-5">
          <div class="grid gap-5 sm:grid-cols-2">
            <UFormField
              :label="t('productPrices.tax')"
              :error="validationErrors.taxId"
              required
            >
              <USelect
                :model-value="state.taxId"
                :items="
                  (catalogueReferences?.taxes || []).map((tax) => ({
                    label: `${tax.name} (${tax.rate}%)`,
                    value: tax.id
                  }))
                "
                class="w-full"
                @update:model-value="updateTax"
              />
            </UFormField>
            <UFormField
              :label="t('productPrices.currency')"
              :error="validationErrors['regularPrice.currency']"
            >
              <USelect
                v-model="regularPrice.currency"
                :items="
                  currencies.map((currency) => ({
                    label: `${currency.code} (${currency.symbol})`,
                    value: currency.code
                  }))
                "
                class="w-full"
                @update:model-value="productValidation.clear('regularPrice.currency')"
              />
            </UFormField>
          </div>
          <div class="grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <UFormField
              :label="t('productRegularPriceFields.gross')"
              :error="validationErrors['regularPrice.sellingGross']"
              required
            >
              <UInput
                :model-value="regularPrice.sellingGross"
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                class="w-full"
                @update:model-value="(value) => updatePricePair('selling', 'gross', value)"
              />
            </UFormField>
            <UButton
              :icon="regularPrice.sellingChained ? 'i-lucide-link' : 'i-lucide-unlink'"
              color="neutral"
              variant="ghost"
              size="sm"
              :aria-label="t('productRegularPrice.toggleChain')"
              @click="toggleRegularPriceChain('selling')"
            />
            <UFormField
              :label="t('productRegularPriceFields.net')"
              :error="validationErrors['regularPrice.sellingNet']"
              required
            >
              <UInput
                :model-value="regularPrice.sellingNet"
                inputmode="decimal"
                class="w-full"
                @update:model-value="(value) => updatePricePair('selling', 'net', value)"
              />
            </UFormField>
          </div>
          <div class="grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <UFormField :label="t('productRegularPriceFields.purchaseGross')">
              <UInput
                :model-value="regularPrice.purchaseGross"
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                class="w-full"
                @update:model-value="(value) => updatePricePair('purchase', 'gross', value)"
              />
            </UFormField>
            <UButton
              :icon="regularPrice.purchaseChained ? 'i-lucide-link' : 'i-lucide-unlink'"
              color="neutral"
              variant="ghost"
              size="sm"
              :aria-label="t('productRegularPrice.toggleChain')"
              @click="toggleRegularPriceChain('purchase')"
            />
            <UFormField :label="t('productRegularPriceFields.purchaseNet')">
              <UInput
                :model-value="regularPrice.purchaseNet"
                inputmode="decimal"
                class="w-full"
                @update:model-value="(value) => updatePricePair('purchase', 'net', value)"
              />
            </UFormField>
          </div>
          <div class="grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <UFormField :label="t('productRegularPriceFields.listGross')">
              <UInput
                :model-value="regularPrice.listGross"
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                class="w-full"
                @update:model-value="(value) => updatePricePair('list', 'gross', value)"
              />
            </UFormField>
            <UButton
              :icon="regularPrice.listChained ? 'i-lucide-link' : 'i-lucide-unlink'"
              color="neutral"
              variant="ghost"
              size="sm"
              :aria-label="t('productRegularPrice.toggleChain')"
              @click="toggleRegularPriceChain('list')"
            />
            <UFormField :label="t('productRegularPriceFields.listNet')">
              <UInput
                :model-value="regularPrice.listNet"
                inputmode="decimal"
                class="w-full"
                @update:model-value="(value) => updatePricePair('list', 'net', value)"
              />
            </UFormField>
          </div>
          <div class="grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <UFormField :label="t('productRegularPriceFields.cheapestGross')">
              <UInput disabled :model-value="regularPrice.sellingGross" class="w-full" />
            </UFormField>
            <UButton
              :icon="regularPrice.cheapestChained ? 'i-lucide-link' : 'i-lucide-unlink'"
              color="neutral"
              variant="ghost"
              size="sm"
              :aria-label="t('productRegularPrice.toggleChain')"
              @click="regularPrice.cheapestChained = !regularPrice.cheapestChained"
            />
            <UFormField :label="t('productRegularPriceFields.cheapestNet')">
              <UInput disabled :model-value="regularPrice.sellingNet" class="w-full" />
            </UFormField>
          </div>
        </div>
      </UPageCard>

      <UPageCard
        :title="t('productSections.deliverability')"
        :description="t('productSections.deliverabilityDescription')"
      >
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <UFormField :label="t('inventory.stock')">
            <div class="flex gap-2">
              <UInput :model-value="String(inventory.stock)" disabled class="w-full" /><UButton
                icon="i-lucide-pencil"
                color="neutral"
                variant="outline"
                :aria-label="t('inventory.adjustStock')"
                @click="openStockAdjustment"
              />
            </div>
          </UFormField>
          <UFormField :label="t('inventory.availableStock')">
            <UInput :model-value="String(inventory.availableStock)" disabled class="w-full" />
          </UFormField>
          <UFormField :label="t('inventory.unavailableStock')">
            <UInput :model-value="String(inventory.unavailableStock)" disabled class="w-full" />
          </UFormField>
          <UFormField :label="t('inventory.incomingStock')">
            <UInput :model-value="String(inventory.incomingStock)" disabled class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.deliveryTime')">
            <USelect
              v-model="state.deliveryTimeId"
              :items="
                (catalogueReferences?.deliveryTimes || []).map((deliveryTime) => ({
                  label:
                    deliveryTime.labels[selectedLocale]
                    || deliveryTime.labels[defaultLocale]
                    || `${deliveryTime.min}–${deliveryTime.max} ${deliveryTime.unit}`,
                  value: deliveryTime.id
                }))
              "
              class="w-full"
            />
          </UFormField>
          <UFormField :label="t('productSections.restockTime')">
            <UInput v-model="state.restockTimeDays" inputmode="numeric" class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.minPurchaseQuantity')">
            <UInput v-model="state.minPurchaseQuantity" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.purchaseSteps')">
            <UInput v-model="state.purchaseSteps" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.maxPurchaseQuantity')">
            <UInput v-model="state.maxPurchaseQuantity" inputmode="decimal" class="w-full" />
          </UFormField>
          <div class="grid gap-3 sm:grid-cols-2 lg:col-span-1">
            <UFormField
              :label="t('productSections.clearanceSale')"
              class="flex items-center justify-between rounded-md border border-default px-3 py-2"
            >
              <USwitch v-model="state.clearanceSale" />
            </UFormField><UFormField
              :label="t('productSections.freeShipping')"
              class="flex items-center justify-between rounded-md border border-default px-3 py-2"
            >
              <USwitch v-model="state.freeShipping" />
            </UFormField>
          </div>
        </div>
        <div class="mt-6">
          <p class="mb-3 text-sm font-medium text-highlighted">
            {{ t('inventory.stockByWarehouse') }}
          </p>
          <AppDataTable
            :data="inventory.levels"
            :columns="inventoryLevelColumns"
            :get-row-id="(row) => row.warehouseId"
            :max-height="null"
            table-key="product-inventory-levels"
            :column-labels="{
              warehouse: t('inventory.warehouse'),
              stock: t('inventory.stock'),
              reservedStock: t('inventory.reservedStock'),
              unavailableStock: t('inventory.unavailableStock'),
              availableStock: t('inventory.availableStock'),
              incomingStock: t('inventory.incomingStock')
            }"
          />
        </div>
      </UPageCard>

      <UPageCard
        :title="t('productSections.labelling')"
        :description="t('productSections.labellingDescription')"
      >
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <UFormField :label="t('products.ean')">
            <UInput class="w-full" disabled :model-value="product.ean || ''" />
          </UFormField>
          <UFormField :label="t('productEditor.manufacturerNumber')">
            <UInput v-model="state.manufacturerNumber" class="w-full" />
          </UFormField>
          <UFormField :label="t('productExtra.releaseDate')">
            <UPopover :content="{ align: 'start' }" :modal="true">
              <UButton
                :label="releaseDateLabel"
                icon="i-lucide-calendar-clock"
                color="neutral"
                variant="outline"
                class="w-full justify-start font-normal"
              /><template #content>
                <div class="space-y-3 p-3">
                  <UCalendar v-model="releaseCalendar" /><UFormField
                    :label="t('productExtra.time')"
                  >
                    <UInput v-model="releaseTime" type="time" class="w-full" />
                  </UFormField>
                </div>
              </template>
            </UPopover>
          </UFormField>
        </div>
      </UPageCard>

      <UPageCard
        :title="t('productSections.visibilityStructure')"
        :description="t('productSections.visibilityStructureDescription')"
      >
        <div class="space-y-6">
          <UFormField :label="t('productSections.searchKeywords')">
            <div class="rounded-md border border-default p-2">
              <div v-if="productKeywords.length" class="mb-2 flex flex-wrap gap-2">
                <UBadge
                  v-for="keyword in productKeywords"
                  :key="keyword"
                  color="neutral"
                  variant="subtle"
                  class="gap-1"
                >
                  <span>{{ keyword }}</span>
                  <button
                    type="button"
                    class="cursor-pointer"
                    :aria-label="t('common.delete')"
                    @click="removeProductKeyword(keyword)"
                  >
                    <UIcon name="i-lucide-x" class="size-3" />
                  </button>
                </UBadge>
              </div>
              <UInput
                v-model="keywordInput"
                :placeholder="t('productExtra.keywordsPlaceholder')"
                class="border-0 shadow-none"
                @keydown.enter.prevent="addProductKeyword"
              />
            </div>
          </UFormField>
          <UFormField :label="t('productExtra.tags')">
            <div class="rounded-md border border-default p-2">
              <div v-if="productTags.length" class="mb-2 flex flex-wrap gap-2">
                <UBadge
                  v-for="tag in productTags"
                  :key="tag.name"
                  color="neutral"
                  variant="subtle"
                  class="gap-1"
                >
                  <span>{{ tag.name }}</span>
                  <button
                    type="button"
                    class="cursor-pointer"
                    :aria-label="t('common.delete')"
                    @click="removeProductTag(tag.name)"
                  >
                    <UIcon name="i-lucide-x" class="size-3" />
                  </button>
                </UBadge>
              </div>
              <UInput
                v-model="tagInput"
                :placeholder="t('productExtra.tagsPlaceholder')"
                class="border-0 shadow-none"
                @keydown.enter.prevent="addProductTag"
              />
            </div>
          </UFormField>
          <UFormField :label="t('productCategories.label')">
            <div class="rounded-md border border-default p-3">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-muted">
                  {{ t('productCategories.description') }}
                </p>
                <UButton
                  :label="t('productCategories.select')"
                  color="neutral"
                  variant="outline"
                  @click="categoryPickerOpen = true"
                />
              </div>
              <div v-if="selectedCategoryLabels.length" class="mt-3 flex flex-wrap gap-2">
                <UBadge
                  v-for="category in selectedCategoryLabels"
                  :key="category.id"
                  color="neutral"
                  variant="subtle"
                  class="gap-1"
                >
                  <span>{{ category.name }}</span>
                  <button
                    type="button"
                    class="cursor-pointer"
                    :aria-label="t('common.delete')"
                    @click="
                      selectedCategoryIds = selectedCategoryIds.filter((id) => id !== category.id)
                    "
                  >
                    <UIcon name="i-lucide-x" class="size-3" />
                  </button>
                </UBadge>
              </div>
            </div>
          </UFormField>
          <div
            v-if="productSalesChannels.length"
            class="space-y-3 rounded-md border border-default p-4"
          >
            <div>
              <p class="font-medium text-highlighted">
                {{ t('productSalesChannels.title') }}
              </p>
              <p class="text-sm text-muted">
                {{ t('productSalesChannels.description') }}
              </p>
            </div>
            <div
              v-for="channel in productSalesChannels"
              :key="channel.id"
              class="grid gap-3 border-t border-default pt-3 sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-center"
            >
              <div>
                <p class="font-medium text-highlighted">
                  {{ channel.name }}
                </p>
                <p class="text-sm text-muted">
                  {{ channel.connectionName }}
                </p>
              </div>
              <USelect
                v-model="salesChannelVisibility[channel.id]"
                :items="salesChannelVisibilityOptions"
                class="w-full"
              />
            </div>
          </div>
          <UFormField
            :label="t('productEditor.featured')"
            class="flex items-center justify-between rounded-md border border-default px-3 py-2"
          >
            <USwitch v-model="state.isFeatured" />
          </UFormField>
        </div>
      </UPageCard>
    </UForm>

    <template v-else-if="selectedTab === 'variants'">
      <UPageCard
        :title="t('products.variants')"
        :description="t('productVariantGeneration.description')"
        class="mb-4"
      >
        <template #footer>
          <UButton
            :label="t('catalogue.generateVariants', { count: combinations })"
            :disabled="!localizedPropertyGroups.length"
            @click="optionOpen = true"
          />
        </template>
      </UPageCard>
      <div v-if="variantsTotal" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <UInput
          v-model="variantsTableSearch"
          icon="i-lucide-search"
          :placeholder="t('products.searchVariants')"
          class="w-full sm:max-w-xl"
        />
        <UButton
          v-if="selectedVariantIds.length"
          :label="
            t('products.bulkDeleteVariants', {
              count: selectedVariantIds.length
            })
          "
          color="error"
          variant="outline"
          @click="variantIdsToDelete = selectedVariantIds"
        />
      </div>
      <AppDataTable
        v-model:row-selection="variantRowSelection"
        v-model:sorting="variantSorting"
        :data="variants"
        :columns="variantColumns"
        :get-row-id="(row) => row.id"
        :loading="variantsStatus === 'pending'"
        :max-height="null"
        server-sorting
        table-key="product-variants"
        :column-labels="{
          name: t('products.variantName'),
          sku: t('products.productNumber'),
          price: t('products.price')
        }"
      >
        <template #footer>
          <TablePaginationFooter
            v-if="variantsTotal"
            v-model:page="variantsPage"
            v-model:page-size="variantsPagination.pageSize"
            :total="variantsTotal"
            class="border-t-0 pt-0"
          />
        </template>
      </AppDataTable>
    </template>

    <template v-else-if="selectedTab === 'specifications'">
      <UPageCard
        :title="t('productSpecifications.title')"
        :description="t('productSpecifications.description')"
      >
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <UFormField :label="t('productSections.weight')">
            <UInput v-model="state.weightGrams" inputmode="numeric" class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.length')">
            <UInput v-model="state.lengthMillimeters" inputmode="numeric" class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.width')">
            <UInput v-model="state.widthMillimeters" inputmode="numeric" class="w-full" />
          </UFormField>
          <UFormField :label="t('productSections.height')">
            <UInput v-model="state.heightMillimeters" inputmode="numeric" class="w-full" />
          </UFormField>
        </div>
      </UPageCard>

      <UPageCard :title="t('productUnits.title')">
        <div class="grid gap-6 sm:grid-cols-2">
          <UFormField :label="t('productUnits.unit')">
            <USelect
              v-model="state.unitId"
              :items="
                (catalogueReferences?.units || []).map((unit) => ({
                  label: `${unit.labels[selectedLocale] || unit.labels[defaultLocale] || unit.code} (${unit.symbol})`,
                  value: unit.id
                }))
              "
              class="w-full"
            />
          </UFormField>
          <UFormField :label="t('productUnits.purchaseUnit')">
            <UInput v-model="state.purchaseUnit" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productUnits.referenceUnit')">
            <UInput v-model="state.referenceUnit" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productUnits.packUnit')">
            <UInput v-model="state.packUnit" class="w-full" />
          </UFormField>
          <UFormField :label="t('productUnits.packUnitPlural')">
            <UInput v-model="state.packUnitPlural" class="w-full" />
          </UFormField>
        </div>
      </UPageCard>
      <UPageCard
        :title="t('productProperties.title')"
        :description="t('productProperties.description')"
      >
        <div
          v-if="!localizedPropertyGroups.length"
          class="rounded-lg border border-dashed border-default px-6 py-12 text-center"
        >
          <p class="font-medium text-highlighted">
            {{ t('productProperties.emptyTitle') }}
          </p>
          <p class="mt-1 text-sm text-muted">
            {{ t('productProperties.emptyDescription') }}
          </p>
          <UButton
            class="mt-4"
            :label="t('productProperties.openGroups')"
            color="neutral"
            variant="outline"
            @click="navigateTo('/catalogue/attributes')"
          />
        </div>
        <div v-else-if="!selectedSpecificationGroups.length" class="py-16 text-center">
          <UIcon name="i-lucide-tags" class="mx-auto size-10 text-primary" />
          <p class="mt-4 font-medium text-highlighted">
            {{ t('productProperties.noneAssigned') }}
          </p>
          <p class="mx-auto mt-1 max-w-md text-sm text-muted">
            {{ t('productProperties.noneAssignedDescription') }}
          </p>
          <UButton
            class="mt-5"
            :label="t('productProperties.configure')"
            @click="openSpecifications"
          />
        </div>
        <div v-else>
          <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <UInput
              v-model="addedPropertiesSearch"
              icon="i-lucide-search"
              :placeholder="t('productProperties.searchAdded')"
              class="w-full sm:max-w-xl"
            />
            <div class="flex shrink-0 gap-2">
              <UButton
                v-if="selectedPropertyRowIds.length"
                :label="
                  t('productProperties.bulkDelete', {
                    count: selectedPropertyRowIds.length
                  })
                "
                color="error"
                variant="outline"
                @click="propertyIdsToRemove = selectedPropertyRowIds"
              />
              <UButton
                :label="t('productProperties.configure')"
                color="neutral"
                variant="outline"
                @click="openSpecifications"
              />
            </div>
          </div>
          <AppDataTable
            v-model:row-selection="propertyRowSelection"
            :data="paginatedProperties"
            :columns="productPropertyColumns"
            :get-row-id="(row) => row.id"
            :max-height="null"
            table-key="product-assigned-properties"
            :column-labels="{
              name: t('productProperties.property'),
              values: t('productProperties.propertyValues')
            }"
          >
            <template #footer>
              <TablePaginationFooter
                v-if="selectedSpecificationGroups.length"
                v-model:page="propertiesPage"
                v-model:page-size="propertiesPagination.pageSize"
                :total="propertiesTotal"
                class="border-t-0 pt-0"
              />
            </template>
          </AppDataTable>
        </div>
      </UPageCard>
    </template>

    <template v-else-if="selectedTab === 'seo'">
      <UPageCard :title="t('productSeo.title')" :description="t('productSeo.description')">
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
    </template>

    <template v-else-if="selectedTab === 'stock-movements'">
      <UPageCard
        :title="t('inventoryMovements.title')"
        :description="t('inventoryMovements.description')"
      >
        <div v-if="!movementsTotal" class="py-12 text-center text-sm text-muted">
          {{ t('inventoryMovements.empty') }}
        </div>
        <AppDataTable
          v-else
          :data="inventoryMovements"
          :columns="inventoryMovementColumns"
          :max-height="null"
          table-key="product-stock-movements"
          :column-labels="{
            createdAt: t('inventoryMovements.date'),
            warehouse: t('inventory.warehouse'),
            quantityDelta: t('inventoryMovements.change'),
            quantityAfter: t('inventory.stock'),
            note: t('inventory.note')
          }"
        >
          <template #footer>
            <TablePaginationFooter
              v-if="movementsTotal"
              v-model:page="movementsPage"
              v-model:page-size="movementsPagination.pageSize"
              :total="movementsTotal"
              class="border-t-0 pt-0"
            />
          </template>
        </AppDataTable>
      </UPageCard>
    </template>

    <template v-else-if="selectedTab === 'gallery'">
      <UPageCard :title="t('productGallery.title')" :description="t('productGallery.description')">
        <template #footer>
          <div class="flex justify-end">
            <input
              ref="mediaInput"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
              multiple
              class="hidden"
              @change="uploadMedia"
            >
            <UButton
              :label="t('productGallery.upload')"
              :loading="mediaUploading"
              @click="mediaInput?.click()"
            />
          </div>
        </template>

        <div v-if="mediaStatus === 'pending'" class="py-12 text-center text-muted">
          {{ t('common.loading') }}
        </div>
        <div
          v-else-if="!media.length"
          class="rounded-lg border border-dashed border-default px-6 py-14 text-center"
        >
          <UIcon name="i-lucide-images" class="mx-auto size-8 text-muted" />
          <p class="mt-3 font-medium text-highlighted">
            {{ t('productGallery.empty') }}
          </p>
          <p class="mt-1 text-sm text-muted">
            {{ t('productGallery.emptyDescription') }}
          </p>
        </div>
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <article
            v-for="(item, index) in media"
            :key="item.id"
            class="overflow-hidden rounded-lg border border-default bg-default"
          >
            <div class="relative aspect-square bg-elevated">
              <img
                :src="item.url"
                :alt="item.altText || product.name"
                class="size-full object-cover"
              >
              <UBadge v-if="index === 0" color="primary" class="absolute left-3 top-3">
                {{ t('productGallery.cover') }}
              </UBadge>
            </div>
            <div class="p-2">
              <p class="truncate text-xs text-muted" :title="item.fileName">
                {{ item.fileName }}
              </p>
              <div class="mt-2 flex items-center justify-between gap-1">
                <div class="flex items-center gap-1">
                  <UButton
                    icon="i-lucide-arrow-left"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :disabled="index === 0 || mediaUpdating"
                    :aria-label="t('productGallery.moveLeft')"
                    @click="moveMedia(index, -1)"
                  />
                  <UButton
                    icon="i-lucide-arrow-right"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :disabled="index === media.length - 1 || mediaUpdating"
                    :aria-label="t('productGallery.moveRight')"
                    @click="moveMedia(index, 1)"
                  />
                </div>
                <div class="flex items-center gap-1">
                  <UButton
                    v-if="index !== 0"
                    :label="t('productGallery.setCover')"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :disabled="mediaUpdating"
                    @click="setCoverMedia(item.id)"
                  />
                  <UButton
                    icon="i-lucide-trash-2"
                    color="error"
                    variant="ghost"
                    size="xs"
                    :loading="mediaDeleting === item.id"
                    :aria-label="t('common.delete')"
                    @click="deleteMedia(item.id)"
                  />
                </div>
              </div>
            </div>
          </article>
        </div>
      </UPageCard>
    </template>

    <template v-else-if="selectedTab === 'related'">
      <div class="grid gap-6 xl:grid-cols-2">
        <UPageCard
          :title="t('productRelations.downloads')"
          :description="t('productRelations.downloadsDescription')"
        >
          <div v-if="downloadsStatus === 'pending'" class="py-10 text-center text-muted">
            {{ t('common.loading') }}
          </div>
          <div
            v-else-if="!downloads.length"
            class="rounded-lg border border-dashed border-default px-6 py-10 text-center"
          >
            <UIcon name="i-lucide-file-down" class="mx-auto size-8 text-muted" />
            <p class="mt-3 font-medium text-highlighted">
              {{ t('productRelations.noDownloads') }}
            </p>
          </div>
          <div v-else class="divide-y divide-default">
            <div
              v-for="download in downloads"
              :key="download.id"
              class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
            >
              <div class="flex min-w-0 items-center gap-3">
                <UIcon name="i-lucide-file-text" class="size-5 shrink-0 text-muted" />
                <div class="min-w-0">
                  <p class="truncate font-medium text-highlighted">
                    {{ download.title || download.fileName }}
                  </p>
                  <p class="truncate text-sm text-muted">
                    {{ download.fileName }}<span v-if="download.mimeType"> · {{ download.mimeType }}</span>
                  </p>
                </div>
              </div>
              <UButton
                :to="download.url"
                target="_blank"
                icon="i-lucide-external-link"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="t('productRelations.openDownload', { name: download.title || download.fileName })"
              />
            </div>
          </div>
        </UPageCard>

        <UPageCard
          :title="t('productRelations.crossSellings')"
          :description="t('productRelations.crossSellingsDescription')"
        >
          <div v-if="crossSellingsStatus === 'pending'" class="py-10 text-center text-muted">
            {{ t('common.loading') }}
          </div>
          <div
            v-else-if="!crossSellings.length"
            class="rounded-lg border border-dashed border-default px-6 py-10 text-center"
          >
            <UIcon name="i-lucide-git-compare-arrows" class="mx-auto size-8 text-muted" />
            <p class="mt-3 font-medium text-highlighted">
              {{ t('productRelations.noCrossSellings') }}
            </p>
          </div>
          <div v-else class="space-y-4">
            <article
              v-for="group in crossSellings"
              :key="group.id"
              class="rounded-lg border border-default p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-medium text-highlighted">
                    {{ crossSellingLabel(group) }}
                  </p>
                  <p class="mt-1 text-sm text-muted">
                    {{ t('productRelations.productCount', { count: group.products.length }) }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <UBadge v-if="!group.active" color="neutral" variant="subtle">
                    {{ t('productRelations.inactive') }}
                  </UBadge>
                  <UBadge v-if="group.sourceProductStreamId" color="neutral" variant="subtle">
                    {{ t('productRelations.dynamicStream') }}
                  </UBadge>
                </div>
              </div>
              <p v-if="!group.products.length" class="mt-4 text-sm text-muted">
                {{ t('productRelations.noAssignedProducts') }}
              </p>
              <div v-else class="mt-4 flex flex-wrap gap-2">
                <UButton
                  v-for="assignedProduct in group.products"
                  :key="assignedProduct.id"
                  :to="`/catalogue/products/${assignedProduct.id}`"
                  :label="assignedProduct.name || t('products.product')"
                  color="neutral"
                  variant="outline"
                  size="sm"
                />
              </div>
            </article>
          </div>
        </UPageCard>
      </div>
    </template>

    <template v-else-if="selectedTab === 'extensions'">
      <div class="overflow-x-auto border-b border-default">
        <UTabs
          v-model="selectedCustomFieldSet"
          :items="customFieldSetTabs"
          :content="false"
          class="min-w-max"
        />
      </div>
      <UPageCard
        :title="
          selectedCustomFieldSet === 'uncategorized'
            ? t('productCustomFields.uncategorized')
            : activeCustomFieldSet
              ? setLabel(activeCustomFieldSet)
              : ''
        "
        :description="
          selectedCustomFieldSet === 'uncategorized'
            ? t('productCustomFields.uncategorizedDescription')
            : activeCustomFieldSet?.technicalName
        "
      >
        <div v-if="!activeCustomFields.length" class="py-10 text-center text-muted">
          {{ t('productCustomFields.emptySet') }}
        </div>
        <CustomFieldValuesForm
          v-else
          v-model="customFieldValues"
          :fields="activeCustomFields"
          :locale="selectedLocale"
          :fallback-locale="defaultLocale"
          :date-placeholder="t('productExtra.releaseDatePlaceholder')"
        />
      </UPageCard>
    </template>

    <template v-else-if="selectedTab === 'prices'">
      <div class="flex justify-end">
        <UButton
          :label="t('productAdvancedPriceTab.addRule')"
          @click="advancedRules.push({ id: Date.now(), rows: [newAdvancedRow()] })"
        />
      </div>

      <UPageCard
        v-for="(rule, ruleIndex) in advancedRules"
        :key="rule.id"
        :title="`${t('productAdvancedPriceTab.title')} ${ruleIndex + 1}`"
        :description="t('productRegularPrice.advancedDescription')"
        class="mb-6"
      >
        <div class="overflow-x-auto rounded-lg border border-default">
          <table class="w-full min-w-250 border-collapse">
            <thead class="bg-elevated/50">
              <tr class="text-left text-sm text-muted">
                <th class="w-28 px-4 py-3 font-medium">
                  {{ t('productAdvancedPrice.quantityFrom') }}
                </th>
                <th class="w-28 px-4 py-3 font-medium">
                  {{ t('productAdvancedPrice.quantityTo') }}
                </th>
                <th class="w-52 px-4 py-3 font-medium">
                  {{ t('productAdvancedPrice.priceType') }}
                </th>
                <th class="px-4 py-3 font-medium">
                  {{ advancedCurrency }}
                </th>
                <th class="w-14 px-2 py-3" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, index) in rule.rows"
                :key="`${rule.id}-${index}`"
                class="border-t border-default"
              >
                <td class="p-3 align-middle">
                  <UInput
                    v-model="row.from"
                    type="number"
                    min="1"
                    step="1"
                    :disabled="index === 0"
                    class="w-24"
                  />
                </td>
                <td class="p-3 align-middle">
                  <UInput
                    v-model="row.to"
                    type="number"
                    min="1"
                    step="1"
                    :placeholder="'∞'"
                    class="w-24"
                    @blur="addAdvancedScale(rule, index)"
                  />
                </td>
                <td class="p-3 align-middle">
                  <div class="space-y-1 text-xs font-normal text-muted">
                    <p>{{ t('productAdvancedPrice.price') }}</p>
                    <p>{{ t('productAdvancedPrice.listPrice') }}</p>
                    <p>{{ t('productAdvancedPrice.cheapestPrice') }}</p>
                  </div>
                </td>
                <td class="p-3 align-middle">
                  <div class="space-y-1">
                    <div
                      class="grid grid-cols-[minmax(0,1fr)_28px_minmax(0,1fr)] items-center gap-2"
                    >
                      <UInput
                        :model-value="row.gross"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        @update:model-value="
                          (value) => updateAdvancedPair(row, 'price', 'gross', value)
                        "
                      />
                      <UButton
                        :icon="row.priceChained ? 'i-lucide-link' : 'i-lucide-unlink'"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        class="justify-self-center"
                        :aria-label="t('productAdvancedPrice.price')"
                        @click="toggleAdvancedPriceChain(row, 'price')"
                      />
                      <UInput
                        :model-value="row.net"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        @update:model-value="
                          (value) => updateAdvancedPair(row, 'price', 'net', value)
                        "
                      />
                    </div>
                    <div
                      class="grid grid-cols-[minmax(0,1fr)_28px_minmax(0,1fr)] items-center gap-2"
                    >
                      <UInput
                        :model-value="row.listGross"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        @update:model-value="
                          (value) => updateAdvancedPair(row, 'list', 'gross', value)
                        "
                      />
                      <UButton
                        :icon="row.listChained ? 'i-lucide-link' : 'i-lucide-unlink'"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        class="justify-self-center"
                        :aria-label="t('productAdvancedPrice.listPrice')"
                        @click="toggleAdvancedPriceChain(row, 'list')"
                      />
                      <UInput
                        :model-value="row.listNet"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        @update:model-value="
                          (value) => updateAdvancedPair(row, 'list', 'net', value)
                        "
                      />
                    </div>
                    <div
                      class="grid grid-cols-[minmax(0,1fr)_28px_minmax(0,1fr)] items-center gap-2"
                    >
                      <UInput
                        :model-value="row.cheapestGross"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        @update:model-value="
                          (value) => updateAdvancedPair(row, 'cheapest', 'gross', value)
                        "
                      />
                      <UButton
                        :icon="row.cheapestChained ? 'i-lucide-link' : 'i-lucide-unlink'"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        class="justify-self-center"
                        :aria-label="t('productAdvancedPrice.cheapestPrice')"
                        @click="toggleAdvancedPriceChain(row, 'cheapest')"
                      />
                      <UInput
                        :model-value="row.cheapestNet"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        @update:model-value="
                          (value) => updateAdvancedPair(row, 'cheapest', 'net', value)
                        "
                      />
                    </div>
                  </div>
                </td>
                <td class="p-3 align-middle text-right">
                  <UDropdownMenu :items="tierActions(rule, index)">
                    <UButton
                      icon="i-lucide-ellipsis-vertical"
                      color="neutral"
                      variant="ghost"
                      :aria-label="t('common.delete')"
                    />
                  </UDropdownMenu>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <template #footer>
          <div class="flex justify-end">
            <UButton
              :label="t('common.delete')"
              color="error"
              variant="ghost"
              @click="removeAdvancedRule(rule.id)"
            />
          </div>
        </template>
      </UPageCard>
    </template>

    <UPageCard
      v-else
      :title="tabs.find((tab) => tab.value === selectedTab)?.label"
      :description="t('products.tabPreparation')"
    />
  </div>

  <UModal
    v-model:open="duplicateMediaOpen"
    :title="t('productGallery.duplicateTitle')"
    :description="t('productGallery.duplicateDescription')"
  >
    <template #body>
      <ul class="list-disc space-y-1 pl-5 text-sm text-muted">
        <li v-for="name in duplicateMediaNames" :key="name">
          {{ name }}
        </li>
      </ul>
    </template>
    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton
          :label="t('common.cancel')"
          color="neutral"
          variant="subtle"
          @click="cancelDuplicateMediaUpload"
        />
        <UButton
          :label="t('productGallery.addWithNewName')"
          color="neutral"
          variant="outline"
          :loading="mediaUploading"
          @click="uploadMediaFiles('rename')"
        />
        <UButton
          :label="t('productGallery.replace')"
          :loading="mediaUploading"
          @click="uploadMediaFiles('replace')"
        />
      </div>
    </template>
  </UModal>

  <UModal
    v-model:open="specificationsOpen"
    :title="t('productProperties.configure')"
    :description="t('productProperties.modalDescription')"
    :ui="{ content: 'max-w-6xl' }"
  >
    <template #body>
      <div class="grid min-h-120 grid-cols-[280px_minmax(0,1fr)]">
        <aside class="border-r border-default p-4">
          <p class="mb-4 text-sm text-muted">
            {{ t('productProperties.selectGroup') }}
          </p>
          <div class="space-y-1">
            <button
              v-for="group in localizedPropertyGroups"
              :key="group.id"
              type="button"
              class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm"
              :class="
                activeSpecificationGroup?.id === group.id
                  ? 'bg-elevated font-medium text-highlighted'
                  : 'text-muted hover:bg-elevated/60'
              "
              @click="activeSpecificationGroupId = group.id"
            >
              <span class="truncate">{{ group.name }}</span>
              <span
                v-if="
                  group.properties.filter((property) =>
                    specificationPropertyIds.includes(property.id)
                  ).length
                "
                class="ml-3 text-xs"
              >
                {{
                  group.properties.filter((property) =>
                    specificationPropertyIds.includes(property.id)
                  ).length
                }}
              </span>
            </button>
          </div>
        </aside>
        <section class="p-5">
          <UInput
            v-model="specificationSearch"
            icon="i-lucide-search"
            :placeholder="t('productProperties.searchValues')"
            class="mb-5 w-full"
          />
          <div v-if="activeSpecificationGroup" class="space-y-2">
            <p class="text-sm font-medium text-highlighted">
              {{ activeSpecificationGroup.name }}
            </p>
            <UCheckbox
              v-for="property in visibleSpecificationProperties"
              :key="property.id"
              :label="property.name"
              :model-value="specificationPropertyIds.includes(property.id)"
              class="flex rounded-md px-3 py-2 hover:bg-elevated/60"
              @update:model-value="
                (value: boolean | 'indeterminate') =>
                  toggleSpecificationProperty(property.id, Boolean(value))
              "
            />
            <p
              v-if="!visibleSpecificationProperties.length"
              class="py-8 text-center text-sm text-muted"
            >
              {{ t('productProperties.noValues') }}
            </p>
          </div>
        </section>
      </div>
    </template>
    <template #footer>
      <div class="flex w-full items-center justify-between gap-3">
        <p class="text-sm text-muted">
          {{
            t('productProperties.selectedCount', {
              count: specificationPropertyIds.length
            })
          }}
        </p>
        <div class="flex gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="specificationsOpen = false"
          />
          <UButton
            :label="t('common.save')"
            :loading="specificationsSaving"
            @click="saveSpecifications"
          />
        </div>
      </div>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="propertyRemovalOpen"
    :title="t('productProperties.removeTitle')"
    :description="
      t('productProperties.removeDescription', {
        count: propertyIdsToRemove.length
      })
    "
    :confirm-label="t('common.delete')"
    :loading="specificationsSaving"
    @confirm="removeProductProperties"
  />

  <UModal
    v-model:open="stockAdjustmentOpen"
    :title="t('inventory.adjustStock')"
    :description="t('inventory.adjustStockDescription')"
  >
    <template #body>
      <UForm class="space-y-5" @submit.prevent="saveStockAdjustment">
        <UFormField
          :label="t('inventory.warehouse')"
          :error="stockValidationErrors.warehouseId"
          required
        >
          <USelect
            v-model="stockForm.warehouseId"
            :items="
              warehouses.map((warehouse) => ({
                label: warehouse.name,
                value: warehouse.id
              }))
            "
            class="w-full"
            @update:model-value="updateStockWarehouse"
          />
        </UFormField>
        <UFormField :label="t('inventory.stock')" :error="stockValidationErrors.quantity" required>
          <UInput
            v-model="stockForm.quantity"
            type="number"
            step="0.0001"
            class="w-full"
            @update:model-value="stockValidation.clear('quantity')"
          />
        </UFormField>
        <UFormField :label="t('inventory.note')">
          <UTextarea v-model="stockForm.note" class="w-full" :rows="3" />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="stockAdjustmentOpen = false"
          /><UButton :label="t('common.save')" type="submit" :loading="stockSaving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <ConfirmationModal
    v-model:open="variantRemovalOpen"
    :title="t('products.deleteVariantsTitle')"
    :description="
      t('products.deleteVariantsDescription', {
        count: variantIdsToDelete.length
      })
    "
    :confirm-label="t('common.delete')"
    :loading="variantsDeleting"
    @confirm="removeVariants"
  />

  <UModal
    v-model:open="optionOpen"
    :title="t('productVariantGeneration.open')"
    :ui="{ content: 'max-w-6xl' }"
  >
    <template #body>
      <div class="grid min-h-120 grid-cols-[280px_minmax(0,1fr)]">
        <aside class="border-r border-default p-4">
          <p class="mb-4 text-sm text-muted">
            {{ t('productVariantGeneration.description') }}
          </p>
          <div class="space-y-1">
            <button
              v-for="group in localizedPropertyGroups"
              :key="group.id"
              type="button"
              class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm"
              :class="
                activeVariantGroup?.id === group.id
                  ? 'bg-elevated font-medium text-highlighted'
                  : 'text-muted hover:bg-elevated/60'
              "
              @click="activeVariantGroupId = group.id"
            >
              <span>{{ group.name }}</span><span v-if="(variantPropertyIds[group.id] || []).length" class="text-xs">{{
                (variantPropertyIds[group.id] || []).length
              }}</span>
            </button>
          </div>
        </aside>
        <section class="p-5">
          <UInput
            v-model="variantSearch"
            icon="i-lucide-search"
            :placeholder="t('products.search')"
            class="mb-5 w-full"
          />
          <div v-if="activeVariantGroup" class="space-y-2">
            <p class="text-sm font-medium text-highlighted">
              {{ activeVariantGroup.name }}
            </p>
            <UCheckbox
              v-for="property in visibleVariantProperties"
              :key="property.id"
              :label="property.name"
              :model-value="(variantPropertyIds[activeVariantGroupId] || []).includes(property.id)"
              class="flex rounded-md px-3 py-2 hover:bg-elevated/60"
              @update:model-value="
                (value: boolean | 'indeterminate') =>
                  (variantPropertyIds[activeVariantGroupId] = Boolean(value)
                    ? [
                      ...new Set([
                        ...(variantPropertyIds[activeVariantGroupId] || []),
                        property.id
                      ])
                    ]
                    : (variantPropertyIds[activeVariantGroupId] || []).filter(
                      (id) => id !== property.id
                    ))
              "
            />
          </div>
        </section>
      </div>
    </template>
    <template #footer>
      <div class="flex w-full items-center justify-between gap-3">
        <p class="text-sm text-muted">
          {{ combinations }} combinations
        </p>
        <div class="flex gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="optionOpen = false"
          /><UButton
            :label="t('catalogue.generateVariants', { count: combinations })"
            :disabled="!combinations"
            :loading="generating"
            @click="generateVariants"
          />
        </div>
      </div>
    </template>
  </UModal>

  <UModal
    v-model:open="priceOpen"
    :title="t('productAdvancedPrice.add')"
    :description="t('productAdvancedPrice.description')"
  >
    <template #body>
      <UForm class="space-y-4" @submit.prevent="addPrice">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField :label="t('productPrices.currency')" :error="priceValidationErrors.currency" required>
            <USelect
              v-model="priceForm.currency"
              :items="
                currencies.map((currency) => ({
                  label: `${currency.code} (${currency.symbol})`,
                  value: currency.code
                }))
              "
              class="w-full"
              @update:model-value="priceValidation.clear('currency')"
            />
          </UFormField>
          <UFormField :label="t('productPrices.tax')" :error="priceValidationErrors.taxRate" required>
            <UInput
              v-model="priceForm.taxRate"
              inputmode="decimal"
              class="w-full"
              @update:model-value="priceValidation.clear('taxRate')"
            />
          </UFormField>
          <UFormField :label="t('productPriceLabels.net')" :error="priceValidationErrors.netAmount" required>
            <UInput
              v-model="priceForm.netAmount"
              inputmode="decimal"
              class="w-full"
              @update:model-value="priceValidation.clear('netAmount')"
            />
          </UFormField>
          <UFormField :label="t('productPriceLabels.gross')" :error="priceValidationErrors.grossAmount" required>
            <UInput
              v-model="priceForm.grossAmount"
              inputmode="decimal"
              class="w-full"
              @update:model-value="priceValidation.clear('grossAmount')"
            />
          </UFormField>
          <UFormField :label="t('productAdvancedPrice.listNet')">
            <UInput v-model="priceForm.listNetAmount" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productAdvancedPrice.listGross')">
            <UInput v-model="priceForm.listGrossAmount" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productAdvancedPrice.quantityFrom')" :error="priceValidationErrors.quantityStart" required>
            <UInput
              v-model="priceForm.quantityStart"
              inputmode="decimal"
              class="w-full"
              @update:model-value="priceValidation.clear('quantityStart')"
            />
          </UFormField>
          <UFormField :label="t('productAdvancedPrice.quantityTo')">
            <UInput v-model="priceForm.quantityEnd" inputmode="decimal" class="w-full" />
          </UFormField>
          <UFormField :label="t('productAdvancedPrice.validFrom')">
            <UInput v-model="priceForm.validFrom" type="date" class="w-full" />
          </UFormField>
          <UFormField :label="t('productAdvancedPrice.validUntil')">
            <UInput v-model="priceForm.validUntil" type="date" class="w-full" />
          </UFormField>
        </div>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="priceOpen = false"
          /><UButton :label="t('common.save')" type="submit" :loading="priceSaving" />
        </div>
      </UForm>
    </template>
  </UModal>

  <CategoryPickerModal
    v-model:open="categoryPickerOpen"
    v-model="selectedCategoryIds"
    :categories="categoriesData?.categories ?? []"
    :locale="selectedLocale"
    :fallback-locale="defaultLocale"
    :title="t('productCategories.select')"
    :search-placeholder="t('products.search')"
    :empty-label="t('categories.emptyTitle')"
    :save-label="t('productCategories.select')"
  />
</template>

<style scoped>
:deep(table tbody td.font-medium) {
  padding-top: 0.25rem;
  padding-bottom: 0.25rem;
  font-size: 12px;
  font-weight: 400;
}

:deep(table tbody td:has(> .p-1)) {
  text-align: center;
}

:deep(table tbody td:nth-child(5)) {
  text-align: center;
}
</style>

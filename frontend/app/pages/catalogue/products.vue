<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Row, SortingState } from '@tanstack/table-core'

type Product = {
  id: string
  name: string
  seoUrl: string
  translations: Record<string, { name: string, seoUrl: string | null }>
  shortDescription: string | null
  sku: string | null
  status: 'draft' | 'active' | 'archived'
  manufacturer: string | null
  coverUrl: string | null
  hasVariants: boolean
  listingPrice: {
    grossAmount: number | null
    currency: string | null
  }
  stock: number
  updatedAt: string
}

const { t } = useI18n()
const route = useRoute()
const auth = useAuth()
const toast = useAppToast()
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const open = ref(false)
const saving = ref(false)
const productsDeleting = ref(false)
const productIdsToDelete = ref<string[]>([])
const productRowSelection = ref<Record<string, boolean>>({})
const productSorting = ref<SortingState>([])
const formValidation = useFormValidation()
const validationErrors = formValidation.errors
const form = reactive({ name: '' })
const selectedLocale = ref(auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const defaultLocale = computed(() => auth.tenant.value?.defaultSnippetLocale || 'en-GB')
const currentPage = ref(1)
const pagination = reactive({ pageSize: 25 })
const statusFilter = ref('all')
const nameFilter = ref('')
const debouncedNameFilter = ref('')
const serverSorting = computed(() => {
  const sorting = productSorting.value[0]

  return ['name', 'sku', 'status', 'updatedAt'].includes(sorting?.id || '')
    ? sorting
    : undefined
})
const productListUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(currentPage.value),
    limit: String(pagination.pageSize)
  })

  if (debouncedNameFilter.value) {
    params.set('search', debouncedNameFilter.value)
  }
  if (statusFilter.value !== 'all') {
    params.set('status', statusFilter.value)
  }
  if (serverSorting.value) {
    params.set('sort', serverSorting.value.id)
    params.set('direction', serverSorting.value.desc ? 'DESC' : 'ASC')
  }

  return `/products?${params.toString()}`
})
const { data, status, refresh } = await useAsyncData(
  'catalogue-products',
  () => apiFetch<{ products: Product[], pagination: { total: number } }>(productListUrl.value)
)
const products = computed(() => data.value?.products ?? [])
const selectedProductIds = computed(() =>
  Object.entries(productRowSelection.value)
    .filter(([, selected]) => selected)
    .map(([id]) => id)
)
const productRemovalOpen = computed({
  get: () => productIdsToDelete.value.length > 0,
  set: (open: boolean) => {
    if (!open) {
      productIdsToDelete.value = []
    }
  }
})
const localizedProducts = computed(() =>
  products.value.map((product) => {
    const translation
      = product.translations[selectedLocale.value]
        || product.translations[defaultLocale.value]

    return {
      ...product,
      name: translation?.name || product.name,
      seoUrl: translation?.seoUrl || product.seoUrl
    }
  })
)
const { data: localesData } = await useAsyncData('catalogue-product-locales', () =>
  apiFetch<{ locales: { code: string, label: string }[] }>('/products/locales')
)
const localeItems = computed(() => localesData.value?.locales ?? [])

const statusItems = computed(() => [
  { label: t('products.allStatuses'), value: 'all' },
  { label: t('products.active'), value: 'active' },
  { label: t('products.draft'), value: 'draft' },
  { label: t('products.archived'), value: 'archived' }
])
const paginatedProducts = localizedProducts
const totalResults = computed(() => data.value?.pagination.total ?? 0)

const statusColor = (status: Product['status']): 'success' | 'warning' | 'neutral' => {
  if (status === 'active') return 'success'
  if (status === 'draft') return 'warning'
  return 'neutral'
}
const productStatus = (status: Product['status']) => t(`products.${status}`)
const date = (value: string) => {
  const parsed = new Date(value)
  return `${String(parsed.getDate()).padStart(2, '0')}.${String(parsed.getMonth() + 1).padStart(2, '0')}.${parsed.getFullYear()}.`
}
const price = (product: Product) => {
  const amount = product.listingPrice.grossAmount

  if (amount === null || !product.listingPrice.currency) {
    return '—'
  }

  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: product.listingPrice.currency
  }).format(amount / 100)
}

const getRowItems = (row: Row<Product>) => [
  [
    {
      label: t('products.open'),
      icon: 'i-lucide-arrow-up-right',
      onSelect: () => navigateTo(`/catalogue/products/${row.original.id}`)
    }
  ],
  [
    {
      label: t('common.delete'),
      icon: 'i-lucide-trash',
      color: 'error' as const,
      onSelect: () => requestProductRemoval([row.original.id])
    }
  ]
]

const columns: TableColumn<Product>[] = [
  {
    accessorKey: 'name',
    header: t('products.product'),
    cell: ({ row }) =>
      h('div', { class: 'flex min-w-0 items-center gap-3' }, [
        h('img', {
          src: row.original.coverUrl || '/placeholder-light.webp',
          alt: row.original.name,
          class: 'size-8 shrink-0 rounded-md border border-default object-cover'
        }),
        h('div', { class: 'flex min-w-0 items-center gap-1.5' }, [
          h(
            'button',
            {
              class: 'cursor-pointer truncate text-left font-medium text-highlighted hover:text-primary',
              onClick: () => navigateTo(`/catalogue/products/${row.original.id}`)
            },
            row.original.name
          ),
          row.original.hasVariants
            ? h(UButton, {
                icon: 'i-lucide-git-branch',
                color: 'neutral',
                variant: 'ghost',
                size: 'xs',
                title: t('products.openVariants'),
                ariaLabel: t('products.openVariants'),
                onClick: () => navigateTo(`/catalogue/products/${row.original.id}?tab=variants`)
              })
            : null
        ])
      ])
  },
  {
    accessorKey: 'sku',
    header: t('products.productNumber'),
    cell: ({ row }) => row.original.sku || '—'
  },
  {
    accessorKey: 'manufacturer',
    enableSorting: false,
    header: t('products.manufacturer'),
    cell: ({ row }) => row.original.manufacturer || '—'
  },
  {
    accessorKey: 'listingPrice',
    enableSorting: false,
    header: t('products.price'),
    cell: ({ row }) => price(row.original)
  },
  {
    accessorKey: 'stock',
    enableSorting: false,
    header: t('products.stock'),
    cell: ({ row }) => row.original.stock
  },
  {
    accessorKey: 'status',
    header: t('products.status'),
    filterFn: 'equals',
    cell: ({ row }) =>
      h(UBadge, { color: statusColor(row.original.status), variant: 'subtle' }, () =>
        productStatus(row.original.status)
      )
  },
  {
    accessorKey: 'updatedAt',
    header: t('products.updated'),
    cell: ({ row }) => date(row.original.updatedAt)
  },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) =>
      h(
        'div',
        { class: 'text-right' },
        h(UDropdownMenu, { items: getRowItems(row), content: { align: 'end' } }, () =>
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

watch(
  () => route.params.id,
  (productId, previousProductId) => {
    if (!productId && previousProductId) {
      void refresh()
    }
  }
)

let searchDebounce: ReturnType<typeof setTimeout> | undefined

watch(nameFilter, (value) => {
  currentPage.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    debouncedNameFilter.value = value.trim()
  }, 300)
})
watch([statusFilter, productSorting], () => {
  currentPage.value = 1
})
watch(
  () => pagination.pageSize,
  () => {
    currentPage.value = 1
  }
)
watch(productListUrl, () => {
  void refresh()
})

const openCreate = () => {
  selectedLocale.value = defaultLocale.value
  form.name = ''
  formValidation.clear()
  open.value = true
}

const requestProductRemoval = (productIds: string[]) => {
  productIdsToDelete.value = productIds
}

const errorMessage = (error: unknown, fallback: string): string => {
  const message = (error as { data?: { message?: unknown } })?.data?.message

  return typeof message === 'string' && message !== '' ? message : fallback
}

const removeProducts = async () => {
  productsDeleting.value = true

  try {
    await apiFetch('/products', {
      method: 'DELETE',
      body: { ids: productIdsToDelete.value }
    })
    productRowSelection.value = {}
    productIdsToDelete.value = []
    await refresh()
    toast.success(t('products.deleted'), t('common.changesSaved'))
  } catch (error: unknown) {
    toast.error(t('common.error'), errorMessage(error, t('products.deleteFailed')))
  } finally {
    productsDeleting.value = false
  }
}

const add = async () => {
  const missingLabels = formValidation.validateRequired([
    {
      field: 'name',
      value: form.name,
      label: t('products.name'),
      message: t('common.requiredField')
    }
  ])
  if (missingLabels.length > 0) {
    toast.error(
      t('products.createFailed'),
      t('common.requiredFields', { fields: missingLabels.join(', ') })
    )
    return
  }

  saving.value = true
  try {
    const response = await apiFetch<{ product: Product }>('/products', {
      method: 'POST',
      body: { name: form.name }
    })
    await refresh()
    open.value = false
    form.name = ''
    toast.success(t('products.created'), t('common.changesSaved'))
    await navigateTo(`/catalogue/products/${response.product.id}`)
  } catch (error: unknown) {
    formValidation.notifyApiError(
      error,
      toast,
      t('common.error'),
      t('products.createFailed')
    )
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <NuxtPage v-if="route.params.id" />

  <template v-else>
    <div>
      <AppDataTable
        v-model:row-selection="productRowSelection"
        v-model:sorting="productSorting"
        :data="paginatedProducts"
        :columns="columns"
        :get-row-id="(row) => row.id"
      :loading="status === 'pending'"
      server-sorting
      selectable
        table-key="catalogue-products"
        :column-labels="{
          name: t('products.product'),
          sku: t('products.productNumber'),
          manufacturer: t('products.manufacturer'),
          status: t('products.status'),
          listingPrice: t('products.price'),
          stock: t('products.stock'),
          updatedAt: t('products.updated')
        }"
      >
        <template #header>
          <div class="flex w-full flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
              <p class="whitespace-nowrap text-sm font-medium text-highlighted">
                {{ t('nav.products') }} ({{ totalResults }})
              </p>
              <UInput
                v-model="nameFilter"
                class="w-full sm:w-72"
                icon="i-lucide-search"
                :placeholder="t('products.search')"
              />
              <UButton
                v-if="selectedProductIds.length"
                :label="t('products.bulkDelete', { count: selectedProductIds.length })"
                color="error"
                variant="outline"
                @click="requestProductRemoval(selectedProductIds)"
              />
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <USelect v-model="statusFilter" :items="statusItems" class="min-w-32" />
              <LocaleSelect
                v-model="selectedLocale"
                :options="localeItems"
                class="min-w-48"
              />
              <UButton :label="t('products.add')" @click="openCreate" />
            </div>
          </div>
        </template>

        <template #footer>
          <TablePaginationFooter
            v-model:page="currentPage"
            v-model:page-size="pagination.pageSize"
            :total="totalResults"
            class="border-t-0 pt-0"
          />
        </template>
      </AppDataTable>
    </div>

    <UModal
      v-model:open="open"
      :title="t('products.add')"
      :description="t('products.addDescription')"
    >
      <template #body>
        <UForm class="space-y-4" @submit.prevent="add">
          <ModalLocaleField
            v-model="selectedLocale"
            :options="localeItems"
            :default-locale="defaultLocale"
            :active="open"
            creating
          />
          <UFormField
            :label="t('products.name')"
            :error="validationErrors.name"
            name="name"
            required
          >
            <UInput
              v-model="form.name"
              autofocus
              class="w-full"
              @update:model-value="formValidation.clear('name')"
            />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton
              :label="t('common.cancel')"
              color="neutral"
              variant="subtle"
              @click="open = false"
            />
            <UButton :label="t('common.save')" type="submit" :loading="saving" />
          </div>
        </UForm>
      </template>
    </UModal>

    <ConfirmationModal
      v-model:open="productRemovalOpen"
      :title="t('products.deleteTitle')"
      :description="t('products.deleteDescription', { count: productIdsToDelete.length })"
      :confirm-label="t('common.delete')"
      :loading="productsDeleting"
      @confirm="removeProducts"
    />
  </template>
</template>

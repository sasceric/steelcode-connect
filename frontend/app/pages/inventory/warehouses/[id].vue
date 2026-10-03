<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'

type Warehouse = {
  id: string
  name: string
  code: string
}
type WarehouseStockItem = {
  productId: string
  name: string
  sku: string | null
  stock: number
  reservedStock: number
  unavailableStock: number
  availableStock: number
  incomingStock: number
}
type WarehouseStockResponse = {
  warehouse: Warehouse
  items: WarehouseStockItem[]
  pagination: { total: number }
}

const localePath = useLocalePath()
const route = useRoute()
const { t } = useI18n()
const search = ref('')
const debouncedSearch = ref('')
const sorting = ref<SortingState>([])
const page = ref(1)
const pageSize = ref(25)
let searchDebounce: ReturnType<typeof setTimeout> | undefined

const serverSorting = computed(() => {
  const current = sorting.value[0]

  return [
    'name',
    'sku',
    'stock',
    'reservedStock',
    'unavailableStock',
    'availableStock',
    'incomingStock'
  ].includes(current?.id || '')
    ? current
    : undefined
})
const stockUrl = computed(() => {
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(pageSize.value)
  })
  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  if (serverSorting.value) {
    params.set('sort', serverSorting.value.id)
    params.set('direction', serverSorting.value.desc ? 'DESC' : 'ASC')
  }

  return `/inventory/warehouses/${route.params.id}/stock?${params.toString()}`
})
const { data, status, refresh } = await useAsyncData(
  `inventory-warehouse-stock-${route.params.id}`,
  () => apiFetch<WarehouseStockResponse>(stockUrl.value)
)
const items = computed(() => data.value?.items ?? [])
const warehouse = computed(() => data.value?.warehouse)
const total = computed(() => data.value?.pagination.total ?? 0)
const columns: TableColumn<WarehouseStockItem>[] = [
  {
    accessorKey: 'name',
    header: () => t('products.name'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-highlighted hover:text-primary',
          onClick: () => navigateTo(localePath(`/catalogue/products/${row.original.productId}`))
        },
        row.original.name
      )
  },
  {
    accessorKey: 'sku',
    header: () => t('products.productNumber'),
    cell: ({ row }) => row.original.sku || '—'
  },
  { accessorKey: 'stock', header: () => t('inventory.stock') },
  { accessorKey: 'reservedStock', header: () => t('inventory.reservedStock') },
  { accessorKey: 'unavailableStock', header: () => t('inventory.unavailableStock') },
  { accessorKey: 'availableStock', header: () => t('inventory.availableStock') },
  { accessorKey: 'incomingStock', header: () => t('inventory.incomingStock') }
]

watch(search, (value) => {
  page.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    debouncedSearch.value = value.trim()
  }, 300)
})
watch(sorting, () => { page.value = 1 })
watch(pageSize, () => { page.value = 1 })
watch(stockUrl, () => { void refresh() })
</script>

<template>
  <AppDataTable
    v-model:sorting="sorting"
    :data="items"
    :columns="columns"
    :get-row-id="(row) => row.productId"
    :loading="status === 'pending'"
    server-sorting
    table-key="warehouse-stock"
    :column-labels="{
      name: t('products.name'),
      sku: t('products.productNumber'),
      stock: t('inventory.stock'),
      reservedStock: t('inventory.reservedStock'),
      unavailableStock: t('inventory.unavailableStock'),
      availableStock: t('inventory.availableStock'),
      incomingStock: t('inventory.incomingStock')
    }"
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center gap-3">
        <UButton
          icon="i-lucide-arrow-left"
          color="neutral"
          variant="ghost"
          :aria-label="t('nav.warehouses')"
          @click="navigateTo(localePath('/inventory/warehouses'))"
        />
        <p class="whitespace-nowrap text-sm font-medium text-highlighted">
          {{ warehouse?.name || t('inventory.warehouse') }} · {{ t('inventory.stock') }} ({{ total }})
        </p>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          :placeholder="t('inventory.searchStock')"
          class="w-full sm:w-72"
        />
      </div>
    </template>

    <template #empty>
      <AppEmptyState
        :title="t('inventory.emptyStock')"
        :description="t('inventory.emptyStockDescription')"
        icon="i-lucide-package-search"
      />
    </template>

    <template #footer>
      <TablePaginationFooter
        v-model:page="page"
        v-model:page-size="pageSize"
        :total="total"
        class="border-t-0 pt-0"
      />
    </template>
  </AppDataTable>
</template>

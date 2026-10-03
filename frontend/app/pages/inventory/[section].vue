<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { SortingState } from '@tanstack/table-core'

type StockItem = {
  productId: string
  name: string
  sku: string | null
  stock: number
  availableStock: number
  unavailableStock: number
  incomingStock: number
}
type Warehouse = {
  id: string
  code: string
  name: string
  active: boolean
  fulfillmentEnabled: boolean
  priority: number
  default: boolean
}

const localePath = useLocalePath()
const route = useRoute()
const { t } = useI18n()
const notify = useAppToast()
const section = computed(() =>
  ['warehouses'].includes(String(route.params.section))
    ? String(route.params.section)
    : 'stock'
)
const search = ref('')
const debouncedSearch = ref('')
const stockSorting = ref<SortingState>([])
const warehouseSorting = ref<SortingState>([])
const stockPage = ref(1)
const warehousesPage = ref(1)
const stockPagination = reactive({ pageSize: 25 })
const warehousesPagination = reactive({ pageSize: 25 })
const warehouseOpen = ref(false)
const warehouseSaveError = ref('')
const saving = ref(false)
const editingWarehouse = ref<Warehouse | null>(null)
const warehouseForm = reactive({
  name: '',
  code: '',
  active: true,
  fulfillmentEnabled: true,
  priority: 0
})
const warehouseValidation = useFormValidation()
const warehouseValidationErrors = warehouseValidation.errors
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const stockServerSorting = computed(() => {
  const sorting = stockSorting.value[0]

  return ['name', 'sku', 'stock', 'availableStock'].includes(sorting?.id || '')
    ? sorting
    : undefined
})
const warehouseServerSorting = computed(() => {
  const sorting = warehouseSorting.value[0]

  return ['name', 'code', 'active', 'priority'].includes(sorting?.id || '') ? sorting : undefined
})
const stockListUrl = computed(() => {
  const params = new URLSearchParams({ page: String(stockPage.value), limit: String(stockPagination.pageSize) })
  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  if (stockServerSorting.value) {
    params.set('sort', stockServerSorting.value.id)
    params.set('direction', stockServerSorting.value.desc ? 'DESC' : 'ASC')
  }
  return `/inventory/stock?${params.toString()}`
})
const warehouseListUrl = computed(() => {
  const params = new URLSearchParams({ page: String(warehousesPage.value), limit: String(warehousesPagination.pageSize) })
  if (debouncedSearch.value) params.set('search', debouncedSearch.value)
  if (warehouseServerSorting.value) {
    params.set('sort', warehouseServerSorting.value.id)
    params.set('direction', warehouseServerSorting.value.desc ? 'DESC' : 'ASC')
  }
  return `/inventory/warehouses?${params.toString()}`
})
const {
  data: stockData,
  status: stockStatus,
  refresh: refreshStock
} = await useAsyncData('inventory-stock', () =>
  apiFetch<{ items: StockItem[], pagination: { total: number } }>(stockListUrl.value)
)
const {
  data: warehousesData,
  status: warehousesStatus,
  refresh: refreshWarehouses
} = await useAsyncData('inventory-warehouses-page', () =>
  apiFetch<{ warehouses: Warehouse[], pagination: { total: number } }>(warehouseListUrl.value)
)
const paginatedStockItems = computed(() => stockData.value?.items ?? [])
const paginatedWarehouses = computed(() => warehousesData.value?.warehouses ?? [])
const stockTotal = computed(() => stockData.value?.pagination?.total ?? 0)
const warehousesTotal = computed(() => warehousesData.value?.pagination?.total ?? 0)

const stockColumns: TableColumn<StockItem>[] = [
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
  {
    accessorKey: 'stock',
    header: () => t('inventory.stock')
  },
  {
    accessorKey: 'availableStock',
    header: () => t('inventory.availableStock')
  },
  {
    accessorKey: 'unavailableStock',
    header: () => t('inventory.unavailableStock')
  },
  {
    accessorKey: 'incomingStock',
    header: () => t('inventory.incomingStock')
  }
]
const warehouseActions = (warehouse: Warehouse) => [
  [
    {
      label: t('common.edit'),
      icon: 'i-lucide-pencil',
      onSelect: () => openWarehouse(warehouse)
    }
  ]
]
const warehouseColumns: TableColumn<Warehouse>[] = [
  {
    accessorKey: 'name',
    header: () => t('inventory.warehouse'),
    cell: ({ row }) =>
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-highlighted hover:text-primary',
          onClick: () => navigateTo(localePath(`/inventory/warehouses/${row.original.id}`))
        },
        row.original.name
      )
  },
  { accessorKey: 'code', header: () => t('inventory.code') },
  {
    accessorKey: 'active',
    header: () => t('inventory.status'),
    cell: ({ row }) =>
      h(
        UBadge,
        {
          color: row.original.active ? 'success' : 'neutral',
          variant: 'subtle'
        },
        () => (row.original.active ? t('inventory.active') : t('inventory.inactive'))
      )
  },
  {
    accessorKey: 'fulfillmentEnabled',
    header: () => t('inventory.fulfillment'),
    cell: ({ row }) =>
      h(
        UBadge,
        {
          color: row.original.fulfillmentEnabled ? 'success' : 'neutral',
          variant: 'subtle'
        },
        () =>
          row.original.fulfillmentEnabled
            ? t('inventory.fulfillmentEnabled')
            : t('inventory.fulfillmentDisabled')
      )
  },
  {
    accessorKey: 'priority',
    header: () => t('inventory.fulfillmentPriority')
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
        h(UDropdownMenu, { items: warehouseActions(row.original), content: { align: 'end' } }, () =>
          h(UButton, {
            'icon': 'i-lucide-ellipsis-vertical',
            'color': 'neutral',
            'variant': 'ghost',
            'aria-label': t('common.edit')
          })
        )
      )
  }
]

const openWarehouse = (warehouse?: Warehouse) => {
  warehouseValidation.clear()
  warehouseSaveError.value = ''
  editingWarehouse.value = warehouse || null
  Object.assign(
    warehouseForm,
    warehouse
      ? {
          name: warehouse.name,
          code: warehouse.code,
          active: warehouse.active,
          fulfillmentEnabled: warehouse.fulfillmentEnabled,
          priority: warehouse.priority
        }
      : {
          name: '',
          code: '',
          active: true,
          fulfillmentEnabled: true,
          priority: 0
        }
  )
  warehouseOpen.value = true
}
const saveWarehouse = async () => {
  warehouseSaveError.value = ''
  const fields = [
    {
      field: 'name',
      value: warehouseForm.name,
      label: t('inventory.warehouse'),
      message: t('common.requiredField')
    },
    ...(editingWarehouse.value?.default
      ? []
      : [
          {
            field: 'code',
            value: warehouseForm.code,
            label: t('inventory.code'),
            message: t('common.requiredField')
          }
        ])
  ]
  if (
    !warehouseValidation.requireFields(
      fields,
      notify,
      t('inventory.warehouseSaveFailed'),
      labels => t('common.requiredFields', { fields: labels.join(', ') })
    )
  ) {
    return
  }

  saving.value = true
  try {
    if (editingWarehouse.value)
      await apiFetch(`/inventory/warehouses/${editingWarehouse.value.id}`, {
        method: 'PATCH',
        body: warehouseForm
      })
    else
      await apiFetch('/inventory/warehouses', {
        method: 'POST',
        body: warehouseForm
      })
    await refreshWarehouses()
    warehouseOpen.value = false
    notify.success(t('inventory.warehouseSaved'), t('common.changesSaved'))
  } catch (error: unknown) {
    const response = (error as { data?: { message?: string, blockers?: string[] } }).data
    warehouseSaveError.value = response?.blockers?.length
      ? t('inventory.warehouseDeactivationBlocked')
      : response?.message || t('common.tryAgain')
    warehouseValidation.notifyApiError(
      error,
      notify,
      t('inventory.warehouseSaveFailed'),
      t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}
let searchDebounce: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
  stockPage.value = 1
  warehousesPage.value = 1
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    debouncedSearch.value = value.trim()
  }, 300)
})

watch(stockSorting, () => {
  stockPage.value = 1
})
watch(warehouseSorting, () => {
  warehousesPage.value = 1
})
watch(() => stockPagination.pageSize, () => { stockPage.value = 1 })
watch(() => warehousesPagination.pageSize, () => { warehousesPage.value = 1 })
watch(stockListUrl, () => { void refreshStock() })
watch(warehouseListUrl, () => { void refreshWarehouses() })

watch(section, () => {
  search.value = ''
})
</script>

<template>
  <div>
    <AppDataTable
      v-if="section === 'stock'"
      v-model:sorting="stockSorting"
      :data="paginatedStockItems"
      :columns="stockColumns"
      :get-row-id="(row) => row.productId"
      :loading="stockStatus === 'pending'"
      server-sorting
      table-key="inventory-stock"
      :column-labels="{
        name: t('products.name'),
        sku: t('products.productNumber'),
        stock: t('inventory.stock'),
        availableStock: t('inventory.availableStock'),
        unavailableStock: t('inventory.unavailableStock'),
        incomingStock: t('inventory.incomingStock')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center gap-3">
          <p class="whitespace-nowrap text-sm font-medium text-highlighted">
            {{ t('inventory.stock') }} ({{ stockTotal }})
          </p>
          <UInput
            v-model="search"
            icon="i-lucide-search"
            :placeholder="t('inventory.searchStock')"
            class="w-full sm:w-72"
          />
        </div>
      </template>

      <template #footer>
        <TablePaginationFooter
          v-model:page="stockPage"
          v-model:page-size="stockPagination.pageSize"
          :total="stockTotal"
          class="border-t-0 pt-0"
        />
      </template>
    </AppDataTable>

    <AppDataTable
      v-else
      v-model:sorting="warehouseSorting"
      :data="paginatedWarehouses"
      :columns="warehouseColumns"
      :get-row-id="(row) => row.id"
      :loading="warehousesStatus === 'pending'"
      server-sorting
      table-key="inventory-warehouses"
      :column-labels="{
        name: t('inventory.warehouse'),
        code: t('inventory.code'),
        active: t('inventory.status'),
        fulfillmentEnabled: t('inventory.fulfillment'),
        priority: t('inventory.fulfillmentPriority')
      }"
    >
      <template #header>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 flex-wrap items-center gap-3">
            <p class="whitespace-nowrap text-sm font-medium text-highlighted">
              {{ t('nav.warehouses') }} ({{ warehousesTotal }})
            </p>
            <UInput
              v-model="search"
              icon="i-lucide-search"
              :placeholder="t('inventory.searchWarehouses')"
              class="w-full sm:w-72"
            />
          </div>
          <UButton :label="t('inventory.addWarehouse')" @click="openWarehouse()" />
        </div>
      </template>

      <template #footer>
        <TablePaginationFooter
          v-model:page="warehousesPage"
          v-model:page-size="warehousesPagination.pageSize"
          :total="warehousesTotal"
          class="border-t-0 pt-0"
        />
      </template>
    </AppDataTable>
  </div>

  <UModal
    v-model:open="warehouseOpen"
    :title="editingWarehouse ? t('common.edit') : t('inventory.addWarehouse')"
  >
    <template #body>
      <UForm
        :state="warehouseForm"
        class="space-y-5"
        @submit="saveWarehouse"
      >
        <UAlert
          v-if="warehouseSaveError"
          color="error"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          :title="t('inventory.warehouseSaveFailed')"
          :description="warehouseSaveError"
        />
        <UFormField
          :label="t('inventory.warehouse')"
          :error="warehouseValidationErrors.name"
          required
        >
          <UInput
            v-model="warehouseForm.name"
            class="w-full"
            @update:model-value="warehouseValidation.clear('name')"
          />
        </UFormField>
        <UFormField :label="t('inventory.code')" :error="warehouseValidationErrors.code" required>
          <UInput
            v-model="warehouseForm.code"
            :disabled="editingWarehouse?.default"
            class="w-full"
            @update:model-value="warehouseValidation.clear('code')"
          />
        </UFormField>
        <UFormField
          :label="t('inventory.active')"
          class="flex items-center justify-between rounded-md border border-default px-3 py-2"
        >
          <USwitch v-model="warehouseForm.active" :disabled="editingWarehouse?.default" />
        </UFormField>
        <UFormField
          :label="t('inventory.fulfillment')"
          :description="t('inventory.fulfillmentDescription')"
          class="flex items-center justify-between rounded-md border border-default px-3 py-2"
        >
          <USwitch
            v-model="warehouseForm.fulfillmentEnabled"
            :disabled="editingWarehouse?.default"
          />
        </UFormField>
        <UFormField :label="t('inventory.fulfillmentPriority')">
          <UInput
            v-model.number="warehouseForm.priority"
            type="number"
            min="0"
            :disabled="editingWarehouse?.default"
            class="w-full"
          />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="warehouseOpen = false"
          />
          <UButton :label="t('common.save')" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
</template>

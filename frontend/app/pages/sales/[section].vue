<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type Order = {
  id: string
  number: string
  status: string
  sourceStatus: string | null
  unresolvedProductCount: number
  customer: string
  currency: string | null
  orderedAt: string | null
  connection: string
}

type Customer = {
  id: string
  name: string
  customerNumber: string | null
  profileImported: boolean
  email: string | null
  phone: string | null
  connection: string | null
  updatedAt: string
}

type PickList = {
  orderId: string
  number: string
  status: string
  orderedAt: string | null
  warehouse: string
  lineCount: number
}

const localePath = useLocalePath()
const route = useRoute()
const { t } = useI18n()
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const UBadge = resolveComponent('UBadge')
const page = ref(1)
const pageSize = ref(25)
const search = ref('')
const debouncedSearch = ref('')
const section = computed(() => {
  if (route.params.section === 'customers') {
    return 'customers'
  }

  return route.params.section === 'picklists' ? 'picklists' : 'orders'
})
const title = computed(() => {
  if (section.value === 'customers') {
    return t('nav.customers')
  }

  return section.value === 'picklists' ? t('nav.pickLists') : t('nav.orders')
})
const endpoint = computed(() => {
  const parameters = new URLSearchParams({
    page: String(page.value),
    limit: String(pageSize.value)
  })
  if (debouncedSearch.value) {
    parameters.set('search', debouncedSearch.value)
  }

  return `/sales/${section.value}?${parameters.toString()}`
})
const { data, status, refresh } = await useAsyncData(
  () => `sales-${section.value}`,
  () => apiFetch<{ orders?: Order[], customers?: Customer[], picklists?: PickList[], pagination: { total: number } }>(endpoint.value),
)
const rows = computed<(Order | Customer | PickList)[]>(() =>
  data.value?.orders || data.value?.customers || data.value?.picklists || []
)
const total = computed(() => data.value?.pagination.total ?? 0)
const date = (value: string | null) => value ? new Intl.DateTimeFormat().format(new Date(value)) : '—'
const orderStatus = (order: Order) => order.status === 'historical'
  ? `${t('sales.historical')} · ${order.sourceStatus?.replaceAll('_', ' ') || t('sales.unknownSourceState')}`
  : order.status.replaceAll('_', ' ')
const orderColumns = computed<TableColumn<Order>[]>(() => [
  {
    accessorKey: 'number',
    header: t('sales.orderNumber'),
    cell: ({ row }) => h(
      'button',
      {
        class: 'cursor-pointer font-medium text-primary hover:underline',
        onClick: () => navigateTo(localePath(`/sales/orders/${row.original.id}`))
      },
      row.original.number
    )
  },
  { accessorKey: 'customer', header: t('sales.customer') },
  { accessorKey: 'connection', header: t('sales.source') },
  {
    accessorKey: 'status',
    header: t('sales.status'),
    cell: ({ row }) => h('div', { class: 'flex items-center gap-2' }, [
      h('span', orderStatus(row.original)),
      row.original.unresolvedProductCount > 0
        ? h(UBadge, {
            label: t('sales.unmatchedProducts', { count: row.original.unresolvedProductCount }),
            color: 'warning',
            variant: 'subtle',
            size: 'xs'
          })
        : null
    ])
  },
  { accessorKey: 'currency', header: t('sales.currency'), cell: ({ row }) => row.original.currency || '—' },
  { accessorKey: 'orderedAt', header: t('sales.orderedAt'), cell: ({ row }) => date(row.original.orderedAt) },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h(
      'div',
      { class: 'flex justify-end' },
      h(UDropdownMenu, {
        items: [[{
          label: t('purchasing.view'),
          icon: 'i-lucide-eye',
          onSelect: () => navigateTo(localePath(`/sales/orders/${row.original.id}`))
        }]],
        content: { align: 'end' }
      }, () => h(UButton, {
        icon: 'i-lucide-ellipsis-vertical',
        color: 'neutral',
        variant: 'ghost'
      }))
    )
  }
])
const customerColumns = computed<TableColumn<Customer>[]>(() => [
  {
    accessorKey: 'name',
    header: t('sales.customer'),
    cell: ({ row }) => h('div', { class: 'flex items-center gap-2' }, [
      h(
        'button',
        {
          class: 'cursor-pointer font-medium text-primary hover:underline',
          onClick: () => navigateTo(localePath(`/sales/customers/${row.original.id}`))
        },
        row.original.name
      ),
      !row.original.profileImported
        ? h(UBadge, {
            label: t('sales.orderOnly'),
            color: 'warning',
            variant: 'subtle',
            size: 'xs'
          })
        : null
    ])
  },
  { accessorKey: 'customerNumber', header: t('sales.customerNumber'), cell: ({ row }) => row.original.customerNumber || '—' },
  { accessorKey: 'email', header: t('common.email'), cell: ({ row }) => row.original.email || '—' },
  { accessorKey: 'phone', header: t('common.phone'), cell: ({ row }) => row.original.phone || '—' },
  { accessorKey: 'connection', header: t('sales.source'), cell: ({ row }) => row.original.connection || '—' },
  { accessorKey: 'updatedAt', header: t('sales.updatedAt'), cell: ({ row }) => date(row.original.updatedAt) },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h(
      'div',
      { class: 'flex justify-end' },
      h(UDropdownMenu, {
        items: [[{
          label: t('purchasing.view'),
          icon: 'i-lucide-eye',
          onSelect: () => navigateTo(localePath(`/sales/customers/${row.original.id}`))
        }]],
        content: { align: 'end' }
      }, () => h(UButton, {
        icon: 'i-lucide-ellipsis-vertical',
        color: 'neutral',
        variant: 'ghost'
      }))
    )
  }
])
const pickListColumns = computed<TableColumn<PickList>[]>(() => [
  {
    accessorKey: 'number',
    header: t('sales.orderNumber'),
    cell: ({ row }) => h(
      'button',
      {
        class: 'cursor-pointer font-medium text-primary hover:underline',
        onClick: () => navigateTo(localePath(`/sales/picklists/${row.original.orderId}`))
      },
      row.original.number
    )
  },
  { accessorKey: 'warehouse', header: t('sales.pickWarehouse') },
  { accessorKey: 'lineCount', header: t('sales.pickLines') },
  { accessorKey: 'orderedAt', header: t('sales.orderedAt'), cell: ({ row }) => date(row.original.orderedAt) },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h(
      'div',
      { class: 'flex justify-end' },
      h(UDropdownMenu, {
        items: [[{
          label: t('sales.viewPickList'),
          icon: 'i-lucide-clipboard-list',
          onSelect: () => navigateTo(localePath(`/sales/picklists/${row.original.orderId}`))
        }]],
        content: { align: 'end' }
      }, () => h(UButton, {
        icon: 'i-lucide-ellipsis-vertical',
        color: 'neutral',
        variant: 'ghost'
      }))
    )
  }
])
const columns = computed<TableColumn<Order | Customer | PickList>[]>(() => {
  const current = section.value === 'orders'
    ? orderColumns.value
    : section.value === 'customers'
      ? customerColumns.value
      : pickListColumns.value

  return current as TableColumn<Order | Customer | PickList>[]
})

let debounce: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  page.value = 1
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    debouncedSearch.value = value.trim()
  }, 300)
})
watch([endpoint, section], () => {
  void refresh()
})
watch(pageSize, () => {
  page.value = 1
})
</script>

<template>
  <AppDataTable
    :table-key="`sales-${section}`"
    :data="rows"
    :columns="columns"
    :loading="status === 'pending'"
    :column-labels="section === 'orders'
      ? { number: t('sales.orderNumber'), customer: t('sales.customer'), connection: t('sales.source'), status: t('sales.status'), currency: t('sales.currency'), orderedAt: t('sales.orderedAt') }
      : section === 'customers'
        ? { name: t('sales.customer'), email: t('common.email'), phone: t('common.phone'), connection: t('sales.source'), updatedAt: t('sales.updatedAt') }
        : { number: t('sales.orderNumber'), warehouse: t('sales.pickWarehouse'), lineCount: t('sales.pickLines'), orderedAt: t('sales.orderedAt') }"
    server-sorting
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-medium text-highlighted">
          {{ title }} ({{ total }})
        </p>
        <UInput
          v-model="search"
          class="w-full sm:w-72"
          icon="i-lucide-search"
          :placeholder="t('sales.search', { entity: title.toLowerCase() })"
        />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        :icon="section === 'picklists' ? 'i-lucide-clipboard-list' : 'i-lucide-shopping-bag'"
        :title="t('sales.noRecords', { entity: title })"
        :description="section === 'picklists' ? t('sales.noPickListsDescription') : t('sales.noRecordsDescription')"
      />
    </template>
    <template #footer>
      <TablePaginationFooter
        v-model:page="page"
        v-model:page-size="pageSize"
        :total="total"
      />
    </template>
  </AppDataTable>
</template>

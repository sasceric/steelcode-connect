<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type Issue = {
  id: string
  reference: string
  detail: string
  occurredAt: string
  targetId: string
}

const { t } = useI18n()
const notify = useAppToast()
const auth = useAuth()
const canManage = computed(() => auth.tenant.value?.role === 'owner')
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')
const type = ref('unmatched_products')
const page = ref(1)
const pageSize = ref(25)
const retryingId = ref<string | null>(null)
const types = computed(() => [
  'unmatched_products',
  'unallocated_orders',
  'shipment_reconciliation',
  'stock_sync_failed',
  'sales_sync_failed',
  'import_failed',
  'stock_discrepancy',
  'quarantined_returns'
].map(value => ({ label: t(`operationsExceptions.types.${value}`), value })))
const endpoint = computed(() => {
  const params = new URLSearchParams({
    type: type.value,
    page: String(page.value),
    limit: String(pageSize.value)
  })

  return `/operations/exceptions?${params}`
})
const { data, status, refresh } = await useAsyncData(
  'operations-exceptions',
  () => apiFetch<{ issues: Issue[], pagination: { total: number } }>(endpoint.value)
)
const issues = computed(() => data.value?.issues || [])
const total = computed(() => data.value?.pagination.total ?? 0)

watch(endpoint, () => {
  void refresh()
})
watch(type, () => {
  page.value = 1
})
watch(pageSize, () => {
  page.value = 1
})

function targetRoute(issue: Issue) {
  switch (type.value) {
    case 'stock_sync_failed':
      return `/catalogue/products/${issue.targetId}`
    case 'import_failed':
    case 'sales_sync_failed':
      return `/integrations/${issue.targetId}`
    case 'stock_discrepancy':
      return `/inventory/warehouses/${issue.targetId}`
    case 'quarantined_returns':
      return `/sales/orders/${issue.targetId}?tab=returns`
    case 'shipment_reconciliation':
      return `/sales/orders/${issue.targetId}?tab=details`
    default:
      return `/sales/orders/${issue.targetId}`
  }
}

function issueDetail(issue: Issue) {
  switch (type.value) {
    case 'unmatched_products':
      return t('operationsExceptions.unmatchedCount', { count: issue.detail })
    case 'unallocated_orders':
      return t('operationsExceptions.openQuantity', { quantity: issue.detail })
    case 'shipment_reconciliation':
      return t('operationsExceptions.shipmentDetail')
    case 'stock_discrepancy':
      return t('operationsExceptions.stockDifference', { quantity: issue.detail })
    default:
      return issue.detail
  }
}

async function retry(issue: Issue) {
  if (!canManage.value || retryingId.value) {
    return
  }
  const url = type.value === 'stock_sync_failed'
    ? `/operations/exceptions/stock-sync/${issue.id}/retry`
    : type.value === 'sales_sync_failed'
      ? `/operations/exceptions/sales-sync/${issue.targetId}/retry`
    : `/operations/exceptions/orders/${issue.id}/retry-allocation`
  retryingId.value = issue.id
  try {
    await apiFetch(url, { method: 'POST' })
    await refresh()
    notify.success(t('operationsExceptions.retryQueued'), t('operationsExceptions.retryDescription'))
  } catch (error: unknown) {
    const payload = typeof error === 'object' && error !== null && 'data' in error
      ? (error as { data?: { message?: unknown } }).data
      : null
    notify.error(
      t('common.tryAgain'),
      typeof payload?.message === 'string'
        ? payload.message
        : error instanceof Error ? error.message : t('common.tryAgain')
    )
  } finally {
    retryingId.value = null
  }
}

function actions(issue: Issue) {
  const items = [{
    label: t('operationsExceptions.open'),
    icon: 'i-lucide-arrow-up-right',
    onSelect: () => navigateTo(targetRoute(issue))
  }]
  if (canManage.value && ['unmatched_products', 'unallocated_orders', 'stock_sync_failed', 'sales_sync_failed'].includes(type.value)) {
    items.push({
      label: t('operationsExceptions.retry'),
      icon: 'i-lucide-rotate-cw',
      onSelect: () => retry(issue)
    })
  }

  return [items]
}

const columns = computed<TableColumn<Issue>[]>(() => [
  {
    accessorKey: 'reference',
    header: t('operationsExceptions.reference'),
    cell: ({ row }) => h('button', {
      class: 'cursor-pointer font-medium text-primary hover:underline',
      onClick: () => navigateTo(targetRoute(row.original))
    }, row.original.reference)
  },
  {
    accessorKey: 'detail',
    header: t('operationsExceptions.detail'),
    cell: ({ row }) => h('span', {
      class: 'block max-w-[36rem] truncate',
      title: issueDetail(row.original)
    }, issueDetail(row.original))
  },
  {
    accessorKey: 'occurredAt',
    header: t('operationsExceptions.updatedAt'),
    cell: ({ row }) => new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short'
    }).format(new Date(row.original.occurredAt))
  },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => h(
      'div',
      { class: 'flex justify-end' },
      h(UDropdownMenu, {
        items: actions(row.original),
        content: { align: 'end' }
      }, () => h(UButton, {
        icon: 'i-lucide-ellipsis-vertical',
        color: 'neutral',
        variant: 'ghost'
      }))
    )
  }
])
</script>

<template>
  <AppDataTable
    table-key="operations-exceptions"
    :data="issues"
    :columns="columns"
    :loading="status === 'pending'"
    :column-labels="{
      reference: t('operationsExceptions.reference'),
      detail: t('operationsExceptions.detail'),
      occurredAt: t('operationsExceptions.updatedAt')
    }"
    server-sorting
  >
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-medium text-highlighted">
          {{ t('operationsExceptions.title') }} ({{ total }})
        </p>
        <USelect
          v-model="type"
          :items="types"
          value-key="value"
          class="w-full sm:w-72"
        />
      </div>
    </template>
    <template #empty>
      <AppEmptyState
        icon="i-lucide-circle-check"
        :title="t('operationsExceptions.emptyTitle')"
        :description="t('operationsExceptions.emptyDescription')"
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

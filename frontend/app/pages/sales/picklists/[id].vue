<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type PickLine = {
  itemId: string
  warehouseId: string
  warehouse: string
  warehouseCode: string
  sku: string
  name: string
  quantity: string
}

type PickOrder = {
  number: string
  status: string
  customer: string
  orderedAt: string | null
  shippingAddress: Record<string, unknown>
  pickList: PickLine[]
}

type PickTask = {
  id: string
  status: string
  stale: boolean
  warehouse: string
  lines: PickLine[]
  pickedQuantities: Record<string, string>
  version: number
  createdAt: string
  updatedAt: string
}

const localePath = useLocalePath()
const route = useRoute()
const { t } = useI18n()
const notify = useAppToast()
const auth = useAuth()
const { data, status, refresh: refreshOrder } = await useAsyncData(
  () => `sales-picklist-${route.params.id}`,
  () => apiFetch<{ order: PickOrder }>(`/sales/orders/${route.params.id}`)
)
const { data: taskData, refresh: refreshTask } = await useAsyncData(
  () => `sales-pick-task-${route.params.id}`,
  () => apiFetch<{ pickTask: PickTask | null }>(`/sales/orders/${route.params.id}/pick-task`)
)
const order = computed(() => data.value?.order)
const pickTask = computed(() => taskData.value?.pickTask)
const pickedQuantities = ref<Record<string, string>>({})
const saving = ref(false)
const canManage = computed(() => auth.tenant.value?.role === 'owner')
const canEdit = computed(() => pickTask.value
  && !pickTask.value.stale
  && ['open', 'in_progress'].includes(pickTask.value.status)
  && canManage.value)
const canStart = computed(() => canManage.value
  && !!order.value?.pickList.length
  && (!pickTask.value
    || (pickTask.value.stale
      && ['open', 'in_progress', 'stale'].includes(pickTask.value.status)
      && Object.values(pickTask.value.pickedQuantities).every(quantity => Number(quantity) === 0))))
const lines = computed(() => pickTask.value?.lines || order.value?.pickList || [])

watch(pickTask, (task) => {
  if (!task) {
    pickedQuantities.value = {}
    return
  }

  pickedQuantities.value = Object.fromEntries(
    task.lines.map(line => [line.itemId, task.pickedQuantities[line.itemId] ?? '0'])
  )
}, { immediate: true })

function updatePicked(itemId: string, value: string | number) {
  pickedQuantities.value[itemId] = String(value)
}

async function startPicking() {
  if (!canManage.value || saving.value) {
    return
  }

  saving.value = true
  try {
    await apiFetch(`/sales/orders/${route.params.id}/pick-task`, { method: 'POST' })
    await Promise.all([refreshOrder(), refreshTask()])
    notify.success(t('sales.pickTaskStarted'), t('sales.pickTaskStartedDescription'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
    await Promise.all([refreshOrder(), refreshTask()])
  } finally {
    saving.value = false
  }
}

async function savePicking(complete: boolean) {
  if (!pickTask.value || !canEdit.value || saving.value) {
    return
  }

  saving.value = true
  try {
    await apiFetch(`/sales/orders/${route.params.id}/pick-task`, {
      method: 'PATCH',
      body: {
        taskId: pickTask.value.id,
        version: pickTask.value.version,
        complete,
        quantities: pickedQuantities.value
      }
    })
    await Promise.all([refreshOrder(), refreshTask()])
    notify.success(
      complete ? t('sales.pickTaskConfirmed') : t('common.changesSaved'),
      t('sales.pickTaskNoStockChange')
    )
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), error instanceof Error ? error.message : t('common.tryAgain'))
    await Promise.all([refreshOrder(), refreshTask()])
  } finally {
    saving.value = false
  }
}

function saveProgress() {
  void savePicking(false)
}

function confirmPicking() {
  void savePicking(true)
}

const columns = computed<TableColumn<PickLine>[]>(() => [
  { accessorKey: 'warehouse', header: t('sales.pickWarehouse') },
  { accessorKey: 'sku', header: t('products.productNumber') },
  { accessorKey: 'name', header: t('products.product') },
  { accessorKey: 'quantity', header: t('sales.pickQuantity') },
  {
    id: 'pickedQuantity',
    header: t('sales.pickedQuantity'),
    cell: ({ row }) => {
      if (!canEdit.value) {
        return pickTask.value?.pickedQuantities[row.original.itemId] || '—'
      }

      return h(resolveComponent('UInput'), {
        modelValue: pickedQuantities.value[row.original.itemId] ?? '0',
        'onUpdate:modelValue': (value: string | number) => updatePicked(row.original.itemId, value),
        type: 'number',
        min: 0,
        max: row.original.quantity,
        step: 0.0001,
        class: 'w-28'
      })
    }
  }
])
const address = computed(() => {
  const value = order.value?.shippingAddress
  if (!value) {
    return '—'
  }

  const field = (name: string) => typeof value[name] === 'string' ? String(value[name]).trim() : ''
  return [
    field('company') || [field('firstName'), field('lastName')].filter(Boolean).join(' '),
    field('street'),
    [field('zipcode'), field('city')].filter(Boolean).join(' '),
    field('countryCode')
  ].filter(Boolean).join(', ') || '—'
})

function printPickList() {
  if (import.meta.client) {
    window.print()
  }
}
</script>

<template>
  <UDashboardPanel id="sales-pick-list">
    <template #header>
      <UDashboardNavbar :title="order ? `${t('nav.pickLists')} · ${order.number}` : t('nav.pickLists')">
        <template #leading>
          <UButton
            icon="i-lucide-arrow-left"
            color="neutral"
            variant="ghost"
            :to="localePath('/sales/picklists')"
          />
        </template>
        <template #right>
          <UButton
            v-if="lines.length"
            icon="i-lucide-printer"
            color="neutral"
            variant="outline"
            @click="printPickList"
          >
            {{ t('sales.printPickList') }}
          </UButton>
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="status === 'pending'" class="p-4 text-sm text-muted">
        {{ t('common.loading') }}
      </div>
      <div v-else-if="order" id="pick-list-sheet" class="space-y-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-lg font-semibold text-highlighted">
              {{ t('nav.pickLists') }} · {{ order.number }}
            </h1>
            <p class="text-sm text-muted">
              {{ order.customer }}
            </p>
            <p class="text-sm text-muted">
              {{ address }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <UBadge color="neutral" variant="subtle">
              {{ order.status.replaceAll('_', ' ') }}
            </UBadge>
            <UBadge v-if="pickTask" color="primary" variant="subtle">
              {{ t(`sales.pickStatus.${pickTask.status}`) }}
            </UBadge>
          </div>
        </div>

        <UAlert
          icon="i-lucide-info"
          color="info"
          variant="subtle"
          :description="t('sales.pickListNotice')"
        />

        <UAlert
          v-if="pickTask?.stale"
          icon="i-lucide-triangle-alert"
          color="warning"
          variant="subtle"
          :title="t('sales.pickTaskStale')"
          :description="t('sales.pickTaskStaleDescription')"
        />

        <div class="flex flex-wrap items-center gap-2">
          <UButton
            v-if="canStart"
            icon="i-lucide-clipboard-list"
            :loading="saving"
            @click="startPicking"
          >
            {{ t('sales.startPicking') }}
          </UButton>
          <template v-if="canEdit">
            <UButton
              color="neutral"
              variant="outline"
              :loading="saving"
              @click="saveProgress"
            >
              {{ t('sales.savePickProgress') }}
            </UButton>
            <UButton
              icon="i-lucide-clipboard-check"
              :loading="saving"
              @click="confirmPicking"
            >
              {{ t('sales.confirmPicked') }}
            </UButton>
          </template>
        </div>

        <AppDataTable
          table-key="sales-pick-list-lines"
          :data="lines"
          :columns="columns"
          max-height="h-auto"
        >
          <template #empty>
            <AppEmptyState
              icon="i-lucide-clipboard-list"
              :title="t('sales.noPickLines')"
              :description="t('sales.noPickLinesDescription')"
            />
          </template>
        </AppDataTable>
      </div>
    </template>
  </UDashboardPanel>
</template>

<style>
@media print {
  body * {
    visibility: hidden !important;
  }

  #pick-list-sheet,
  #pick-list-sheet * {
    visibility: visible !important;
  }

  #pick-list-sheet {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    background: white;
    color: black;
  }
}
</style>

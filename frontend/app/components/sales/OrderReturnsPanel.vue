<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type ReturnOption = {
  itemId: string
  warehouseId: string
  warehouse: string
  sku: string
  name: string
  shipped: string
  returned: string
  remaining: string
}

type ReturnRecord = {
  id: string
  itemName: string
  sku: string | null
  warehouse: string
  quantity: string
  reason: string
  condition: string
  disposition: string
  note: string | null
  createdAt: string
}

const props = defineProps<{
  orderId: string
}>()

const { t } = useI18n()
const notify = useAppToast()
const auth = useAuth()
const canManage = computed(() => auth.tenant.value?.role === 'owner')
const UButton = resolveComponent('UButton')
const { data, status, refresh } = await useAsyncData(
  () => `sales-order-returns-${props.orderId}`,
  () => apiFetch<{ options: ReturnOption[], returns: ReturnRecord[] }>(`/sales/orders/${props.orderId}/returns`)
)
const options = computed(() => data.value?.options || [])
const records = computed(() => data.value?.returns || [])
const selectableOptions = computed(() => options.value
  .filter(option => Number(option.remaining) > 0)
  .map(option => ({
    label: `${option.name} · ${option.sku} · ${option.warehouse} (${option.remaining})`,
    productName: option.name,
    productNumber: option.sku,
    variantCombination: `${option.warehouse} · ${t('salesReturns.remaining')}: ${option.remaining}`,
    value: `${option.itemId}:${option.warehouseId}`
  })))
const reasonItems = computed(() => [
  'customer_return', 'damaged', 'wrong_item', 'warranty', 'other'
].map(value => ({ label: t(`salesReturns.reasons.${value}`), value })))
const conditionItems = computed(() => [
  'sealed', 'opened', 'damaged', 'unknown'
].map(value => ({ label: t(`salesReturns.conditions.${value}`), value })))
const dispositionItems = computed(() => [
  'quarantine', 'restock', 'write_off'
].map(value => ({ label: t(`salesReturns.dispositions.${value}`), value })))
const finalDispositionItems = computed(() => dispositionItems.value.filter(item => item.value !== 'quarantine'))
const form = reactive({
  option: '',
  quantity: '1',
  reason: 'customer_return',
  condition: 'unknown',
  disposition: 'quarantine',
  note: ''
})
const selectedOption = computed(() => options.value.find(
  option => `${option.itemId}:${option.warehouseId}` === form.option
))
const saving = ref(false)
const requestId = ref<string | null>(null)
const resolveOpen = ref(false)
const resolving = ref(false)
const selectedReturn = ref<ReturnRecord | null>(null)
const resolveCondition = ref('unknown')
const resolveDisposition = ref('restock')

watch(form, () => {
  requestId.value = null
})

function updateOption(value: string | string[]) {
  form.option = typeof value === 'string' ? value : ''
}

function errorMessage(error: unknown) {
  if (typeof error === 'object' && error !== null && 'data' in error) {
    const data = (error as { data?: { message?: unknown } }).data
    if (typeof data?.message === 'string') {
      return data.message
    }
  }

  return error instanceof Error ? error.message : t('common.tryAgain')
}

async function receiveReturn() {
  if (!canManage.value || !selectedOption.value || saving.value) {
    return
  }
  if (Number(form.quantity) <= 0 || Number(form.quantity) > Number(selectedOption.value.remaining)) {
    notify.error(t('common.tryAgain'), t('salesReturns.quantityInvalid'))
    return
  }

  requestId.value ||= crypto.randomUUID()
  saving.value = true
  try {
    await apiFetch(`/sales/orders/${props.orderId}/returns`, {
      method: 'POST',
      body: {
        requestId: requestId.value,
        itemId: selectedOption.value.itemId,
        warehouseId: selectedOption.value.warehouseId,
        quantity: form.quantity,
        reason: form.reason,
        condition: form.condition,
        disposition: form.disposition,
        note: form.note.trim() || null
      }
    })
    await refresh()
    form.option = ''
    form.quantity = '1'
    form.condition = 'unknown'
    form.disposition = 'quarantine'
    form.note = ''
    requestId.value = null
    notify.success(t('salesReturns.recorded'), t('salesReturns.stockNotice'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), errorMessage(error))
  } finally {
    saving.value = false
  }
}

function openResolution(record: ReturnRecord) {
  selectedReturn.value = record
  resolveCondition.value = record.condition
  resolveDisposition.value = 'restock'
  resolveOpen.value = true
}

async function resolveReturn() {
  if (!canManage.value || !selectedReturn.value || resolving.value) {
    return
  }
  resolving.value = true
  try {
    await apiFetch(`/sales/orders/${props.orderId}/returns/${selectedReturn.value.id}/disposition`, {
      method: 'POST',
      body: {
        condition: resolveCondition.value,
        disposition: resolveDisposition.value
      }
    })
    await refresh()
    resolveOpen.value = false
    notify.success(t('salesReturns.resolved'), t('salesReturns.stockNotice'))
  } catch (error: unknown) {
    notify.error(t('common.tryAgain'), errorMessage(error))
  } finally {
    resolving.value = false
  }
}

const columns = computed<TableColumn<ReturnRecord>[]>(() => [
  { accessorKey: 'itemName', header: t('products.product') },
  { accessorKey: 'sku', header: t('products.productNumber') },
  { accessorKey: 'warehouse', header: t('sales.pickWarehouse') },
  { accessorKey: 'quantity', header: t('sales.quantity') },
  {
    accessorKey: 'reason',
    header: t('salesReturns.reason'),
    cell: ({ row }) => t(`salesReturns.reasons.${row.original.reason}`)
  },
  {
    accessorKey: 'condition',
    header: t('salesReturns.condition'),
    cell: ({ row }) => t(`salesReturns.conditions.${row.original.condition}`)
  },
  {
    accessorKey: 'disposition',
    header: t('salesReturns.disposition'),
    cell: ({ row }) => t(`salesReturns.dispositions.${row.original.disposition}`)
  },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => row.original.disposition === 'quarantine' && canManage.value
      ? h(UButton, {
          label: t('salesReturns.resolve'),
          color: 'neutral',
          variant: 'ghost',
          size: 'xs',
          onClick: () => openResolution(row.original)
        })
      : null
  }
])
</script>

<template>
  <div class="space-y-4 p-4">
    <UAlert
      icon="i-lucide-info"
      color="info"
      variant="subtle"
      :description="t('salesReturns.authorityNotice')"
    />

    <UCard v-if="canManage && selectableOptions.length">
      <template #header>
        {{ t('salesReturns.recordReturn') }}
      </template>
      <div class="grid gap-4 md:grid-cols-2">
        <UFormField :label="t('salesReturns.shippedItem')" class="md:col-span-2">
          <SearchableSelect
            :model-value="form.option"
            :items="selectableOptions"
            :placeholder="t('salesReturns.selectItem')"
            :search-placeholder="t('salesReturns.searchItem')"
            @update:model-value="updateOption"
          />
        </UFormField>
        <UFormField
          :label="t('sales.quantity')"
          :description="selectedOption ? `${t('salesReturns.remaining')}: ${selectedOption.remaining}` : undefined"
        >
          <UInput
            v-model="form.quantity"
            type="number"
            min="0.0001"
            :max="selectedOption?.remaining"
            step="0.0001"
          />
        </UFormField>
        <UFormField :label="t('salesReturns.reason')">
          <USelect v-model="form.reason" :items="reasonItems" value-key="value" class="w-full" />
        </UFormField>
        <UFormField :label="t('salesReturns.condition')">
          <USelect v-model="form.condition" :items="conditionItems" value-key="value" class="w-full" />
        </UFormField>
        <UFormField :label="t('salesReturns.disposition')">
          <USelect v-model="form.disposition" :items="dispositionItems" value-key="value" class="w-full" />
        </UFormField>
        <UFormField :label="t('salesReturns.note')" class="md:col-span-2">
          <UTextarea v-model="form.note" :rows="2" class="w-full" />
        </UFormField>
      </div>
      <UButton
        class="mt-4"
        icon="i-lucide-package-plus"
        :loading="saving"
        :disabled="!selectedOption"
        @click="receiveReturn"
      >
        {{ t('salesReturns.recordReturn') }}
      </UButton>
    </UCard>

    <AppDataTable
      table-key="sales-order-returns"
      :data="records"
      :columns="columns"
      :loading="status === 'pending'"
      max-height="h-auto"
    >
      <template #header>
        <p class="text-sm font-medium text-highlighted">
          {{ t('salesReturns.history') }} ({{ records.length }})
        </p>
      </template>
      <template #empty>
        <AppEmptyState
          icon="i-lucide-package-open"
          :title="t('salesReturns.emptyTitle')"
          :description="t('salesReturns.emptyDescription')"
        />
      </template>
    </AppDataTable>

    <UModal v-model:open="resolveOpen" :title="t('salesReturns.resolve')">
      <template #body>
        <div class="space-y-4">
          <p class="text-sm text-muted">
            {{ selectedReturn?.itemName }} · {{ selectedReturn?.quantity }}
          </p>
          <UFormField :label="t('salesReturns.condition')">
            <USelect v-model="resolveCondition" :items="conditionItems" value-key="value" class="w-full" />
          </UFormField>
          <UFormField :label="t('salesReturns.disposition')">
            <USelect v-model="resolveDisposition" :items="finalDispositionItems" value-key="value" class="w-full" />
          </UFormField>
          <UButton :loading="resolving" @click="resolveReturn">
            {{ t('salesReturns.confirmDisposition') }}
          </UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>

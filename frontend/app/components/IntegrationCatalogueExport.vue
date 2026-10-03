<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

type Settings = {
  scope: 'selected' | 'all'
  automaticSync: boolean
  createMissingReferences: boolean
  categoryRootId: string
  categoryIds: string[]
  brandIds: string[]
  manufacturerIds: string[]
  productIds: string[]
  excludeIds: string[]
  includeDescendants: boolean
  includeVariants: boolean
  salesChannelId: string
  destinationLocaleId?: string
  publicationMode: string
  fields: string[]
  priceMarkup: string
  mappings: Record<string, Record<string, string>>
}
type PreviewItem = {
  id: string
  name: string
  sku: string
  action: string
  status: string
  issues: string[]
  result: string | null
  fields: string[]
  changes: Record<string, unknown>
  dependencies: { type: string, payload: Record<string, unknown> }[]
}
type Plan = { id: string, run_id: string, status: string, stale: boolean }
type Run = {
  id: string
  status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled'
  currentStage: string
  totalItems: number
  processedItems: number
  failedItems: number
  failureReason: string | null
  createdAt: string
  startedAt: string | null
  updatedAt: string
  completedAt: string | null
}
const props = defineProps<{
  connectionId: string
  connectorKey?: string
  mode: 'export' | 'mapping'
}>()
const emit = defineEmits<{ queued: [] }>()
const { t } = useI18n()
const toast = useAppToast()
const settings = ref<Settings>()
const savedSettings = ref('')
const loading = ref(false)
const saving = ref(false)
const plan = ref<Plan | null>(null)
const run = ref<Run | null>(null)
const pollingError = ref(false)
const syncHealth = ref<{ pendingChanges: number, oldestSeconds: number }>()
let refreshing = false
const items = ref<PreviewItem[]>([])
const total = ref(0)
const summary = ref<{ creates: number, updates: number, blocked: number }>()
const page = ref(1)
const pageSize = ref(25)
const mappingType = ref('category')
const localId = ref('')
const targetId = ref('')
const labels = ref<Record<string, string>>({})
const mappingPage = ref(1)
const mappingPageSize = ref(25)
const confirmationOpen = ref(false)
const confirmationAction = ref<'preview' | 'sync'>('preview')
const detailItem = ref<PreviewItem>()
const detailOpen = ref(false)
let timer: ReturnType<typeof setInterval> | undefined
const dirty = computed(() => JSON.stringify(settings.value) !== savedSettings.value)
const pending = computed(() => run.value
  ? ['queued', 'running'].includes(run.value.status)
  : ['preparing', 'publishing'].includes(plan.value?.status || ''))
const canPublish = computed(() => plan.value?.status === 'ready' && !plan.value.stale && !dirty.value)
const isWoo = computed(() => props.connectorKey === 'woocommerce')
const referenceTypes = computed(() => isWoo.value
  ? ['category', 'brand', 'propertyGroup', 'tax', 'product']
  : ['category', 'manufacturer', 'property', 'tax', 'currency', 'locale', 'unit', 'deliveryTime', 'customField', 'product'])
const mappingTypeOptions = computed(() => referenceTypes.value.map(value => ({ value, label: t(`catalogueExport.references.${value}`) })))
const selectionTypes = ['categoryIds', 'brandIds', 'manufacturerIds', 'productIds', 'excludeIds'] as const
const selectionReferenceTypes = { categoryIds: 'category', brandIds: 'brand', manufacturerIds: 'manufacturer', productIds: 'product', excludeIds: 'product' }
const fieldOptions = computed(() => ['content', 'prices', 'classification', 'fulfilment', 'customFields', 'media'].map(value => ({
  value,
  label: t(`catalogueExport.${isWoo.value && ['content', 'classification', 'fulfilment'].includes(value) ? 'wooFields' : 'fields'}.${value}`)
})))
function exportLabel(key: string) {
  return t(`catalogueExport.${key}`, { provider: isWoo.value ? 'WooCommerce' : 'Shopware' })
}
const modeOptions = computed(() => ['keep', 'activate', 'deactivate'].map(value => ({ value, label: t(`catalogueExport.modes.${value}`) })))
const scopeOptions = computed(() => ['selected', 'all'].map(value => ({ value, label: t(`catalogueExport.scopes.${value}`) })))
const UButton = resolveComponent('UButton')
const previewColumns = computed<TableColumn<PreviewItem>[]>(() => [
  { accessorKey: 'name', header: t('products.name') },
  { accessorKey: 'sku', header: t('products.sku') },
  { accessorKey: 'action', header: t('catalogueExport.action'), cell: ({ row }) => t(`catalogueExport.actions.${row.original.action}`) },
  { accessorKey: 'status', header: t('products.status'), cell: ({ row }) => row.original.issues.length ? t('catalogueExport.blocked') : t(`catalogueExport.statuses.${row.original.status}`) },
  { accessorKey: 'issues', header: t('catalogueExport.issues'), cell: ({ row }) => row.original.result || row.original.issues.join(' · ') || '—' },
  { id: 'actions', cell: ({ row }) => h(UButton, { 'icon': 'i-lucide-eye', 'color': 'neutral', 'variant': 'ghost', 'aria-label': exportLabel('changes'), 'onClick': () => showChanges(row.original) }) }
])
type MappingRow = { id: string, type: string, localId: string, targetId: string }
const mappingRows = computed<MappingRow[]>(() => Object.entries(settings.value?.mappings || {}).flatMap(([type, values]) => Object.entries(values).map(([localId, targetId]) => ({ id: `${type}:${localId}`, type, localId, targetId }))))
const visibleMappings = computed(() => mappingRows.value.slice((mappingPage.value - 1) * mappingPageSize.value, mappingPage.value * mappingPageSize.value))
const mappingColumns = computed<TableColumn<MappingRow>[]>(() => [
  { accessorKey: 'type', header: t('catalogueExport.mappingType'), cell: ({ row }) => t(`catalogueExport.references.${row.original.type}`) },
  { accessorKey: 'localId', header: t('catalogueExport.source'), cell: ({ row }) => labels.value[row.original.localId] || row.original.localId },
  { accessorKey: 'targetId', header: exportLabel('destination'), cell: ({ row }) => labels.value[row.original.targetId] || row.original.targetId },
  { id: 'actions', cell: ({ row }) => h(UButton, { 'icon': 'i-lucide-trash-2', 'color': 'neutral', 'variant': 'ghost', 'aria-label': t('common.remove'), 'onClick': () => removeMapping(row.original) }) }
])
const previewColumnLabels = computed(() => ({
  name: t('products.name'),
  sku: t('products.sku'),
  action: t('catalogueExport.action'),
  status: t('products.status'),
  issues: t('catalogueExport.issues')
}))
const mappingColumnLabels = computed(() => ({
  type: t('catalogueExport.mappingType'),
  localId: t('catalogueExport.source'),
  targetId: exportLabel('destination')
}))
function errorMessage(error: unknown) {
  return (error as { data?: { message?: string } })?.data?.message || t('common.tryAgain')
}
function normalizeSettings(value: Settings): Settings {
  // PHP serializes an empty associative array as []. Use object dictionaries
  // so the first mapping is reactive and survives JSON serialization.
  return {
    ...value,
    mappings: Object.fromEntries(
      Object.entries(value.mappings || {}).map(([type, values]) => [type, { ...values }])
    )
  }
}
async function save() {
  if (!settings.value) return false
  saving.value = true
  try {
    const result = await apiFetch<{ settings: Settings }>(`/integrations/${props.connectionId}/exports/configuration`, { method: 'PUT', body: settings.value })
    settings.value = normalizeSettings(result.settings)
    savedSettings.value = JSON.stringify(settings.value)
    toast.success(t('common.saved'), t('common.saved'))
    return true
  } catch (error) {
    toast.error(t('integrations.updateFailed'), errorMessage(error))
    return false
  } finally {
    saving.value = false
  }
}
async function loadPreview() {
  const response = await apiFetch<{ plan: Plan | null, run?: Run | null, items: PreviewItem[], total: number, summary?: typeof summary.value, syncHealth?: typeof syncHealth.value }>(`/integrations/${props.connectionId}/exports/plans/latest?page=${page.value}&limit=${pageSize.value}`)
  plan.value = response.plan
  run.value = response.run || null
  items.value = response.items
  total.value = response.total
  summary.value = response.summary
  syncHealth.value = response.syncHealth
  pollingError.value = false
}
async function refreshProgress() {
  if (refreshing) return
  refreshing = true
  try {
    await loadPreview()
  } catch {
    pollingError.value = true
  } finally {
    refreshing = false
  }
}
async function cancelled() {
  await refreshProgress()
  emit('queued')
}
async function preview() {
  if (!await save()) return
  loading.value = true
  try {
    await apiFetch(`/integrations/${props.connectionId}/exports/preview`, { method: 'POST' })
    page.value = 1
    await loadPreview()
    emit('queued')
  } catch (error) {
    toast.error(t('catalogueExport.preview'), errorMessage(error))
  } finally {
    loading.value = false
  }
}
async function publish() {
  if (confirmationAction.value === 'sync') {
    await syncNow()
    return
  }
  if (!canPublish.value || !plan.value) return
  loading.value = true
  try {
    await apiFetch(`/integrations/${props.connectionId}/exports/plans/${plan.value.id}/publish`, { method: 'POST', body: { confirmed: true } })
    confirmationOpen.value = false
    await loadPreview()
    emit('queued')
  } catch (error) {
    toast.error(t('catalogueExport.publish'), errorMessage(error))
  } finally {
    loading.value = false
  }
}
async function syncNow() {
  if (!await save()) return
  loading.value = true
  try {
    await apiFetch(`/integrations/${props.connectionId}/exports/sync`, { method: 'POST', body: { confirmed: true } })
    confirmationOpen.value = false
    page.value = 1
    await loadPreview()
    emit('queued')
  } catch (error) {
    toast.error(t('catalogueExport.syncNow'), errorMessage(error))
  } finally {
    loading.value = false
  }
}
function resetSelection() {
  if (!settings.value) return
  for (const key of selectionTypes) settings.value[key] = []
  settings.value.scope = 'selected'
}
function clearSelection(key: typeof selectionTypes[number]) {
  if (!settings.value) return
  settings.value[key] = []
  if (key === 'productIds') settings.value.scope = 'selected'
}
function selectAll(key: typeof selectionTypes[number]) {
  if (!settings.value || key === 'excludeIds') return
  settings.value[key] = []
  if (key === 'productIds') settings.value.scope = 'all'
}
function isAllSelected(key: typeof selectionTypes[number]) {
  if (!settings.value || key === 'excludeIds') return false
  if (key === 'productIds') return settings.value.scope === 'all'
  return settings.value.scope === 'all' || settings.value[key].length === 0
}
function isSelectionDisabled(key: typeof selectionTypes[number]) {
  return settings.value?.scope === 'all' && key !== 'excludeIds' && key !== 'productIds'
}
function selectionClearOptions(key: typeof selectionTypes[number]) {
  const hasSelection = Boolean(settings.value?.[key].length)
    || (key === 'productIds' && settings.value?.scope === 'all')
  return hasSelection && !isSelectionDisabled(key)
    ? { 'aria-label': t('catalogueExport.clearSelection') }
    : false
}
function selectCategoryRoot(value: string | string[]) {
  if (settings.value) settings.value.categoryRootId = typeof value === 'string' ? value : ''
}
function selectValues(key: typeof selectionTypes[number], value: string | string[]) {
  if (!settings.value) return
  settings.value[key] = Array.isArray(value) ? value : []
  if (key === 'productIds') settings.value.scope = 'selected'
}
function selectChannel(value: string | string[]) {
  if (settings.value) settings.value.salesChannelId = typeof value === 'string' ? value : ''
}
function selectLanguage(value: string | string[]) {
  if (settings.value) settings.value.destinationLocaleId = typeof value === 'string' ? value : ''
}
function selectLocal(value: string | string[]) {
  localId.value = typeof value === 'string' ? value : ''
}
function selectTarget(value: string | string[]) {
  targetId.value = typeof value === 'string' ? value : ''
}
function rememberLabels(items: { value: string, label: string }[]) {
  for (const item of items) labels.value[item.value] = item.label
}
async function loadMappingLabels() {
  const groups = new Map<string, Set<string>>()
  for (const row of visibleMappings.value) {
    for (const [side, id] of [['local', row.localId], ['target', row.targetId]]) {
      if (!side || !id || labels.value[id]) continue
      const key = `${side}/${row.type}`
      if (!groups.has(key)) groups.set(key, new Set())
      groups.get(key)!.add(id)
    }
  }
  await Promise.all([...groups].map(async ([key, ids]) => {
    const response = await apiFetch<{ items: { id: string, label: string }[] }>(`/integrations/${props.connectionId}/exports/references/${key}?ids=${[...ids].join(',')}`)
    for (const item of response.items) labels.value[item.id] = item.label
  }))
}
function addMapping() {
  if (!settings.value || !localId.value || !targetId.value) return
  settings.value.mappings = {
    ...settings.value.mappings,
    [mappingType.value]: {
      ...settings.value.mappings[mappingType.value],
      [localId.value]: targetId.value
    }
  }
  localId.value = ''
  targetId.value = ''
}
function removeMapping(row: MappingRow) {
  delete settings.value?.mappings[row.type]?.[row.localId]
}
function showChanges(item: PreviewItem) {
  detailItem.value = item
  detailOpen.value = true
}
function confirmPublication() {
  confirmationAction.value = 'preview'
  confirmationOpen.value = true
}
function confirmSync() {
  confirmationAction.value = 'sync'
  confirmationOpen.value = true
}
watch(mappingType, () => {
  localId.value = ''
  targetId.value = ''
})
watch([page, pageSize], () => {
  void refreshProgress()
})
watch(visibleMappings, () => {
  void loadMappingLabels().catch(() => undefined)
})
onMounted(async () => {
  try {
    const response = await apiFetch<{ settings: Settings }>(`/integrations/${props.connectionId}/exports/configuration`)
    settings.value = normalizeSettings(response.settings)
    savedSettings.value = JSON.stringify(settings.value)
    await loadPreview()
    timer = setInterval(() => {
      if (pending.value || pollingError.value || settings.value?.automaticSync) void refreshProgress()
    }, 2000)
  } catch (error) {
    toast.error(exportLabel('title'), errorMessage(error))
  }
})
onBeforeUnmount(() => clearInterval(timer))
defineExpose({ save })
</script>

<template>
  <div v-if="settings" class="space-y-6">
    <template v-if="mode === 'export'">
      <UPageCard
        :title="exportLabel('title')"
        :description="t(isWoo ? 'catalogueExport.wooDescription' : 'catalogueExport.description')"
      >
        <p class="text-sm text-muted">
          {{ t('catalogueExport.emptySelectionNotice') }}
        </p>
        <UFormField :label="t('catalogueExport.scope')">
          <USelect
            v-model="settings.scope"
            :items="scopeOptions"
            class="w-80 max-w-full"
          />
        </UFormField>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <UFormField
            v-if="!isWoo"
            :label="t('catalogueExport.salesChannel')"
          >
            <IntegrationReferenceSelect
              :model-value="settings.salesChannelId"
              :connection-id="connectionId"
              side="target"
              type="salesChannel"
              :placeholder="t('catalogueExport.salesChannel')"
              @update:model-value="selectChannel"
            />
          </UFormField>
          <UFormField
            v-else
            :label="t('catalogueExport.language')"
            :description="t('catalogueExport.languageDescription')"
          >
            <IntegrationReferenceSelect
              :model-value="settings.destinationLocaleId || ''"
              :connection-id="connectionId"
              side="local"
              type="locale"
              :placeholder="t('catalogueExport.languageDefault')"
              @update:model-value="selectLanguage"
            />
          </UFormField>
          <UFormField
            v-for="key in selectionTypes"
            :key="key"
            :label="t(`catalogueExport.selection.${key}`)"
          >
            <IntegrationReferenceSelect
              :model-value="settings[key]"
              :connection-id="connectionId"
              side="local"
              :type="selectionReferenceTypes[key]"
              multiple
              :disabled="isSelectionDisabled(key)"
              :all-option-label="key === 'excludeIds' ? undefined : t(`catalogueExport.allOptions.${key}`)"
              :all-selected="isAllSelected(key)"
              :clear="selectionClearOptions(key)"
              :reset-model-value-on-clear="false"
              :placeholder="t(`catalogueExport.selection.${key}`)"
              @update:model-value="selectValues(key, $event)"
              @selected="rememberLabels"
              @select-all="selectAll(key)"
              @clear="clearSelection(key)"
            />
            <p class="mt-1 text-xs text-muted">
              {{ isAllSelected(key) ? t(`catalogueExport.allOptions.${key}`) : t('catalogueExport.selected', { count: settings[key].length }) }}
            </p>
          </UFormField>
        </div>
        <UCheckbox
          v-model="settings.includeDescendants"
          :label="t('catalogueExport.descendants')"
        />
        <UCheckbox
          v-model="settings.includeVariants"
          :label="t('catalogueExport.variants')"
        />
        <UFormField :label="t('catalogueExport.publicationMode')">
          <USelect
            v-model="settings.publicationMode"
            :items="modeOptions"
            class="w-80 max-w-full"
          />
        </UFormField>
        <UCheckbox
          v-model="settings.createMissingReferences"
          :label="t('catalogueExport.createReferences')"
          :description="t(isWoo ? 'catalogueExport.wooCreateReferencesDescription' : 'catalogueExport.createReferencesDescription')"
        />
        <UFormField
          v-if="settings.createMissingReferences"
          :label="t('catalogueExport.categoryRoot')"
          :description="exportLabel('categoryRootDescription')"
        >
          <IntegrationReferenceSelect
            :model-value="settings.categoryRootId"
            :connection-id="connectionId"
            side="target"
            type="category"
            :placeholder="t('catalogueExport.categoryRoot')"
            @update:model-value="selectCategoryRoot"
          />
        </UFormField>
        <UCheckbox
          v-model="settings.automaticSync"
          :label="t('catalogueExport.automaticSync')"
          :description="exportLabel('automaticSyncDescription')"
        />
        <div class="flex flex-wrap gap-2">
          <UButton
            :label="t('common.save')"
            icon="i-lucide-save"
            :loading="saving"
            :disabled="!dirty"
            @click="save"
          />
          <UButton
            :label="t('catalogueExport.preview')"
            icon="i-lucide-scan-eye"
            :loading="loading"
            :disabled="pending || (!isWoo && !settings.salesChannelId)"
            @click="preview"
          />
          <UButton
            :label="t('catalogueExport.publish')"
            icon="i-lucide-upload"
            :disabled="!canPublish"
            @click="confirmPublication"
          />
          <UButton
            :label="t('catalogueExport.syncNow')"
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="outline"
            :disabled="pending || (!isWoo && !settings.salesChannelId)"
            @click="confirmSync"
          />
          <UButton
            :label="t('catalogueExport.resetSelection')"
            color="neutral"
            variant="ghost"
            @click="resetSelection"
          />
        </div>
      </UPageCard>
      <IntegrationRunProgress
        v-if="run"
        :connection-id="connectionId"
        :run="run"
        @cancelled="cancelled"
      />
      <UAlert
        v-if="settings.automaticSync && syncHealth && syncHealth.pendingChanges > 0"
        icon="i-lucide-clock"
        :color="syncHealth.oldestSeconds >= 120 ? 'warning' : 'info'"
        variant="subtle"
        :title="t('catalogueExport.backlogTitle')"
        :description="t('catalogueExport.backlogDescription', { count: syncHealth.pendingChanges, seconds: syncHealth.oldestSeconds })"
      />
      <UAlert
        v-if="pollingError"
        icon="i-lucide-wifi-off"
        color="warning"
        variant="subtle"
        :title="t('integrations.runProgressUnavailable')"
        :description="t('integrations.runProgressUnavailableDescription')"
      />
      <UAlert
        v-if="plan"
        :title="exportLabel(`planStatuses.${plan.status}`)"
        :description="plan.stale || dirty ? t('catalogueExport.stale') : t('catalogueExport.summary', { total, creates: summary?.creates || 0, updates: summary?.updates || 0, blocked: summary?.blocked || 0 })"
        :color="plan.status === 'blocked' || plan.status === 'failed' ? 'warning' : 'info'"
        variant="subtle"
      />
      <AppDataTable
        table-key="integration-catalogue-export"
        :data="items"
        :columns="previewColumns"
        :column-labels="previewColumnLabels"
        :sortable="false"
        :loading="loading"
        max-height="max-h-[420px]"
      >
        <template #header>
          {{ t('catalogueExport.previewCount', { count: total }) }}
        </template>
        <template #empty>
          <AppEmptyState
            icon="i-lucide-upload"
            :title="t('catalogueExport.emptyTitle')"
            :description="t('catalogueExport.emptyDescription')"
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
    <template v-else>
      <UPageCard
        :title="t('catalogueExport.mappingTitle')"
        :description="t(isWoo ? 'catalogueExport.wooMappingDescription' : 'catalogueExport.mappingDescription')"
      >
        <UCheckboxGroup
          v-model="settings.fields"
          :items="fieldOptions"
        />
        <UFormField
          :label="t('catalogueExport.markup')"
          :description="t('catalogueExport.markupDescription')"
        >
          <UInput
            v-model="settings.priceMarkup"
            inputmode="decimal"
            class="w-40"
          />
        </UFormField>
        <p class="text-sm text-muted">
          {{ t(isWoo ? 'catalogueExport.wooAutomaticMapping' : 'catalogueExport.automaticMapping') }}
        </p>
        <div class="flex flex-wrap items-end gap-3">
          <UFormField :label="t('catalogueExport.mappingType')">
            <USelect
              v-model="mappingType"
              :items="mappingTypeOptions"
            />
          </UFormField>
          <UFormField :label="t('catalogueExport.source')">
            <IntegrationReferenceSelect
              :model-value="localId"
              :connection-id="connectionId"
              side="local"
              :type="mappingType"
              :placeholder="t('catalogueExport.source')"
              @update:model-value="selectLocal"
              @selected="rememberLabels"
            />
          </UFormField>
          <UFormField :label="exportLabel('destination')">
            <IntegrationReferenceSelect
              :model-value="targetId"
              :connection-id="connectionId"
              side="target"
              :type="mappingType"
              :placeholder="exportLabel('destination')"
              @update:model-value="selectTarget"
              @selected="rememberLabels"
            />
          </UFormField>
          <UButton
            :label="t('catalogueExport.addMapping')"
            :disabled="!localId || !targetId"
            @click="addMapping"
          />
        </div>
        <UButton
          :label="t('common.save')"
          :loading="saving"
          class="self-start"
          @click="save"
        />
      </UPageCard>
      <AppDataTable
        table-key="integration-export-mappings"
        :data="visibleMappings"
        :columns="mappingColumns"
        :column-labels="mappingColumnLabels"
        :sortable="false"
        max-height="max-h-[420px]"
      >
        <template #empty>
          <AppEmptyState
            icon="i-lucide-arrow-left-right"
            :title="t('catalogueExport.noMappings')"
            :description="t(isWoo ? 'catalogueExport.wooAutomaticMapping' : 'catalogueExport.automaticMapping')"
          />
        </template>
        <template #footer>
          <TablePaginationFooter
            v-model:page="mappingPage"
            v-model:page-size="mappingPageSize"
            :total="mappingRows.length"
          />
        </template>
      </AppDataTable>
    </template>
    <UModal
      v-model:open="confirmationOpen"
      :title="exportLabel(confirmationAction === 'sync' ? 'confirmSyncTitle' : 'confirmTitle')"
      :description="t(confirmationAction === 'sync' ? 'catalogueExport.confirmSyncDescription' : 'catalogueExport.confirmDescription')"
    >
      <template #footer>
        <UButton
          :label="t(confirmationAction === 'sync' ? 'catalogueExport.syncNow' : 'catalogueExport.publish')"
          :loading="loading"
          :disabled="confirmationAction === 'preview' ? !canPublish : pending"
          @click="publish"
        />
      </template>
    </UModal>
    <UModal
      v-model:open="detailOpen"
      :title="detailItem?.name"
      :description="exportLabel('changes')"
    >
      <template #body>
        <pre class="max-h-[480px] overflow-auto whitespace-pre-wrap break-words text-xs">{{ JSON.stringify(detailItem?.changes, null, 2) }}</pre>
        <template v-if="detailItem?.dependencies?.length">
          <h3 class="mt-4 font-semibold">
            {{ t('catalogueExport.referenceChanges') }}
          </h3>
          <pre class="max-h-[240px] overflow-auto whitespace-pre-wrap break-words text-xs">{{ JSON.stringify(detailItem.dependencies, null, 2) }}</pre>
        </template>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
type Connection = {
  id: string
  connectorKey: string
  name: string
  configuration: Record<string, string>
  status: string
  enabled: boolean
  createdAt: string
}

type ImportSettings = {
  areas: Record<string, boolean>
  productMatchOrder: string[]
  salesHistoryFrom: string | null
  salesContinuousSync: boolean
  salesContinuousStartedAt: string | null
  stockAuthority: 'shopware' | 'connect'
}

type ImportRun = {
  id: string
  type: string
  status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled'
  currentStage: string
  totalItems: number
  processedItems: number
  createdItems: number
  updatedItems: number
  failedItems: number
  failureReason: string | null
  createdAt: string
  startedAt: string | null
  completedAt: string | null
}

type ImportLog = {
  id: string
  level: 'info' | 'warning' | 'error'
  stage: string
  message: string
  context: Record<string, unknown>
  createdAt: string
}

type SalesSyncStatus = {
  enabled: boolean
  startedAt: string | null
  lastSyncedAt: string | null
  lastError: string | null
  lastErrorAt: string | null
  pendingOrders: number
}

const getApiErrorMessage = (error: unknown): string | null => {
  if (
    typeof error !== 'object'
    || error === null
    || !('data' in error)
    || typeof error.data !== 'object'
    || error.data === null
    || !('message' in error.data)
    || typeof error.data.message !== 'string'
  ) {
    return null
  }

  return error.data.message
}

const route = useRoute()
const { t } = useI18n()
const toast = useAppToast()
const connection = ref<Connection | null>(null)
const settings = ref<ImportSettings | null>(null)
const runs = ref<ImportRun[]>([])
const logs = ref<ImportLog[]>([])
const salesSyncStatus = ref<SalesSyncStatus | null>(null)
const selectedRunId = ref<string>()
const activeTab = useRouteTab('import')
const saving = ref(false)
const queueing = ref(false)
let pollingTimer: ReturnType<typeof setInterval> | null = null
let lastSalesStatusLoadAt = 0

const connectionId = computed(() => String(route.params.id))
const activeRun = computed(() =>
  runs.value.find(run => ['queued', 'running'].includes(run.status)) || null
)
const connectManagesStock = computed({
  get: () => settings.value?.stockAuthority === 'connect',
  set: (enabled: boolean) => {
    if (settings.value) {
      settings.value.stockAuthority = enabled ? 'connect' : 'shopware'
    }
  }
})
const visibleRun = computed(() => activeRun.value || runs.value[0] || null)
const salesSyncDescription = computed(() => {
  const status = salesSyncStatus.value
  if (!status?.enabled) {
    return t('integrations.salesSyncDisabled')
  }
  if (status.lastError) {
    return status.lastError
  }
  if (!status.lastSyncedAt) {
    return t('integrations.salesSyncWaiting')
  }

  return t('integrations.salesSyncLastRun', {
    date: formattedDate(status.lastSyncedAt),
    pending: status.pendingOrders
  })
})
const tabs = computed(() => [
  {
    label: t('integrations.importTab'),
    icon: 'i-lucide-download',
    value: 'import'
  },
  {
    label: t('integrations.mappingTab'),
    icon: 'i-lucide-arrow-left-right',
    value: 'mapping'
  },
  {
    label: t('integrations.historyTab'),
    icon: 'i-lucide-history',
    value: 'history'
  },
  {
    label: t('integrations.logsTab'),
    icon: 'i-lucide-scroll-text',
    value: 'logs'
  }
])
const importAreas = computed(() => [
  {
    key: 'translations',
    label: t('integrations.areaTranslations'),
    description: t('integrations.areaTranslationsDescription')
  },
  {
    key: 'manufacturers',
    label: t('integrations.areaManufacturers'),
    description: t('integrations.areaManufacturersDescription')
  },
  {
    key: 'taxes',
    label: t('integrations.areaTaxes'),
    description: t('integrations.areaTaxesDescription')
  },
  {
    key: 'units',
    label: t('integrations.areaUnits'),
    description: t('integrations.areaUnitsDescription')
  },
  {
    key: 'deliveryTimes',
    label: t('integrations.areaDeliveryTimes'),
    description: t('integrations.areaDeliveryTimesDescription')
  },
  {
    key: 'prices',
    label: t('integrations.areaPrices'),
    description: t('integrations.areaPricesDescription')
  },
  {
    key: 'variants',
    label: t('integrations.areaVariants'),
    description: t('integrations.areaVariantsDescription')
  },
  {
    key: 'customFields',
    label: t('integrations.areaCustomFields'),
    description: t('integrations.areaCustomFieldsDescription')
  },
  {
    key: 'properties',
    label: t('integrations.areaProperties'),
    description: t('integrations.areaPropertiesDescription')
  },
  {
    key: 'tags',
    label: t('integrations.areaTags'),
    description: t('integrations.areaTagsDescription')
  },
  {
    key: 'categories',
    label: t('integrations.areaCategories'),
    description: t('integrations.areaCategoriesDescription')
  },
  {
    key: 'channelPublications',
    label: t('integrations.areaChannelPublications'),
    description: t('integrations.areaChannelPublicationsDescription')
  },
  {
    key: 'productDownloads',
    label: t('integrations.areaProductDownloads'),
    description: t('integrations.areaProductDownloadsDescription')
  },
  {
    key: 'crossSellings',
    label: t('integrations.areaCrossSellings'),
    description: t('integrations.areaCrossSellingsDescription')
  }
])
const productMatchOptions = computed(() => [
  {
    value: 'externalId',
    label: t('integrations.matchExternalId'),
    description: t('integrations.matchExternalIdDescription'),
    disabled: true
  },
  {
    value: 'sku',
    label: t('integrations.matchSku'),
    description: t('integrations.matchSkuDescription')
  },
  {
    value: 'ean',
    label: t('integrations.matchEan'),
    description: t('integrations.matchEanDescription')
  }
])

const formattedDate = (value: string) =>
  new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(new Date(value))

const progress = (run: ImportRun) =>
  run.totalItems > 0
    ? Math.min(run.processedItems, run.totalItems)
    : null

const percentage = (run: ImportRun) =>
  run.totalItems > 0
    ? Math.round((run.processedItems / run.totalItems) * 100)
    : 0

const progressLabel = (run: ImportRun) => {
  if (run.status === 'queued') {
    return t('integrations.importQueuedStatus')
  }

  if (run.type === 'sales' && run.totalItems > 0) {
    return t('integrations.salesImportProgress', {
      processed: run.processedItems,
      total: run.totalItems,
      percentage: percentage(run)
    })
  }

  if (run.status === 'completed' && run.totalItems > 0) {
    return t('integrations.importProgressWithPercent', {
      processed: run.processedItems,
      total: run.totalItems,
      percentage: percentage(run)
    })
  }

  if (run.currentStage !== 'products') {
    return stageLabel(run.currentStage)
  }

  return t('integrations.importProgressWithPercent', {
    processed: run.processedItems,
    total: run.totalItems,
    percentage: percentage(run)
  })
}

const statusLabel = (status: ImportRun['status']) =>
  t(`integrations.importStatus.${status}`)

const stageLabel = (stage: string) =>
  t(`integrations.importStages.${stage}`)

const levelColor = (level: ImportLog['level']) => {
  if (level === 'error') {
    return 'error' as const
  }

  if (level === 'warning') {
    return 'warning' as const
  }

  return 'info' as const
}

const contextDetails = (context: Record<string, unknown>) =>
  Object.entries(context)
    .map(([key, value]) => `${key}: ${String(value)}`)
    .join(' · ')

const logMessage = (log: ImportLog) =>
  log.message.startsWith('integrationLog.')
    ? t(log.message, log.context)
    : log.message

const loadConfiguration = async () => {
  const response = await apiFetch<{
    connection: Connection
    importSettings: ImportSettings
  }>(`/integrations/${connectionId.value}/configuration`)
  connection.value = response.connection
  settings.value = response.importSettings
}

const loadRuns = async () => {
  const response = await apiFetch<{ runs: ImportRun[] }>(
    `/integrations/${connectionId.value}/imports`
  )
  runs.value = response.runs

  if (!selectedRunId.value && response.runs[0]) {
    selectedRunId.value = response.runs[0].id
  }
}

const loadLogs = async () => {
  if (!selectedRunId.value) {
    logs.value = []
    return
  }

  const response = await apiFetch<{ logs: ImportLog[] }>(
    `/integrations/${connectionId.value}/imports/${selectedRunId.value}/logs`
  )
  logs.value = response.logs
}

const loadSalesSyncStatus = async () => {
  if (connection.value?.connectorKey !== 'shopware') {
    return
  }

  salesSyncStatus.value = await apiFetch<SalesSyncStatus>(
    `/integrations/${connectionId.value}/sales-sync`
  )
  lastSalesStatusLoadAt = Date.now()
}

const openLogs = async (run: ImportRun) => {
  selectedRunId.value = run.id
  activeTab.value = 'logs'
  await loadLogs()
}

const load = async () => {
  await Promise.all([loadConfiguration(), loadRuns()])
}

const save = async () => {
  if (!settings.value) {
    return
  }

  saving.value = true
  try {
    const response = await apiFetch<{
      message: string
      importSettings: ImportSettings
    }>(`/integrations/${connectionId.value}/configuration`, {
      method: 'PATCH',
      body: {
        importSettings: settings.value
      }
    })
    settings.value = response.importSettings
    await loadSalesSyncStatus()
    toast.success(t('common.saved'), response.message)
  } catch (error: unknown) {
    toast.error(
      t('integrations.updateFailed'),
      getApiErrorMessage(error) || t('common.tryAgain')
    )
  } finally {
    saving.value = false
  }
}

const queueImport = async () => {
  queueing.value = true
  try {
    const response = await apiFetch<{
      message: string
      run: ImportRun
    }>(`/integrations/${connectionId.value}/imports/products`, {
      method: 'POST'
    })
    runs.value = [
      response.run,
      ...runs.value.filter(run => run.id !== response.run.id)
    ]
    selectedRunId.value = response.run.id
    toast.success(t('integrations.importProducts'), response.message)
  } catch (error: unknown) {
    toast.error(
      t('integrations.importFailed'),
      getApiErrorMessage(error) || t('common.tryAgain')
    )
  } finally {
    queueing.value = false
  }
}

const queueSalesImport = async () => {
  if (!settings.value) {
    return
  }

  queueing.value = true
  try {
    const configuration = await apiFetch<{
      importSettings: ImportSettings
    }>(`/integrations/${connectionId.value}/configuration`, {
      method: 'PATCH',
      body: {
        importSettings: settings.value
      }
    })
    settings.value = configuration.importSettings

    const response = await apiFetch<{
      message: string
      run: ImportRun
    }>(`/integrations/${connectionId.value}/imports/sales`, {
      method: 'POST'
    })
    runs.value = [
      response.run,
      ...runs.value.filter(run => run.id !== response.run.id)
    ]
    selectedRunId.value = response.run.id
    toast.success(t('integrations.importSales'), response.message)
  } catch (error: unknown) {
    toast.error(
      t('integrations.salesImportFailed'),
      getApiErrorMessage(error) || t('common.tryAgain')
    )
  } finally {
    queueing.value = false
  }
}

const updateSalesHistoryFrom = (value: string) => {
  if (settings.value) {
    settings.value.salesHistoryFrom = value || null
  }
}

onMounted(() => {
  void loadSalesSyncStatus()

  pollingTimer = setInterval(() => {
    void loadRuns()

    if (activeTab.value === 'import' && Date.now() - lastSalesStatusLoadAt > 10000) {
      void loadSalesSyncStatus()
    }

    if (activeTab.value === 'logs') {
      void loadLogs()
    }
  }, 2000)
})

onBeforeUnmount(() => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
  }
})

await load()
</script>

<template>
  <UDashboardPanel id="integration-configuration" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar
        :title="connection?.name || t('integrations.configuration')"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
          <UButton
            :label="t('integrations.backToIntegrations')"
            to="/integrations"
            color="neutral"
            variant="ghost"
          />
        </template>
        <template #right>
          <UButton
            :label="t('common.save')"
            :loading="saving"
            @click="save"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <UTabs
          v-model="activeTab"
          :items="tabs"
          :content="false"
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="mx-auto w-full max-w-7xl space-y-6">
        <UPageCard v-if="connection" variant="subtle">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
              <p class="text-sm text-muted">
                {{ t('integrations.baseUrl') }}
              </p>
              <p class="mt-1 truncate font-medium text-highlighted">
                {{ connection.configuration.baseUrl }}
              </p>
            </div>
            <UBadge
              :label="connection.status"
              :color="connection.status === 'active' ? 'success' : 'neutral'"
              variant="subtle"
            />
          </div>
        </UPageCard>

        <template v-if="settings && activeTab === 'import'">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-highlighted">
                {{ t('integrations.importScope') }}
              </h2>
              <p class="mt-1 text-sm text-muted">
                {{ t('integrations.importScopeDescription') }}
              </p>
            </div>
            <UButton
              :label="t('integrations.importCatalogue')"
              :loading="queueing"
              :disabled="activeRun !== null || !connection?.enabled || connection.status !== 'active'"
              @click="queueImport"
            />
          </div>

          <UPageCard>
            <div class="grid gap-5 sm:grid-cols-2">
              <USwitch
                :model-value="true"
                :label="t('integrations.areaProducts')"
                :description="t('integrations.areaProductsDescription')"
                disabled
              />
              <USwitch
                v-for="area in importAreas"
                :key="area.key"
                v-model="settings.areas[area.key]"
                :label="area.label"
                :description="area.description"
              />
            </div>
          </UPageCard>

          <UPageCard v-if="connection?.connectorKey === 'shopware'">
            <template #header>
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="font-medium text-highlighted">
                    {{ t('integrations.salesImportTitle') }}
                  </p>
                  <p class="mt-1 text-sm text-muted">
                    {{ t('integrations.salesImportDescription') }}
                  </p>
                </div>
                <UButton
                  :label="t('integrations.importSales')"
                  :loading="queueing"
                  :disabled="activeRun !== null || !connection?.enabled || connection.status !== 'active' || (!settings.areas.salesCustomers && !settings.areas.salesOrders)"
                  @click="queueSalesImport"
                />
              </div>
            </template>
            <div class="grid gap-5 sm:grid-cols-2">
              <USwitch
                v-model="settings.areas.salesCustomers"
                :label="t('integrations.salesCustomers')"
                :description="t('integrations.salesCustomersDescription')"
              />
              <USwitch
                v-model="settings.areas.salesOrders"
                :label="t('integrations.salesOrders')"
                :description="t('integrations.salesOrdersDescription')"
              />
              <UFormField :label="t('integrations.salesHistoryFrom')">
                <CustomFieldDateInput
                  :model-value="settings.salesHistoryFrom ?? ''"
                  :with-time="false"
                  @update:model-value="updateSalesHistoryFrom"
                />
              </UFormField>
              <USwitch
                v-model="settings.salesContinuousSync"
                :label="t('integrations.salesContinuousSync')"
                :description="t('integrations.salesContinuousSyncDescription')"
              />
              <USwitch
                v-model="connectManagesStock"
                :label="t('integrations.connectManagesStock')"
                :description="t('integrations.connectManagesStockDescription')"
              />
            </div>
            <UAlert
              v-if="salesSyncStatus"
              class="mt-5"
              :icon="salesSyncStatus.lastError ? 'i-lucide-circle-alert' : 'i-lucide-refresh-cw'"
              :color="salesSyncStatus.lastError ? 'error' : salesSyncStatus.lastSyncedAt ? 'success' : 'info'"
              variant="subtle"
              :title="t('integrations.salesSyncStatus')"
              :description="salesSyncDescription"
            />
          </UPageCard>

          <UAlert
            icon="i-lucide-list-tree"
            color="info"
            variant="subtle"
            :title="t('integrations.importPipelineTitle')"
            :description="t('integrations.importPipelineDescription')"
          />

          <UPageCard v-if="visibleRun" variant="subtle">
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
              <div class="flex items-center gap-2">
                <span class="font-medium text-highlighted">
                  {{ stageLabel(visibleRun.currentStage) }}
                </span>
                <UBadge
                  :label="statusLabel(visibleRun.status)"
                  :color="visibleRun.status === 'failed' ? 'error' : visibleRun.status === 'completed' ? 'success' : 'primary'"
                  variant="subtle"
                  size="xs"
                />
              </div>
              <div class="flex items-center gap-3">
                <span class="text-muted">
                  {{ progressLabel(visibleRun) }}
                </span>
                <IntegrationImportCancelButton
                  v-if="['queued', 'running'].includes(visibleRun.status)"
                  :connection-id="connectionId"
                  :run-id="visibleRun.id"
                  @cancelled="loadRuns"
                />
              </div>
            </div>
            <UProgress
              class="mt-2"
              :model-value="progress(visibleRun) || 0"
              :max="visibleRun.totalItems || 100"
              size="sm"
            />
          </UPageCard>
        </template>

        <template v-else-if="settings && activeTab === 'mapping'">
          <div>
            <h2 class="text-lg font-semibold text-highlighted">
              {{ t('integrations.productMatching') }}
            </h2>
            <p class="mt-1 text-sm text-muted">
              {{ t('integrations.productMatchingDescription') }}
            </p>
          </div>

          <UPageCard>
            <UCheckboxGroup
              v-model="settings.productMatchOrder"
              :items="productMatchOptions"
              class="grid gap-5 sm:grid-cols-3"
            />
          </UPageCard>

          <UPageCard>
            <h3 class="font-medium text-highlighted">
              {{ t('integrations.canonicalMapping') }}
            </h3>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div class="rounded-md border border-default p-3">
                Shopware product number → {{ t('products.sku') }}
              </div>
              <div class="rounded-md border border-default p-3">
                EAN → EAN
              </div>
              <div class="rounded-md border border-default p-3">
                {{ t('products.name') }} → {{ t('products.name') }}
              </div>
              <div class="rounded-md border border-default p-3">
                Shopware price → {{ t('products.price') }}
              </div>
            </div>
          </UPageCard>
        </template>

        <template v-else-if="activeTab === 'history'">
          <div>
            <h2 class="text-lg font-semibold text-highlighted">
              {{ t('integrations.importHistory') }}
            </h2>
            <p class="mt-1 text-sm text-muted">
              {{ t('integrations.importHistoryDescription') }}
            </p>
          </div>

          <UPageCard variant="subtle" :ui="{ container: 'divide-y divide-default' }">
            <p v-if="!runs.length" class="text-sm text-muted">
              {{ t('integrations.noImportHistory') }}
            </p>
            <div
              v-for="run in runs"
              :key="run.id"
              class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <div class="flex items-center gap-2">
                  <p class="font-medium text-highlighted">
                    {{ t(run.type === 'sales' ? 'integrations.importSales' : 'integrations.importProducts') }}
                  </p>
                  <UBadge
                    :label="statusLabel(run.status)"
                    :color="run.status === 'failed' ? 'error' : run.status === 'completed' ? 'success' : 'primary'"
                    variant="subtle"
                  />
                </div>
                <p class="mt-1 text-sm text-muted">
                  {{ formattedDate(run.createdAt) }}
                </p>
              </div>
              <div class="text-sm text-muted sm:text-right">
                <p>
                  {{ t('integrations.importSummary', {
                    created: run.createdItems,
                    updated: run.updatedItems,
                    failed: run.failedItems
                  }) }}
                </p>
                <p v-if="run.failureReason" class="mt-1 text-error">
                  {{ run.failureReason }}
                </p>
                <UButton
                  class="mt-2"
                  :label="t('integrations.viewLog')"
                  color="neutral"
                  variant="ghost"
                  size="xs"
                  @click="openLogs(run)"
                />
              </div>
            </div>
          </UPageCard>
        </template>

        <template v-else-if="activeTab === 'logs'">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-highlighted">
                {{ t('integrations.importLog') }}
              </h2>
              <p class="mt-1 text-sm text-muted">
                {{ t('integrations.importLogDescription') }}
              </p>
            </div>
            <USelect
              v-model="selectedRunId"
              :items="runs.map(run => ({
                label: `${formattedDate(run.createdAt)} · ${statusLabel(run.status)}`,
                value: run.id
              }))"
              class="min-w-64"
              @update:model-value="loadLogs"
            />
          </div>

          <UPageCard variant="subtle" :ui="{ container: 'divide-y divide-default' }">
            <p v-if="!selectedRunId" class="text-sm text-muted">
              {{ t('integrations.noImportHistory') }}
            </p>
            <p v-else-if="!logs.length" class="text-sm text-muted">
              {{ t('integrations.noLogEntries') }}
            </p>
            <div
              v-for="log in logs"
              :key="log.id"
              class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
            >
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <UBadge
                    :label="log.level"
                    :color="levelColor(log.level)"
                    variant="subtle"
                    size="xs"
                  />
                  <span class="text-xs font-medium text-muted">
                    {{ stageLabel(log.stage) }}
                  </span>
                </div>
                <p class="mt-2 text-sm text-highlighted">
                  {{ logMessage(log) }}
                </p>
                <p v-if="contextDetails(log.context)" class="mt-1 text-xs text-muted">
                  {{ contextDetails(log.context) }}
                </p>
              </div>
              <p class="shrink-0 text-xs text-muted">
                {{ formattedDate(log.createdAt) }}
              </p>
            </div>
          </UPageCard>
        </template>
      </div>
    </template>
  </UDashboardPanel>
</template>

<script setup lang="ts">
import ananasLogo from '~/assets/media/ananas-logo.svg'
import olxLogo from '~/assets/media/pik-logo.webp'
import shopwareLogo from '~/assets/media/shopware-logo.png'
import woocommerceLogo from '~/assets/media/woocommerce-logo.png'

type Definition = {
  key: string
  name: string
  directions: string[]
  credentials: string[]
  configuration: string[]
}
type Connection = {
  id: string
  connectorKey: string
  name: string
  directions: string[]
  configuration: Record<string, string>
  status: string
  enabled: boolean
  createdAt: string
}

type ImportRun = {
  id: string
  type: string
  status: 'queued' | 'running' | 'completed' | 'failed'
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

const localePath = useLocalePath()
const { t } = useI18n()
const toast = useAppToast()
const open = ref(false)
const definitions = ref<Definition[]>([])
const connections = ref<Connection[]>([])
const selected = ref<Definition | null>(null)
const name = ref('')
const configuration = ref<Record<string, string>>({})
const secrets = ref<Record<string, string>>({})
const tested = ref(false)
const removeOpen = ref(false)
const connectionToRemove = ref<Connection | null>(null)
const updatingId = ref<string | null>(null)
const syncingSalesChannelsId = ref<string | null>(null)
const queueingImportId = ref<string | null>(null)
const importRuns = ref<Record<string, ImportRun[]>>({})
const validation = useFormValidation()
const validationErrors = validation.errors
let importPollingTimer: ReturnType<typeof setInterval> | null = null
const connectorLogos: Record<string, string> = {
  shopware: shopwareLogo,
  woocommerce: woocommerceLogo,
  olx: olxLogo,
  ananas: ananasLogo
}
const credentialLabel = (credential: string) =>
  ({
    accessKeyId: t('integrations.accessKeyId'),
    secretAccessKey: t('integrations.secretAccessKey'),
    consumerKey: t('integrations.consumerKey'),
    consumerSecret: t('integrations.consumerSecret'),
    accessToken: t('integrations.accessToken'),
    apiKey: t('integrations.apiKey'),
    clientId: t('integrations.clientId'),
    clientSecret: t('integrations.clientSecret')
  })[credential] || credential
const configurationLabel = (field: string) => t('integrations.' + field)
const installedDate = (date: string) => {
  const value = new Date(date)
  return `${String(value.getDate()).padStart(2, '0')}.${String(value.getMonth() + 1).padStart(2, '0')}.${value.getFullYear()}.`
}

const connectedBaseUrl = (connection: Connection) => {
  if (!['shopware', 'woocommerce'].includes(connection.connectorKey)) {
    return null
  }

  return connection.configuration.baseUrl?.trim() || null
}

const load = async () => {
  const response = await apiFetch<{
    definitions: Definition[]
    connections: Connection[]
  }>('/integrations')
  definitions.value = response.definitions
  connections.value = response.connections
}

const loadImportRuns = async (connection: Connection) => {
  if (connection.connectorKey !== 'shopware') {
    return
  }

  const response = await apiFetch<{ runs: ImportRun[] }>(
    `/integrations/${connection.id}/imports`
  )
  importRuns.value = {
    ...importRuns.value,
    [connection.id]: response.runs
  }
}

const activeImport = (connection: Connection) =>
  importRuns.value[connection.id]?.find(run =>
    ['queued', 'running'].includes(run.status)
  ) || null

const hasActiveImports = computed(() =>
  connections.value.some(connection => activeImport(connection) !== null)
)

const stopImportPolling = () => {
  if (!importPollingTimer) {
    return
  }

  clearInterval(importPollingTimer)
  importPollingTimer = null
}

const refreshImportRuns = async () => {
  await Promise.all(
    connections.value
      .filter(connection => connection.connectorKey === 'shopware')
      .map(loadImportRuns)
  )

  if (!hasActiveImports.value) {
    stopImportPolling()
  }
}

const startImportPolling = () => {
  if (importPollingTimer || !hasActiveImports.value) {
    return
  }

  importPollingTimer = setInterval(() => {
    void refreshImportRuns()
  }, 2000)
}

const importProgress = (run: ImportRun) =>
  run.totalItems > 0
    ? Math.min(run.processedItems, run.totalItems)
    : null

const importProgressLabel = (run: ImportRun) => {
  if (run.status === 'queued') {
    return t('integrations.importQueuedStatus')
  }

  if (run.currentStage !== 'products') {
    return t(`integrations.importStages.${run.currentStage}`)
  }

  return t('integrations.importProgress', {
    processed: run.processedItems,
    total: run.totalItems || '…'
  })
}

const queueProductImport = async (connection: Connection) => {
  queueingImportId.value = connection.id
  try {
    const response = await apiFetch<{
      message: string
      run: ImportRun
    }>(`/integrations/${connection.id}/imports/products`, {
      method: 'POST'
    })
    importRuns.value = {
      ...importRuns.value,
      [connection.id]: [
        response.run,
        ...(importRuns.value[connection.id] || []).filter(
          run => run.id !== response.run.id
        )
      ]
    }
    toast.success(t('integrations.importCatalogue'), response.message)
    startImportPolling()
  } catch (error: any) {
    const existingRun = error?.data?.run as ImportRun | undefined
    if (existingRun) {
      importRuns.value = {
        ...importRuns.value,
        [connection.id]: [
          existingRun,
          ...(importRuns.value[connection.id] || []).filter(
            run => run.id !== existingRun.id
          )
        ]
      }
      startImportPolling()
    }
    toast.error(
      t('integrations.importFailed'),
      error?.data?.message || t('common.tryAgain')
    )
  } finally {
    queueingImportId.value = null
  }
}

const add = (definition: Definition) => {
  selected.value = definition
  name.value = definition.name
  configuration.value = {}
  secrets.value = {}
  tested.value = false
  validation.clear()
  open.value = true
}

const updateDraftConfiguration = (field: string, value: string) => {
  configuration.value[field] = value
  tested.value = false
  validation.clear(`configuration.${field}`)
}

const updateDraftSecret = (credential: string, value: string) => {
  secrets.value[credential] = value
  tested.value = false
  validation.clear(`secrets.${credential}`)
}

const validateConnection = () => {
  if (!selected.value) return false

  return validation.requireFields(
    [
      {
        field: 'name',
        value: name.value,
        label: t('integrations.name'),
        message: t('common.requiredField')
      },
      ...selected.value.configuration.map(field => ({
        field: `configuration.${field}`,
        value: configuration.value[field],
        label: configurationLabel(field),
        message: t('common.requiredField')
      })),
      ...selected.value.credentials.map(credential => ({
        field: `secrets.${credential}`,
        value: secrets.value[credential],
        label: credentialLabel(credential),
        message: t('common.requiredField')
      }))
    ],
    toast,
    t('integrations.testFailed'),
    fields => t('common.requiredFields', { fields: fields.join(', ') })
  )
}

const save = async () => {
  if (!selected.value) return
  if (!validateConnection()) return

  try {
    await apiFetch('/integrations', {
      method: 'POST',
      body: {
        connectorKey: selected.value.key,
        name: name.value,
        configuration: configuration.value,
        secrets: secrets.value
      }
    })
    open.value = false
    await load()
    await refreshImportRuns()
    toast.success(t('integrations.saved'), t('common.changesSaved'))
  } catch (error: any) {
    validation.notifyApiError(error, toast, t('integrations.updateFailed'), t('common.tryAgain'))
  }
}

const testDraft = async () => {
  if (!selected.value) return
  if (!validateConnection()) return

  try {
    const response = await apiFetch<{ message: string }>('/integrations/test', {
      method: 'POST',
      body: {
        connectorKey: selected.value.key,
        configuration: configuration.value,
        secrets: secrets.value
      }
    })
    tested.value = true
    toast.success(t('integrations.tested'), response.message)
  } catch (error: any) {
    tested.value = false
    validation.notifyApiError(error, toast, t('integrations.testFailed'), t('common.tryAgain'))
  }
}

const updateEnabled = async (connection: Connection, enabled: boolean) => {
  updatingId.value = connection.id
  try {
    const response = await apiFetch<{
      message: string
      connection: Connection
    }>('/integrations/' + connection.id, {
      method: 'PATCH',
      body: { enabled }
    })
    connections.value = connections.value.map(item =>
      item.id === connection.id ? response.connection : item
    )
    toast.success(t('common.saved'), response.message)
  } catch (error: any) {
    toast.error(t('integrations.updateFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    updatingId.value = null
  }
}

const askToRemove = (connection: Connection) => {
  connectionToRemove.value = connection
  removeOpen.value = true
}

const openConfiguration = (connection: Connection) =>
  navigateTo(localePath(`/integrations/${connection.id}`))

const connectionActions = (connection: Connection) => [
  [
    {
      label: t('integrations.configuration'),
      icon: 'i-lucide-settings-2',
      onSelect: () => openConfiguration(connection),
    },
  ],
  ...(connection.connectorKey === 'shopware'
    ? [
        [
          {
            label: t('integrations.importCatalogue'),
            icon: 'i-lucide-download',
            disabled:
              queueingImportId.value === connection.id ||
              activeImport(connection) !== null ||
              !connection.enabled ||
              connection.status !== 'active',
            onSelect: () => queueProductImport(connection),
          },
          {
            label: t('productSalesChannels.sync'),
            icon: 'i-lucide-refresh-cw',
            disabled: syncingSalesChannelsId.value === connection.id,
            onSelect: () => syncSalesChannels(connection),
          },
        ],
      ]
    : []),
  [
    {
      label: t('integrations.remove'),
      icon: 'i-lucide-trash-2',
      color: 'error' as const,
      disabled: updatingId.value === connection.id,
      onSelect: () => askToRemove(connection),
    },
  ],
]

const syncSalesChannels = async (connection: Connection) => {
  syncingSalesChannelsId.value = connection.id
  try {
    const response = await apiFetch<{ message: string }>(
      `/integrations/${connection.id}/sales-channels/sync`,
      { method: 'POST' }
    )
    toast.success(t('productSalesChannels.synced'), response.message)
  } catch (error: any) {
    toast.error(
      t('productSalesChannels.syncFailed'),
      error?.data?.message || t('integrations.testFailed')
    )
  } finally {
    syncingSalesChannelsId.value = null
  }
}

const remove = async () => {
  if (!connectionToRemove.value) return
  updatingId.value = connectionToRemove.value.id
  try {
    const response = await apiFetch<{ message: string }>(
      '/integrations/' + connectionToRemove.value.id,
      { method: 'DELETE' }
    )
    connections.value = connections.value.filter(
      connection => connection.id !== connectionToRemove.value?.id
    )
    const nextImportRuns = { ...importRuns.value }
    delete nextImportRuns[connectionToRemove.value.id]
    importRuns.value = nextImportRuns
    toast.success(t('integrations.remove'), response.message)
    removeOpen.value = false
    connectionToRemove.value = null
  } catch (error: any) {
    toast.error(t('integrations.removeFailed'), error?.data?.message || t('common.tryAgain'))
  } finally {
    updatingId.value = null
  }
}

await load()
await refreshImportRuns()

onMounted(startImportPolling)
onBeforeUnmount(stopImportPolling)
</script>

<template>
  <UDashboardPanel id="integrations" :ui="{ body: 'lg:py-12' }">
    <template #header>
      <UDashboardNavbar :title="t('integrations.title')">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <div class="w-full lg:max-w-7xl mx-auto space-y-8">
        <div>
          <h2 class="text-lg font-semibold text-highlighted">
            {{ t('integrations.available') }}
          </h2>
          <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <UCard v-for="definition in definitions" :key="definition.key">
              <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                  <img
                    :src="connectorLogos[definition.key]"
                    :alt="definition.name"
                    class="h-10 w-10 rounded-[5px] bg-white p-1.5 object-contain"
                  >
                  <h3 class="font-semibold text-highlighted">
                    {{ definition.name }}
                  </h3>
                </div>
                <p class="text-sm text-muted">
                  {{ t('integrations.syncAvailable') }}
                </p>
                <UButton :label="t('integrations.add')" block @click="add(definition)" />
              </div>
            </UCard>
          </div>
        </div>

        <div>
          <h2 class="text-lg font-semibold text-highlighted">
            {{ t('integrations.installed') }}
          </h2>
          <UPageCard variant="subtle" class="mt-4" :ui="{ container: 'divide-y divide-default' }">
            <p v-if="!connections.length" class="text-sm text-muted">
              {{ t('integrations.none') }}
            </p>
            <div
              v-for="connection in connections"
              :key="connection.id"
              class="py-4 first:pt-0 last:pb-0"
            >
              <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                  <USwitch
                    class="mr-2"
                    :model-value="connection.enabled"
                    :disabled="updatingId === connection.id"
                    @update:model-value="updateEnabled(connection, $event)"
                  />
                  <img
                    :src="connectorLogos[connection.connectorKey]"
                    :alt="connection.name"
                    class="h-10 w-10 shrink-0 rounded-[5px] bg-white p-1.5 object-contain"
                  >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ connection.name }}
                    </p>
                    <div
                      v-if="connectedBaseUrl(connection)"
                      class="mt-1 flex min-w-0 items-center gap-1.5 text-sm text-muted"
                    >
                      <UIcon
                        name="i-lucide-link"
                        class="size-3.5 shrink-0"
                      />
                      <span class="shrink-0">
                        {{ t('integrations.baseUrl') }}:
                      </span>
                      <span class="truncate text-highlighted">
                        {{ connectedBaseUrl(connection) }}
                      </span>
                    </div>
                    <p class="text-sm text-muted">
                      {{
                        t('integrations.installedOn', {
                          date: installedDate(connection.createdAt)
                        })
                      }}
                    </p>
                  </div>
                </div>
                <UDropdownMenu
                  :items="connectionActions(connection)"
                  :content="{ align: 'end' }"
                >
                  <UButton
                    icon="i-lucide-ellipsis-vertical"
                    color="neutral"
                    variant="ghost"
                    :loading="syncingSalesChannelsId === connection.id"
                    :disabled="updatingId === connection.id"
                    :aria-label="t('common.edit')"
                  />
                </UDropdownMenu>
              </div>
              <div
                v-if="activeImport(connection)"
                class="mt-3 rounded-md border border-default bg-elevated/20 p-3"
              >
                <div class="flex items-center justify-between gap-3 text-sm">
                  <span class="font-medium text-highlighted">
                    {{ t('integrations.importCatalogue') }}
                  </span>
                  <span class="text-muted">
                    {{ importProgressLabel(activeImport(connection)!) }}
                  </span>
                </div>
                <UProgress
                  class="mt-2"
                  :model-value="importProgress(activeImport(connection)!)"
                  :max="activeImport(connection)!.totalItems || 100"
                  size="sm"
                />
              </div>
            </div>
          </UPageCard>
        </div>
      </div>
    </template>
  </UDashboardPanel>

  <UModal v-model:open="open" :title="t('integrations.add')">
    <template #body>
      <UForm class="space-y-4" @submit.prevent="save">
        <UFormField :label="t('integrations.name')" :error="validationErrors.name" required>
          <UInput v-model="name" @update:model-value="validation.clear('name')" />
        </UFormField>
        <UFormField
          v-for="field in selected?.configuration || []"
          :key="field"
          :label="configurationLabel(field)"
          :error="validationErrors[`configuration.${field}`]"
          required
        >
          <UInput
            v-model="configuration[field]"
            @update:model-value="updateDraftConfiguration(field, $event)"
          />
        </UFormField>
        <UFormField
          v-for="credential in selected?.credentials || []"
          :key="credential"
          :label="credentialLabel(credential)"
          :error="validationErrors[`secrets.${credential}`]"
          required
        >
          <UInput
            v-model="secrets[credential]"
            type="password"
            @update:model-value="updateDraftSecret(credential, $event)"
          />
        </UFormField>
        <div class="flex justify-end gap-2">
          <UButton
            :label="t('common.cancel')"
            color="neutral"
            variant="subtle"
            @click="open = false"
          /><UButton
            :label="t('integrations.test')"
            color="neutral"
            type="button"
            @click="testDraft"
          /><UButton :label="t('common.save')" type="submit" :disabled="!tested" />
        </div>
      </UForm>
    </template>
  </UModal>

  <UModal
    v-model:open="removeOpen"
    :title="t('integrations.removeTitle')"
    :description="
      t('integrations.removeDescription', {
        name: connectionToRemove?.name || ''
      })
    "
  >
    <template #body>
      <div class="flex justify-end gap-2">
        <UButton
          :label="t('common.cancel')"
          color="neutral"
          variant="subtle"
          @click="removeOpen = false"
        />
        <UButton
          :label="t('integrations.remove')"
          color="error"
          :loading="updatingId === connectionToRemove?.id"
          @click="remove"
        />
      </div>
    </template>
  </UModal>
</template>

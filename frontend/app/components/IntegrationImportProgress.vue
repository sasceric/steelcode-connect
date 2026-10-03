<script setup lang="ts">
type ImportRun = {
  id: string
  connectionId: string
  connectionName: string
  connectorKey: string
  type: string
  status: 'queued' | 'running'
  currentStage: string
  totalItems: number
  processedItems: number
}

const localePath = useLocalePath()
const { t } = useI18n()
const runs = ref<ImportRun[]>([])
let pollingTimer: ReturnType<typeof setInterval> | null = null

const load = async () => {
  try {
    const response = await apiFetch<{ runs: ImportRun[] }>(
      '/integrations/imports/active'
    )
    runs.value = response.runs
  } catch {
    runs.value = []
  }
}

const progress = (run: ImportRun) =>
  run.totalItems > 0
    ? Math.min(run.processedItems, run.totalItems)
    : null

const percentage = (run: ImportRun) =>
  run.totalItems > 0
    ? Math.round((run.processedItems / run.totalItems) * 100)
    : 0

const label = (run: ImportRun) => {
  if (run.status === 'queued') {
    return t('integrations.importQueuedStatus')
  }

  if (run.currentStage !== 'products') {
    return t(`integrations.importStages.${run.currentStage}`)
  }

  return t('integrations.importProgressWithPercent', {
    processed: run.processedItems,
    total: run.totalItems,
    percentage: percentage(run)
  })
}

onMounted(() => {
  void load()
  pollingTimer = setInterval(() => {
    void load()
  }, 2000)
})

onBeforeUnmount(() => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
  }
})
</script>

<template>
  <div
    v-if="runs.length"
    class="fixed inset-x-4 bottom-4 z-50 mx-auto w-auto max-w-md sm:left-auto sm:right-6 sm:mx-0 sm:w-96"
  >
    <UCard
      variant="subtle"
      :ui="{
        body: 'p-3 sm:p-3'
      }"
      class="border border-default shadow-lg"
    >
      <div class="space-y-3">
        <div
          v-for="run in runs"
          :key="run.id"
          class="flex items-start gap-2 rounded-md"
        >
          <NuxtLink
            :to="localePath(`/integrations/${run.connectionId}`)"
            class="min-w-0 flex-1 rounded-md outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-primary"
          >
            <div class="flex items-center justify-between gap-3 text-sm">
              <div class="flex min-w-0 items-center gap-2">
                <UIcon
                  name="i-lucide-refresh-cw"
                  class="size-4 shrink-0 animate-spin text-primary"
                />
                <span class="truncate font-medium text-highlighted">
                  {{ run.connectionName }}
                </span>
              </div>
              <span class="shrink-0 text-muted">
                {{ label(run) }}
              </span>
            </div>
            <UProgress
              class="mt-2"
              :model-value="progress(run)"
              :max="run.totalItems || 100"
              size="sm"
            />
          </NuxtLink>
          <IntegrationImportCancelButton
            :connection-id="run.connectionId"
            :run-id="run.id"
            size="xs"
            @cancelled="load"
          />
        </div>
      </div>
    </UCard>
  </div>
</template>

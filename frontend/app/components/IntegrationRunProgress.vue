<script setup lang="ts">
const props = defineProps<{
  connectionId: string
  run: {
    id: string
    status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled'
    currentStage: string
    totalItems: number
    processedItems: number
    failedItems: number
    failureReason: string | null
    createdAt: string
    updatedAt: string
    startedAt: string | null
    completedAt: string | null
  }
}>()
const emit = defineEmits<{ cancelled: [] }>()
const { t } = useI18n()
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | undefined
const active = computed(() => ['queued', 'running'].includes(props.run.status))
const age = computed(() => Math.max(0, Math.floor((now.value - Date.parse(props.run.updatedAt)) / 1000)))
const duration = computed(() => Math.max(0, Math.floor(
  ((props.run.completedAt ? Date.parse(props.run.completedAt) : now.value) - Date.parse(props.run.createdAt)) / 1000
)))
const percentage = computed(() => Math.min(100, Math.round(props.run.processedItems / Math.max(1, props.run.totalItems) * 100)))
const progress = computed(() => props.run.totalItems > 0 ? Math.min(props.run.processedItems, props.run.totalItems) : null)
const stalled = computed(() => props.run.status === 'running' && age.value >= 120)
const color = computed(() => props.run.status === 'failed' ? 'error' : props.run.failedItems > 0 ? 'warning' : props.run.status === 'completed' ? 'success' : 'primary')
function formatDuration(seconds: number) {
  return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
}
function cancelled() {
  emit('cancelled')
}
onMounted(() => {
  timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <UPageCard variant="subtle">
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
      <div class="flex items-center gap-2">
        <UIcon
          v-if="active"
          :name="run.status === 'queued' ? 'i-lucide-clock' : 'i-lucide-refresh-cw'"
          :class="run.status === 'running' ? 'size-4 animate-spin text-primary' : 'size-4 text-primary'"
        />
        <span class="font-medium text-highlighted">
          {{ t(active ? `integrations.importStages.${run.currentStage}` : 'integrations.runProgressTitle') }}
        </span>
        <UBadge
          :label="t(`integrations.importStatus.${run.status}`)"
          :color="color"
          variant="subtle"
          size="xs"
        />
      </div>
      <div class="flex items-center gap-3">
        <span class="text-muted" aria-live="polite">
          {{ run.totalItems > 0
            ? t('integrations.runProgressCounts', { processed: run.processedItems, total: run.totalItems, percentage })
            : t('integrations.runProgressProcessed', { processed: run.processedItems }) }}
        </span>
        <IntegrationImportCancelButton
          v-if="active"
          :connection-id="connectionId"
          :run-id="run.id"
          @cancelled="cancelled"
        />
      </div>
    </div>
    <UProgress
      v-if="active || run.totalItems > 0"
      :model-value="progress"
      :max="run.totalItems || 100"
      :color="color"
      size="sm"
    />
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
      <span>{{ t('integrations.runElapsed', { time: formatDuration(duration) }) }}</span>
      <span v-if="active">{{ t('integrations.runLastActivity', { time: formatDuration(age) }) }}</span>
      <span>{{ t('integrations.runFailures', { count: run.failedItems }) }}</span>
    </div>
    <UAlert
      v-if="run.status === 'queued' || stalled"
      :icon="stalled ? 'i-lucide-circle-alert' : 'i-lucide-clock'"
      :color="stalled ? 'warning' : 'info'"
      variant="subtle"
      :title="t(stalled ? 'integrations.runNoRecentProgress' : 'integrations.importQueuedStatus')"
      :description="t(stalled ? 'integrations.runNoRecentProgressDescription' : 'integrations.runQueuedDescription')"
    />
    <UAlert
      v-if="run.failureReason"
      icon="i-lucide-circle-alert"
      color="error"
      variant="subtle"
      :title="t('integrations.importStatus.failed')"
      :description="run.failureReason"
    />
  </UPageCard>
</template>

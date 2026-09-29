<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    connectionId: string
    runId: string
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  }>(),
  {
    size: 'sm',
  },
)

const emit = defineEmits<{
  cancelled: []
}>()

const { t } = useI18n()
const toast = useAppToast()
const open = ref(false)
const cancelling = ref(false)

const cancelImport = async () => {
  cancelling.value = true

  try {
    const response = await apiFetch<{ message: string }>(
      `/integrations/${props.connectionId}/imports/${props.runId}/cancel`,
      {
        method: 'POST',
      },
    )

    open.value = false
    toast.success(t('integrations.importCancelled'), response.message)
    emit('cancelled')
  } catch (error: any) {
    toast.error(
      t('integrations.cancelImportFailed'),
      error?.data?.message || t('common.tryAgain'),
    )
  } finally {
    cancelling.value = false
  }
}
</script>

<template>
  <UButton
    :label="t('integrations.cancelImport')"
    icon="i-lucide-square"
    color="error"
    variant="outline"
    :size="size"
    @click="open = true"
  />

  <ConfirmationModal
    v-model:open="open"
    :title="t('integrations.cancelImportTitle')"
    :description="t('integrations.cancelImportDescription')"
    :confirm-label="t('integrations.cancelImport')"
    :loading="cancelling"
    @confirm="cancelImport"
  />
</template>

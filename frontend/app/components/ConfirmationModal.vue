<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    description?: string
    confirmLabel: string
    loading?: boolean
    confirmColor?: 'error' | 'primary' | 'warning'
  }>(),
  {
    description: '',
    loading: false,
    confirmColor: 'error',
  },
)

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [] }>()
</script>

<template>
  <UModal v-model:open="open" :title="title" :description="description">
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="$t('common.cancel')"
          color="neutral"
          variant="subtle"
          :disabled="loading"
          @click="open = false"
        />
        <UButton
          :label="confirmLabel"
          :color="confirmColor"
          :loading="loading"
          @click="emit('confirm')"
        />
      </div>
    </template>
  </UModal>
</template>

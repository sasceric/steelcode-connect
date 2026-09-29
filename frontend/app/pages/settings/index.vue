<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <UPageCard
    :title="t('settings.general')"
    :description="t('settings.generalDescription')"
    variant="subtle"
  >
    <div class="flex items-start gap-3 rounded-lg bg-elevated/60 p-4">
      <UIcon name="i-lucide-settings-2" class="mt-0.5 size-5 text-primary" />
      <p class="text-sm leading-6 text-muted">
        {{ t('settings.generalEmpty') }}
      </p>
    </div>
  </UPageCard>
</template>

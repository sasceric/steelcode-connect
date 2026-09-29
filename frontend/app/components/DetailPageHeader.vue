<script setup lang="ts">
const hasToolbarTarget = ref(false)

defineProps<{
  title: string
  subtitle?: string | null
  backTo: string
  backLabel?: string
}>()

onMounted(() => {
  hasToolbarTarget.value = document.querySelector('#detail-page-header') !== null
})
</script>

<template>
  <Teleport to="#detail-page-header" :disabled="!hasToolbarTarget">
    <header
      :class="
        hasToolbarTarget
          ? 'flex w-full flex-col gap-2 border-t border-default bg-elevated/95 px-4 py-2 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:justify-between'
          : 'sticky top-0 z-30 -mt-6 flex flex-col gap-2 border-b border-default bg-elevated/95 px-4 py-2 shadow-md backdrop-blur lg:-mt-12 sm:flex-row sm:items-center sm:justify-between'
      "
    >
      <div class="flex min-w-0 items-center gap-2">
        <UButton
          icon="i-lucide-arrow-left"
          color="neutral"
          variant="ghost"
          :aria-label="backLabel || 'Back'"
          @click="navigateTo(backTo)"
        />
        <div class="min-w-0">
          <h1 class="truncate text-base font-semibold text-highlighted">
            {{ title }}
          </h1>
          <p v-if="subtitle" class="truncate text-sm text-muted">
            {{ subtitle }}
          </p>
        </div>
      </div>
      <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
        <slot name="actions" />
      </div>
    </header>
  </Teleport>
</template>

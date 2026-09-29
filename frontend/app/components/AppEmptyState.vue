<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    description: string
    icon?: string
  }>(),
  {
    icon: 'i-lucide-package-search'
  }
)
</script>

<template>
  <div class="py-16 text-center">
    <UIcon :name="icon" class="mx-auto size-10 text-primary" />
    <p class="mt-4 font-medium text-highlighted">
      {{ title }}
    </p>
    <p class="mx-auto mt-1 max-w-md text-sm text-muted">
      {{ description }}
    </p>
    <div v-if="$slots.default" class="mt-5">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  message: string
}>()
</script>

<template>
  <UAlert
    color="info"
    variant="subtle"
    icon="i-lucide-info"
    :title="message"
  />
</template>

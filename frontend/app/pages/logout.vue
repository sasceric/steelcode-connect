<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuth()

onMounted(async () => {
  await auth.logout().catch(() => undefined)
  await navigateTo('/login')
})
</script>

<template>
  <div class="min-h-screen bg-muted/30" />
</template>

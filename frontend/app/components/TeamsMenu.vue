<script setup lang="ts">
defineProps<{
  collapsed?: boolean
}>()

const colorMode = useColorMode()

const logoSrc = computed(() => {
  return colorMode.value === 'dark' ? '/sc-connect-logo-dark.svg' : '/sc-connect-logo.svg'
})
</script>

<template>
  <NuxtLink
    to="/"
    aria-label="SteelCode Connect home"
    class="flex h-10 items-center rounded-md px-2 hover:bg-elevated"
  >
    <ClientOnly>
      <img
        :src="logoSrc"
        alt="SteelCode Connect"
        class="h-8 object-left"
        :class="collapsed ? 'w-10 object-cover' : 'w-44 object-contain'"
      >
      <template #fallback>
        <img
          src="/sc-connect-logo.svg"
          alt="SteelCode Connect"
          class="h-8 object-left"
          :class="collapsed ? 'w-10 object-cover' : 'w-44 object-contain'"
        >
      </template>
    </ClientOnly>
  </NuxtLink>
</template>

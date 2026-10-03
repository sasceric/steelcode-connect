<script setup lang="ts">
const localePath = useLocalePath()
const props = withDefaults(defineProps<{
  id: string
  name?: string | null
  sku?: string | null
  coverUrl?: string | null
  isVariant?: boolean
  hasVariants?: boolean
}>(), {
  name: null,
  sku: null,
  coverUrl: null,
  isVariant: false,
  hasVariants: false
})

const { t } = useI18n()
const imageFailed = ref(false)
const label = computed(() => props.name || props.sku || '—')
const imageSource = computed(() =>
  imageFailed.value ? '/placeholder-light.webp' : props.coverUrl || '/placeholder-light.webp'
)

watch(() => props.coverUrl, () => {
  imageFailed.value = false
})

function showPlaceholder() {
  imageFailed.value = true
}
</script>

<template>
  <div class="flex min-w-0 items-center gap-3">
    <img
      :src="imageSource"
      alt=""
      loading="lazy"
      class="size-8 shrink-0 rounded-md border border-default object-cover"
      @error="showPlaceholder"
    >
    <div class="flex min-w-0 items-center gap-1.5">
      <NuxtLink
        :to="localePath(`/catalogue/products/${id}`)"
        :title="label"
        class="cursor-pointer truncate text-left font-medium text-highlighted hover:text-primary"
      >
        {{ isVariant ? `↳ ${label}` : label }}
      </NuxtLink>
      <UButton
        v-if="hasVariants"
        :to="localePath(`/catalogue/products/${id}?tab=variants`)"
        icon="i-lucide-git-branch"
        color="neutral"
        variant="ghost"
        size="xs"
        :title="t('products.openVariants')"
        :aria-label="t('products.openVariants')"
      />
    </div>
  </div>
</template>

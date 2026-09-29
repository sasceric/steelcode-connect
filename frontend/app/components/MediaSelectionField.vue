<script setup lang="ts">
type MediaItem = { id: string; name: string; url: string }
const mediaId = defineModel<string>({ default: '' })
const pickerOpen = ref(false)
const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const toast = useToast()
const { data, refresh } = await useAsyncData('media-selection-field', () =>
  apiFetch<{ media: MediaItem[] }>('/media'),
)
const selected = computed(() => (data.value?.media ?? []).find((item) => item.id === mediaId.value))
const upload = async (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const body = new FormData()
    body.append('file', file)
    const response = await apiFetch<{ media: MediaItem }>('/media', {
      method: 'POST',
      body,
    })
    await refresh()
    mediaId.value = response.media.id
  } catch (error: any) {
    toast.add({
      title: error?.data?.message || 'Image could not be uploaded.',
      color: 'error',
    })
  } finally {
    uploading.value = false
    const target = event.target as HTMLInputElement
    target.value = ''
  }
}
</script>

<template>
  <div class="rounded-md border border-dashed border-muted bg-elevated/20 p-2">
    <div v-if="selected" class="flex items-center justify-between gap-3">
      <img
        :src="selected.url"
        :alt="selected.name"
        class="size-9 rounded border border-default object-cover"
      />
      <UButton
        icon="i-lucide-x"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Remove image"
        @click="mediaId = ''"
      />
    </div>
    <div v-else class="flex items-center justify-between gap-3">
      <UIcon name="i-lucide-image" class="size-8 text-muted" />
      <div class="ml-auto flex gap-2">
        <input ref="input" type="file" accept="image/*" class="hidden" @change="upload" />
        <UButton
          label="Upload file"
          color="neutral"
          variant="outline"
          size="sm"
          :loading="uploading"
          @click="input?.click()"
        />
        <UButton
          icon="i-lucide-image-plus"
          size="sm"
          aria-label="Select image"
          @click="pickerOpen = true"
        />
      </div>
    </div>
  </div>
  <MediaPickerModal v-model:open="pickerOpen" @select="(item) => (mediaId = item.id)" />
</template>

<script setup lang="ts">
type MediaItem = { id: string; name: string; url: string }

const open = defineModel<boolean>('open', { default: false })
const props = withDefaults(defineProps<{ multiple?: boolean }>(), {
  multiple: false,
})
const emit = defineEmits<{
  select: [media: MediaItem]
  selectMany: [media: MediaItem[]]
}>()
const search = ref('')
const selectedIds = ref<string[]>([])
const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const toast = useToast()
const { data, refresh } = await useAsyncData('media-library-picker', () =>
  apiFetch<{ media: MediaItem[] }>('/media'),
)
const media = computed(() => data.value?.media ?? [])
const filteredMedia = computed(() =>
  media.value.filter((item) => item.name.toLowerCase().includes(search.value.toLowerCase())),
)

const choose = (item: MediaItem) => {
  selectedIds.value = props.multiple
    ? selectedIds.value.includes(item.id)
      ? selectedIds.value.filter((id) => id !== item.id)
      : [...selectedIds.value, item.id]
    : [item.id]
}
const selectedMedia = computed(() =>
  media.value.filter((item) => selectedIds.value.includes(item.id)),
)
const applySelection = () => {
  if (props.multiple) emit('selectMany', selectedMedia.value)
  else if (selectedMedia.value[0]) emit('select', selectedMedia.value[0])
  selectedIds.value = []
  open.value = false
}
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
    choose(response.media)
  } catch (error: any) {
    toast.add({
      title: error?.data?.message || 'Image could not be uploaded.',
      color: 'error',
    })
  } finally {
    uploading.value = false
    ;(event.target as HTMLInputElement).value = ''
  }
}
</script>

<template>
  <UModal v-model:open="open" title="Choose media" :ui="{ content: 'max-w-6xl' }">
    <template #body>
      <div class="grid min-h-130 grid-cols-[minmax(0,1fr)_300px]">
        <div class="space-y-5 border-r border-default p-5">
          <div class="flex gap-3">
            <UInput
              v-model="search"
              icon="i-lucide-search"
              placeholder="Search images…"
              class="flex-1"
            /><input
              ref="input"
              type="file"
              accept="image/*"
              class="hidden"
              @change="upload"
            /><UButton label="Upload image" :loading="uploading" @click="input?.click()" />
          </div>
          <div
            v-if="!filteredMedia.length"
            class="rounded-xl border border-dashed border-default px-6 py-20 text-center"
          >
            <UIcon name="i-lucide-images" class="mx-auto size-10 text-muted" />
            <p class="mt-3 font-medium text-highlighted">No images found</p>
          </div>
          <div
            v-else
            class="grid max-h-110 grid-cols-3 gap-3 overflow-y-auto pr-1 sm:grid-cols-4 lg:grid-cols-5"
          >
            <button
              v-for="item in filteredMedia"
              :key="item.id"
              type="button"
              class="overflow-hidden rounded-lg border bg-default text-left"
              :class="
                selectedIds.includes(item.id)
                  ? 'border-primary ring-2 ring-primary/25'
                  : 'border-default'
              "
              @click="choose(item)"
            >
              <img
                :src="item.url"
                :alt="item.name"
                class="aspect-square w-full object-cover"
              /><span class="block truncate p-2 text-xs">{{ item.name }}</span>
            </button>
          </div>
        </div>
        <aside class="p-5">
          <p class="text-sm font-medium text-highlighted">Preview</p>
          <div v-if="selectedMedia[0]" class="mt-4">
            <img
              :src="selectedMedia[0].url"
              :alt="selectedMedia[0].name"
              class="aspect-square w-full rounded-lg border border-default object-cover"
            />
            <p class="mt-3 truncate text-sm text-muted">
              {{ selectedMedia[0].name }}
            </p>
          </div>
          <p v-else class="mt-4 text-sm text-muted">Select an image to preview it.</p>
        </aside>
      </div>
    </template>
    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton label="Cancel" color="neutral" variant="subtle" @click="open = false" /><UButton
          :label="multiple ? `Add ${selectedIds.length} media` : 'Add media'"
          :disabled="!selectedIds.length"
          @click="applySelection"
        />
      </div>
    </template>
  </UModal>
</template>

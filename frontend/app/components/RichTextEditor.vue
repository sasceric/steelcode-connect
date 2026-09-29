<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { Node } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'

const model = defineModel<string>({ default: '' })
const sourceMode = ref(false)

const Div = Node.create({
  name: 'div',
  group: 'block',
  content: 'block*',
  parseHTML: () => [{ tag: 'div' }],
  renderHTML: ({ HTMLAttributes }) => ['div', HTMLAttributes, 0],
})

const editor = useEditor({
  content: model.value || '',
  extensions: [
    StarterKit.configure({ heading: { levels: [1, 2, 3, 4, 5, 6] } }),
    Link.configure({
      openOnClick: false,
      autolink: true,
      defaultProtocol: 'https',
    }),
    Underline,
    Div,
  ],
  editorProps: {
    attributes: {
      class: 'rich-text-content min-h-56 p-4 text-sm leading-6 outline-none',
    },
  },
  onUpdate: ({ editor }) => {
    model.value = editor.getHTML()
  },
})

watch(model, (value) => {
  if (editor.value && value !== editor.value.getHTML())
    editor.value.commands.setContent(value || '', { emitUpdate: false })
})

const setLink = () => {
  const current = editor.value?.getAttributes('link').href || ''
  const url = window.prompt('URL', current)
  if (url === null) return
  if (!url) editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
  else editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const action = (callback: () => void) => callback()

const toggleSourceMode = () => {
  if (sourceMode.value && editor.value)
    editor.value.commands.setContent(model.value || '', { emitUpdate: false })
  sourceMode.value = !sourceMode.value
}
</script>

<template>
  <div class="overflow-hidden rounded-md border border-default bg-default">
    <div class="flex flex-wrap gap-1 border-b border-default bg-elevated/40 p-2">
      <UButton
        icon="i-lucide-bold"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Bold"
        :class="{ 'bg-elevated': editor?.isActive('bold') }"
        @click="action(() => editor?.chain().focus().toggleBold().run())"
      />
      <UButton
        icon="i-lucide-italic"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Italic"
        :class="{ 'bg-elevated': editor?.isActive('italic') }"
        @click="action(() => editor?.chain().focus().toggleItalic().run())"
      />
      <UButton
        icon="i-lucide-underline"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Underline"
        @click="action(() => editor?.chain().focus().toggleUnderline().run())"
      />
      <USeparator orientation="vertical" class="mx-1 h-6" />
      <UButton
        v-for="level in [1, 2, 3, 4, 5, 6]"
        :key="level"
        :label="`H${level}`"
        color="neutral"
        variant="ghost"
        size="xs"
        :class="{ 'bg-elevated': editor?.isActive('heading', { level }) }"
        @click="
          action(() =>
            editor
              ?.chain()
              .focus()
              .toggleHeading({ level: level as 1 | 2 | 3 | 4 | 5 | 6 })
              .run(),
          )
        "
      />
      <UButton
        label="P"
        color="neutral"
        variant="ghost"
        size="xs"
        :class="{ 'bg-elevated': editor?.isActive('paragraph') }"
        @click="action(() => editor?.chain().focus().setParagraph().run())"
      />
      <USeparator orientation="vertical" class="mx-1 h-6" />
      <UButton
        icon="i-lucide-list"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Bullet list"
        :class="{ 'bg-elevated': editor?.isActive('bulletList') }"
        @click="action(() => editor?.chain().focus().toggleBulletList().run())"
      />
      <UButton
        icon="i-lucide-list-ordered"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Ordered list"
        :class="{ 'bg-elevated': editor?.isActive('orderedList') }"
        @click="action(() => editor?.chain().focus().toggleOrderedList().run())"
      />
      <UButton
        icon="i-lucide-link"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Link"
        :class="{ 'bg-elevated': editor?.isActive('link') }"
        @click="setLink"
      />
      <UButton
        icon="i-lucide-remove-formatting"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Clear formatting"
        @click="action(() => editor?.chain().focus().unsetAllMarks().clearNodes().run())"
      />
      <UButton
        icon="i-lucide-code-2"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Edit HTML"
        :class="{ 'bg-elevated': sourceMode }"
        @click="toggleSourceMode"
      />
    </div>
    <UTextarea
      v-if="sourceMode"
      v-model="model"
      class="w-full [&_textarea]:min-h-56 [&_textarea]:rounded-none [&_textarea]:border-0 [&_textarea]:font-mono [&_textarea]:text-xs"
    />
    <ClientOnly v-else>
      <EditorContent :editor="editor" /><template #fallback>
        <div class="min-h-56 p-4" />
      </template>
    </ClientOnly>
  </div>
</template>

<style scoped>
:deep(.rich-text-content h1) {
  margin: 1.25rem 0 0.75rem;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
}
:deep(.rich-text-content h2) {
  margin: 1.125rem 0 0.625rem;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.3;
}
:deep(.rich-text-content h3) {
  margin: 1rem 0 0.5rem;
  font-size: 1.25rem;
  font-weight: 600;
  line-height: 1.35;
}
:deep(.rich-text-content h4) {
  margin: 0.875rem 0 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
}
:deep(.rich-text-content h5) {
  margin: 0.75rem 0 0.375rem;
  font-size: 1rem;
  font-weight: 600;
}
:deep(.rich-text-content h6) {
  margin: 0.75rem 0 0.375rem;
  font-size: 0.875rem;
  font-weight: 600;
}
:deep(.rich-text-content p) {
  margin: 0.5rem 0;
}
:deep(.rich-text-content ul) {
  margin: 0.5rem 0;
  list-style: disc;
  padding-left: 1.5rem;
}
:deep(.rich-text-content ol) {
  margin: 0.5rem 0;
  list-style: decimal;
  padding-left: 1.5rem;
}
:deep(.rich-text-content a) {
  color: var(--ui-primary);
  text-decoration: underline;
}
</style>

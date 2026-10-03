<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: false })

const localePath = useLocalePath()
const toast = useToast()
const { t } = useI18n()
const loading = ref(false)
const sent = ref(false)
const schema = z.object({ email: z.email('Enter a valid email address') })
type Schema = z.output<typeof schema>
const state = reactive<Schema>({ email: '' })

const submit = async (event: FormSubmitEvent<Schema>) => {
  loading.value = true
  try {
    await apiFetch('/auth/password-reset/request', {
      method: 'POST',
      body: event.data,
    })
    sent.value = true
  } catch (error) {
    toast.add({
      title: t('auth.unableToRequestReset'),
      description: error instanceof Error ? error.message : t('auth.requestNewResetLink'),
      color: 'error',
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-muted/30 p-4">
    <UCard class="w-full max-w-md">
      <template #header>
        <div>
          <h1 class="text-xl font-semibold">
            {{ t('auth.resetPassword') }}
          </h1>
          <p class="mt-1 text-sm text-muted">
            {{ t('auth.resetDescription') }}
          </p>
        </div>
      </template>
      <p v-if="sent" class="text-sm text-muted">
        {{ t('auth.resetSent') }}
      </p>
      <UForm v-else :schema="schema" :state="state" class="space-y-4" @submit="submit">
        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="state.email" type="email" autocomplete="email" class="w-full" />
        </UFormField>
        <UButton :label="t('auth.sendResetInstructions')" type="submit" block :loading="loading" />
      </UForm>
      <template #footer>
        <NuxtLink :to="localePath('/login')" class="text-sm font-medium text-primary">
          {{ t('auth.backToSignIn') }}
        </NuxtLink>
      </template>
    </UCard>
  </div>
</template>

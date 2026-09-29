<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: false })

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const loading = ref(false)
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const schema = z.object({
  password: z.string().min(8, 'Use at least 8 characters'),
})
type Schema = z.output<typeof schema>
const state = reactive<Schema>({ password: '' })

const submit = async (event: FormSubmitEvent<Schema>) => {
  loading.value = true
  try {
    await apiFetch('/auth/password-reset/reset', {
      method: 'POST',
      body: { token: token.value, password: event.data.password },
    })
    toast.add({ title: t('auth.passwordReset'), color: 'success' })
    await navigateTo('/login')
  } catch (error) {
    toast.add({
      title: t('auth.unableToResetPassword'),
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
            {{ t('auth.chooseNewPassword') }}
          </h1>
          <p class="mt-1 text-sm text-muted">
            {{ t('auth.newPasswordDescription') }}
          </p>
        </div>
      </template>
      <UForm :schema="schema" :state="state" class="space-y-4" @submit="submit">
        <UFormField :label="t('auth.newPassword')" name="password">
          <UInput
            v-model="state.password"
            type="password"
            autocomplete="new-password"
            class="w-full"
          />
        </UFormField>
        <UButton
          :label="t('auth.resetPassword')"
          type="submit"
          block
          :disabled="!token"
          :loading="loading"
        />
      </UForm>
    </UCard>
  </div>
</template>

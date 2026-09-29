<script setup lang="ts">
import * as z from 'zod'
import type { FormError, FormSubmitEvent } from '@nuxt/ui'

const toast = useToast()
const saving = ref(false)

const passwordSchema = z.object({
  currentPassword: z.string().min(8, 'Enter your current password.'),
  newPassword: z.string().min(8, 'Use at least 8 characters.'),
})

type PasswordSchema = z.output<typeof passwordSchema>

const password = reactive<PasswordSchema>({
  currentPassword: '',
  newPassword: '',
})

const validate = (state: Partial<PasswordSchema>): FormError[] => {
  if (state.currentPassword && state.newPassword && state.currentPassword === state.newPassword) {
    return [
      {
        name: 'newPassword',
        message: 'New password must be different from your current password.',
      },
    ]
  }

  return []
}

const submit = async (event: FormSubmitEvent<PasswordSchema>) => {
  saving.value = true

  try {
    const response = await apiFetch<{ message: string }>('/auth/password', {
      method: 'PATCH',
      body: event.data,
    })

    password.currentPassword = ''
    password.newPassword = ''
    toast.add({
      title: response.message,
      color: 'success',
    })
  } catch (error) {
    toast.add({
      title: 'Unable to update password',
      description: error instanceof Error ? error.message : 'Please try again.',
      color: 'error',
    })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UPageCard
    title="Password"
    description="Confirm your current password before setting a new one."
    variant="subtle"
  >
    <UForm
      :schema="passwordSchema"
      :state="password"
      :validate="validate"
      class="flex max-w-xs flex-col gap-4"
      @submit="submit"
    >
      <UFormField name="currentPassword" label="Current password" required>
        <UInput
          v-model="password.currentPassword"
          type="password"
          autocomplete="current-password"
          class="w-full"
        />
      </UFormField>

      <UFormField name="newPassword" label="New password" required>
        <UInput
          v-model="password.newPassword"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UButton label="Update password" class="w-fit" type="submit" :loading="saving" />
    </UForm>
  </UPageCard>

  <UPageCard
    title="Account"
    description="No longer want to use our service? Account deletion is not available yet."
    class="bg-linear-to-tl from-error/10 from-5% to-default"
  >
    <template #footer>
      <UButton label="Delete account" color="error" disabled />
    </template>
  </UPageCard>
</template>

<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: false })

const auth = useAuth()
const toast = useToast()
const { t } = useI18n()
const loading = ref(false)

const schema = z.object({
  tenantName: z.string().min(2, 'Company name is required'),
  email: z.email('Enter a valid email address'),
  password: z.string().min(8, 'Use at least 8 characters'),
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({
  tenantName: '',
  email: '',
  password: '',
})

const submit = async (event: FormSubmitEvent<Schema>) => {
  loading.value = true

  try {
    await auth.register(event.data.tenantName, event.data.email, event.data.password)
    await navigateTo('/')
  } catch (error) {
    toast.add({
      title: t('auth.unableToCreateAccount'),
      description: error instanceof Error ? error.message : 'Please try again.',
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
            {{ t('auth.createWorkspace') }}
          </h1>
          <p class="mt-1 text-sm text-muted">
            {{ t('auth.createWorkspaceDescription') }}
          </p>
        </div>
      </template>

      <UForm :schema="schema" :state="state" class="space-y-4" @submit="submit">
        <UFormField :label="t('auth.companyName')" name="tenantName">
          <UInput v-model="state.tenantName" autocomplete="organization" class="w-full" />
        </UFormField>

        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="state.email" type="email" autocomplete="email" class="w-full" />
        </UFormField>

        <UFormField :label="t('auth.password')" name="password">
          <UInput
            v-model="state.password"
            type="password"
            autocomplete="new-password"
            class="w-full"
          />
        </UFormField>

        <UButton :label="t('auth.createAccount')" type="submit" block :loading="loading" />
      </UForm>

      <template #footer>
        <p class="text-sm text-muted">
          {{ t('auth.alreadyHaveAccount') }}
          <NuxtLink to="/login" class="font-medium text-primary">
            {{ t('auth.signIn') }}
          </NuxtLink>
        </p>
      </template>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: false })

const localePath = useLocalePath()
const auth = useAuth()
const toast = useToast()
const { t } = useI18n()
const loading = ref(false)

const schema = z.object({
  email: z.email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({
  email: '',
  password: '',
})

const submit = async (event: FormSubmitEvent<Schema>) => {
  loading.value = true

  try {
    await auth.login(event.data.email, event.data.password)
    await navigateTo(localePath('/'))
  } catch (error) {
    toast.add({
      title: t('auth.unableToSignIn'),
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
            {{ t('auth.signIn') }}
          </h1>
          <p class="mt-1 text-sm text-muted">
            {{ t('auth.signInDescription') }}
          </p>
        </div>
      </template>

      <UForm :schema="schema" :state="state" class="space-y-4" @submit="submit">
        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="state.email" type="email" autocomplete="email" class="w-full" />
        </UFormField>

        <UFormField :label="t('auth.password')" name="password">
          <UInput
            v-model="state.password"
            type="password"
            autocomplete="current-password"
            class="w-full"
          />
        </UFormField>

        <div class="flex justify-end">
          <NuxtLink :to="localePath('/forgot-password')" class="text-sm font-medium text-primary">
            {{ t('auth.forgotPassword') }}
          </NuxtLink>
        </div>

        <UButton :label="t('auth.signIn')" type="submit" block :loading="loading" />
      </UForm>

      <template #footer>
        <p class="text-sm text-muted">
          {{ t('auth.newHere') }}
          <NuxtLink :to="localePath('/signup')" class="font-medium text-primary">
            {{ t('auth.createAccount') }}
          </NuxtLink>
        </p>
      </template>
    </UCard>
  </div>
</template>

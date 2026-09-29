<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

const auth = useAuth()
const toast = useToast()
const { t } = useI18n()
const saving = ref(false)

const schema = z.object({
  name: z.string().min(1, 'Company name is required'),
  oib: z.string(),
  pdv: z.string(),
  phone: z.string(),
  email: z.union([z.email('Enter a valid email address'), z.literal('')]),
  website: z.union([z.url('Enter a full URL including https://'), z.literal('')]),
})

type Schema = z.output<typeof schema>

const state = reactive<Schema>({
  name: '',
  oib: '',
  pdv: '',
  phone: '',
  email: '',
  website: '',
})

const sync = () => {
  Object.assign(state, {
    name: auth.tenant.value?.name || '',
    oib: auth.tenant.value?.oib || '',
    pdv: auth.tenant.value?.pdv || '',
    phone: auth.tenant.value?.phone || '',
    email: auth.tenant.value?.email || '',
    website: auth.tenant.value?.website || '',
  })
}

watch(auth.tenant, sync, { immediate: true })

const submit = async (event: FormSubmitEvent<Schema>) => {
  saving.value = true

  try {
    const { tenant } = await apiFetch<{ tenant: AuthTenant }>('/tenant', {
      method: 'PATCH',
      body: event.data,
    })

    auth.tenant.value = tenant
    toast.add({ title: 'Company updated', color: 'success' })
  } catch (error) {
    toast.add({
      title: 'Unable to save company',
      description: error instanceof Error ? error.message : 'Please try again.',
      color: 'error',
    })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UForm :schema="schema" :state="state" class="mx-auto w-full max-w-2xl" @submit="submit">
    <UPageCard
      :title="t('company.details')"
      :description="t('company.detailsDescription')"
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        :label="t('common.saveChanges')"
        type="submit"
        color="neutral"
        :loading="saving"
        class="w-fit lg:ms-auto"
      />
    </UPageCard>

    <div>
      <UPageCard variant="subtle">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField :label="t('company.companyName')" name="name" required>
            <UInput v-model="state.name" class="w-full" />
          </UFormField>

          <UFormField :label="t('common.email')" name="email">
            <UInput v-model="state.email" type="email" class="w-full" />
          </UFormField>

          <UFormField :label="t('company.oib')" name="oib">
            <UInput v-model="state.oib" class="w-full" />
          </UFormField>

          <UFormField :label="t('company.pdv')" name="pdv">
            <UInput v-model="state.pdv" class="w-full" />
          </UFormField>

          <UFormField :label="t('common.phone')" name="phone">
            <UInput v-model="state.phone" class="w-full" />
          </UFormField>

          <UFormField :label="t('common.website')" name="website">
            <UInput v-model="state.website" placeholder="https://example.com" class="w-full" />
          </UFormField>
        </div>
      </UPageCard>
    </div>
  </UForm>
</template>

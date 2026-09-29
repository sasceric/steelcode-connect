<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

const company = useCompany()
const { addresses } = company
const toast = useToast()
const { t } = useI18n()
const open = ref(false)
const saving = ref(false)
const editingId = ref<string>()

const schema = z.object({
  company: z.string(),
  department: z.string(),
  street: z.string().min(1, 'Street is required'),
  additionalAddressLine1: z.string(),
  additionalAddressLine2: z.string(),
  zipcode: z.string().min(1, 'ZIP code is required'),
  city: z.string().min(1, 'City is required'),
  country: z.string().length(2, 'Use a 2-letter country code'),
  countryState: z.string(),
  phone: z.string(),
})

type Schema = z.output<typeof schema>

const emptyState = (): Schema => ({
  company: '',
  department: '',
  street: '',
  additionalAddressLine1: '',
  additionalAddressLine2: '',
  zipcode: '',
  city: '',
  country: 'BA',
  countryState: '',
  phone: '',
})

const state = reactive<Schema>(emptyState())

const edit = (address?: Address) => {
  editingId.value = address?.id

  Object.assign(
    state,
    address
      ? {
          company: address.company || '',
          department: address.department || '',
          street: address.street,
          additionalAddressLine1: address.additionalAddressLine1 || '',
          additionalAddressLine2: address.additionalAddressLine2 || '',
          zipcode: address.zipcode,
          city: address.city,
          country: address.country,
          countryState: address.countryState || '',
          phone: address.phone || '',
        }
      : emptyState(),
  )

  open.value = true
}

const submit = async (event: FormSubmitEvent<Schema>) => {
  saving.value = true

  try {
    await company.saveAddress(
      {
        ...event.data,
        country: event.data.country.toUpperCase(),
        company: event.data.company || null,
        department: event.data.department || null,
        countryState: event.data.countryState || null,
        phone: event.data.phone || null,
        additionalAddressLine1: event.data.additionalAddressLine1 || null,
        additionalAddressLine2: event.data.additionalAddressLine2 || null,
      },
      editingId.value,
    )

    open.value = false
    toast.add({ title: 'Address saved', color: 'success' })
  } catch (error) {
    toast.add({
      title: error instanceof Error ? error.message : 'Unable to save address',
      color: 'error',
    })
  } finally {
    saving.value = false
  }
}

const addressActions = (address: Address) => [
  [
    {
      label: 'Edit',
      icon: 'i-lucide-pencil',
      onSelect: () => edit(address),
    },
    {
      label: 'Set default',
      icon: 'i-lucide-star',
      onSelect: () => company.setDefaultAddress(address.id),
    },
    {
      label: 'Delete',
      icon: 'i-lucide-trash',
      color: 'error' as const,
      onSelect: () => company.removeAddress(address.id),
    },
  ],
]

onMounted(company.loadAddresses)
</script>

<template>
  <div>
    <UPageCard
      :title="t('company.addresses')"
      :description="t('company.addressesDescription')"
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        :label="t('company.addAddress')"
        color="neutral"
        class="w-fit lg:ms-auto"
        @click="edit()"
      />
    </UPageCard>

    <UPageCard
      variant="subtle"
      :ui="{
        container: 'p-0 sm:p-0 gap-y-0',
        wrapper: 'items-stretch',
        header: 'p-4 mb-0 border-b border-default',
      }"
    >
      <div class="divide-y divide-default">
        <div v-for="address in addresses" :key="address.id" class="flex justify-between gap-4 p-4">
          <div>
            <div class="font-medium">
              {{ address.company || 'Company address' }}
              <UBadge
                v-if="address.isDefault"
                label="Default"
                color="success"
                variant="subtle"
                class="ml-2"
              />
            </div>
            <p class="text-sm text-muted">
              {{ address.street }}, {{ address.zipcode }} {{ address.city }},
              {{ address.country }}
            </p>
          </div>

          <UDropdownMenu :items="addressActions(address)">
            <UButton icon="i-lucide-ellipsis-vertical" color="neutral" variant="ghost" />
          </UDropdownMenu>
        </div>

        <p v-if="!addresses.length" class="p-4 text-sm text-muted">No addresses yet.</p>
      </div>
    </UPageCard>
  </div>

  <UModal v-model:open="open" :title="editingId ? 'Edit address' : 'Add address'">
    <template #body>
      <UForm :schema="schema" :state="state" class="grid gap-4 sm:grid-cols-2" @submit="submit">
        <UFormField label="Company" name="company">
          <UInput v-model="state.company" class="w-full" />
        </UFormField>

        <UFormField label="Department" name="department">
          <UInput v-model="state.department" class="w-full" />
        </UFormField>

        <UFormField label="Street" name="street" required>
          <UInput v-model="state.street" class="w-full" />
        </UFormField>

        <UFormField label="Additional address line 1" name="additionalAddressLine1">
          <UInput v-model="state.additionalAddressLine1" class="w-full" />
        </UFormField>

        <UFormField label="Additional address line 2" name="additionalAddressLine2">
          <UInput v-model="state.additionalAddressLine2" class="w-full" />
        </UFormField>

        <UFormField label="ZIP code" name="zipcode" required>
          <UInput v-model="state.zipcode" class="w-full" />
        </UFormField>

        <UFormField label="City" name="city" required>
          <UInput v-model="state.city" class="w-full" />
        </UFormField>

        <UFormField label="Country code" name="country" required>
          <UInput v-model="state.country" class="w-full" />
        </UFormField>

        <UFormField label="State / region" name="countryState">
          <UInput v-model="state.countryState" class="w-full" />
        </UFormField>

        <UFormField label="Phone" name="phone">
          <UInput v-model="state.phone" class="w-full" />
        </UFormField>

        <div class="flex justify-end gap-2 sm:col-span-2">
          <UButton label="Cancel" color="neutral" variant="subtle" @click="open = false" />
          <UButton label="Save" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
</template>

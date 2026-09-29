<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

const company = useCompany()
const { paymentMethods } = company
const toast = useToast()
const { t } = useI18n()
const open = ref(false)
const saving = ref(false)
const editing = ref<PaymentMethod>()

const schema = z.object({
  label: z.string().min(1, 'Card name is required'),
  provider: z.enum(['visa', 'mastercard']),
  cardNumber: z
    .string()
    .refine(
      (value) => value.startsWith('****') || value.replace(/\D/g, '').length >= 12,
      'Enter a valid card number',
    ),
  expirationDate: z.string().regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Use the MM/YY format'),
  isDefault: z.boolean(),
})

type Schema = z.output<typeof schema>

const state = reactive<Schema>({
  label: '',
  provider: 'visa',
  cardNumber: '',
  expirationDate: '',
  isDefault: false,
})

const formatCardNumber = (value: string) =>
  value
    .replace(/\D/g, '')
    .slice(0, 19)
    .replace(/(.{4})/g, '$1 ')
    .trim()

const formatExpirationDate = (value: string) =>
  value
    .replace(/\D/g, '')
    .slice(0, 4)
    .replace(/^(\d{2})(\d{1,2})$/, '$1/$2')

const edit = (method?: PaymentMethod) => {
  editing.value = method

  Object.assign(state, {
    label: method?.label || '',
    provider: method?.provider === 'mastercard' ? 'mastercard' : 'visa',
    cardNumber:
      typeof method?.details.cardNumberMasked === 'string' ? method.details.cardNumberMasked : '',
    expirationDate:
      typeof method?.details.expirationDate === 'string' ? method.details.expirationDate : '',
    isDefault: method?.isDefault || false,
  })

  open.value = true
}

const paymentMethodActions = (method: PaymentMethod) => [
  [
    {
      label: 'Edit',
      icon: 'i-lucide-pencil',
      onSelect: () => edit(method),
    },
    {
      label: 'Set default',
      icon: 'i-lucide-star',
      onSelect: () => company.setDefaultPaymentMethod(method.id),
    },
    {
      label: 'Delete',
      icon: 'i-lucide-trash',
      color: 'error' as const,
      onSelect: () => company.removePaymentMethod(method.id),
    },
  ],
]

const submit = async (event: FormSubmitEvent<Schema>) => {
  saving.value = true

  try {
    const digits = event.data.cardNumber.replace(/\D/g, '')
    const cardNumberMasked = event.data.cardNumber.startsWith('****')
      ? event.data.cardNumber
      : `**** **** **** ${digits.slice(-4)}`
    const method = await company.savePaymentMethod(
      {
        type: 'card',
        provider: event.data.provider,
        label: event.data.label,
        details: {
          cardNumberMasked,
          expirationDate: event.data.expirationDate,
        },
        active: true,
      },
      editing.value?.id,
    )

    if (event.data.isDefault) {
      await company.setDefaultPaymentMethod(method.id)
    }

    open.value = false
    toast.add({ title: 'Payment method saved', color: 'success' })
  } catch (error) {
    toast.add({
      title: error instanceof Error ? error.message : 'Unable to save payment method',
      color: 'error',
    })
  } finally {
    saving.value = false
  }
}

onMounted(company.loadPaymentMethods)
</script>

<template>
  <div>
    <UPageCard
      :title="t('company.paymentMethods')"
      :description="t('company.paymentMethodsDescription')"
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        :label="t('company.addCard')"
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
      }"
    >
      <div class="divide-y divide-default">
        <div
          v-for="method in paymentMethods"
          :key="method.id"
          class="flex justify-between gap-4 p-4"
        >
          <div>
            <p class="font-medium capitalize">
              {{ method.provider }} {{ method.details.cardNumberMasked }}
              <UBadge
                v-if="method.isDefault"
                label="Default"
                color="success"
                variant="subtle"
                class="ml-2"
              />
            </p>
            <p class="text-sm text-muted">
              {{ method.label }}
            </p>
          </div>

          <UDropdownMenu :items="paymentMethodActions(method)">
            <UButton icon="i-lucide-ellipsis-vertical" color="neutral" variant="ghost" />
          </UDropdownMenu>
        </div>

        <p v-if="!paymentMethods.length" class="p-4 text-sm text-muted">No payment methods yet.</p>
      </div>
    </UPageCard>
  </div>

  <UModal v-model:open="open" :title="editing ? 'Edit payment method' : 'Add card'">
    <template #body>
      <UForm :schema="schema" :state="state" class="space-y-4" @submit="submit">
        <UFormField label="Card name" name="label" required>
          <UInput v-model="state.label" class="w-full" />
        </UFormField>

        <UFormField label="Provider" name="provider" required>
          <USelect v-model="state.provider" :items="['visa', 'mastercard']" class="w-full" />
        </UFormField>

        <UFormField label="Card number" name="cardNumber" required>
          <UInput
            v-model="state.cardNumber"
            placeholder="1234 5678 9012 3456"
            inputmode="numeric"
            autocomplete="cc-number"
            class="w-full"
            @update:model-value="state.cardNumber = formatCardNumber($event)"
          />
        </UFormField>

        <UFormField label="Expiration date" name="expirationDate" required>
          <UInput
            v-model="state.expirationDate"
            placeholder="MM/YY"
            inputmode="numeric"
            autocomplete="cc-exp"
            class="w-full"
            @update:model-value="state.expirationDate = formatExpirationDate($event)"
          />
        </UFormField>

        <UCheckbox v-model="state.isDefault" label="Default payment method" />

        <div class="flex justify-end gap-2">
          <UButton label="Cancel" color="neutral" variant="subtle" @click="open = false" />
          <UButton label="Save" type="submit" :loading="saving" />
        </div>
      </UForm>
    </template>
  </UModal>
</template>

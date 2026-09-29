export type Address = {
  id: string
  isDefault: boolean
  country: string
  company: string | null
  department: string | null
  street: string
  zipcode: string
  city: string
  countryState: string | null
  phone: string | null
  additionalAddressLine1: string | null
  additionalAddressLine2: string | null
}

export type AddressInput = Omit<Address, 'id' | 'isDefault'>

export type PaymentMethod = {
  id: string
  type: string
  provider: string
  label: string
  details: Record<string, unknown>
  active: boolean
  isDefault: boolean
}

export type PaymentMethodInput = Omit<PaymentMethod, 'id' | 'isDefault'>

export type SubscriptionPlan = {
  code: string
  name: string
  price: number
  productLimit: number | null
}

export type Subscription = SubscriptionPlan & {
  interval: 'monthly' | 'annual'
  currency: string
}

export type Invoice = {
  id: string
  number: string
  plan: string
  amount: number
  currency: string
  billingPeriod: string
  status: string
}

export const useCompany = () => {
  const addresses = useState<Address[]>('company:addresses', () => [])
  const paymentMethods = useState<PaymentMethod[]>('company:payment-methods', () => [])
  const plans = useState<SubscriptionPlan[]>('company:plans', () => [])
  const subscription = useState<Subscription | null>('company:subscription', () => null)
  const invoices = useState<Invoice[]>('company:invoices', () => [])

  const loadAddresses = async () => {
    const response = await apiFetch<{ addresses: Address[] }>('/tenant/addresses')
    addresses.value = response.addresses
  }

  const saveAddress = async (data: AddressInput, id?: string) => {
    const response = id
      ? await apiFetch<{ address: Address }>(`/tenant/addresses/${id}`, {
          method: 'PATCH',
          body: data,
        })
      : await apiFetch<{ address: Address }>('/tenant/addresses', {
          method: 'POST',
          body: data,
        })

    await loadAddresses()

    return response.address
  }

  const removeAddress = async (id: string) => {
    await apiFetch(`/tenant/addresses/${id}`, { method: 'DELETE' })
    await loadAddresses()
  }

  const setDefaultAddress = async (id: string) => {
    await apiFetch(`/tenant/addresses/${id}/default`, { method: 'POST' })
    await loadAddresses()
  }

  const loadPaymentMethods = async () => {
    const response = await apiFetch<{ paymentMethods: PaymentMethod[] }>('/tenant/payment-methods')
    paymentMethods.value = response.paymentMethods
  }

  const savePaymentMethod = async (data: PaymentMethodInput, id?: string) => {
    const response = id
      ? await apiFetch<{ paymentMethod: PaymentMethod }>(`/tenant/payment-methods/${id}`, {
          method: 'PATCH',
          body: data,
        })
      : await apiFetch<{ paymentMethod: PaymentMethod }>('/tenant/payment-methods', {
          method: 'POST',
          body: data,
        })

    await loadPaymentMethods()

    return response.paymentMethod
  }

  const removePaymentMethod = async (id: string) => {
    await apiFetch(`/tenant/payment-methods/${id}`, { method: 'DELETE' })
    await loadPaymentMethods()
  }

  const setDefaultPaymentMethod = async (id: string) => {
    await apiFetch(`/tenant/payment-methods/${id}/default`, { method: 'POST' })
    await loadPaymentMethods()
  }

  const loadSubscription = async () => {
    const response = await apiFetch<{
      plans: SubscriptionPlan[]
      subscription: Subscription | null
    }>('/tenant/subscription')

    plans.value = response.plans
    subscription.value = response.subscription
  }

  const selectPlan = async (plan: string, interval: Subscription['interval']) => {
    const response = await apiFetch<{ subscription: Subscription }>('/tenant/subscription', {
      method: 'PUT',
      body: { plan, interval },
    })

    subscription.value = response.subscription
  }

  const loadInvoices = async () => {
    const response = await apiFetch<{ invoices: Invoice[] }>('/tenant/invoices')
    invoices.value = response.invoices
  }

  const invoicePdfUrl = (id: string) => `/api/v1/tenant/invoices/${id}/pdf`

  return {
    addresses,
    paymentMethods,
    plans,
    subscription,
    invoices,
    loadAddresses,
    saveAddress,
    removeAddress,
    setDefaultAddress,
    loadPaymentMethods,
    savePaymentMethod,
    removePaymentMethod,
    setDefaultPaymentMethod,
    loadSubscription,
    selectPlan,
    loadInvoices,
    invoicePdfUrl,
  }
}

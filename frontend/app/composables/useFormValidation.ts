type ApiFieldErrors = Record<string, string | string[]>
type RequiredField = {
  field: string
  value: unknown
  label: string
  message: string
}

type ApiError = {
  data?: {
    errors?: ApiFieldErrors
    message?: string
  }
}

type ErrorNotifier = {
  error: (title: string, description: string) => void
}

type FormErrorEvent = {
  errors: Array<{ name?: string }>
}

export const useFormValidation = () => {
  const errors = ref<Record<string, string>>({})

  const clear = (field?: string) => {
    if (!field) {
      errors.value = {}
      return
    }

    const remaining: Record<string, string> = {}
    for (const [key, value] of Object.entries(errors.value)) {
      if (key !== field) remaining[key] = value
    }
    errors.value = remaining
  }

  const set = (fieldErrors: Record<string, string>) => {
    errors.value = fieldErrors
  }

  const validateRequired = (fields: RequiredField[]): string[] => {
    const next: Record<string, string> = {}
    const missingLabels: string[] = []

    for (const { field, value, label, message } of fields) {
      const hasValue = Array.isArray(value)
        ? value.length > 0
        : String(value ?? '').trim() !== ''
      if (hasValue) continue

      next[field] = message
      missingLabels.push(label)
    }

    errors.value = next

    return missingLabels
  }

  const applyApiError = (error: unknown): boolean => {
    const fieldErrors = (error as ApiError)?.data?.errors
    if (!fieldErrors) return false

    const next: Record<string, string> = {}
    for (const [field, value] of Object.entries(fieldErrors)) {
      const message = Array.isArray(value) ? value[0] : value
      if (message) next[field] = message
    }
    errors.value = next

    return Object.keys(errors.value).length > 0
  }

  const requireFields = (
    fields: RequiredField[],
    notifier: ErrorNotifier,
    title: string,
    message: (labels: string[]) => string
  ): boolean => {
    const missingLabels = validateRequired(fields)
    if (missingLabels.length === 0) return true

    notifier.error(title, message(missingLabels))

    return false
  }

  const notifyApiError = (
    error: unknown,
    notifier: ErrorNotifier,
    title: string,
    fallback: string
  ): void => {
    applyApiError(error)
    notifier.error(title, (error as ApiError)?.data?.message || fallback)
  }

  const notifyFormErrors = (
    event: FormErrorEvent,
    notifier: ErrorNotifier,
    title: string,
    labels: Record<string, string>,
    message: (fields: string[]) => string
  ): void => {
    const fields = [
      ...new Set(
        event.errors.map(error => labels[error.name || ''] || error.name).filter(Boolean)
      )
    ] as string[]
    if (fields.length === 0) return

    notifier.error(title, message(fields))
  }

  return {
    errors,
    clear,
    set,
    validateRequired,
    applyApiError,
    requireFields,
    notifyApiError,
    notifyFormErrors
  }
}

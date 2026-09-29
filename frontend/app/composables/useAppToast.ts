export function useAppToast() {
  const toast = useToast()

  return {
    success: (title: string, description: string) =>
      toast.add({
        title,
        description,
        icon: 'i-lucide-check',
        color: 'success',
      }),
    error: (title: string, description: string) =>
      toast.add({ title, description, icon: 'i-lucide-x', color: 'error' }),
  }
}

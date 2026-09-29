export const useClientPagination = <Item>(
  items: () => Item[],
  initialPageSize = 25
) => {
  const pagination = ref({
    pageIndex: 0,
    pageSize: initialPageSize
  })

  const total = computed(() => items().length)
  const page = computed({
    get: () => pagination.value.pageIndex + 1,
    set: (value: number) => {
      pagination.value.pageIndex = Math.max(0, value - 1)
    }
  })
  const pageCount = computed(() => Math.max(1, Math.ceil(total.value / pagination.value.pageSize)))
  const paginatedItems = computed(() => {
    const start = pagination.value.pageIndex * pagination.value.pageSize

    return items().slice(start, start + pagination.value.pageSize)
  })
  const reset = () => {
    pagination.value.pageIndex = 0
  }

  watch([total, () => pagination.value.pageSize], () => {
    if (page.value > pageCount.value) {
      pagination.value.pageIndex = pageCount.value - 1
    }
  })

  return {
    page,
    paginatedItems,
    pagination,
    reset,
    total
  }
}

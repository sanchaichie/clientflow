import type { Paginated } from '~/types/api'

type FilterValue = string | number | boolean | null | undefined

/**
 * Shared state for every paginated table: page, page size, search (debounced),
 * extra filters, loading and error. Re-fetches automatically when any of them
 * change, and goes back to page 1 when a filter or the search changes.
 *
 *   const { rows, total, page, search, filters, loading, refresh } =
 *     usePaginatedList<Client>('/api/clients', { status: null })
 */
export function usePaginatedList<
  T,
  F extends Record<string, FilterValue> = Record<string, FilterValue>
>(
  url: MaybeRefOrGetter<string>,
  initialFilters: F = {} as F,
  options: { perPage?: number; sort?: string } = {}
) {
  const api = useApi()

  const rows = ref<T[]>([]) as Ref<T[]>
  const total = ref(0)
  const page = ref(1)
  const perPage = ref(options.perPage ?? 15)
  const sort = ref(options.sort ?? '')
  const search = ref('')
  const debouncedSearch = ref('')
  const filters = reactive({ ...initialFilters }) as F
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Wait until the user stops typing before searching (avoids a request per key).
  let timer: ReturnType<typeof setTimeout> | undefined
  watch(search, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => (debouncedSearch.value = value.trim()), 300)
  })

  const query = computed(() => {
    const q: Record<string, string | number> = { page: page.value, per_page: perPage.value }
    if (sort.value) q.sort = sort.value
    if (debouncedSearch.value) q.search = debouncedSearch.value
    for (const [key, value] of Object.entries(filters)) {
      if (value === null || value === undefined || value === '' || value === false) continue
      q[key] = value === true ? 1 : value
    }
    return q
  })

  let requestId = 0
  async function refresh() {
    const id = ++requestId
    loading.value = true
    error.value = null
    try {
      const res = await api<Paginated<T>>(toValue(url), { query: query.value })
      if (id !== requestId) return // a newer request already started; ignore this one
      rows.value = res.data
      total.value = res.meta.total
      // Deleting the last row of the last page: step back a page.
      if (res.data.length === 0 && page.value > 1 && page.value > res.meta.last_page) {
        page.value = res.meta.last_page
      }
    } catch (e) {
      if (id === requestId) error.value = toApiError(e).message
    } finally {
      if (id === requestId) loading.value = false
    }
  }

  // Filter or search changed → back to page 1 (which also triggers a fetch).
  watch([debouncedSearch, () => ({ ...filters }), sort], () => {
    if (page.value !== 1) page.value = 1
    else refresh()
  })
  watch([page, perPage, () => toValue(url)], refresh)

  onMounted(refresh)
  onBeforeUnmount(() => clearTimeout(timer))

  return { rows, total, page, perPage, sort, search, filters, loading, error, refresh }
}

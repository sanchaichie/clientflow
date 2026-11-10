/** The configured Laravel API client (see plugins/api.ts). */
export function useApi() {
  return useNuxtApp().$api
}

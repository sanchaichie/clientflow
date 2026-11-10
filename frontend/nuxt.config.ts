// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: ['@nuxt/eslint', '@nuxt/ui'],

  // ClientFlow is an authenticated dashboard: no SEO benefit from SSR, and
  // client-side rendering lets the browser talk to Sanctum directly with cookies.
  ssr: false,

  devtools: {
    enabled: true
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      // Overridden by NUXT_PUBLIC_API_BASE in .env
      apiBase: 'http://localhost:8000'
    }
  },

  devServer: {
    host: 'localhost',
    port: 3000
  },

  compatibilityDate: '2026-06-30',

  typescript: {
    strict: true
  },

  eslint: {
    config: {
      // Formatting is handled by Prettier; ESLint focuses on code quality.
      stylistic: false
    }
  }
})

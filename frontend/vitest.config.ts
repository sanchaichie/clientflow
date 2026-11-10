import { defineVitestConfig } from '@nuxt/test-utils/config'

/**
 * Frontend tests.
 * - tests/unit/*.test.ts       → plain functions, fast Node environment
 * - tests/nuxt/*.nuxt.test.ts  → composables & components inside a Nuxt runtime
 *                                (auto-imports, useState, Nuxt UI) via happy-dom
 */
export default defineVitestConfig({
  test: {
    include: ['tests/**/*.test.ts'],
    environment: 'node',
    environmentMatchGlobs: [['tests/nuxt/**', 'nuxt']],
    environmentOptions: {
      nuxt: { domEnvironment: 'happy-dom' }
    }
  }
})

import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'happy-dom',
      exclude: [...configDefaults.exclude, 'e2e/**'],
      root: fileURLToPath(new URL('./', import.meta.url)),
      globals: true,
      // Les listes Reka (Select) montent toutes leurs options même fermées : montages plus lents sous charge.
      testTimeout: 15000,
      setupFiles: ['./test-setup.ts'],
    },
  })
)

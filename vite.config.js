import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/diplo/' : '/',
  plugins: [vue(), tailwindcss()],
  server: {
    watch: {
      ignored: ['**/storage/**'],
    },
    proxy: {
      '/api': 'http://127.0.0.1:8080',
      '/storage': 'http://127.0.0.1:8080',
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  test: {
    environment: 'jsdom',
  },
}))

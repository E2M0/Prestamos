import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// El proxy envía todo lo que empiece por /api al backend PHP (puerto 8000).
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})

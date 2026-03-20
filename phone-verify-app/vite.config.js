import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  base: '/printflow/public/phone-verify/',
  build: {
    outDir: '../public/phone-verify',
    emptyOutDir: true,
  },
})

import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    // O portal é servido pelo Laravel/FrankenPHP em produção desta leva.
    outDir: '../backend/public/spa',
    emptyOutDir: true,
  },
})

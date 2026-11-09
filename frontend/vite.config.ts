import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
// O destino do build é configurável por VITE_OUT_DIR: localmente o portal é
// servido pelo Laravel/FrankenPHP (backend/public/spa); na imagem web (nginx),
// o Dockerfile usa "dist".
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: process.env.VITE_OUT_DIR ?? '../backend/public/spa',
    emptyOutDir: true,
  },
})

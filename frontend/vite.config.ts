import path from 'node:path'

import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
// O destino do build é configurável por VITE_OUT_DIR: localmente o portal é
// servido pelo Laravel/FrankenPHP (backend/public/spa); na imagem web (nginx),
// o Dockerfile usa "dist".
//
// O `base` acompanha o destino: o Laravel serve os arquivos a partir de
// `public/spa`, então os assets precisam de URLs sob `/spa/` (senão o catch-all
// do Laravel devolve o index.html no lugar do JS — tela em branco). A imagem
// nginx serve o conteúdo na raiz, então o base é `/`.
const outDir = process.env.VITE_OUT_DIR ?? '../backend/public/spa'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  base: outDir.endsWith('dist') ? '/' : '/spa/',
  build: {
    outDir,
    emptyOutDir: true,
  },
  // Testes de componente (Vitest + jsdom); `setup.ts` liga os matchers do
  // jest-dom e limpa o DOM entre os testes.
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/teste/setup.ts'],
  },
})
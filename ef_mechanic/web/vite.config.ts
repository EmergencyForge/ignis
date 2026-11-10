import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: '../nui',
    emptyOutDir: true,
    assetsDir: 'assets',
  },
  server: {
    port: 5179,
    strictPort: true,
  },
})

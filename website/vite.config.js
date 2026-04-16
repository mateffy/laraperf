import path from "path"
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { TanStackRouterVite } from '@tanstack/router-plugin/vite'

export default defineConfig({
  plugins: [
    TanStackRouterVite({ 
      target: 'react', 
    }), 
    react(), 
    tailwindcss()
  ],
  build: {
    outDir: '../docs',
    emptyOutDir: true,
  },
  base: '/',
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  ssr: {
    noExternal: ['@tanstack/react-router'],
    external: ['three', '@react-three/fiber', '@react-three/postprocessing', 'postprocessing'],
  },
})
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  optimizeDeps: {
    include: [
      'axios', 
      'class-variance-authority', 
      '@tanstack/react-table', 
      '@tanstack/react-query',
      'lucide-react',
      'react-router-dom',
      'zustand',
      'zod',
      'react-hook-form',
      '@hookform/resolvers',
      'sonner',
      'date-fns',
      'recharts'
    ],
  },
  server: {
    port: 3000,
    proxy: {
      '/Api/V8': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/Api/access_token': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/api': {
        target: 'http://localhost:8080/custom/api',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
})

import { defineConfig } from 'vite'
import uni from '@dcloudio/vite-plugin-uni'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [uni()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@api': path.resolve(__dirname, './src/api')
    }
  },
  server: {
    port: 8081,
    host: '0.0.0.0',
    strictPort: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true
      }
    }
  },
  css: {
    preprocessorOptions: {
      scss: {
        // additionalData: '@import "@/common/styles/variables.scss";'
      }
    }
  }
})

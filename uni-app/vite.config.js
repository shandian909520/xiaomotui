import { defineConfig } from 'vite'
import uni from '@dcloudio/vite-plugin-uni'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [uni()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './'),
      '@api': path.resolve(__dirname, './api')
    }
  },
  server: {
    port: 37075,
    host: '0.0.0.0',
    strictPort: false
  },
  css: {
    preprocessorOptions: {
      scss: {
        // additionalData: '@import "@/common/styles/variables.scss";'
      }
    }
  }
})

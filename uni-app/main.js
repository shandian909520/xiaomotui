import App from './App'
import { createSSRApp } from 'vue'
import pinia from './stores/index_simple.js'

export function createApp() {
  const app = createSSRApp(App)

  // 注册Pinia
  app.use(pinia)

  // 全局配置
  app.config.globalProperties.$baseUrl = 'http://localhost:37080'

  return {
    app
  }
}

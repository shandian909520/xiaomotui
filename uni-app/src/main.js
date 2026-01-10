import App from './App'
import { createSSRApp } from 'vue'

export function createApp() {
  const app = createSSRApp(App)

  // 全局配置
  app.config.globalProperties.$baseUrl = 'http://localhost:37080'

  return {
    app
  }
}

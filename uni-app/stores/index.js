/**
 * Pinia Store 配置
 * 统一导出所有store模块
 */

import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'

// 创建pinia实例
const pinia = createPinia()

// 持久化插件配置
pinia.use(
	createPersistedState({
		// 存储配置
		storage: {
			getItem(key) {
				return uni.getStorageSync(key)
			},
			setItem(key, value) {
				uni.setStorageSync(key, value)
			}
		}
	})
)

export default pinia

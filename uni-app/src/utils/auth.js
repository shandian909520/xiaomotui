/**
 * 认证工具函数
 * 提供登录检查、权限验证等辅助功能
 */

import { useUserStore } from '../stores/user.js'

/**
 * 检查是否已登录
 * @returns {Boolean} 是否已登录
 */
export function isLoggedIn() {
	const userStore = useUserStore()
	return userStore.checkLoginStatus()
}

/**
 * 获取Token
 * @returns {String} Token值
 */
export function getToken() {
	try {
		return uni.getStorageSync('xiaomotui_token') || ''
	} catch (e) {
		console.error('获取Token失败', e)
		return ''
	}
}

/**
 * 检查Token是否有效
 * @returns {Boolean} Token是否有效
 */
export function isTokenValid() {
	const token = getToken()
	if (!token) return false

	const tokenExpires = uni.getStorageSync('xiaomotui_token_expires')
	if (!tokenExpires) return false

	// 检查是否过期（提前5分钟判定为过期）
	const now = Date.now()
	return tokenExpires > now + 5 * 60 * 1000
}

/**
 * 跳转到登录页
 * @param {String} redirect 登录后要跳转的页面
 */
export function navigateToLogin(redirect = '') {
	const url = redirect ? `/pages/auth/index?redirect=${encodeURIComponent(redirect)}` : '/pages/auth/index'

	uni.navigateTo({
		url,
		fail: () => {
			// 如果navigateTo失败，使用reLaunch
			uni.reLaunch({
				url: '/pages/auth/index'
			})
		}
	})
}

/**
 * 检查登录状态，未登录则跳转登录页
 * @param {String} redirect 登录后要跳转的页面
 * @returns {Boolean} 是否已登录
 */
export function requireLogin(redirect = '') {
	if (!isLoggedIn() || !isTokenValid()) {
		// 获取当前页面路径作为重定向地址
		if (!redirect) {
			const pages = getCurrentPages()
			if (pages.length > 0) {
				const currentPage = pages[pages.length - 1]
				redirect = `/${currentPage.route}`
				if (currentPage.options && Object.keys(currentPage.options).length > 0) {
					const query = Object.entries(currentPage.options)
						.map(([key, value]) => `${key}=${value}`)
						.join('&')
					redirect += `?${query}`
				}
			}
		}

		navigateToLogin(redirect)
		return false
	}

	return true
}

/**
 * 检查是否是商户
 * @returns {Boolean} 是否是商户
 */
export function isMerchant() {
	const userStore = useUserStore()
	return userStore.isMerchant
}

/**
 * 检查是否是管理员
 * @returns {Boolean} 是否是管理员
 */
export function isAdmin() {
	const userStore = useUserStore()
	return userStore.isAdmin
}

/**
 * 检查用户权限
 * @param {String|Array} roles 需要的角色（'user'|'merchant'|'admin' 或数组）
 * @returns {Boolean} 是否有权限
 */
export function hasRole(roles) {
	const userStore = useUserStore()
	const userRole = userStore.userInfo.role

	if (Array.isArray(roles)) {
		return roles.includes(userRole)
	}

	return userRole === roles
}

/**
 * 检查用户权限，无权限则提示并返回
 * @param {String|Array} roles 需要的角色
 * @param {String} message 提示信息
 * @returns {Boolean} 是否有权限
 */
export function requireRole(roles, message = '您没有权限访问') {
	if (!hasRole(roles)) {
		uni.showToast({
			title: message,
			icon: 'none',
			duration: 2000
		})

		setTimeout(() => {
			uni.navigateBack({
				fail: () => {
					uni.switchTab({
						url: '/pages/index/index'
					})
				}
			})
		}, 2000)

		return false
	}

	return true
}

/**
 * 获取当前用户信息
 * @returns {Object} 用户信息
 */
export function getUserInfo() {
	const userStore = useUserStore()
	return userStore.userInfo
}

/**
 * 获取用户ID
 * @returns {Number} 用户ID
 */
export function getUserId() {
	const userInfo = getUserInfo()
	return userInfo.id || 0
}

/**
 * 获取用户昵称
 * @returns {String} 用户昵称
 */
export function getNickname() {
	const userStore = useUserStore()
	return userStore.displayName
}

/**
 * 获取用户头像
 * @returns {String} 用户头像URL
 */
export function getAvatar() {
	const userInfo = getUserInfo()
	return userInfo.avatar || '/static/default-avatar.png'
}

/**
 * 退出登录
 * @returns {Promise}
 */
export async function logout() {
	const userStore = useUserStore()
	return await userStore.logout()
}

/**
 * 登录成功后的处理
 * @param {String} redirect 要跳转的页面
 */
export function handleLoginSuccess(redirect = '') {
	// 如果有重定向地址，跳转到重定向地址
	if (redirect) {
		uni.redirectTo({
			url: decodeURIComponent(redirect),
			fail: () => {
				// 重定向失败，跳转到首页
				uni.switchTab({
					url: '/pages/index/index'
				})
			}
		})
	} else {
		// 没有重定向地址，跳转到首页
		uni.switchTab({
			url: '/pages/index/index'
		})
	}
}

/**
 * 获取当前登录平台
 * @returns {String} 平台标识
 */
export function getCurrentPlatform() {
	// #ifdef MP-WEIXIN
	return 'wechat'
	// #endif

	// #ifdef MP-ALIPAY
	return 'alipay'
	// #endif

	// #ifdef H5
	return 'h5'
	// #endif

	// #ifdef APP-PLUS
	return 'app'
	// #endif

	return 'unknown'
}

/**
 * 获取平台显示名称
 * @returns {String} 平台显示名称
 */
export function getPlatformName() {
	const platform = getCurrentPlatform()
	const platformNames = {
		wechat: '微信小程序',
		alipay: '支付宝小程序',
		h5: 'H5',
		app: 'APP',
		unknown: '未知平台'
	}

	return platformNames[platform] || platformNames.unknown
}

/**
 * 检查是否支持某个平台的登录
 * @param {String} platform 平台标识
 * @returns {Boolean} 是否支持
 */
export function isSupportPlatform(platform) {
	const currentPlatform = getCurrentPlatform()
	return currentPlatform === platform
}

export default {
	isLoggedIn,
	getToken,
	isTokenValid,
	navigateToLogin,
	requireLogin,
	isMerchant,
	isAdmin,
	hasRole,
	requireRole,
	getUserInfo,
	getUserId,
	getNickname,
	getAvatar,
	logout,
	handleLoginSuccess,
	getCurrentPlatform,
	getPlatformName,
	isSupportPlatform
}

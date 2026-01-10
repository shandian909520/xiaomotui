/**
 * 用户状态管理 Store
 * 管理用户登录状态、用户信息、Token等
 */

import { defineStore } from 'pinia'
import request from '../api/request'
import authApi from '../api/modules/auth'

export const useUserStore = defineStore('user', {
	// 状态定义
	state: () => ({
		// 用户Token
		token: '',

		// Token过期时间
		tokenExpires: 0,

		// 用户信息
		userInfo: {
			id: 0,
			openid: '',
			nickname: '',
			avatar: '',
			phone: '',
			member_level: 'BASIC',
			role: 'user',
			merchant_id: null
		},

		// 是否已登录
		isLoggedIn: false,

		// 登录平台
		platform: ''
	}),

	// 计算属性
	getters: {
		/**
		 * 是否是商户
		 */
		isMerchant: (state) => {
			return state.userInfo.role === 'merchant' && state.userInfo.merchant_id > 0
		},

		/**
		 * 是否是管理员
		 */
		isAdmin: (state) => {
			return state.userInfo.role === 'admin'
		},

		/**
		 * Token是否有效
		 */
		isTokenValid: (state) => {
			if (!state.token) return false
			// 检查是否过期（提前5分钟判定为过期）
			const now = Date.now()
			return state.tokenExpires > now + 5 * 60 * 1000
		},

		/**
		 * 获取用户显示名称
		 */
		displayName: (state) => {
			return state.userInfo.nickname || state.userInfo.phone || '未命名用户'
		}
	},

	// 操作方法
	actions: {
		/**
		 * 设置Token
		 * @param {String} token Token值
		 * @param {Number} expiresIn 过期时间（秒）
		 */
		setToken(token, expiresIn = 86400) {
			this.token = token
			this.tokenExpires = Date.now() + expiresIn * 1000
			this.isLoggedIn = true

			// 同步到request实例
			request.setToken(token)

			// 存储到本地
			uni.setStorageSync('xiaomotui_token', token)
			uni.setStorageSync('xiaomotui_token_expires', this.tokenExpires)
		},

		/**
		 * 清除Token
		 */
		clearToken() {
			this.token = ''
			this.tokenExpires = 0
			this.isLoggedIn = false

			// 清除request实例的token
			request.clearToken()

			// 清除本地存储
			uni.removeStorageSync('xiaomotui_token')
			uni.removeStorageSync('xiaomotui_token_expires')
		},

		/**
		 * 设置用户信息
		 * @param {Object} userInfo 用户信息
		 */
		setUserInfo(userInfo) {
			this.userInfo = {
				...this.userInfo,
				...userInfo
			}

			// 存储到本地
			uni.setStorageSync('xiaomotui_user_info', this.userInfo)
		},

		/**
		 * 清除用户信息
		 */
		clearUserInfo() {
			this.userInfo = {
				id: 0,
				openid: '',
				nickname: '',
				avatar: '',
				phone: '',
				member_level: 'BASIC',
				role: 'user',
				merchant_id: null
			}

			// 清除本地存储
			uni.removeStorageSync('xiaomotui_user_info')
		},

		/**
		 * 设置登录平台
		 * @param {String} platform 平台标识
		 */
		setPlatform(platform) {
			this.platform = platform
			uni.setStorageSync('xiaomotui_platform', platform)
		},

		/**
		 * 微信小程序登录
		 * @param {Object} extraData 额外数据（如用户信息等）
		 * @returns {Promise}
		 */
		async wechatLogin(extraData = {}) {
			try {
				// #ifdef MP-WEIXIN
				// 获取微信登录code
				const loginRes = await uni.login({
					provider: 'weixin'
				})

				if (!loginRes[0] && loginRes[1].code) {
					// 调用后端登录接口
					const result = await authApi.login(loginRes[1].code, extraData)

					// 保存Token和用户信息
					this.setToken(result.token, result.expires_in || 86400)
					this.setUserInfo(result.user)
					this.setPlatform('wechat')

					return result
				} else {
					throw new Error(loginRes[0]?.errMsg || '获取微信登录code失败')
				}
				// #endif

				// #ifndef MP-WEIXIN
				throw new Error('当前平台不支持微信登录')
				// #endif
			} catch (error) {
				console.error('微信登录失败', error)
				throw error
			}
		},

		/**
		 * 支付宝小程序登录
		 * @param {Object} extraData 额外数据
		 * @returns {Promise}
		 */
		async alipayLogin(extraData = {}) {
			try {
				// #ifdef MP-ALIPAY
				// 获取支付宝登录code
				const loginRes = await uni.login()

				if (!loginRes[0] && loginRes[1].code) {
					// 调用后端登录接口
					const result = await authApi.login(loginRes[1].code, extraData)

					// 保存Token和用户信息
					this.setToken(result.token, result.expires_in || 86400)
					this.setUserInfo(result.user)
					this.setPlatform('alipay')

					return result
				} else {
					throw new Error(loginRes[0]?.errMsg || '获取支付宝登录code失败')
				}
				// #endif

				// #ifndef MP-ALIPAY
				throw new Error('当前平台不支持支付宝登录')
				// #endif
			} catch (error) {
				console.error('支付宝登录失败', error)
				throw error
			}
		},

		/**
		 * 手机号登录（H5）
		 * @param {String} phone 手机号
		 * @param {String} code 验证码
		 * @returns {Promise}
		 */
		async phoneLogin(phone, code) {
			try {
				const result = await authApi.phoneLogin(phone, code)

				// 保存Token和用户信息
				this.setToken(result.token, result.expires_in || 86400)
				this.setUserInfo(result.user)
				this.setPlatform('h5')

				return result
			} catch (error) {
				console.error('手机号登录失败', error)
				throw error
			}
		},

		/**
		 * 退出登录
		 * @returns {Promise}
		 */
		async logout() {
			try {
				// 调用后端退出接口
				await authApi.logout()
			} catch (error) {
				console.error('退出登录失败', error)
			} finally {
				// 清除本地数据
				this.clearToken()
				this.clearUserInfo()
				this.platform = ''

				// 跳转到登录页
				uni.reLaunch({
					url: '/pages/auth/index'
				})
			}
		},

		/**
		 * 刷新用户信息
		 * @returns {Promise}
		 */
		async refreshUserInfo() {
			try {
				const userInfo = await authApi.getUserInfo()
				this.setUserInfo(userInfo)
				return userInfo
			} catch (error) {
				console.error('刷新用户信息失败', error)
				throw error
			}
		},

		/**
		 * 更新用户信息
		 * @param {Object} data 要更新的用户信息
		 * @returns {Promise}
		 */
		async updateUserInfo(data) {
			try {
				const userInfo = await authApi.updateUserInfo(data)
				this.setUserInfo(userInfo)
				return userInfo
			} catch (error) {
				console.error('更新用户信息失败', error)
				throw error
			}
		},

		/**
		 * 检查登录状态
		 * @returns {Boolean} 是否已登录
		 */
		checkLoginStatus() {
			// 检查Token是否存在且有效
			if (!this.token || !this.isTokenValid) {
				return false
			}

			// 检查用户信息是否存在
			if (!this.userInfo.id) {
				return false
			}

			return true
		},

		/**
		 * 初始化用户状态
		 * 从本地存储恢复用户数据
		 */
		initUserState() {
			try {
				// 恢复Token
				const token = uni.getStorageSync('xiaomotui_token')
				const tokenExpires = uni.getStorageSync('xiaomotui_token_expires')

				if (token && tokenExpires) {
					this.token = token
					this.tokenExpires = tokenExpires

					// 检查是否过期
					if (this.isTokenValid) {
						this.isLoggedIn = true
						request.setToken(token)
					} else {
						// Token已过期，清除
						this.clearToken()
					}
				}

				// 恢复用户信息
				const userInfo = uni.getStorageSync('xiaomotui_user_info')
				if (userInfo) {
					this.userInfo = userInfo
				}

				// 恢复平台信息
				const platform = uni.getStorageSync('xiaomotui_platform')
				if (platform) {
					this.platform = platform
				}
			} catch (error) {
				console.error('初始化用户状态失败', error)
			}
		},

		/**
		 * 获取微信用户手机号
		 * @param {Object} event 微信授权事件
		 * @returns {Promise}
		 */
		async getWechatPhone(event) {
			try {
				const result = await authApi.getWechatPhone(event)

				// 更新用户信息中的手机号
				if (result.phone) {
					this.userInfo.phone = result.phone
					this.setUserInfo(this.userInfo)
				}

				return result
			} catch (error) {
				console.error('获取微信手机号失败', error)
				throw error
			}
		}
	},

	// 持久化配置
	persist: {
		enabled: true,
		strategies: [
			{
				key: 'xiaomotui_user_store',
				storage: {
					getItem: (key) => uni.getStorageSync(key),
					setItem: (key, value) => uni.setStorageSync(key, value)
				},
				paths: ['token', 'tokenExpires', 'userInfo', 'isLoggedIn', 'platform']
			}
		]
	}
})

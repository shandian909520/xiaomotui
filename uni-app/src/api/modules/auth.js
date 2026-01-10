/**
 * 认证相关API
 * 包括登录、注册、token刷新等功能
 */

import request from '../request.js'

export default {
	/**
	 * 用户登录
	 * @param {String} code 微信登录code或其他平台授权码
	 * @param {Object} extraData 额外数据（如手机号等）
	 * @returns {Promise}
	 */
	login(code, extraData = {}) {
		return request.post('/api/auth/login', {
			code,
			...extraData
		})
	},

	/**
	 * 微信小程序登录
	 * @returns {Promise}
	 */
	async wechatLogin() {
		// #ifdef MP-WEIXIN
		try {
			// 获取微信登录code
			const loginRes = await uni.login()
			if (loginRes.code) {
				// 调用后端登录接口
				return await this.login(loginRes.code)
			} else {
				throw new Error('获取微信登录code失败')
			}
		} catch (e) {
			console.error('微信登录失败', e)
			throw e
		}
		// #endif

		// #ifndef MP-WEIXIN
		throw new Error('当前平台不支持微信登录')
		// #endif
	},

	/**
	 * 支付宝小程序登录
	 * @returns {Promise}
	 */
	async alipayLogin() {
		// #ifdef MP-ALIPAY
		try {
			// 获取支付宝登录code
			const loginRes = await uni.login()
			if (loginRes.code) {
				// 调用后端登录接口
				return await this.login(loginRes.code)
			} else {
				throw new Error('获取支付宝登录code失败')
			}
		} catch (e) {
			console.error('支付宝登录失败', e)
			throw e
		}
		// #endif

		// #ifndef MP-ALIPAY
		throw new Error('当前平台不支持支付宝登录')
		// #endif
	},

	/**
	 * 手机号登录（H5）
	 * @param {String} phone 手机号
	 * @param {String} code 验证码
	 * @returns {Promise}
	 */
	phoneLogin(phone, code) {
		return request.post('/api/auth/phone-login', {
			phone,
			code
		})
	},

	/**
	 * 发送验证码
	 * @param {String} phone 手机号
	 * @returns {Promise}
	 */
	sendSmsCode(phone) {
		return request.post('/api/auth/send-code', {
			phone
		})
	},

	/**
	 * 刷新Token
	 * @returns {Promise}
	 */
	refreshToken() {
		return request.post('/api/auth/refresh')
	},

	/**
	 * 退出登录
	 * @returns {Promise}
	 */
	logout() {
		return request.post('/api/auth/logout')
	},

	/**
	 * 获取用户信息
	 * @returns {Promise}
	 */
	getUserInfo() {
		return request.get('/api/auth/user')
	},

	/**
	 * 更新用户信息
	 * @param {Object} data 用户信息
	 * @returns {Promise}
	 */
	updateUserInfo(data) {
		return request.put('/api/auth/user', data)
	},

	/**
	 * 绑定手机号
	 * @param {String} phone 手机号
	 * @param {String} code 验证码
	 * @returns {Promise}
	 */
	bindPhone(phone, code) {
		return request.post('/api/auth/bind-phone', {
			phone,
			code
		})
	},

	/**
	 * 检查登录状态
	 * @returns {Boolean} 是否已登录
	 */
	checkLoginStatus() {
		const token = request.getToken()
		return !!token
	},

	/**
	 * 获取微信用户手机号（需要用户授权）
	 * @param {Object} event 微信button组件的getPhoneNumber事件对象
	 * @returns {Promise}
	 */
	getWechatPhone(event) {
		// #ifdef MP-WEIXIN
		if (event.detail.errMsg === 'getPhoneNumber:ok') {
			// 发送加密数据到后端解密
			return request.post('/api/auth/wechat-phone', {
				encryptedData: event.detail.encryptedData,
				iv: event.detail.iv
			})
		} else {
			return Promise.reject(new Error('用户拒绝授权'))
		}
		// #endif

		// #ifndef MP-WEIXIN
		return Promise.reject(new Error('当前平台不支持'))
		// #endif
	}
}

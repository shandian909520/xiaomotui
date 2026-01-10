/**
 * 统一API请求封装
 * 支持微信小程序、H5、支付宝小程序等多端
 * 实现Token管理、错误处理、请求重试等功能
 */

import config from '../config/api.js'
import ErrorHandler from '../utils/errorHandler.js'

/**
 * 请求类
 */
class Request {
	constructor() {
		this.config = config
		this.requestQueue = [] // 请求队列
		this.isRefreshingToken = false // 是否正在刷新token
	}

	/**
	 * 核心请求方法
	 * @param {Object} options 请求配置
	 * @returns {Promise}
	 */
	request(options) {
		return new Promise((resolve, reject) => {
			// 合并配置
			const requestOptions = this.mergeOptions(options)

			// 显示loading
			if (requestOptions.showLoading) {
				this.showLoading(requestOptions.loadingText)
			}

			// 发起请求
			this.doRequest(requestOptions, resolve, reject, 0)
		})
	}

	/**
	 * 执行请求
	 * @param {Object} options 请求配置
	 * @param {Function} resolve Promise resolve
	 * @param {Function} reject Promise reject
	 * @param {Number} retryCount 当前重试次数
	 */
	doRequest(options, resolve, reject, retryCount) {
		uni.request({
			url: options.url,
			method: options.method,
			data: options.data,
			header: options.header,
			timeout: options.timeout,
			dataType: options.dataType || 'json',
			responseType: options.responseType || 'text',
			success: (res) => {
				this.handleSuccess(res, options, resolve, reject)
			},
			fail: (err) => {
				this.handleFail(err, options, resolve, reject, retryCount)
			},
			complete: () => {
				if (options.showLoading) {
					this.hideLoading()
				}
			}
		})
	}

	/**
	 * 合并请求配置
	 * @param {Object} options 用户配置
	 * @returns {Object} 合并后的配置
	 */
	mergeOptions(options) {
		const defaultOptions = {
			url: this.getFullUrl(options.url),
			method: options.method || 'GET',
			data: options.data || {},
			header: this.getHeaders(options.header),
			timeout: options.timeout || this.config.timeout,
			showLoading: options.showLoading !== false && this.config.showLoading,
			loadingText: options.loadingText || this.config.loadingText,
			enableRetry: options.enableRetry !== false && this.config.enableRetry,
			retryCount: options.retryCount || this.config.retryCount,
			retryDelay: options.retryDelay || this.config.retryDelay
		}

		return defaultOptions
	}

	/**
	 * 获取完整URL
	 * @param {String} url 相对路径或完整URL
	 * @returns {String} 完整URL
	 */
	getFullUrl(url) {
		// 如果是完整URL，直接返回
		if (url.startsWith('http://') || url.startsWith('https://')) {
			return url
		}

		// 拼接基础URL
		const baseUrl = this.config.baseUrl
		const separator = url.startsWith('/') ? '' : '/'
		return `${baseUrl}${separator}${url}`
	}

	/**
	 * 获取请求头
	 * @param {Object} customHeader 自定义请求头
	 * @returns {Object} 完整请求头
	 */
	getHeaders(customHeader = {}) {
		const headers = {
			...this.config.headers
		}

		// 添加Token
		const token = this.getToken()
		if (token) {
			headers['Authorization'] = `Bearer ${token}`
		}

		// 平台标识
		// #ifdef MP-WEIXIN
		headers['X-Platform'] = 'wechat'
		// #endif

		// #ifdef MP-ALIPAY
		headers['X-Platform'] = 'alipay'
		// #endif

		// #ifdef H5
		headers['X-Platform'] = 'h5'
		// #endif

		// #ifdef APP-PLUS
		headers['X-Platform'] = 'app'
		// #endif

		// 合并自定义请求头
		return {
			...headers,
			...customHeader
		}
	}

	/**
	 * 获取Token
	 * @returns {String} Token
	 */
	getToken() {
		try {
			return uni.getStorageSync(this.config.tokenKey) || ''
		} catch (e) {
			this.log('获取Token失败', e)
			return ''
		}
	}

	/**
	 * 设置Token
	 * @param {String} token Token值
	 */
	setToken(token) {
		try {
			uni.setStorageSync(this.config.tokenKey, token)
		} catch (e) {
			this.log('设置Token失败', e)
		}
	}

	/**
	 * 清除Token
	 */
	clearToken() {
		try {
			uni.removeStorageSync(this.config.tokenKey)
		} catch (e) {
			this.log('清除Token失败', e)
		}
	}

	/**
	 * 处理请求成功
	 * @param {Object} res 响应对象
	 * @param {Object} options 请求配置
	 * @param {Function} resolve Promise resolve
	 * @param {Function} reject Promise reject
	 */
	handleSuccess(res, options, resolve, reject) {
		this.log('请求成功', options.url, res)

		const statusCode = res.statusCode
		const data = res.data

		// HTTP状态码检查
		if (statusCode !== 200) {
			this.handleHttpError(statusCode, options, resolve, reject)
			return
		}

		// 业务状态码检查
		if (data.code === this.config.successCode) {
			// 请求成功
			resolve(data.data || data)
		} else if (data.code === this.config.tokenExpiredCode) {
			// Token过期
			this.handleTokenExpired(options, resolve, reject)
		} else if (data.code === this.config.needLoginCode) {
			// 需要登录
			this.handleNeedLogin(data.message || '请先登录')
			reject(data)
		} else {
			// 业务错误 - 使用统一错误处理
			const errorResult = ErrorHandler.handle(
				data.message || '请求失败',
				`Business Error: ${options.url}`,
				{ silent: false, report: data.code >= 500 }
			)

			reject({
				...data,
				friendlyMessage: errorResult.message
			})
		}
	}

	/**
	 * 处理请求失败
	 * @param {Object} err 错误对象
	 * @param {Object} options 请求配置
	 * @param {Function} resolve Promise resolve
	 * @param {Function} reject Promise reject
	 * @param {Number} retryCount 当前重试次数
	 */
	handleFail(err, options, resolve, reject, retryCount) {
		this.log('请求失败', options.url, err)

		// 判断是否需要重试
		if (options.enableRetry && retryCount < options.retryCount) {
			this.log(`准备重试，第${retryCount + 1}次`)
			setTimeout(() => {
				this.doRequest(options, resolve, reject, retryCount + 1)
			}, options.retryDelay)
			return
		}

		// 使用统一错误处理器
		const errorResult = ErrorHandler.handle(err, `API Request: ${options.url}`, {
			silent: false,
			report: true
		})

		// 构造错误对象
		const errorObj = {
			code: errorResult.details.code || 'NETWORK_ERROR',
			message: errorResult.message,
			details: errorResult.details,
			canRetry: errorResult.canRetry
		}

		reject(errorObj)
	}

	/**
	 * 处理HTTP错误
	 * @param {Number} statusCode HTTP状态码
	 * @param {Object} options 请求配置
	 * @param {Function} resolve Promise resolve
	 * @param {Function} reject Promise reject
	 */
	handleHttpError(statusCode, options, resolve, reject) {
		const errorMessages = {
			400: '请求参数错误',
			401: '未授权，请重新登录',
			403: '拒绝访问',
			404: '请求的资源不存在',
			405: '请求方法不允许',
			408: '请求超时',
			500: '服务器内部错误',
			502: '网关错误',
			503: '服务不可用',
			504: '网关超时'
		}

		const message = errorMessages[statusCode] || `请求失败(${statusCode})`
		this.showError(message)
		reject({ code: statusCode, message })
	}

	/**
	 * 处理Token过期
	 * @param {Object} options 原始请求配置
	 * @param {Function} resolve Promise resolve
	 * @param {Function} reject Promise reject
	 */
	handleTokenExpired(options, resolve, reject) {
		if (!this.config.autoRefreshToken) {
			this.handleNeedLogin('登录已过期，请重新登录')
			reject({ code: this.config.tokenExpiredCode, message: '登录已过期' })
			return
		}

		// 将请求加入队列
		this.requestQueue.push({ options, resolve, reject })

		// 如果正在刷新token，不重复刷新
		if (this.isRefreshingToken) {
			return
		}

		// 刷新token
		this.refreshToken()
	}

	/**
	 * 刷新Token
	 */
	async refreshToken() {
		this.isRefreshingToken = true

		try {
			// 调用刷新token接口
			const res = await uni.request({
				url: this.getFullUrl('/api/auth/refresh'),
				method: 'POST',
				header: this.getHeaders()
			})

			if (res.data.code === this.config.successCode) {
				// 更新token
				this.setToken(res.data.data.token)

				// 重新发起队列中的请求
				this.requestQueue.forEach(item => {
					this.request(item.options).then(item.resolve).catch(item.reject)
				})
				this.requestQueue = []
			} else {
				// 刷新失败，跳转登录
				this.handleNeedLogin('登录已过期，请重新登录')
				this.requestQueue.forEach(item => {
					item.reject({ code: this.config.tokenExpiredCode, message: '登录已过期' })
				})
				this.requestQueue = []
			}
		} catch (e) {
			this.log('刷新Token失败', e)
			this.handleNeedLogin('登录已过期，请重新登录')
			this.requestQueue.forEach(item => {
				item.reject({ code: this.config.tokenExpiredCode, message: '登录已过期' })
			})
			this.requestQueue = []
		} finally {
			this.isRefreshingToken = false
		}
	}

	/**
	 * 处理需要登录
	 * @param {String} message 提示信息
	 */
	handleNeedLogin(message) {
		this.showError(message)
		this.clearToken()

		// 延迟跳转，让用户看到提示信息
		setTimeout(() => {
			// #ifdef MP-WEIXIN || MP-ALIPAY
			uni.navigateTo({
				url: '/pages/auth/index'
			})
			// #endif

			// #ifdef H5
			// H5可能需要重定向到登录页
			const loginUrl = '/pages/auth/index'
			const currentUrl = encodeURIComponent(window.location.href)
			uni.navigateTo({
				url: `${loginUrl}?redirect=${currentUrl}`
			})
			// #endif
		}, 1500)
	}

	/**
	 * 获取错误信息
	 * @param {Object} err 错误对象
	 * @returns {String} 错误信息
	 */
	getErrorMessage(err) {
		if (err.errMsg) {
			if (err.errMsg.includes('timeout')) {
				return '请求超时，请检查网络连接'
			}
			if (err.errMsg.includes('fail')) {
				return '网络连接失败，请检查网络设置'
			}
		}
		return '网络异常，请稍后重试'
	}

	/**
	 * 显示Loading
	 * @param {String} title 提示文本
	 */
	showLoading(title) {
		uni.showLoading({
			title,
			mask: true
		})
	}

	/**
	 * 隐藏Loading
	 */
	hideLoading() {
		uni.hideLoading()
	}

	/**
	 * 显示错误提示
	 * @param {String} message 错误信息
	 */
	showError(message) {
		uni.showToast({
			title: message,
			icon: 'none',
			duration: 2000
		})
	}

	/**
	 * 打印日志
	 * @param  {...any} args 日志内容
	 */
	log(...args) {
		if (this.config.showLog) {
			console.log('[XiaoMoTui API]', ...args)
		}
	}

	/**
	 * GET请求
	 * @param {String} url 请求地址
	 * @param {Object} data 请求参数
	 * @param {Object} options 其他配置
	 * @returns {Promise}
	 */
	get(url, data = {}, options = {}) {
		return this.request({
			url,
			method: 'GET',
			data,
			...options
		})
	}

	/**
	 * POST请求
	 * @param {String} url 请求地址
	 * @param {Object} data 请求参数
	 * @param {Object} options 其他配置
	 * @returns {Promise}
	 */
	post(url, data = {}, options = {}) {
		return this.request({
			url,
			method: 'POST',
			data,
			...options
		})
	}

	/**
	 * PUT请求
	 * @param {String} url 请求地址
	 * @param {Object} data 请求参数
	 * @param {Object} options 其他配置
	 * @returns {Promise}
	 */
	put(url, data = {}, options = {}) {
		return this.request({
			url,
			method: 'PUT',
			data,
			...options
		})
	}

	/**
	 * DELETE请求
	 * @param {String} url 请求地址
	 * @param {Object} data 请求参数
	 * @param {Object} options 其他配置
	 * @returns {Promise}
	 */
	delete(url, data = {}, options = {}) {
		return this.request({
			url,
			method: 'DELETE',
			data,
			...options
		})
	}

	/**
	 * 文件上传
	 * @param {String} url 上传地址
	 * @param {String} filePath 文件路径
	 * @param {Object} data 额外参数
	 * @param {Object} options 其他配置
	 * @returns {Promise}
	 */
	upload(url, filePath, data = {}, options = {}) {
		return new Promise((resolve, reject) => {
			// 显示loading
			if (options.showLoading !== false) {
				this.showLoading(options.loadingText || '上传中...')
			}

			uni.uploadFile({
				url: this.getFullUrl(url),
				filePath,
				name: options.name || 'file',
				formData: data,
				header: this.getHeaders(options.header),
				success: (res) => {
					try {
						const data = typeof res.data === 'string' ? JSON.parse(res.data) : res.data
						if (data.code === this.config.successCode) {
							resolve(data.data || data)
						} else {
							this.showError(data.message || '上传失败')
							reject(data)
						}
					} catch (e) {
						this.showError('上传失败')
						reject(e)
					}
				},
				fail: (err) => {
					this.showError(this.getErrorMessage(err))
					reject(err)
				},
				complete: () => {
					if (options.showLoading !== false) {
						this.hideLoading()
					}
				}
			})
		})
	}

	/**
	 * 文件下载
	 * @param {String} url 下载地址
	 * @param {String} savePath 保存路径（可选）
	 * @param {Object} options 其他配置
	 * @returns {Promise}
	 */
	download(url, savePath, options = {}) {
		return new Promise((resolve, reject) => {
			// 显示loading
			if (options.showLoading !== false) {
				this.showLoading(options.loadingText || '下载中...')
			}

			const downloadOptions = {
				url: this.getFullUrl(url),
				header: this.getHeaders(options.header),
				success: (res) => {
					if (res.statusCode === 200) {
						resolve(res.tempFilePath)
					} else {
						this.showError('下载失败')
						reject(res)
					}
				},
				fail: (err) => {
					this.showError(this.getErrorMessage(err))
					reject(err)
				},
				complete: () => {
					if (options.showLoading !== false) {
						this.hideLoading()
					}
				}
			}

			// 如果指定了保存路径
			if (savePath) {
				downloadOptions.filePath = savePath
			}

			uni.downloadFile(downloadOptions)
		})
	}
}

// 创建实例
const request = new Request()

// 导出
export default request

/**
 * 内容发布相关API
 * 包括多平台发布、账号授权、发布记录等功能
 */

import request from '../request.js'

export default {
	/**
	 * 创建发布任务
	 * @param {Object} data 发布数据
	 * @returns {Promise}
	 */
	createPublishTask(data) {
		return request.post('/api/publish/create', {
			content_task_id: data.contentTaskId,  // 内容任务ID
			platforms: data.platforms,            // 目标平台数组
			scheduled_time: data.scheduledTime,   // 定时发布时间
			title: data.title,                    // 标题
			description: data.description,        // 描述
			tags: data.tags,                      // 标签
			cover: data.cover,                    // 封面
			...data
		})
	},

	/**
	 * 立即发布
	 * @param {String} contentTaskId 内容任务ID
	 * @param {Array} platforms 平台列表
	 * @param {Object} config 发布配置
	 * @returns {Promise}
	 */
	publishNow(contentTaskId, platforms, config = {}) {
		return request.post('/api/publish/now', {
			content_task_id: contentTaskId,
			platforms,
			...config
		})
	},

	/**
	 * 获取发布任务列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getPublishTasks(params = {}) {
		return request.get('/api/publish/tasks', {
			page: params.page || 1,
			page_size: params.pageSize || 10,
			status: params.status,
			platform: params.platform,
			start_date: params.startDate,
			end_date: params.endDate
		})
	},

	/**
	 * 获取发布任务详情
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	getPublishTaskDetail(taskId) {
		return request.get(`/api/publish/task/${taskId}`)
	},

	/**
	 * 取消发布任务
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	cancelPublishTask(taskId) {
		return request.post(`/api/publish/task/${taskId}/cancel`)
	},

	/**
	 * 删除发布任务
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	deletePublishTask(taskId) {
		return request.delete(`/api/publish/task/${taskId}`)
	},

	/**
	 * 重新发布
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	republish(taskId) {
		return request.post(`/api/publish/task/${taskId}/republish`)
	},

	/**
	 * 获取平台账号列表
	 * @returns {Promise}
	 */
	getPlatformAccounts() {
		return request.get('/api/publish/accounts')
	},

	/**
	 * 获取平台账号列表（别名）
	 * @returns {Promise}
	 */
	getAccounts() {
		return this.getPlatformAccounts()
	},

	/**
	 * 获取平台账号详情
	 * @param {String} accountId 账号ID
	 * @returns {Promise}
	 */
	getPlatformAccountDetail(accountId) {
		return request.get(`/api/publish/account/${accountId}`)
	},

	/**
	 * 添加平台账号
	 * @param {String} platform 平台名称
	 * @param {Object} data 账号数据
	 * @returns {Promise}
	 */
	addPlatformAccount(platform, data) {
		return request.post('/api/publish/account', {
			platform,
			...data
		})
	},

	/**
	 * 更新平台账号
	 * @param {String} accountId 账号ID
	 * @param {Object} data 账号数据
	 * @returns {Promise}
	 */
	updatePlatformAccount(accountId, data) {
		return request.put(`/api/publish/account/${accountId}`, data)
	},

	/**
	 * 删除平台账号
	 * @param {String} accountId 账号ID
	 * @returns {Promise}
	 */
	deletePlatformAccount(accountId) {
		return request.delete(`/api/publish/account/${accountId}`)
	},

	/**
	 * 删除平台账号（别名）
	 * @param {String} accountId 账号ID
	 * @returns {Promise}
	 */
	deleteAccount(accountId) {
		return this.deletePlatformAccount(accountId)
	},

	/**
	 * 刷新平台账号Token
	 * @param {String} accountId 账号ID
	 * @returns {Promise}
	 */
	refreshPlatformToken(accountId) {
		return request.post(`/api/publish/account/${accountId}/refresh`)
	},

	/**
	 * 刷新平台账号Token（别名）
	 * @param {String} accountId 账号ID
	 * @returns {Promise}
	 */
	refreshAccountToken(accountId) {
		return this.refreshPlatformToken(accountId)
	},

	/**
	 * 获取平台授权URL
	 * @param {String} platform 平台名称
	 * @returns {Promise}
	 */
	getPlatformAuthUrl(platform) {
		return request.get('/api/publish/auth-url', { platform })
	},

	/**
	 * 平台授权回调处理
	 * @param {String} platform 平台名称
	 * @param {String} code 授权码
	 * @returns {Promise}
	 */
	handleAuthCallback(platform, code) {
		return request.post('/api/publish/auth-callback', {
			platform,
			code
		})
	},

	/**
	 * 获取发布统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getPublishStats(params = {}) {
		return request.get('/api/publish/stats', {
			start_date: params.startDate,
			end_date: params.endDate,
			platform: params.platform
		})
	},

	/**
	 * 获取平台发布规则
	 * @param {String} platform 平台名称
	 * @returns {Promise}
	 */
	getPlatformRules(platform) {
		return request.get(`/api/publish/platform/${platform}/rules`)
	},

	/**
	 * 检查内容是否符合平台规则
	 * @param {String} platform 平台名称
	 * @param {String} contentTaskId 内容任务ID
	 * @returns {Promise}
	 */
	checkContentRules(platform, contentTaskId) {
		return request.post('/api/publish/check-rules', {
			platform,
			content_task_id: contentTaskId
		})
	},

	/**
	 * 批量发布
	 * @param {Array} tasks 任务列表
	 * @returns {Promise}
	 */
	batchPublish(tasks) {
		return request.post('/api/publish/batch', {
			tasks
		})
	},

	/**
	 * 获取发布预览
	 * @param {Object} data 发布数据
	 * @returns {Promise}
	 */
	getPublishPreview(data) {
		return request.post('/api/publish/preview', data)
	},

	/**
	 * 获取平台热门标签
	 * @param {String} platform 平台名称
	 * @param {String} category 分类
	 * @returns {Promise}
	 */
	getHotTags(platform, category) {
		return request.get('/api/publish/hot-tags', {
			platform,
			category
		})
	},

	/**
	 * 获取最佳发布时间建议
	 * @param {String} platform 平台名称
	 * @returns {Promise}
	 */
	getBestPublishTime(platform) {
		return request.get('/api/publish/best-time', { platform })
	},

	/**
	 * 抖音授权
	 * @returns {Promise}
	 */
	async authDouyin() {
		try {
			const urlRes = await this.getPlatformAuthUrl('douyin')
			// #ifdef H5
			window.location.href = urlRes.auth_url
			// #endif

			// #ifdef MP-WEIXIN || MP-ALIPAY
			uni.showToast({
				title: '请在H5环境中授权',
				icon: 'none'
			})
			// #endif
			return urlRes
		} catch (e) {
			console.error('抖音授权失败', e)
			throw e
		}
	},

	/**
	 * 小红书授权
	 * @returns {Promise}
	 */
	async authXiaohongshu() {
		try {
			const urlRes = await this.getPlatformAuthUrl('xiaohongshu')
			// #ifdef H5
			window.location.href = urlRes.auth_url
			// #endif

			// #ifdef MP-WEIXIN || MP-ALIPAY
			uni.showToast({
				title: '请在H5环境中授权',
				icon: 'none'
			})
			// #endif
			return urlRes
		} catch (e) {
			console.error('小红书授权失败', e)
			throw e
		}
	},

	/**
	 * 视频号授权
	 * @returns {Promise}
	 */
	async authChannels() {
		try {
			const urlRes = await this.getPlatformAuthUrl('channels')
			// #ifdef MP-WEIXIN
			// 微信小程序可以直接跳转
			uni.navigateToMiniProgram({
				appId: urlRes.app_id,
				path: urlRes.path
			})
			// #endif

			// #ifdef H5
			window.location.href = urlRes.auth_url
			// #endif
			return urlRes
		} catch (e) {
			console.error('视频号授权失败', e)
			throw e
		}
	}
}

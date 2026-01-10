/**
 * 内容生成相关API
 * 包括内容任务创建、状态查询、模板管理等功能
 */

import request from '../request.js'

export default {
	/**
	 * 创建内容生成任务
	 * @param {Object} data 任务数据
	 * @returns {Promise}
	 */
	createTask(data) {
		return request.post('/api/content/generate', {
			type: data.type,              // 内容类型：TEXT/VIDEO/IMAGE
			template_id: data.templateId,  // 模板ID
			device_code: data.deviceCode,  // 设备码（可选）
			scene: data.scene,            // 场景信息
			keywords: data.keywords,      // 关键词
			style: data.style,            // 风格
			platform: data.platform,      // 目标平台
			...data
		})
	},

	/**
	 * 查询任务状态
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	getTaskStatus(taskId) {
		return request.get(`/api/content/task/${taskId}/status`)
	},

	/**
	 * 获取任务详情
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	getTaskDetail(taskId) {
		return request.get(`/api/content/task/${taskId}`)
	},

	/**
	 * 获取任务列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getTaskList(params = {}) {
		return request.get('/api/content/tasks', {
			page: params.page || 1,
			page_size: params.pageSize || 10,
			type: params.type,
			status: params.status,
			start_date: params.startDate,
			end_date: params.endDate
		})
	},

	/**
	 * 取消任务
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	cancelTask(taskId) {
		return request.post(`/api/content/task/${taskId}/cancel`)
	},

	/**
	 * 删除任务
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	deleteTask(taskId) {
		return request.delete(`/api/content/task/${taskId}`)
	},

	/**
	 * 重新生成
	 * @param {String} taskId 原任务ID
	 * @param {Object} data 新配置（可选）
	 * @returns {Promise}
	 */
	regenerate(taskId, data = {}) {
		return request.post(`/api/content/task/${taskId}/regenerate`, data)
	},

	/**
	 * 根据反馈重新生成（带反馈信息）
	 * @param {String} taskId 原任务ID
	 * @param {Object} data 反馈和调整参数
	 * @returns {Promise}
	 */
	regenerateContent(taskId, data = {}) {
		return request.post(`/api/content/task/${taskId}/regenerate`, {
			regenerate_reason: data.regenerate_reason,
			adjust_params: data.adjust_params
		})
	},

	/**
	 * 提交内容反馈
	 * @param {Object} data 反馈数据
	 * @returns {Promise}
	 */
	submitFeedback(data) {
		return request.post('/api/content/feedback', {
			task_id: data.task_id,
			feedback_type: data.feedback_type,   // like/dislike
			reasons: data.reasons || [],         // 不满意原因数组
			other_reason: data.other_reason,     // 其他原因
			submit_time: data.submit_time
		})
	},

	/**
	 * 获取模板列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getTemplateList(params = {}) {
		return request.get('/api/content/templates', {
			category: params.category,
			type: params.type,
			page: params.page || 1,
			page_size: params.pageSize || 20
		})
	},

	/**
	 * 获取模板详情
	 * @param {String} templateId 模板ID
	 * @returns {Promise}
	 */
	getTemplateDetail(templateId) {
		return request.get(`/api/content/template/${templateId}`)
	},

	/**
	 * 创建自定义模板
	 * @param {Object} data 模板数据
	 * @returns {Promise}
	 */
	createTemplate(data) {
		return request.post('/api/content/template', {
			name: data.name,
			type: data.type,
			category: data.category,
			content: data.content,
			config: data.config,
			...data
		})
	},

	/**
	 * 更新模板
	 * @param {String} templateId 模板ID
	 * @param {Object} data 模板数据
	 * @returns {Promise}
	 */
	updateTemplate(templateId, data) {
		return request.put(`/api/content/template/${templateId}`, data)
	},

	/**
	 * 删除模板
	 * @param {String} templateId 模板ID
	 * @returns {Promise}
	 */
	deleteTemplate(templateId) {
		return request.delete(`/api/content/template/${templateId}`)
	},

	/**
	 * 预览模板效果
	 * @param {String} templateId 模板ID
	 * @param {Object} data 预览数据
	 * @returns {Promise}
	 */
	previewTemplate(templateId, data = {}) {
		return request.post(`/api/content/template/${templateId}/preview`, data)
	},

	/**
	 * 下载内容
	 * @param {String} taskId 任务ID
	 * @param {String} savePath 保存路径（可选）
	 * @returns {Promise}
	 */
	downloadContent(taskId, savePath) {
		return request.download(`/api/content/task/${taskId}/download`, savePath)
	},

	/**
	 * 保存到相册
	 * @param {String} url 文件URL或临时路径
	 * @param {String} type 类型：image/video
	 * @returns {Promise}
	 */
	async saveToAlbum(url, type = 'image') {
		try {
			// 先下载文件
			let filePath = url
			if (url.startsWith('http')) {
				const downloadRes = await uni.downloadFile({ url })
				filePath = downloadRes.tempFilePath
			}

			// 保存到相册
			if (type === 'video') {
				await uni.saveVideoToPhotosAlbum({ filePath })
			} else {
				await uni.saveImageToPhotosAlbum({ filePath })
			}

			uni.showToast({
				title: '保存成功',
				icon: 'success'
			})
			return true
		} catch (e) {
			if (e.errMsg && e.errMsg.includes('auth deny')) {
				uni.showModal({
					title: '提示',
					content: '需要授权访问相册',
					success: (res) => {
						if (res.confirm) {
							uni.openSetting()
						}
					}
				})
			} else {
				uni.showToast({
					title: '保存失败',
					icon: 'none'
				})
			}
			throw e
		}
	},

	/**
	 * 分享内容
	 * @param {Object} data 分享数据
	 * @returns {Promise}
	 */
	async shareContent(data) {
		try {
			// #ifdef MP-WEIXIN
			// 微信小程序使用分享接口
			await uni.shareAppMessage({
				title: data.title,
				path: data.path,
				imageUrl: data.imageUrl
			})
			return true
			// #endif

			// #ifdef H5
			// H5使用Web Share API或复制链接
			if (navigator.share) {
				await navigator.share({
					title: data.title,
					text: data.description,
					url: data.url
				})
			} else {
				// 复制链接到剪贴板
				// #ifdef H5
				await navigator.clipboard.writeText(data.url)
				uni.showToast({
					title: '链接已复制',
					icon: 'success'
				})
				// #endif
			}
			return true
			// #endif

			// #ifdef APP-PLUS
			// APP使用原生分享
			plus.share.sendWithSystem({
				type: 'text',
				content: data.url,
				href: data.url
			}, () => {
				uni.showToast({
					title: '分享成功',
					icon: 'success'
				})
			}, (e) => {
				console.error('分享失败', e)
			})
			return true
			// #endif
		} catch (e) {
			console.error('分享失败', e)
			throw e
		}
	},

	/**
	 * 获取内容统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getContentStats(params = {}) {
		return request.get('/api/content/stats', {
			start_date: params.startDate,
			end_date: params.endDate,
			type: params.type
		})
	},

	/**
	 * 批量生成内容
	 * @param {Array} tasks 任务列表
	 * @returns {Promise}
	 */
	batchGenerate(tasks) {
		return request.post('/api/content/batch-generate', {
			tasks
		})
	},

	/**
	 * 获取AI推荐配置
	 * @param {Object} scene 场景信息
	 * @returns {Promise}
	 */
	getAIRecommendation(scene) {
		return request.post('/api/content/ai-recommend', scene)
	}
}

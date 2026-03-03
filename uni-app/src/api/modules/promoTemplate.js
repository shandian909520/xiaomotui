/**
 * 视频合成模板相关API
 * 包括模板创建、变体生成、变体列表等功能
 */

import request from '../request.js'

export default {
	/**
	 * 创建模板
	 * @param {Object} data 模板数据
	 * @returns {Promise}
	 */
	create(data) {
		return request.post('/api/merchant/promo/templates', {
			name: data.name,
			material_ids: data.materialIds,
			duration: data.duration,
			transition: data.transition,
			music_id: data.musicId,
			variant_count: data.variantCount
		})
	},

	/**
	 * 获取模板列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/api/merchant/promo/templates', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			status: params.status,
			keyword: params.keyword
		})
	},

	/**
	 * 获取模板详情
	 * @param {String|Number} templateId 模板ID
	 * @returns {Promise}
	 */
	getDetail(templateId) {
		return request.get(`/api/merchant/promo/templates/${templateId}`)
	},

	/**
	 * 更新模板
	 * @param {String|Number} templateId 模板ID
	 * @param {Object} data 更新数据
	 * @returns {Promise}
	 */
	update(templateId, data) {
		return request.put(`/api/merchant/promo/templates/${templateId}`, data)
	},

	/**
	 * 删除模板
	 * @param {String|Number} templateId 模板ID
	 * @returns {Promise}
	 */
	delete(templateId) {
		return request.delete(`/api/merchant/promo/templates/${templateId}`)
	},

	/**
	 * 生成变体
	 * @param {String|Number} templateId 模板ID
	 * @param {Number} count 生成数量
	 * @returns {Promise}
	 */
	generateVariants(templateId, count = 1) {
		return request.post(`/api/merchant/promo/templates/${templateId}/generate`, {
			count
		})
	},

	/**
	 * 获取变体列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getVariantList(params = {}) {
		return request.get('/api/merchant/promo/variants', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			template_id: params.templateId,
			status: params.status
		})
	},

	/**
	 * 获取下一个可用变体
	 * @param {String|Number} templateId 模板ID
	 * @returns {Promise}
	 */
	getNextVariant(templateId) {
		return request.get(`/api/merchant/promo/templates/${templateId}/next-variant`)
	},

	/**
	 * 获取变体详情
	 * @param {String|Number} variantId 变体ID
	 * @returns {Promise}
	 */
	getVariantDetail(variantId) {
		return request.get(`/api/merchant/promo/variants/${variantId}`)
	},

	/**
	 * 删除变体
	 * @param {String|Number} variantId 变体ID
	 * @returns {Promise}
	 */
	deleteVariant(variantId) {
		return request.delete(`/api/merchant/promo/variants/${variantId}`)
	},

	/**
	 * 获取生成任务状态
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	getGenerateTaskStatus(taskId) {
		return request.get(`/api/merchant/promo/tasks/${taskId}/status`)
	},

	/**
	 * 发布变体到设备
	 * @param {String|Number} variantId 变体ID
	 * @param {Object} data 发布数据
	 * @returns {Promise}
	 */
	publishVariantToDevice(variantId, data) {
		return request.post(`/api/merchant/promo/variants/${variantId}/publish`, {
			device_ids: data.deviceIds,
			start_time: data.startTime,
			end_time: data.endTime
		})
	},

	/**
	 * 获取可用音乐列表
	 * @returns {Promise}
	 */
	getMusicList() {
		return request.get('/api/merchant/promo/music')
	}
}

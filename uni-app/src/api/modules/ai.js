/**
 * AI服务相关API
 * 文心一言AI内容生成接口
 */

import request from '../request.js'

export default {
	/**
	 * 获取AI服务状态
	 * @returns {Promise}
	 */
	getStatus() {
		return request.get('/ai/status')
	},

	/**
	 * 获取可用的内容风格
	 * @returns {Promise}
	 */
	getStyles() {
		return request.get('/ai/styles')
	},

	/**
	 * 获取可用的发布平台
	 * @returns {Promise}
	 */
	getPlatforms() {
		return request.get('/ai/platforms')
	},

	/**
	 * 生成AI文案
	 * @param {Object} data 生成参数
	 * @param {String} data.scene 场景
	 * @param {String} data.style 风格
	 * @param {String} data.platform 平台
	 * @param {String} data.category 类别
	 * @param {String} data.requirements 额外要求
	 * @returns {Promise}
	 */
	generateText(data) {
		return request.post('/ai/generate-text', data)
	},

	/**
	 * 批量生成AI文案
	 * @param {Array} batchParams 批量参数
	 * @returns {Promise}
	 */
	batchGenerateText(batchParams) {
		return request.post('/ai/batch-generate-text', { batch_params: batchParams })
	},

	/**
	 * 优化文案
	 * @param {Object} data 优化参数
	 * @param {String} data.text 原文案
	 * @param {String} data.direction 优化方向
	 * @returns {Promise}
	 */
	optimizeText(data) {
		return request.post('/ai/optimize-text', data)
	},

	/**
	 * 获取AI配置
	 * @returns {Promise}
	 */
	getConfig() {
		return request.get('/ai/config')
	}
}

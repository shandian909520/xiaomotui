/**
 * 点评 API(顾客端,合规:仅返回草稿 + 跳转链接 + 埋点)
 * 对应 controller: app\controller\Review
 * 不实现自动好评/代写代发,仅提供 AI 评价灵感草稿
 */

import request from '../request.js'

export default {
	/**
	 * 获取点评配置(平台入口 + 开关)
	 * @param {Number} deviceId 设备 ID
	 * @returns {Promise} {enabled, merchant_name, platforms[], insight_supported, compliance_tip}
	 */
	getReviewConfig(deviceId) {
		return request.get('/api/review/config', { device_id: deviceId })
	},

	/**
	 * 获取评价灵感草稿(1~5 条)
	 * @param {Object} params {device_id, platform, count}
	 *   platform: DIANPING/MEITUAN/GAODE/BAIDU/DOUYIN
	 * @returns {Promise} {platform, platform_name, drafts[], compliance_tip, disclaimer}
	 */
	getReviewDraft(params = {}) {
		return request.get('/api/review/draft', params)
	},

	/**
	 * 记录点评行为(view/jump/feedback/draft_copy 等)
	 * @param {Object} payload {device_id, platform, action, draft_index, extra}
	 * @returns {Promise} {recorded: true}
	 */
	recordReviewAction(payload) {
		return request.post('/api/review/action', payload)
	}
}
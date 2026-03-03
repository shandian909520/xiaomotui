/**
 * 推广相关API
 * 消费者碰NFC后的推广发布确认和奖励领取
 */

import request from '../request.js'

export default {
	/**
	 * 获取推广信息（复用NFC触发接口）
	 * @param {string} deviceCode 设备码
	 * @returns {Promise}
	 */
	getPromoInfo(deviceCode) {
		return request.post('/api/nfc/trigger', {
			device_code: deviceCode,
			trigger_source: 'promo',
			platform: 'h5',
		})
	},

	/**
	 * 确认发布
	 * @param {object} data { device_code, platform, trigger_id, openid }
	 * @returns {Promise}
	 */
	confirmPublish(data) {
		return request.post('/api/promo/confirm-publish', data)
	},

	/**
	 * 查询奖励领取状态
	 * @param {number} triggerId 触发记录ID
	 * @returns {Promise}
	 */
	getRewardStatus(triggerId) {
		return request.get('/api/promo/reward-status', {
			trigger_id: triggerId,
		})
	},
}

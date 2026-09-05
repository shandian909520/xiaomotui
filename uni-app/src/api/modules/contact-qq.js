/**
 * QQ 联系方式 API（模块7 - Agent C 业务闭环）
 * 顾客端：
 *   GET  /api/contact/qq-config?device_id=
 *   POST /api/contact/qq-action
 * 商家后台（鉴权）：
 *   PUT  /api/contact/admin/qq-config
 *
 * 对应 controller：app\controller\ContactQq
 * 数据源：xmt_nfc_devices.qq_contact_config JSON 字段（Flyway 20260904000005 加列）
 * 调用方：AggregationPageService.buildContactBlock（uni-app 端经 /api/nfc/aggregation-page）
 */

import request from '../request.js'

export default {
	/**
	 * 获取某设备的 QQ 联系方式配置
	 * @param {Number} deviceId 设备 ID
	 * @returns {Promise} {device_id, enabled, config:{qq_number, qq_qrcode, qq_group_url, kefu_qrcode}}
	 */
	getQqConfig(deviceId) {
		return request.get('/api/contact/qq-config', { device_id: deviceId })
	},

	/**
	 * 商家后台保存 QQ 配置（鉴权）
	 * @param {Object} payload {device_id, qq_number, qq_qrcode, qq_group_url, kefu_qrcode, enabled}
	 * @returns {Promise}
	 */
	saveQqConfig(payload = {}) {
		return request.put('/api/contact/admin/qq-config', payload)
	},

	/**
	 * 记录 QQ 联系动作埋点（公开）
	 * @param {Object} payload {device_id, action: view|click|copy_qq|join_group|contact_kefu, user_hash?}
	 * @returns {Promise} {recorded: true}
	 */
	recordQqAction(payload = {}) {
		return request.post('/api/contact/qq-action', payload)
	}
}
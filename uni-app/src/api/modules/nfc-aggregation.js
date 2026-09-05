/**
 * NFC 聚合页 API
 * 对应 controller: app\controller\Nfc@getAggregationPage
 * 路由: GET /api/nfc/aggregation-page?device_code=xxx
 */

import request from '../request.js'

export default {
	/**
	 * 获取设备聚合页全部区块
	 * 返回 wifi/publish/groupbuy/review/contact/lottery 6 个 block + highlight
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	getAggregationPage(deviceCode) {
		return request.get('/api/nfc/aggregation-page', { device_code: deviceCode })
	},

	/**
	 * 获取设备团购商品列表(顾客端)
	 * @param {String} deviceCode 设备码
	 * @returns {Promise} {device_id, list, total}
	 */
	getGroupBuyItems(deviceCode) {
		return request.get('/api/nfc/group-buy-items', { device_code: deviceCode })
	}
}
/**
 * 团购商品 API(顾客端)
 * 复用 Nfc::getGroupBuyItems(NFC 聚合页路由)作为入口
 * 也提供按 device_id 直查的兜底方法,供非 NFC 入口使用
 */

import request from '../request.js'

export default {
	/**
	 * 按 device_code 获取团购商品(走 NFC 路由,标准入口)
	 * @param {String} deviceCode 设备码
	 * @returns {Promise} {device_id, list, total}
	 */
	getItemsByDeviceCode(deviceCode) {
		return request.get('/api/nfc/group-buy-items', { device_code: deviceCode })
	},

	/**
	 * 按 device_id 获取团购商品(Controller 内部仍按 device_code 查询)
	 * 兼容老 uni-app 调用方
	 * @param {String|number} deviceId
	 * @returns {Promise}
	 */
	getItemsByDevice(deviceId) {
		// 注意: 后端现仅支持 device_code 查询;device_id 调用需要先转换为 code
		// 这里留作 TODO, 由 uni-app 端先调 nfc.getDeviceDetail 拿到 device_code 再调
		return Promise.reject(new Error('TODO: 需先调 /api/nfc/device/{code} 获取 device_code 再调 getItemsByDeviceCode'))
	}
}
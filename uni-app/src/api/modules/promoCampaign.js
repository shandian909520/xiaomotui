/**
 * 商家推广活动管理API
 * 用于商家创建和管理推广活动
 */

import request from '../request.js'

export default {
	/**
	 * 获取活动列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/api/merchant/promo/campaigns', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			status: params.status || '', // active/ended/all
			keyword: params.keyword || ''
		})
	},

	/**
	 * 获取活动详情
	 * @param {Number|String} id 活动ID
	 * @returns {Promise}
	 */
	getDetail(id) {
		return request.get(`/api/merchant/promo/campaigns/${id}`)
	},

	/**
	 * 创建活动
	 * @param {Object} data 活动数据
	 * @returns {Promise}
	 */
	create(data) {
		return request.post('/api/merchant/promo/campaigns', {
			name: data.name,
			description: data.description || '',
			variant_ids: data.variantIds || data.variant_ids || [],
			promo_text: data.promoText || data.promo_text || '',
			tags: data.tags || [],
			coupon_id: data.couponId || data.coupon_id || null,
			platforms: data.platforms || ['douyin'],
			start_time: data.startTime || data.start_time || null,
			end_time: data.endTime || data.end_time || null
		})
	},

	/**
	 * 更新活动
	 * @param {Number|String} id 活动ID
	 * @param {Object} data 活动数据
	 * @returns {Promise}
	 */
	update(id, data) {
		return request.put(`/api/merchant/promo/campaigns/${id}`, {
			name: data.name,
			description: data.description || '',
			variant_ids: data.variantIds || data.variant_ids || [],
			promo_text: data.promoText || data.promo_text || '',
			tags: data.tags || [],
			coupon_id: data.couponId || data.coupon_id || null,
			platforms: data.platforms || ['douyin'],
			start_time: data.startTime || data.start_time || null,
			end_time: data.endTime || data.end_time || null
		})
	},

	/**
	 * 删除活动
	 * @param {Number|String} id 活动ID
	 * @returns {Promise}
	 */
	delete(id) {
		return request.delete(`/api/merchant/promo/campaigns/${id}`)
	},

	/**
	 * 结束活动
	 * @param {Number|String} id 活动ID
	 * @returns {Promise}
	 */
	end(id) {
		return request.post(`/api/merchant/promo/campaigns/${id}/end`)
	},

	/**
	 * 绑定设备
	 * @param {Number|String} id 活动ID
	 * @param {Array} deviceIds 设备ID数组
	 * @returns {Promise}
	 */
	bindDevices(id, deviceIds) {
		return request.post(`/api/merchant/promo/campaigns/${id}/devices`, {
			device_ids: deviceIds
		})
	},

	/**
	 * 解绑设备
	 * @param {Number|String} id 活动ID
	 * @param {Number|String} deviceId 设备ID
	 * @returns {Promise}
	 */
	unbindDevice(id, deviceId) {
		return request.delete(`/api/merchant/promo/campaigns/${id}/devices/${deviceId}`)
	},

	/**
	 * 获取活动统计
	 * @param {Number|String} id 活动ID
	 * @returns {Promise}
	 */
	getStats(id) {
		return request.get(`/api/merchant/promo/campaigns/${id}/stats`)
	},

	/**
	 * 获取活动绑定的设备列表
	 * @param {Number|String} id 活动ID
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDevices(id, params = {}) {
		return request.get(`/api/merchant/promo/campaigns/${id}/devices`, {
			page: params.page || 1,
			page_size: params.pageSize || 20
		})
	},

	/**
	 * 通过设备码绑定设备
	 * @param {Number|String} id 活动ID
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	bindDeviceByCode(id, deviceCode) {
		return request.post(`/api/merchant/promo/campaigns/${id}/devices/bind-by-code`, {
			device_code: deviceCode
		})
	}
}

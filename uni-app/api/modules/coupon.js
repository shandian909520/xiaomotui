/**
 * 优惠券相关API
 * 优惠券创建、管理、领取等
 */

import request from '../request.js'

export default {
	/**
	 * 获取优惠券列表
	 * @param {Object} params 查询参数
	 * @param {Number} params.page 页码
	 * @param {Number} params.limit 每页数量
	 * @param {String} params.status 状态
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/marketing/coupon/list', params)
	},

	/**
	 * 获取优惠券详情
	 * @param {Number} id 优惠券ID
	 * @returns {Promise}
	 */
	getDetail(id) {
		return request.get(`/marketing/coupon/detail/${id}`)
	},

	/**
	 * 创建优惠券
	 * @param {Object} data 优惠券数据
	 * @param {String} data.name 名称
	 * @param {String} data.type 类型
	 * @param {Number} data.discount 折扣
	 * @param {Number} data.total 总数量
	 * @returns {Promise}
	 */
	create(data) {
		return request.post('/marketing/coupon/create', data)
	},

	/**
	 * 更新优惠券
	 * @param {Number} id 优惠券ID
	 * @param {Object} data 优惠券数据
	 * @returns {Promise}
	 */
	update(id, data) {
		return request.put(`/marketing/coupon/update/${id}`, data)
	},

	/**
	 * 删除优惠券
	 * @param {Number} id 优惠券ID
	 * @returns {Promise}
	 */
	delete(id) {
		return request.delete(`/marketing/coupon/delete/${id}`)
	},

	/**
	 * 发放优惠券
	 * @param {Number} id 优惠券ID
	 * @param {Object} data 发放数据
	 * @returns {Promise}
	 */
	grant(id, data) {
		return request.post(`/marketing/coupon/grant/${id}`, data)
	},

	/**
	 * 领取优惠券
	 * @param {Number} id 优惠券ID
	 * @returns {Promise}
	 */
	claim(id) {
		return request.post(`/marketing/coupon/claim/${id}`)
	},

	/**
	 * 我的优惠券
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	myList(params = {}) {
		return request.get('/marketing/coupon/my', params)
	},

	/**
	 * 使用优惠券
	 * @param {Number} id 优惠券用户ID
	 * @returns {Promise}
	 */
	use(id) {
		return request.post(`/marketing/coupon/use/${id}`)
	}
}

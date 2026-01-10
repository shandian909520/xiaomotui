/**
 * 团购活动相关API
 * 团购创建、管理、参与等
 */

import request from '../request.js'

export default {
	/**
	 * 获取团购列表
	 * @param {Object} params 查询参数
	 * @param {Number} params.page 页码
	 * @param {Number} params.limit 每页数量
	 * @param {String} params.status 状态
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/marketing/groupbuy/list', params)
	},

	/**
	 * 获取团购详情
	 * @param {Number} id 团购ID
	 * @returns {Promise}
	 */
	getDetail(id) {
		return request.get(`/marketing/groupbuy/detail/${id}`)
	},

	/**
	 * 创建团购
	 * @param {Object} data 团购数据
	 * @param {String} data.title 标题
	 * @param {Number} data.original_price 原价
	 * @param {Number} data.group_price 团购价
	 * @param {Number} data.min_people 最少成团人数
	 * @returns {Promise}
	 */
	create(data) {
		return request.post('/marketing/groupbuy/create', data)
	},

	/**
	 * 更新团购
	 * @param {Number} id 团购ID
	 * @param {Object} data 团购数据
	 * @returns {Promise}
	 */
	update(id, data) {
		return request.put(`/marketing/groupbuy/update/${id}`, data)
	},

	/**
	 * 删除团购
	 * @param {Number} id 团购ID
	 * @returns {Promise}
	 */
	delete(id) {
		return request.delete(`/marketing/groupbuy/delete/${id}`)
	},

	/**
	 * 开团
	 * @param {Number} id 团购ID
	 * @returns {Promise}
	 */
	open(id) {
		return request.post(`/marketing/groupbuy/open/${id}`)
	},

	/**
	 * 参团
	 * @param {Number} groupId 团ID
	 * @returns {Promise}
	 */
	join(groupId) {
		return request.post(`/marketing/groupbuy/join/${groupId}`)
	},

	/**
	 * 我的团购
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	myList(params = {}) {
		return request.get('/marketing/groupbuy/my', params)
	},

	/**
	 * 团购统计
	 * @param {Number} id 团购ID
	 * @returns {Promise}
	 */
	statistics(id) {
		return request.get(`/marketing/groupbuy/statistics/${id}`)
	}
}

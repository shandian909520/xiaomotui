/**
 * 就餐服务相关API
 * 就餐会话、订单、服务呼叫等
 */

import request from '../request.js'

export default {
	/**
	 * 获取就餐会话列表
	 * @param {Object} params 查询参数
	 * @param {Number} params.page 页码
	 * @param {Number} params.limit 每页数量
	 * @param {String} params.status 状态
	 * @returns {Promise}
	 */
	getSessionList(params = {}) {
		return request.get('/dining/session/list', params)
	},

	/**
	 * 获取就餐会话详情
	 * @param {Number} id 会话ID
	 * @returns {Promise}
	 */
	getSessionDetail(id) {
		return request.get(`/dining/session/detail/${id}`)
	},

	/**
	 * 创建就餐会话
	 * @param {Object} data 会话数据
	 * @param {Number} data.table_id 餐桌ID
	 * @param {Number} data.people_count 就餐人数
	 * @returns {Promise}
	 */
	createSession(data) {
		return request.post('/dining/session/create', data)
	},

	/**
	 * 结束就餐会话
	 * @param {Number} id 会话ID
	 * @returns {Promise}
	 */
	endSession(id) {
		return request.post(`/dining/session/end/${id}`)
	},

	/**
	 * 呼叫服务
	 * @param {Object} data 服务数据
	 * @param {Number} data.table_id 餐桌ID
	 * @param {String} data.service_type 服务类型
	 * @param {String} data.note 备注
	 * @returns {Promise}
	 */
	callService(data) {
		return request.post('/dining/service/call', data)
	},

	/**
	 * 获取服务呼叫列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getServiceCalls(params = {}) {
		return request.get('/dining/service/list', params)
	},

	/**
	 * 响应服务呼叫
	 * @param {Number} id 呼叫ID
	 * @param {Object} data 响应数据
	 * @returns {Promise}
	 */
	respondService(id, data) {
		return request.post(`/dining/service/respond/${id}`, data)
	},

	/**
	 * 完成服务呼叫
	 * @param {Number} id 呼叫ID
	 * @returns {Promise}
	 */
	completeService(id) {
		return request.post(`/dining/service/complete/${id}`)
	},

	/**
	 * 加入就餐会话(扫码加入)
	 * @param {Number} sessionId 会话ID
	 * @returns {Promise}
	 */
	joinSession(sessionId) {
		return request.post(`/dining/session/join/${sessionId}`)
	},

	/**
	 * 获取就餐统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getStatistics(params = {}) {
		return request.get('/dining/statistics', params)
	}
}

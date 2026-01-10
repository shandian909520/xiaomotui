/**
 * 告警系统相关API
 * 设备异常告警、规则管理等
 */

import request from '../request.js'

export default {
	/**
	 * 获取告警列表
	 * @param {Object} params 查询参数
	 * @param {Number} params.page 页码
	 * @param {Number} params.limit 每页数量
	 * @param {String} params.status 状态
	 * @param {String} params.level 告警级别
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/alert/list', params)
	},

	/**
	 * 获取告警详情
	 * @param {Number} id 告警ID
	 * @returns {Promise}
	 */
	getDetail(id) {
		return request.get(`/alert/detail/${id}`)
	},

	/**
	 * 处理告警
	 * @param {Number} id 告警ID
	 * @param {Object} data 处理数据
	 * @param {String} data.action 处理动作
	 * @param {String} data.remark 备注
	 * @returns {Promise}
	 */
	handle(id, data) {
		return request.post(`/alert/handle/${id}`, data)
	},

	/**
	 * 获取告警规则列表
	 * @returns {Promise}
	 */
	getRules() {
		return request.get('/alert/rules')
	},

	/**
	 * 创建告警规则
	 * @param {Object} data 规则数据
	 * @returns {Promise}
	 */
	createRule(data) {
		return request.post('/alert/rules', data)
	},

	/**
	 * 更新告警规则
	 * @param {Number} id 规则ID
	 * @param {Object} data 规则数据
	 * @returns {Promise}
	 */
	updateRule(id, data) {
		return request.put(`/alert/rules/${id}`, data)
	},

	/**
	 * 删除告警规则
	 * @param {Number} id 规则ID
	 * @returns {Promise}
	 */
	deleteRule(id) {
		return request.delete(`/alert/rules/${id}`)
	},

	/**
	 * 获取告警统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getStatistics(params = {}) {
		return request.get('/alert/statistics', params)
	}
}

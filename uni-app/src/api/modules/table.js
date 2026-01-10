/**
 * 餐桌管理相关API
 * 餐桌信息、状态管理等
 */

import request from '../request.js'

export default {
	/**
	 * 获取餐桌列表
	 * @param {Object} params 查询参数
	 * @param {Number} params.page 页码
	 * @param {Number} params.limit 每页数量
	 * @param {String} params.status 状态
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/dining/table/list', params)
	},

	/**
	 * 获取餐桌详情
	 * @param {Number} id 餐桌ID
	 * @returns {Promise}
	 */
	getDetail(id) {
		return request.get(`/dining/table/detail/${id}`)
	},

	/**
	 * 创建餐桌
	 * @param {Object} data 餐桌数据
	 * @param {String} data.table_number 桌号
	 * @param {Number} data.capacity 容纳人数
	 * @param {String} data.location 位置
	 * @returns {Promise}
	 */
	create(data) {
		return request.post('/dining/table/create', data)
	},

	/**
	 * 更新餐桌
	 * @param {Number} id 餐桌ID
	 * @param {Object} data 餐桌数据
	 * @returns {Promise}
	 */
	update(id, data) {
		return request.put(`/dining/table/update/${id}`, data)
	},

	/**
	 * 删除餐桌
	 * @param {Number} id 餐桌ID
	 * @returns {Promise}
	 */
	delete(id) {
		return request.delete(`/dining/table/delete/${id}`)
	},

	/**
	 * 更新餐桌状态
	 * @param {Number} id 餐桌ID
	 * @param {String} status 状态
	 * @returns {Promise}
	 */
	updateStatus(id, status) {
		return request.put(`/dining/table/status/${id}`, { status })
	},

	/**
	 * 开台
	 * @param {Number} id 餐桌ID
	 * @param {Object} data 开台数据
	 * @returns {Promise}
	 */
	open(id, data) {
		return request.post(`/dining/table/open/${id}`, data)
	},

	/**
	 * 清台
	 * @param {Number} id 餐桌ID
	 * @returns {Promise}
	 */
	clear(id) {
		return request.post(`/dining/table/clear/${id}`)
	},

	/**
	 * 获取餐桌二维码
	 * @param {Number} id 餐桌ID
	 * @returns {Promise}
	 */
	getQrCode(id) {
		return request.get(`/dining/table/qrcode/${id}`)
	}
}

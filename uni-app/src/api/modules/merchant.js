/**
 * 商家管理相关API
 * 包括商家信息、设备管理等功能
 */

import request from '../request.js'

export default {
	/**
	 * 获取商家信息
	 * @returns {Promise}
	 */
	getMerchantInfo() {
		return request.get('/api/merchant/info')
	},

	/**
	 * 更新商家信息
	 * @param {Object} data 商家数据
	 * @returns {Promise}
	 */
	updateMerchantInfo(data) {
		return request.put('/api/merchant/info', {
			name: data.name,
			description: data.description,
			logo: data.logo,
			contact_phone: data.contactPhone,
			contact_email: data.contactEmail,
			address: data.address,
			business_hours: data.businessHours,
			category: data.category,
			settings: data.settings
		})
	},

	/**
	 * 上传商家Logo
	 * @param {Object} file 文件对象
	 * @returns {Promise}
	 */
	uploadLogo(file) {
		return request.upload('/api/merchant/logo', {
			file
		})
	},

	/**
	 * 获取设备列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDeviceList(params = {}) {
		return request.get('/api/merchant/devices', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			status: params.status,  // online/offline/error
			type: params.type,      // nfc/qr/beacon
			keyword: params.keyword
		})
	},

	/**
	 * 获取设备详情
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	getDeviceDetail(deviceCode) {
		return request.get(`/api/merchant/device/${deviceCode}`)
	},

	/**
	 * 添加设备
	 * @param {Object} data 设备数据
	 * @returns {Promise}
	 */
	addDevice(data) {
		return request.post('/api/merchant/device', {
			device_code: data.deviceCode,
			name: data.name,
			type: data.type,
			location: data.location,
			table_id: data.tableId,
			description: data.description
		})
	},

	/**
	 * 更新设备信息
	 * @param {String} deviceCode 设备码
	 * @param {Object} data 设备数据
	 * @returns {Promise}
	 */
	updateDevice(deviceCode, data) {
		return request.put(`/api/merchant/device/${deviceCode}`, {
			name: data.name,
			location: data.location,
			table_id: data.tableId,
			description: data.description,
			status: data.status
		})
	},

	/**
	 * 删除设备
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	deleteDevice(deviceCode) {
		return request.delete(`/api/merchant/device/${deviceCode}`)
	},

	/**
	 * 批量导入设备
	 * @param {Array} devices 设备列表
	 * @returns {Promise}
	 */
	batchImportDevices(devices) {
		return request.post('/api/merchant/devices/batch-import', {
			devices
		})
	},

	/**
	 * 生成设备二维码
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	generateDeviceQRCode(deviceCode) {
		return request.get(`/api/merchant/device/${deviceCode}/qrcode`)
	},

	/**
	 * 获取设备统计数据
	 * @param {String} deviceCode 设备码
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDeviceStats(deviceCode, params = {}) {
		return request.get(`/api/merchant/device/${deviceCode}/stats`, {
			start_date: params.startDate,
			end_date: params.endDate
		})
	},

	/**
	 * 获取设备触发记录
	 * @param {String} deviceCode 设备码
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDeviceTriggerLogs(deviceCode, params = {}) {
		return request.get(`/api/merchant/device/${deviceCode}/triggers`, {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			start_date: params.startDate,
			end_date: params.endDate
		})
	},

	/**
	 * 重启设备
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	restartDevice(deviceCode) {
		return request.post(`/api/merchant/device/${deviceCode}/restart`)
	},

	/**
	 * 获取商家统计概览
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getMerchantStats(params = {}) {
		return request.get('/api/merchant/stats', {
			start_date: params.startDate,
			end_date: params.endDate,
			type: params.type
		})
	},

	/**
	 * 获取设备在线状态
	 * @returns {Promise}
	 */
	getDevicesOnlineStatus() {
		return request.get('/api/merchant/devices/online-status')
	},

	/**
	 * 绑定设备到桌台
	 * @param {String} deviceCode 设备码
	 * @param {String|Number} tableId 桌台ID
	 * @returns {Promise}
	 */
	bindDeviceToTable(deviceCode, tableId) {
		return request.post(`/api/merchant/device/${deviceCode}/bind-table`, {
			table_id: tableId
		})
	},

	/**
	 * 解绑设备
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	unbindDevice(deviceCode) {
		return request.post(`/api/merchant/device/${deviceCode}/unbind`)
	},

	/**
	 * 获取设备配置
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	getDeviceConfig(deviceCode) {
		return request.get(`/api/merchant/device/${deviceCode}/config`)
	},

	/**
	 * 更新设备配置
	 * @param {String} deviceCode 设备码
	 * @param {Object} config 配置数据
	 * @returns {Promise}
	 */
	updateDeviceConfig(deviceCode, config) {
		return request.put(`/api/merchant/device/${deviceCode}/config`, config)
	}
}

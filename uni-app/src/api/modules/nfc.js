/**
 * NFC相关API
 * 包括设备触发、设备管理、状态查询等功能
 */

import request from '../request.js'

export default {
	/**
	 * NFC设备触发
	 * @param {String} deviceCode 设备码
	 * @param {Object} extraData 额外数据
	 * @returns {Promise}
	 */
	trigger(deviceCode, extraData = {}) {
		return request.post('/api/nfc/trigger', {
			device_code: deviceCode,
			...extraData
		})
	},

	/**
	 * 获取设备配置
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	getDeviceConfig(deviceCode) {
		return request.get(`/api/nfc/device/${deviceCode}/config`)
	},

	/**
	 * 获取设备详情
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	getDeviceDetail(deviceCode) {
		return request.get(`/api/nfc/device/${deviceCode}`)
	},

	/**
	 * 设备状态上报
	 * @param {String} deviceCode 设备码
	 * @param {Object} status 状态数据
	 * @returns {Promise}
	 */
	reportDeviceStatus(deviceCode, status) {
		return request.post('/api/nfc/device/status', {
			device_code: deviceCode,
			battery: status.battery,      // 电量
			signal: status.signal,         // 信号强度
			temperature: status.temperature, // 温度
			...status
		})
	},

	/**
	 * 获取设备列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDeviceList(params = {}) {
		return request.get('/api/nfc/devices', params)
	},

	/**
	 * 绑定设备
	 * @param {String} deviceCode 设备码
	 * @param {String} name 设备名称
	 * @param {Object} config 设备配置
	 * @returns {Promise}
	 */
	bindDevice(deviceCode, name, config = {}) {
		return request.post('/api/nfc/device/bind', {
			device_code: deviceCode,
			name,
			config
		})
	},

	/**
	 * 解绑设备
	 * @param {String} deviceCode 设备码
	 * @returns {Promise}
	 */
	unbindDevice(deviceCode) {
		return request.post('/api/nfc/device/unbind', {
			device_code: deviceCode
		})
	},

	/**
	 * 更新设备配置
	 * @param {String} deviceCode 设备码
	 * @param {Object} config 配置数据
	 * @returns {Promise}
	 */
	updateDeviceConfig(deviceCode, config) {
		return request.put(`/api/nfc/device/${deviceCode}/config`, config)
	},

	/**
	 * 获取设备触发记录
	 * @param {String} deviceCode 设备码
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDeviceTriggers(deviceCode, params = {}) {
		return request.get(`/api/nfc/device/${deviceCode}/triggers`, params)
	},

	/**
	 * 获取设备统计数据
	 * @param {String} deviceCode 设备码
	 * @param {String} startDate 开始日期
	 * @param {String} endDate 结束日期
	 * @returns {Promise}
	 */
	getDeviceStats(deviceCode, startDate, endDate) {
		return request.get(`/api/nfc/device/${deviceCode}/stats`, {
			start_date: startDate,
			end_date: endDate
		})
	},

	/**
	 * 扫描二维码获取设备码（降级方案）
	 * @returns {Promise}
	 */
	async scanQRCode() {
		try {
			// #ifdef MP-WEIXIN || MP-ALIPAY || APP-PLUS
			const res = await uni.scanCode({
				scanType: ['qrCode']
			})
			return res.result
			// #endif

			// #ifdef H5
			// H5需要使用第三方扫码库或跳转到扫码页面
			uni.showToast({
				title: 'H5暂不支持扫码',
				icon: 'none'
			})
			return Promise.reject(new Error('H5暂不支持扫码'))
			// #endif
		} catch (e) {
			if (e.errMsg && e.errMsg.includes('cancel')) {
				return Promise.reject(new Error('取消扫码'))
			}
			return Promise.reject(e)
		}
	},

	/**
	 * 初始化NFC功能
	 * @returns {Promise}
	 */
	async initNFC() {
		// #ifdef MP-WEIXIN
		try {
			// 判断是否支持NFC
			const nfcAdapter = uni.getNFCAdapter()
			if (!nfcAdapter) {
				throw new Error('当前设备不支持NFC')
			}

			// 初始化HCE
			await uni.startHCE({
				aid_list: ['F0010203040506']
			})

			return true
		} catch (e) {
			console.error('NFC初始化失败', e)
			throw e
		}
		// #endif

		// #ifndef MP-WEIXIN
		return Promise.reject(new Error('当前平台不支持NFC'))
		// #endif
	},

	/**
	 * 监听NFC消息
	 * @param {Function} callback 回调函数
	 */
	listenNFC(callback) {
		// #ifdef MP-WEIXIN
		uni.onHCEMessage((res) => {
			if (callback && typeof callback === 'function') {
				callback(res)
			}
		})
		// #endif

		// #ifndef MP-WEIXIN
		console.warn('当前平台不支持NFC监听')
		// #endif
	},

	/**
	 * 停止NFC
	 */
	async stopNFC() {
		// #ifdef MP-WEIXIN
		try {
			await uni.stopHCE()
			return true
		} catch (e) {
			console.error('停止NFC失败', e)
			return false
		}
		// #endif

		// #ifndef MP-WEIXIN
		return Promise.resolve(true)
		// #endif
	},

	/**
	 * 检查NFC是否可用
	 * @returns {Promise<Boolean>}
	 */
	async checkNFCAvailable() {
		// #ifdef MP-WEIXIN
		try {
			const nfcAdapter = uni.getNFCAdapter()
			return !!nfcAdapter
		} catch (e) {
			return false
		}
		// #endif

		// #ifndef MP-WEIXIN
		return Promise.resolve(false)
		// #endif
	}
}

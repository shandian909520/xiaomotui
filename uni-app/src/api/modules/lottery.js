/**
 * 大转盘抽奖 API(顾客端)
 * 对应 controller: app\controller\Lottery
 * 用户匿名(基于 IP+UA 哈希),无需 token
 */

import request from '../request.js'

export default {
	/**
	 * 获取设备当前抽奖活动
	 * @param {String} deviceCode 设备码
	 * @returns {Promise} {enabled, activity_id, name, prizes[], ...}
	 */
	getLotteryByDevice(deviceCode) {
		return request.get('/api/lottery/by-device', { device_code: deviceCode })
	},

	/**
	 * 抽奖
	 * @param {Object} payload {activity_id, user_hash, device_id}
	 * @returns {Promise} {prize_id, prize_name, prize_type, is_winning, coupon}
	 */
	drawLottery(payload) {
		return request.post('/api/lottery/draw', payload)
	},

	/**
	 * 我的中奖记录
	 * @param {Object} params {device_id, user_hash, limit}
	 * @returns {Promise} {list, total}
	 */
	myRecords(params = {}) {
		return request.get('/api/lottery/my-records', params)
	}
}
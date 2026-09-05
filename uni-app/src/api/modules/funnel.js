/**
 * 漏斗埋点 API (Agent E)
 * 顾客端公开打点 + 商家后台聚合查询
 *
 * 公开:
 *   POST /api/funnel/record       funnel.record()
 *
 * 鉴权:
 *   GET  /api/funnel/funnel       funnel.getFunnel()
 *   GET  /api/funnel/daily        funnel.getDailyStat()
 *   GET  /api/funnel/merchant     funnel.getMerchantFunnel()
 */

import request from '../request.js'

export default {
	/**
	 * 公开打点(失败不影响主流程)
	 * @param {Object} payload { device_id, user_hash, step, block, action, meta }
	 * @returns {Promise<{recorded:boolean}>}
	 */
	record(payload) {
		return request.post('/api/funnel/record', payload, {
			skipErrorHandler: true
		}).then((res) => res).catch(() => ({ recorded: false }))
	},

	/**
	 * 商家后台:按设备聚合漏斗(鉴权)
	 */
	getFunnel(params) {
		return request.get('/api/funnel/funnel', { params })
	},

	/**
	 * 商家后台:按设备按日统计
	 */
	getDailyStat(params) {
		return request.get('/api/funnel/daily', { params })
	},

	/**
	 * 商家后台:商家级 4 卡片漏斗(NFC / H5 / 任务完成 / 加粉)
	 */
	getMerchantFunnel(params) {
		return request.get('/api/funnel/merchant', { params })
	}
}
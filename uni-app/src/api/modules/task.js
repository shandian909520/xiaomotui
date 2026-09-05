/**
 * 任务引擎相关API
 * 碰一碰任务实例、动作、凭证、奖励等功能
 */

import request from '../request.js'

export default {
	/**
	 * 获取任务实例详情（含bundle、动作清单、进度）
	 * @param {String} id 任务实例ID
	 * @returns {Promise}
	 */
	getTaskInstance(id) {
		return request.get(`/api/task/instance/${id}`)
	},

	/**
	 * 开始一个任务动作
	 * @param {String} id 任务实例ID
	 * @param {String} actionId 动作ID
	 * @returns {Promise} 卡片数据 {jump_type, scheme_url, qrcode_url, copy_text, guide_steps}
	 */
	startAction(id, actionId) {
		return request.post(`/api/task/instance/${id}/action/${actionId}/start`)
	},

	/**
	 * 上传动作凭证（截图等）
	 * @param {String} id 任务实例ID
	 * @param {String} actionId 动作ID
	 * @param {String} filePath 本地文件路径
	 * @returns {Promise} {proof_id, audit_status}
	 */
	uploadProof(id, actionId, filePath) {
		return request.upload(
			`/api/task/instance/${id}/action/${actionId}/proof`,
			filePath,
			{},
			{ loadingText: '上传凭证中...' }
		)
	},

	/**
	 * 领取奖励
	 * @param {String} id 任务实例ID
	 * @returns {Promise} 奖励发放结果
	 */
	claimReward(id) {
		return request.post(`/api/task/instance/${id}/claim-reward`)
	}
}

/**
 * 用户相关API
 * 包括用户信息、设置、通知等功能
 */

import request from '../request.js'

export default {
	/**
	 * 获取用户信息
	 * @returns {Promise}
	 */
	getUserInfo() {
		return request.get('/api/user/info')
	},

	/**
	 * 更新用户信息
	 * @param {Object} data 用户数据
	 * @returns {Promise}
	 */
	updateUserInfo(data) {
		return request.put('/api/user/info', {
			nickname: data.nickname,
			avatar: data.avatar,
			gender: data.gender,
			birthday: data.birthday,
			email: data.email,
			phone: data.phone
		})
	},

	/**
	 * 上传头像
	 * @param {Object} file 文件对象
	 * @returns {Promise}
	 */
	uploadAvatar(file) {
		return request.upload('/api/user/avatar', {
			file
		})
	},

	/**
	 * 修改密码
	 * @param {Object} data 密码数据
	 * @returns {Promise}
	 */
	changePassword(data) {
		return request.post('/api/user/change-password', {
			old_password: data.oldPassword,
			new_password: data.newPassword,
			confirm_password: data.confirmPassword
		})
	},

	/**
	 * 获取用户设置
	 * @returns {Promise}
	 */
	getUserSettings() {
		return request.get('/api/user/settings')
	},

	/**
	 * 更新用户设置
	 * @param {Object} settings 设置数据
	 * @returns {Promise}
	 */
	updateUserSettings(settings) {
		return request.put('/api/user/settings', settings)
	},

	/**
	 * 获取通知列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getNotifications(params = {}) {
		return request.get('/api/user/notifications', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			type: params.type,
			status: params.status  // read/unread
		})
	},

	/**
	 * 标记通知已读
	 * @param {String|Number} notificationId 通知ID
	 * @returns {Promise}
	 */
	markNotificationRead(notificationId) {
		return request.put(`/api/user/notification/${notificationId}/read`)
	},

	/**
	 * 批量标记通知已读
	 * @param {Array} notificationIds 通知ID数组
	 * @returns {Promise}
	 */
	batchMarkNotificationsRead(notificationIds) {
		return request.post('/api/user/notifications/batch-read', {
			notification_ids: notificationIds
		})
	},

	/**
	 * 全部标记已读
	 * @returns {Promise}
	 */
	markAllNotificationsRead() {
		return request.post('/api/user/notifications/read-all')
	},

	/**
	 * 删除通知
	 * @param {String|Number} notificationId 通知ID
	 * @returns {Promise}
	 */
	deleteNotification(notificationId) {
		return request.delete(`/api/user/notification/${notificationId}`)
	},

	/**
	 * 获取未读通知数量
	 * @returns {Promise}
	 */
	getUnreadCount() {
		return request.get('/api/user/notifications/unread-count')
	},

	/**
	 * 获取用户操作日志
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getActivityLogs(params = {}) {
		return request.get('/api/user/activity-logs', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			action_type: params.actionType,
			start_date: params.startDate,
			end_date: params.endDate
		})
	},

	/**
	 * 获取用户统计数据
	 * @returns {Promise}
	 */
	getUserStats() {
		return request.get('/api/user/stats')
	},

	/**
	 * 绑定手机号
	 * @param {Object} data 绑定数据
	 * @returns {Promise}
	 */
	bindPhone(data) {
		return request.post('/api/user/bind-phone', {
			phone: data.phone,
			code: data.code
		})
	},

	/**
	 * 绑定邮箱
	 * @param {Object} data 绑定数据
	 * @returns {Promise}
	 */
	bindEmail(data) {
		return request.post('/api/user/bind-email', {
			email: data.email,
			code: data.code
		})
	},

	/**
	 * 发送验证码
	 * @param {Object} data 验证码数据
	 * @returns {Promise}
	 */
	sendVerifyCode(data) {
		return request.post('/api/user/send-code', {
			type: data.type,  // phone/email
			target: data.target,
			purpose: data.purpose  // bind/change/verify
		})
	},

	/**
	 * 注销账号
	 * @param {Object} data 注销数据
	 * @returns {Promise}
	 */
	deleteAccount(data) {
		return request.post('/api/user/delete-account', {
			password: data.password,
			reason: data.reason
		})
	},

	/**
	 * 获取用户偏好设置
	 * @returns {Promise}
	 */
	getUserPreferences() {
		return request.get('/api/user/preferences')
	},

	/**
	 * 更新用户偏好设置
	 * @param {Object} preferences 偏好设置
	 * @returns {Promise}
	 */
	updateUserPreferences(preferences) {
		return request.put('/api/user/preferences', preferences)
	},

	/**
	 * 获取隐私设置
	 * @returns {Promise}
	 */
	getPrivacySettings() {
		return request.get('/api/user/privacy')
	},

	/**
	 * 更新隐私设置
	 * @param {Object} privacy 隐私设置
	 * @returns {Promise}
	 */
	updatePrivacySettings(privacy) {
		return request.put('/api/user/privacy', privacy)
	},

	/**
	 * 获取第三方账号绑定列表
	 * @returns {Promise}
	 */
	getThirdPartyBindings() {
		return request.get('/api/user/third-party-bindings')
	},

	/**
	 * 绑定第三方账号
	 * @param {Object} data 绑定数据
	 * @returns {Promise}
	 */
	bindThirdParty(data) {
		return request.post('/api/user/bind-third-party', {
			platform: data.platform,  // wechat/alipay/douyin
			code: data.code,
			...data
		})
	},

	/**
	 * 解绑第三方账号
	 * @param {String} platform 平台标识
	 * @returns {Promise}
	 */
	unbindThirdParty(platform) {
		return request.post('/api/user/unbind-third-party', {
			platform
		})
	},

	/**
	 * 获取用户权限列表
	 * @returns {Promise}
	 */
	getUserPermissions() {
		return request.get('/api/user/permissions')
	},

	/**
	 * 反馈问题
	 * @param {Object} data 反馈数据
	 * @returns {Promise}
	 */
	submitFeedback(data) {
		return request.post('/api/user/feedback', {
			type: data.type,
			content: data.content,
			images: data.images,
			contact: data.contact
		})
	},

	/**
	 * 获取反馈历史
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getFeedbackHistory(params = {}) {
		return request.get('/api/user/feedback-history', {
			page: params.page || 1,
			page_size: params.pageSize || 10
		})
	}
}

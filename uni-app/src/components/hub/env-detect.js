/**
 * 环境检测工具
 * 判断当前运行环境（微信内H5 / 外部浏览器 / 小程序 / APP）
 * 并根据环境给出动作卡片的最优跳转建议
 *
 * suggestJump 返回值:
 *  - 'scheme' : 外部浏览器/APP，可尝试 scheme 唤起
 *  - 'copy'   : 微信内，建议复制口令
 *  - 'qrcode' : 微信内（或scheme不可用），建议二维码
 *  - 'guide'  : 小程序内，scheme 不可用，走图文引导
 */

/**
 * 获取当前环境
 * @returns {String} 'wechat' | 'browser' | 'miniapp' | 'app'
 */
export function getEnv() {
	// #ifdef MP-WEIXIN
	return 'miniapp'
	// #endif

	// #ifdef MP-ALIPAY || MP-BAIDU || MP-TOUTIAO || MP-QQ
	return 'miniapp'
	// #endif

	// #ifdef APP-PLUS
	return 'app'
	// #endif

	// #ifdef H5
	const ua = (typeof navigator !== 'undefined' ? navigator.userAgent : '') || ''
	if (ua.includes('MicroMessenger')) {
		return 'wechat'
	}
	return 'browser'
	// #endif
}

/**
 * 是否在微信内（H5）
 * @returns {Boolean}
 */
export function isWechat() {
	return getEnv() === 'wechat'
}

/**
 * 是否在小程序内
 * @returns {Boolean}
 */
export function isMiniProgram() {
	return getEnv() === 'miniapp'
}

/**
 * scheme 唤起是否可能成功（仅外部浏览器/APP）
 * @returns {Boolean}
 */
export function canTryScheme() {
	const env = getEnv()
	return env === 'browser' || env === 'app'
}

/**
 * 根据环境和卡片能力给出跳转建议
 * @param {Object} card 动作卡片 {jump_type, scheme_url, qrcode_url, copy_text}
 * @returns {String} 'scheme' | 'copy' | 'qrcode' | 'guide'
 */
export function suggestJump(card) {
	if (!card) return 'guide'

	const env = getEnv()

	// 小程序内 scheme 不可用，直接降级
	if (env === 'miniapp') {
		if (card.qrcode_url) return 'qrcode'
		if (card.copy_text) return 'copy'
		return 'guide'
	}

	// 微信内 H5，scheme 会被拦截
	if (env === 'wechat') {
		if (card.copy_text) return 'copy'
		if (card.qrcode_url) return 'qrcode'
		return 'guide'
	}

	// 外部浏览器 / APP
	if (card.scheme_url && card.jump_type === 'scheme') return 'scheme'
	if (card.qrcode_url && card.jump_type === 'qrcode') return 'qrcode'
	if (card.copy_text) return 'copy'
	return 'guide'
}

export default {
	getEnv,
	isWechat,
	isMiniProgram,
	canTryScheme,
	suggestJump
}

/**
 * 表单验证工具函数
 * 提供常用的验证规则
 */

/**
 * 验证手机号
 * @param {String} phone 手机号
 * @returns {Boolean} 是否有效
 */
export function isPhone(phone) {
	return /^1[3-9]\d{9}$/.test(phone)
}

/**
 * 验证邮箱
 * @param {String} email 邮箱
 * @returns {Boolean} 是否有效
 */
export function isEmail(email) {
	return /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)
}

/**
 * 验证身份证号
 * @param {String} idCard 身份证号
 * @returns {Boolean} 是否有效
 */
export function isIdCard(idCard) {
	return /^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[0-9Xx]$/.test(idCard)
}

/**
 * 验证网址
 * @param {String} url 网址
 * @returns {Boolean} 是否有效
 */
export function isUrl(url) {
	return /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/.test(url)
}

/**
 * 验证中文
 * @param {String} str 字符串
 * @returns {Boolean} 是否为中文
 */
export function isChinese(str) {
	return /^[\u4e00-\u9fa5]+$/.test(str)
}

/**
 * 验证数字
 * @param {String|Number} num 数字
 * @returns {Boolean} 是否为数字
 */
export function isNumber(num) {
	return /^\d+(\.\d+)?$/.test(String(num))
}

/**
 * 验证整数
 * @param {String|Number} num 数字
 * @returns {Boolean} 是否为整数
 */
export function isInteger(num) {
	return /^\d+$/.test(String(num))
}

/**
 * 验证小数
 * @param {String|Number} num 数字
 * @param {Number} decimals 小数位数
 * @returns {Boolean} 是否为有效小数
 */
export function isDecimal(num, decimals = 2) {
	const pattern = new RegExp(`^\\d+\\.\\d{1,${decimals}}$`)
	return pattern.test(String(num))
}

/**
 * 验证密码强度
 * @param {String} password 密码
 * @param {Number} level 强度等级 (1:弱 2:中 3:强)
 * @returns {Boolean} 是否符合强度要求
 */
export function isPasswordStrong(password, level = 2) {
	if (level === 1) {
		// 弱: 至少6位
		return password.length >= 6
	} else if (level === 2) {
		// 中: 至少8位,包含字母和数字
		return /^(?=.*[a-zA-Z])(?=.*\d).{8,}$/.test(password)
	} else if (level === 3) {
		// 强: 至少8位,包含大小写字母、数字和特殊字符
		return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/.test(password)
	}
	return false
}

/**
 * 验证字符串长度
 * @param {String} str 字符串
 * @param {Number} min 最小长度
 * @param {Number} max 最大长度
 * @returns {Boolean} 是否在范围内
 */
export function isLengthValid(str, min, max) {
	const len = String(str).length
	return len >= min && len <= max
}

/**
 * 验证是否为空
 * @param {any} value 值
 * @returns {Boolean} 是否为空
 */
export function isEmpty(value) {
	if (value === null || value === undefined) return true
	if (typeof value === 'string') return value.trim() === ''
	if (Array.isArray(value)) return value.length === 0
	if (typeof value === 'object') return Object.keys(value).length === 0
	return false
}

/**
 * 验证银行卡号
 * @param {String} cardNo 银行卡号
 * @returns {Boolean} 是否有效
 */
export function isBankCard(cardNo) {
	return /^\d{16,19}$/.test(cardNo.replace(/\s/g, ''))
}

/**
 * 验证车牌号
 * @param {String} plateNo 车牌号
 * @returns {Boolean} 是否有效
 */
export function isPlateNo(plateNo) {
	return /^[京津沪渝冀豫云辽黑湘皖鲁新苏浙赣鄂桂甘晋蒙陕吉闽贵粤青藏川宁琼使领A-Z]{1}[A-Z]{1}[A-Z0-9]{4,5}[A-Z0-9挂学警港澳]{1}$/.test(
		plateNo
	)
}

/**
 * 验证微信号
 * @param {String} wechat 微信号
 * @returns {Boolean} 是否有效
 */
export function isWechat(wechat) {
	return /^[a-zA-Z][a-zA-Z0-9_-]{5,19}$/.test(wechat)
}

/**
 * 验证QQ号
 * @param {String} qq QQ号
 * @returns {Boolean} 是否有效
 */
export function isQQ(qq) {
	return /^[1-9]\d{4,10}$/.test(qq)
}

/**
 * 验证IP地址
 * @param {String} ip IP地址
 * @returns {Boolean} 是否有效
 */
export function isIP(ip) {
	return /^((25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d?)$/.test(ip)
}

/**
 * 验证日期格式
 * @param {String} date 日期字符串
 * @param {String} format 格式 (YYYY-MM-DD)
 * @returns {Boolean} 是否有效
 */
export function isDate(date, format = 'YYYY-MM-DD') {
	if (format === 'YYYY-MM-DD') {
		return /^\d{4}-\d{2}-\d{2}$/.test(date)
	}
	return false
}

export default {
	isPhone,
	isEmail,
	isIdCard,
	isUrl,
	isChinese,
	isNumber,
	isInteger,
	isDecimal,
	isPasswordStrong,
	isLengthValid,
	isEmpty,
	isBankCard,
	isPlateNo,
	isWechat,
	isQQ,
	isIP,
	isDate
}

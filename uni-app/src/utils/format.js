/**
 * 格式化工具函数
 * 时间、数字、文件大小等格式化
 */

/**
 * 格式化时间
 * @param {Number|String|Date} time 时间戳或时间字符串
 * @param {String} format 格式化模板 (如: 'YYYY-MM-DD HH:mm:ss')
 * @returns {String} 格式化后的时间
 */
export function formatTime(time, format = 'YYYY-MM-DD HH:mm:ss') {
	const date = new Date(time)

	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	const hour = String(date.getHours()).padStart(2, '0')
	const minute = String(date.getMinutes()).padStart(2, '0')
	const second = String(date.getSeconds()).padStart(2, '0')

	return format
		.replace('YYYY', year)
		.replace('MM', month)
		.replace('DD', day)
		.replace('HH', hour)
		.replace('mm', minute)
		.replace('ss', second)
}

/**
 * 格式化相对时间 (如: 刚刚、5分钟前、3小时前)
 * @param {Number|String|Date} time 时间戳或时间字符串
 * @returns {String} 格式化后的相对时间
 */
export function formatRelativeTime(time) {
	const now = Date.now()
	const timestamp = new Date(time).getTime()
	const diff = now - timestamp

	const minute = 60 * 1000
	const hour = 60 * minute
	const day = 24 * hour
	const month = 30 * day
	const year = 365 * day

	if (diff < minute) {
		return '刚刚'
	} else if (diff < hour) {
		return Math.floor(diff / minute) + '分钟前'
	} else if (diff < day) {
		return Math.floor(diff / hour) + '小时前'
	} else if (diff < month) {
		return Math.floor(diff / day) + '天前'
	} else if (diff < year) {
		return Math.floor(diff / month) + '个月前'
	} else {
		return Math.floor(diff / year) + '年前'
	}
}

/**
 * 格式化数字
 * @param {Number} num 数字
 * @param {Number} decimals 保留小数位数
 * @returns {String} 格式化后的数字
 */
export function formatNumber(num, decimals = 2) {
	if (isNaN(num)) return '0'
	return Number(num).toFixed(decimals)
}

/**
 * 格式化大数字 (如: 1.2万、3.5亿)
 * @param {Number} num 数字
 * @returns {String} 格式化后的数字
 */
export function formatBigNumber(num) {
	if (num < 10000) {
		return String(num)
	} else if (num < 100000000) {
		return (num / 10000).toFixed(1) + '万'
	} else {
		return (num / 100000000).toFixed(1) + '亿'
	}
}

/**
 * 格式化金额
 * @param {Number} amount 金额(分)
 * @param {String} symbol 货币符号
 * @returns {String} 格式化后的金额
 */
export function formatMoney(amount, symbol = '¥') {
	const yuan = (amount / 100).toFixed(2)
	return `${symbol}${yuan}`
}

/**
 * 格式化百分比
 * @param {Number} num 数字
 * @param {Number} decimals 保留小数位数
 * @returns {String} 格式化后的百分比
 */
export function formatPercent(num, decimals = 0) {
	return (num * 100).toFixed(decimals) + '%'
}

/**
 * 格式化文件大小
 * @param {Number} bytes 字节数
 * @returns {String} 格式化后的文件大小
 */
export function formatFileSize(bytes) {
	if (bytes === 0) return '0 B'

	const k = 1024
	const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
	const i = Math.floor(Math.log(bytes) / Math.log(k))

	return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]
}

/**
 * 格式化手机号 (隐藏中间4位)
 * @param {String} phone 手机号
 * @returns {String} 格式化后的手机号
 */
export function formatPhone(phone) {
	if (!phone) return ''
	return phone.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

/**
 * 格式化身份证号 (隐藏中间部分)
 * @param {String} idCard 身份证号
 * @returns {String} 格式化后的身份证号
 */
export function formatIdCard(idCard) {
	if (!idCard) return ''
	return idCard.replace(/(\d{6})\d{8}(\d{4})/, '$1********$2')
}

/**
 * 格式化银行卡号 (每4位空格)
 * @param {String} cardNo 银行卡号
 * @returns {String} 格式化后的银行卡号
 */
export function formatBankCard(cardNo) {
	if (!cardNo) return ''
	return cardNo.replace(/\s/g, '').replace(/(\d{4})/g, '$1 ').trim()
}

/**
 * 千分位格式化
 * @param {Number} num 数字
 * @returns {String} 格式化后的数字
 */
export function formatThousand(num) {
	return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

export default {
	formatTime,
	formatRelativeTime,
	formatNumber,
	formatBigNumber,
	formatMoney,
	formatPercent,
	formatFileSize,
	formatPhone,
	formatIdCard,
	formatBankCard,
	formatThousand
}

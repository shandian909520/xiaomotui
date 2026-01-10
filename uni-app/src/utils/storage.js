/**
 * 本地存储封装
 * 提供同步和异步的存储API
 */

/**
 * 设置存储(同步)
 * @param {String} key 键名
 * @param {any} value 值
 * @returns {Boolean} 是否成功
 */
export function setStorageSync(key, value) {
	try {
		uni.setStorageSync(key, value)
		return true
	} catch (e) {
		console.error('setStorageSync error:', e)
		return false
	}
}

/**
 * 获取存储(同步)
 * @param {String} key 键名
 * @param {any} defaultValue 默认值
 * @returns {any} 存储的值
 */
export function getStorageSync(key, defaultValue = null) {
	try {
		const value = uni.getStorageSync(key)
		return value !== '' ? value : defaultValue
	} catch (e) {
		console.error('getStorageSync error:', e)
		return defaultValue
	}
}

/**
 * 移除存储(同步)
 * @param {String} key 键名
 * @returns {Boolean} 是否成功
 */
export function removeStorageSync(key) {
	try {
		uni.removeStorageSync(key)
		return true
	} catch (e) {
		console.error('removeStorageSync error:', e)
		return false
	}
}

/**
 * 清空所有存储(同步)
 * @returns {Boolean} 是否成功
 */
export function clearStorageSync() {
	try {
		uni.clearStorageSync()
		return true
	} catch (e) {
		console.error('clearStorageSync error:', e)
		return false
	}
}

/**
 * 设置存储(异步)
 * @param {String} key 键名
 * @param {any} value 值
 * @returns {Promise}
 */
export function setStorage(key, value) {
	return new Promise((resolve, reject) => {
		uni.setStorage({
			key,
			data: value,
			success: () => resolve(true),
			fail: (err) => reject(err)
		})
	})
}

/**
 * 获取存储(异步)
 * @param {String} key 键名
 * @param {any} defaultValue 默认值
 * @returns {Promise}
 */
export function getStorage(key, defaultValue = null) {
	return new Promise((resolve, reject) => {
		uni.getStorage({
			key,
			success: (res) => resolve(res.data),
			fail: () => resolve(defaultValue)
		})
	})
}

/**
 * 移除存储(异步)
 * @param {String} key 键名
 * @returns {Promise}
 */
export function removeStorage(key) {
	return new Promise((resolve, reject) => {
		uni.removeStorage({
			key,
			success: () => resolve(true),
			fail: (err) => reject(err)
		})
	})
}

/**
 * 清空所有存储(异步)
 * @returns {Promise}
 */
export function clearStorage() {
	return new Promise((resolve, reject) => {
		uni.clearStorage({
			success: () => resolve(true),
			fail: (err) => reject(err)
		})
	})
}

/**
 * 获取存储信息
 * @returns {Promise}
 */
export function getStorageInfo() {
	return new Promise((resolve, reject) => {
		uni.getStorageInfo({
			success: (res) => resolve(res),
			fail: (err) => reject(err)
		})
	})
}

/**
 * 获取存储信息(同步)
 * @returns {Object} 存储信息
 */
export function getStorageInfoSync() {
	try {
		return uni.getStorageInfoSync()
	} catch (e) {
		console.error('getStorageInfoSync error:', e)
		return {
			keys: [],
			currentSize: 0,
			limitSize: 0
		}
	}
}

export default {
	setStorageSync,
	getStorageSync,
	removeStorageSync,
	clearStorageSync,
	setStorage,
	getStorage,
	removeStorage,
	clearStorage,
	getStorageInfo,
	getStorageInfoSync
}

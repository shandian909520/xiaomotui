// 本地存储工具

/**
 * 获取本地存储
 * @param {string} key
 * @returns {any}
 */
export function getStorage(key) {
  const value = localStorage.getItem(key)
  try {
    return JSON.parse(value)
  } catch {
    return value
  }
}

/**
 * 设置本地存储
 * @param {string} key
 * @param {any} value
 */
export function setStorage(key, value) {
  const data = typeof value === 'object' ? JSON.stringify(value) : value
  localStorage.setItem(key, data)
}

/**
 * 移除本地存储
 * @param {string} key
 */
export function removeStorage(key) {
  localStorage.removeItem(key)
}

/**
 * 清空本地存储
 */
export function clearStorage() {
  localStorage.clear()
}

/**
 * 获取会话存储
 * @param {string} key
 * @returns {any}
 */
export function getSessionStorage(key) {
  const value = sessionStorage.getItem(key)
  try {
    return JSON.parse(value)
  } catch {
    return value
  }
}

/**
 * 设置会话存储
 * @param {string} key
 * @param {any} value
 */
export function setSessionStorage(key, value) {
  const data = typeof value === 'object' ? JSON.stringify(value) : value
  sessionStorage.setItem(key, data)
}

/**
 * 移除会话存储
 * @param {string} key
 */
export function removeSessionStorage(key) {
  sessionStorage.removeItem(key)
}

/**
 * 清空会话存储
 */
export function clearSessionStorage() {
  sessionStorage.clear()
}

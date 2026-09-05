/**
 * API 响应数据规范化辅助工具
 *
 * request.js 响应拦截器已做业务数据解包：
 * 成功时返回 res.data !== undefined ? res.data : res
 * 因此页面层拿到的 res 就是已解包的业务数据，不需要再 .data
 */

/**
 * snake_case 转 camelCase
 * @param {string} str
 * @returns {string}
 */
function toCamelCase(str) {
  return str.replace(/_([a-z])/g, (_, c) => c.toUpperCase())
}

/**
 * 将对象的所有 key 从 snake_case 转为 camelCase（递归）
 * @param {*} obj
 * @returns {*}
 */
export function snakeToCamel(obj) {
  if (Array.isArray(obj)) return obj.map(snakeToCamel)
  if (obj && typeof obj === 'object' && !(obj instanceof Date)) {
    const result = {}
    for (const key of Object.keys(obj)) {
      result[toCamelCase(key)] = snakeToCamel(obj[key])
    }
    return result
  }
  return obj
}

/**
 * 从已解包的响应中提取列表数据（自动做 snake_case -> camelCase 转换）
 * 兼容多种后端返回格式：
 *   - 数组直接返回: [item1, item2]
 *   - 对象含 list 字段: { list: [...] }
 *   - 对象含 data 字段(分页): { list: [...], pagination: { total } }
 *
 * @param {*} res - 已解包的响应数据
 * @returns {Array} 列表数组（camelCase 字段名）
 */
export function normalizeListPayload(res) {
  let list = []
  if (Array.isArray(res)) {
    list = res
  } else if (res && typeof res === 'object') {
    if (Array.isArray(res.list)) {
      list = res.list
    } else if (Array.isArray(res.data)) {
      list = res.data
    }
  }
  return list.map(item => snakeToCamel(item))
}

/**
 * 从已解包的响应中提取分页数据（自动做 snake_case -> camelCase 转换）
 * 后端常见分页结构: { list: [...], pagination: { total } }
 * 也兼容: { list: [...], total } / { data: [...], total }
 *
 * @param {*} res - 已解包的响应数据
 * @returns {{ list: Array, total: number }}
 */
export function normalizePagination(res) {
  if (!res || typeof res !== 'object') {
    return { list: [], total: 0 }
  }

  // { list: [...], pagination: { total } }
  if (Array.isArray(res.list)) {
    const total = res.pagination?.total ?? res.total ?? res.list.length
    return { list: res.list.map(item => snakeToCamel(item)), total }
  }

  // { data: [...], total } or { data: { list, total } }
  if (res.data !== undefined) {
    if (Array.isArray(res.data)) {
      return { list: res.data.map(item => snakeToCamel(item)), total: res.total ?? res.data.length }
    }
    if (res.data && typeof res.data === 'object') {
      if (Array.isArray(res.data.list)) {
        const total = res.data.pagination?.total ?? res.data.total ?? res.data.list.length
        return { list: res.data.list.map(item => snakeToCamel(item)), total }
      }
    }
  }

  // 数组直返
  if (Array.isArray(res)) {
    return { list: res.map(item => snakeToCamel(item)), total: res.length }
  }

  return { list: [], total: 0 }
}

/**
 * 从已解包的响应中提取详情数据
 * 适用于后端返回 { data: {...} } 或直接返回对象的情况
 * 会自动做 snake_case -> camelCase 转换
 *
 * @param {*} res - 已解包的响应数据
 * @returns {Object} 详情对象（camelCase 字段名）
 */
export function normalizeDetail(res) {
  if (!res) return {}
  if (res && typeof res === 'object' && res.data !== undefined) {
    return snakeToCamel(res.data) || {}
  }
  return snakeToCamel(res) || {}
}

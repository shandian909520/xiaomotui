import request from '@/utils/request'

/**
 * 数据统计API服务
 */

/**
 * 获取统计概览数据
 * @param {Object} params - 查询参数
 * @param {string} params.start_date - 开始日期 (YYYY-MM-DD)
 * @param {string} params.end_date - 结束日期 (YYYY-MM-DD)
 * @param {number} params.merchant_id - 商家ID (可选，管理员使用)
 * @returns {Promise}
 */
export function getOverview(params) {
  return request({
    url: '/api/statistics/overview',
    method: 'get',
    params
  })
}

/**
 * 获取趋势数据
 * @param {Object} params - 查询参数
 * @param {string} params.start_date - 开始日期
 * @param {string} params.end_date - 结束日期
 * @param {string} params.metric_type - 指标类型 (trigger/generate/distribute/conversion)
 * @param {number} params.merchant_id - 商家ID (可选)
 * @returns {Promise}
 */
export function getTrend(params) {
  return request({
    url: '/api/statistics/trend',
    method: 'get',
    params
  })
}

/**
 * 获取设备统计数据
 * @param {Object} params - 查询参数
 * @param {string} params.start_date - 开始日期
 * @param {string} params.end_date - 结束日期
 * @param {number} params.merchant_id - 商家ID (可选)
 * @param {number} params.limit - 返回设备数量限制 (默认10)
 * @returns {Promise}
 */
export function getDeviceStats(params) {
  return request({
    url: '/api/statistics/device',
    method: 'get',
    params
  })
}

/**
 * 获取转化数据
 * @param {Object} params - 查询参数
 * @param {string} params.start_date - 开始日期
 * @param {string} params.end_date - 结束日期
 * @param {number} params.merchant_id - 商家ID (可选)
 * @returns {Promise}
 */
export function getConversionStats(params) {
  return request({
    url: '/api/statistics/conversion',
    method: 'get',
    params
  })
}

/**
 * 获取用户行为数据
 * @param {Object} params - 查询参数
 * @param {string} params.start_date - 开始日期
 * @param {string} params.end_date - 结束日期
 * @param {number} params.merchant_id - 商家ID (可选)
 * @returns {Promise}
 */
export function getUserBehavior(params) {
  return request({
    url: '/api/statistics/user-behavior',
    method: 'get',
    params
  })
}

/**
 * 导出统计报表
 * @param {Object} params - 导出参数
 * @param {string} params.start_date - 开始日期
 * @param {string} params.end_date - 结束日期
 * @param {number} params.merchant_id - 商家ID (可选)
 * @param {string} params.format - 导出格式 (excel/pdf)
 * @param {Array} params.metrics - 要导出的指标类型列表
 * @returns {Promise}
 */
export function exportReport(params) {
  return request({
    url: '/api/statistics/export',
    method: 'post',
    data: params,
    responseType: 'blob' // 用于下载文件
  })
}

/**
 * 获取实时数据 (WebSocket或轮询)
 * @param {Object} params - 查询参数
 * @param {number} params.merchant_id - 商家ID (可选)
 * @returns {Promise}
 */
export function getRealTimeData(params) {
  return request({
    url: '/api/statistics/realtime',
    method: 'get',
    params
  })
}

/**
 * 获取营销洞察和建议
 * @param {Object} params - 查询参数
 * @param {string} params.start_date - 开始日期
 * @param {string} params.end_date - 结束日期
 * @param {number} params.merchant_id - 商家ID (可选)
 * @returns {Promise}
 */
export function getMarketingInsights(params) {
  return request({
    url: '/api/statistics/insights',
    method: 'get',
    params
  })
}

/**
 * 获取异常数据预警
 * @param {Object} params - 查询参数
 * @param {number} params.merchant_id - 商家ID (可选)
 * @returns {Promise}
 */
export function getAlerts(params) {
  return request({
    url: '/api/statistics/alerts',
    method: 'get',
    params
  })
}

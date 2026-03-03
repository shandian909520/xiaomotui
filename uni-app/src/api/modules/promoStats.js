/**
 * 推广统计相关API
 * 包括统计概览、趋势数据、平台分布、设备排行等功能
 */

import request from '../request.js'

export default {
  /**
   * 获取统计概览
   * @param {Object} params 查询参数
   * @param {String} params.dateRange 日期范围: 7d/30d/all
   * @returns {Promise}
   */
  getOverview(params = {}) {
    return request.get('/merchant/promo-stats/overview', {
      date_range: params.dateRange || '7d'
    })
  },

  /**
   * 获取趋势数据
   * @param {Object} params 查询参数
   * @param {String} params.dateRange 日期范围: 7d/30d/all
   * @returns {Promise}
   */
  getTrendData(params = {}) {
    return request.get('/merchant/promo-stats/trend', {
      date_range: params.dateRange || '7d'
    })
  },

  /**
   * 获取平台分布
   * @param {Object} params 查询参数
   * @param {String} params.dateRange 日期范围: 7d/30d/all
   * @returns {Promise}
   */
  getPlatformDistribution(params = {}) {
    return request.get('/merchant/promo-stats/platform', {
      date_range: params.dateRange || '7d'
    })
  },

  /**
   * 获取设备排行
   * @param {Object} params 查询参数
   * @param {String} params.dateRange 日期范围: 7d/30d/all
   * @param {Number} params.limit 返回数量，默认5
   * @returns {Promise}
   */
  getDeviceRanking(params = {}) {
    return request.get('/merchant/promo-stats/device-ranking', {
      date_range: params.dateRange || '7d',
      limit: params.limit || 5
    })
  },

  /**
   * 获取今日统计
   * @returns {Promise}
   */
  getTodayStats() {
    return request.get('/merchant/promo-stats/today')
  }
}

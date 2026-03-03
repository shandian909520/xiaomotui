import request from '@/utils/request'

/**
 * 推广统计 API 模块
 * 提供推广活动的统计报表相关接口
 */

// 获取统计概览
export function getOverview(params) {
  return request.get('/merchant/promo-stats/overview', { params })
}

// 获取趋势数据
export function getTrendData(params) {
  return request.get('/merchant/promo-stats/trend', { params })
}

// 获取平台分布
export function getPlatformDistribution(params) {
  return request.get('/merchant/promo-stats/platform', { params })
}

// 获取设备排行
export function getDeviceRanking(params) {
  return request.get('/merchant/promo-stats/device-ranking', { params })
}

// 获取活动对比
export function getCampaignComparison(params) {
  return request.get('/merchant/promo-stats/campaign-comparison', { params })
}

// 获取今日统计
export function getTodayStats() {
  return request.get('/merchant/promo-stats/today')
}

// 获取活动列表（用于下拉选择）
export function getCampaignList(params) {
  return request.get('/merchant/promo-stats/campaign-list', { params })
}

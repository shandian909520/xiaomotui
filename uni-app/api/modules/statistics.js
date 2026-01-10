/**
 * 数据统计相关API
 * 包括数据概览、详细分析、报表导出等功能
 */

import request from '../request.js'

export default {
	/**
	 * 获取统计概览
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getOverview(params = {}) {
		return request.get('/api/statistics/overview', {
			start_date: params.startDate,
			end_date: params.endDate,
			type: params.type  // today/week/month/custom
		})
	},

	/**
	 * 获取内容生成统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getContentStats(params = {}) {
		return request.get('/api/statistics/content', {
			start_date: params.startDate,
			end_date: params.endDate,
			content_type: params.contentType,
			platform: params.platform,
			group_by: params.groupBy  // day/week/month
		})
	},

	/**
	 * 获取设备触发统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDeviceStats(params = {}) {
		return request.get('/api/statistics/device', {
			start_date: params.startDate,
			end_date: params.endDate,
			device_code: params.deviceCode,
			group_by: params.groupBy
		})
	},

	/**
	 * 获取发布统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getPublishStats(params = {}) {
		return request.get('/api/statistics/publish', {
			start_date: params.startDate,
			end_date: params.endDate,
			platform: params.platform,
			status: params.status
		})
	},

	/**
	 * 获取用户行为分析
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getUserBehaviorAnalysis(params = {}) {
		return request.get('/api/statistics/user-behavior', {
			start_date: params.startDate,
			end_date: params.endDate,
			action_type: params.actionType
		})
	},

	/**
	 * 获取热门内容排行
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getHotContentRanking(params = {}) {
		return request.get('/api/statistics/hot-content', {
			start_date: params.startDate,
			end_date: params.endDate,
			type: params.type,
			limit: params.limit || 10
		})
	},

	/**
	 * 获取素材使用统计
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getMaterialUsageStats(params = {}) {
		return request.get('/api/statistics/material-usage', {
			start_date: params.startDate,
			end_date: params.endDate,
			material_type: params.materialType
		})
	},

	/**
	 * 获取转化率分析
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getConversionAnalysis(params = {}) {
		return request.get('/api/statistics/conversion', {
			start_date: params.startDate,
			end_date: params.endDate,
			funnel_type: params.funnelType
		})
	},

	/**
	 * 获取实时数据
	 * @returns {Promise}
	 */
	getRealtimeData() {
		return request.get('/api/statistics/realtime')
	},

	/**
	 * 获取趋势分析
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getTrendAnalysis(params = {}) {
		return request.get('/api/statistics/trend', {
			start_date: params.startDate,
			end_date: params.endDate,
			metric: params.metric,  // trigger/content/publish
			period: params.period   // hour/day/week/month
		})
	},

	/**
	 * 获取对比数据
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getComparisonData(params = {}) {
		return request.get('/api/statistics/comparison', {
			current_start: params.currentStart,
			current_end: params.currentEnd,
			previous_start: params.previousStart,
			previous_end: params.previousEnd,
			metrics: params.metrics
		})
	},

	/**
	 * 导出统计报表
	 * @param {Object} params 导出参数
	 * @returns {Promise}
	 */
	exportReport(params = {}) {
		return request.download('/api/statistics/export', null, {
			start_date: params.startDate,
			end_date: params.endDate,
			type: params.type,
			format: params.format || 'excel'  // excel/csv/pdf
		})
	},

	/**
	 * 获取设备性能分析
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getDevicePerformance(params = {}) {
		return request.get('/api/statistics/device-performance', {
			start_date: params.startDate,
			end_date: params.endDate,
			device_code: params.deviceCode
		})
	},

	/**
	 * 获取内容效果分析
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getContentPerformance(params = {}) {
		return request.get('/api/statistics/content-performance', {
			start_date: params.startDate,
			end_date: params.endDate,
			task_id: params.taskId,
			platform: params.platform
		})
	},

	/**
	 * 获取平台数据对比
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getPlatformComparison(params = {}) {
		return request.get('/api/statistics/platform-comparison', {
			start_date: params.startDate,
			end_date: params.endDate,
			platforms: params.platforms
		})
	},

	/**
	 * 获取异常数据监控
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getAnomalyMonitoring(params = {}) {
		return request.get('/api/statistics/anomaly', {
			start_date: params.startDate,
			end_date: params.endDate,
			threshold: params.threshold
		})
	},

	/**
	 * 获取自定义报表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getCustomReport(params = {}) {
		return request.post('/api/statistics/custom-report', {
			dimensions: params.dimensions,
			metrics: params.metrics,
			filters: params.filters,
			start_date: params.startDate,
			end_date: params.endDate,
			group_by: params.groupBy
		})
	}
}

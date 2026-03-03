/**
 * 商家推广素材管理API
 * 用于商家拍摄和管理推广素材
 */

import request from '../request.js'

export default {
	/**
	 * 上传素材
	 * @param {String} filePath 本地文件路径
	 * @param {String} type 素材类型 (image/video)
	 * @param {Object} options 上传选项
	 * @returns {Promise}
	 */
	upload(filePath, type, options = {}) {
		return request.upload(
			'/api/merchant/promo/materials',
			filePath,
			{ type },
			{
				name: 'file',
				showLoading: options.showLoading !== false,
				loadingText: options.loadingText || '上传中...',
				...options
			}
		)
	},

	/**
	 * 获取素材列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getList(params = {}) {
		return request.get('/api/merchant/promo/materials', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			type: params.type || '', // image/video
			keyword: params.keyword || ''
		})
	},

	/**
	 * 获取素材详情
	 * @param {Number|String} id 素材ID
	 * @returns {Promise}
	 */
	getDetail(id) {
		return request.get(`/api/merchant/promo/materials/${id}`)
	},

	/**
	 * 删除素材
	 * @param {Number|String} id 素材ID
	 * @returns {Promise}
	 */
	delete(id) {
		return request.delete(`/api/merchant/promo/materials/${id}`)
	},

	/**
	 * 批量删除素材
	 * @param {Array} ids 素材ID数组
	 * @returns {Promise}
	 */
	batchDelete(ids) {
		return request.post('/api/merchant/promo/materials/batch-delete', {
			ids
		})
	},

	/**
	 * 获取素材统计
	 * @returns {Promise}
	 */
	getStats() {
		return request.get('/api/merchant/promo/materials/stats')
	}
}

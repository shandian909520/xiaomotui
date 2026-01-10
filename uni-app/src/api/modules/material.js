/**
 * 素材管理相关API
 * 包括素材上传、查询、分类管理等功能
 */

import request from '../request.js'

export default {
	/**
	 * 获取素材列表
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getMaterialList(params = {}) {
		return request.get('/api/material/list', {
			page: params.page || 1,
			page_size: params.pageSize || 20,
			category_id: params.categoryId,
			type: params.type,  // IMAGE/VIDEO/AUDIO/TEXT
			keyword: params.keyword,
			sort: params.sort || 'created_at',
			order: params.order || 'desc'
		})
	},

	/**
	 * 获取素材详情
	 * @param {String|Number} materialId 素材ID
	 * @returns {Promise}
	 */
	getMaterialDetail(materialId) {
		return request.get(`/api/material/${materialId}`)
	},

	/**
	 * 上传素材
	 * @param {Object} file 文件对象
	 * @param {Object} data 附加数据
	 * @returns {Promise}
	 */
	uploadMaterial(file, data = {}) {
		return request.upload('/api/material/upload', {
			file,
			name: data.name,
			category_id: data.categoryId,
			type: data.type,
			description: data.description,
			tags: data.tags
		})
	},

	/**
	 * 更新素材信息
	 * @param {String|Number} materialId 素材ID
	 * @param {Object} data 更新数据
	 * @returns {Promise}
	 */
	updateMaterial(materialId, data) {
		return request.put(`/api/material/${materialId}`, {
			name: data.name,
			category_id: data.categoryId,
			description: data.description,
			tags: data.tags
		})
	},

	/**
	 * 删除素材
	 * @param {String|Number} materialId 素材ID
	 * @returns {Promise}
	 */
	deleteMaterial(materialId) {
		return request.delete(`/api/material/${materialId}`)
	},

	/**
	 * 批量删除素材
	 * @param {Array} materialIds 素材ID数组
	 * @returns {Promise}
	 */
	batchDeleteMaterials(materialIds) {
		return request.post('/api/material/batch-delete', {
			material_ids: materialIds
		})
	},

	/**
	 * 获取素材分类列表
	 * @returns {Promise}
	 */
	getCategoryList() {
		return request.get('/api/material/categories')
	},

	/**
	 * 创建素材分类
	 * @param {Object} data 分类数据
	 * @returns {Promise}
	 */
	createCategory(data) {
		return request.post('/api/material/category', {
			name: data.name,
			parent_id: data.parentId,
			description: data.description,
			sort_order: data.sortOrder
		})
	},

	/**
	 * 更新素材分类
	 * @param {String|Number} categoryId 分类ID
	 * @param {Object} data 分类数据
	 * @returns {Promise}
	 */
	updateCategory(categoryId, data) {
		return request.put(`/api/material/category/${categoryId}`, data)
	},

	/**
	 * 删除素材分类
	 * @param {String|Number} categoryId 分类ID
	 * @returns {Promise}
	 */
	deleteCategory(categoryId) {
		return request.delete(`/api/material/category/${categoryId}`)
	},

	/**
	 * 搜索素材
	 * @param {String} keyword 关键词
	 * @param {Object} filters 过滤条件
	 * @returns {Promise}
	 */
	searchMaterials(keyword, filters = {}) {
		return request.get('/api/material/search', {
			keyword,
			type: filters.type,
			category_id: filters.categoryId,
			start_date: filters.startDate,
			end_date: filters.endDate,
			page: filters.page || 1,
			page_size: filters.pageSize || 20
		})
	},

	/**
	 * 获取素材使用统计
	 * @param {String|Number} materialId 素材ID
	 * @returns {Promise}
	 */
	getMaterialStats(materialId) {
		return request.get(`/api/material/${materialId}/stats`)
	},

	/**
	 * 批量移动素材到分类
	 * @param {Array} materialIds 素材ID数组
	 * @param {String|Number} categoryId 目标分类ID
	 * @returns {Promise}
	 */
	moveMaterialsToCategory(materialIds, categoryId) {
		return request.post('/api/material/batch-move', {
			material_ids: materialIds,
			category_id: categoryId
		})
	},

	/**
	 * 添加素材标签
	 * @param {String|Number} materialId 素材ID
	 * @param {Array} tags 标签数组
	 * @returns {Promise}
	 */
	addMaterialTags(materialId, tags) {
		return request.post(`/api/material/${materialId}/tags`, {
			tags
		})
	},

	/**
	 * 移除素材标签
	 * @param {String|Number} materialId 素材ID
	 * @param {Array} tags 标签数组
	 * @returns {Promise}
	 */
	removeMaterialTags(materialId, tags) {
		return request.delete(`/api/material/${materialId}/tags`, {
			tags
		})
	},

	/**
	 * 获取推荐素材
	 * @param {Object} params 查询参数
	 * @returns {Promise}
	 */
	getRecommendedMaterials(params = {}) {
		return request.get('/api/material/recommended', {
			type: params.type,
			count: params.count || 10,
			scene: params.scene
		})
	},

	/**
	 * 导入素材
	 * @param {Object} data 导入数据
	 * @returns {Promise}
	 */
	importMaterials(data) {
		return request.post('/api/material/import', {
			source: data.source,  // url/platform
			url: data.url,
			platform: data.platform,
			category_id: data.categoryId
		})
	},

	/**
	 * 获取素材导入任务状态
	 * @param {String} taskId 任务ID
	 * @returns {Promise}
	 */
	getImportTaskStatus(taskId) {
		return request.get(`/api/material/import/${taskId}/status`)
	}
}

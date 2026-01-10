/**
 * 模板管理API
 */
import request from '../request.js'

export default {
  /**
   * 获取模板列表
   * @param {Object} params - 查询参数
   * @returns {Promise}
   */
  getList(params = {}) {
    return request({
      url: '/api/template/list',
      method: 'GET',
      data: params
    })
  },

  /**
   * 获取模板详情
   * @param {Number} id - 模板ID
   * @returns {Promise}
   */
  getDetail(id) {
    return request({
      url: `/api/template/detail/${id}`,
      method: 'GET'
    })
  },

  /**
   * 创建模板
   * @param {Object} data - 模板数据
   * @returns {Promise}
   */
  create(data) {
    return request({
      url: '/api/template/create',
      method: 'POST',
      data
    })
  },

  /**
   * 更新模板
   * @param {Number} id - 模板ID
   * @param {Object} data - 模板数据
   * @returns {Promise}
   */
  update(id, data) {
    return request({
      url: `/api/template/update/${id}`,
      method: 'POST',
      data
    })
  },

  /**
   * 删除模板
   * @param {Number} id - 模板ID
   * @returns {Promise}
   */
  delete(id) {
    return request({
      url: `/api/template/delete/${id}`,
      method: 'POST'
    })
  },

  /**
   * 复制模板
   * @param {Number} id - 模板ID
   * @param {Object} data - 新模板数据
   * @returns {Promise}
   */
  copy(id, data = {}) {
    return request({
      url: `/api/template/copy/${id}`,
      method: 'POST',
      data
    })
  },

  /**
   * 获取热门模板
   * @param {Object} params - 查询参数
   * @returns {Promise}
   */
  getHot(params = {}) {
    return request({
      url: '/api/template/hot',
      method: 'GET',
      data: params
    })
  },

  /**
   * 获取模板分类列表
   * @returns {Promise}
   */
  getCategories() {
    return request({
      url: '/api/template/categories',
      method: 'GET'
    })
  },

  /**
   * 获取模板风格选项
   * @returns {Promise}
   */
  getStyles() {
    return request({
      url: '/api/template/styles',
      method: 'GET'
    })
  },

  /**
   * 获取模板统计数据
   * @returns {Promise}
   */
  getStatistics() {
    return request({
      url: '/api/template/statistics',
      method: 'GET'
    })
  },

  /**
   * 切换模板状态
   * @param {Number} id - 模板ID
   * @param {Number} status - 状态（0禁用 1启用）
   * @returns {Promise}
   */
  toggleStatus(id, status) {
    return request({
      url: `/api/template/toggle-status/${id}`,
      method: 'POST',
      data: { status }
    })
  },

  /**
   * 预览模板
   * @param {Number} id - 模板ID
   * @returns {Promise}
   */
  preview(id) {
    return request({
      url: `/api/template/preview/${id}`,
      method: 'GET'
    })
  },

  /**
   * 批量删除模板
   * @param {Array} ids - 模板ID数组
   * @returns {Promise}
   */
  batchDelete(ids) {
    return request({
      url: '/api/template/batch-delete',
      method: 'POST',
      data: { ids }
    })
  }
}

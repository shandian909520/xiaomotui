import request from '@/utils/request'

/**
 * 视频模板管理 API 模块
 */

/**
 * 创建模板
 * @param {Object} data - 模板数据
 * @param {string} data.name - 模板名称
 * @param {Array} data.materials - 素材ID数组
 * @param {number} data.duration_per_image - 每张图片时长(秒)
 * @param {string} data.transition_effect - 转场效果
 * @param {number} data.background_music_id - 背景音乐ID
 * @param {number} data.variant_count - 变体数量
 * @returns {Promise}
 */
export function createTemplate(data) {
  return request({
    url: '/merchant/promo/templates',
    method: 'post',
    data
  })
}

/**
 * 获取模板列表
 * @param {Object} params - 查询参数
 * @param {number} params.page - 页码
 * @param {number} params.limit - 每页数量
 * @param {string} params.keyword - 搜索关键词
 * @returns {Promise}
 */
export function getTemplateList(params) {
  return request({
    url: '/merchant/promo/templates',
    method: 'get',
    params
  })
}

/**
 * 获取模板详情
 * @param {number|string} id - 模板ID
 * @returns {Promise}
 */
export function getTemplateDetail(id) {
  return request({
    url: `/merchant/promo/templates/${id}`,
    method: 'get'
  })
}

/**
 * 更新模板
 * @param {number|string} id - 模板ID
 * @param {Object} data - 模板数据
 * @returns {Promise}
 */
export function updateTemplate(id, data) {
  return request({
    url: `/merchant/promo/templates/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除模板
 * @param {number|string} id - 模板ID
 * @returns {Promise}
 */
export function deleteTemplate(id) {
  return request({
    url: `/merchant/promo/templates/${id}`,
    method: 'delete'
  })
}

/**
 * 生成变体
 * @param {number|string} id - 模板ID
 * @param {number} count - 生成数量
 * @returns {Promise}
 */
export function generateVariants(id, count) {
  return request({
    url: `/merchant/promo/templates/${id}/generate`,
    method: 'post',
    data: { count }
  })
}

/**
 * 获取变体列表
 * @param {Object} params - 查询参数
 * @param {number} params.page - 页码
 * @param {number} params.limit - 每页数量
 * @param {number} params.template_id - 模板ID筛选
 * @returns {Promise}
 */
export function getVariantList(params) {
  return request({
    url: '/merchant/promo/variants',
    method: 'get',
    params
  })
}

/**
 * 获取变体详情
 * @param {number|string} id - 变体ID
 * @returns {Promise}
 */
export function getVariantDetail(id) {
  return request({
    url: `/merchant/promo/variants/${id}`,
    method: 'get'
  })
}

/**
 * 删除变体
 * @param {number|string} id - 变体ID
 * @returns {Promise}
 */
export function deleteVariant(id) {
  return request({
    url: `/merchant/promo/variants/${id}`,
    method: 'delete'
  })
}

/**
 * 批量删除变体
 * @param {Array} ids - 变体ID数组
 * @returns {Promise}
 */
export function batchDeleteVariants(ids) {
  return request({
    url: '/merchant/promo/variants/batch-delete',
    method: 'post',
    data: { ids }
  })
}

import request from '@/utils/request'

/**
 * 素材库 API 模块
 */

/**
 * 上传单个素材
 * @param {File} file - 文件对象
 * @param {string} type - 素材类型 (image/video/audio)
 * @param {Function} onProgress - 上传进度回调
 * @returns {Promise}
 */
export function uploadMaterial(file, type, onProgress) {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('type', type)
  return request({
    url: '/merchant/promo/materials',
    method: 'post',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: onProgress
  })
}

/**
 * 批量上传素材
 * @param {File[]} files - 文件数组
 * @param {string} type - 素材类型
 * @returns {Promise}
 */
export function batchUploadMaterials(files, type) {
  const formData = new FormData()
  files.forEach(file => {
    formData.append('files[]', file)
  })
  formData.append('type', type)
  return request({
    url: '/merchant/promo/materials/batch',
    method: 'post',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

/**
 * 获取素材列表
 * @param {Object} params - 查询参数
 * @param {number} params.page - 页码
 * @param {number} params.limit - 每页数量
 * @param {string} params.type - 素材类型筛选
 * @param {string} params.keyword - 搜索关键词
 * @returns {Promise}
 */
export function getMaterialList(params) {
  return request({
    url: '/merchant/promo/materials',
    method: 'get',
    params
  })
}

/**
 * 删除素材
 * @param {number|string} id - 素材ID
 * @returns {Promise}
 */
export function deleteMaterial(id) {
  return request({
    url: `/merchant/promo/materials/${id}`,
    method: 'delete'
  })
}

/**
 * 获取素材详情
 * @param {number|string} id - 素材ID
 * @returns {Promise}
 */
export function getMaterialDetail(id) {
  return request({
    url: `/merchant/promo/materials/${id}`,
    method: 'get'
  })
}

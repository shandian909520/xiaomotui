import request from '@/utils/request'

/**
 * 创建活动
 * @param {Object} data - 活动数据
 * @returns {Promise}
 */
export function createCampaign(data) {
  return request.post('/merchant/promo/campaigns', data)
}

/**
 * 获取活动列表
 * @param {Object} params - 查询参数
 * @returns {Promise}
 */
export function getCampaignList(params) {
  return request.get('/merchant/promo/campaigns', { params })
}

/**
 * 获取活动详情
 * @param {number|string} id - 活动ID
 * @returns {Promise}
 */
export function getCampaignDetail(id) {
  return request.get(`/merchant/promo/campaigns/${id}`)
}

/**
 * 更新活动
 * @param {number|string} id - 活动ID
 * @param {Object} data - 活动数据
 * @returns {Promise}
 */
export function updateCampaign(id, data) {
  return request.put(`/merchant/promo/campaigns/${id}`, data)
}

/**
 * 删除活动
 * @param {number|string} id - 活动ID
 * @returns {Promise}
 */
export function deleteCampaign(id) {
  return request.delete(`/merchant/promo/campaigns/${id}`)
}

/**
 * 绑定设备
 * @param {number|string} id - 活动ID
 * @param {Array} deviceIds - 设备ID数组
 * @returns {Promise}
 */
export function bindDevices(id, deviceIds) {
  return request.post(`/merchant/promo/campaigns/${id}/devices`, { device_ids: deviceIds })
}

/**
 * 解绑设备
 * @param {number|string} id - 活动ID
 * @param {number|string} deviceId - 设备ID
 * @returns {Promise}
 */
export function unbindDevice(id, deviceId) {
  return request.delete(`/merchant/promo/campaigns/${id}/devices/${deviceId}`)
}

/**
 * 获取活动统计
 * @param {number|string} id - 活动ID
 * @returns {Promise}
 */
export function getCampaignStats(id) {
  return request.get(`/merchant/promo/campaigns/${id}/stats`)
}

/**
 * 获取活动分发记录
 * @param {number|string} id - 活动ID
 * @param {Object} params - 查询参数
 * @returns {Promise}
 */
export function getCampaignDistributions(id, params) {
  return request.get(`/merchant/promo/campaigns/${id}/distributions`, { params })
}

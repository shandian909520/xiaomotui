import request from '@/utils/request'

// 获取设备列表
export function getDeviceList(params) {
  return request({
    url: '/merchant/device/list',
    method: 'get',
    params
  })
}

// 获取设备详情
export function getDeviceDetail(id) {
  return request({
    url: `/merchant/device/${id}`,
    method: 'get'
  })
}

// 创建设备
export function createDevice(data) {
  return request({
    url: '/merchant/device/create',
    method: 'post',
    data
  })
}

// 更新设备
export function updateDevice(data) {
  return request({
    url: `/merchant/device/${data.id}/update`,
    method: 'put',
    data
  })
}

// 删除设备
export function deleteDevice(id) {
  return request({
    url: `/merchant/device/${id}/delete`,
    method: 'delete'
  })
}

// 更新设备状态
export function updateDeviceStatus(id, status) {
  return request({
    url: `/merchant/device/${id}/status`,
    method: 'put',
    data: { status }
  })
}

// 更新设备配置
export function updateDeviceConfig(id, data) {
  return request({
    url: `/merchant/device/${id}/config`,
    method: 'put',
    data
  })
}

// 绑定设备
export function bindDevice(id, merchantId) {
  return request({
    url: `/merchant/device/${id}/bind`,
    method: 'post',
    data: { merchant_id: merchantId }
  })
}

// 解绑设备
export function unbindDevice(id) {
  return request({
    url: `/merchant/device/${id}/unbind`,
    method: 'post'
  })
}

// 批量更新
export function batchUpdateDevice(data) {
  return request({
    url: '/merchant/device/batch/update',
    method: 'post',
    data
  })
}

// 批量启用
export function batchEnableDevice(ids) {
  return request({
    url: '/merchant/device/batch/enable',
    method: 'post',
    data: { ids }
  })
}

// 批量禁用
export function batchDisableDevice(ids) {
  return request({
    url: '/merchant/device/batch/disable',
    method: 'post',
    data: { ids }
  })
}

// 获取告警列表
export function getAlerts(params) {
  return request({
    url: '/merchant/device/alerts',
    method: 'get',
    params
  })
}

// 解决告警
export function resolveAlert(id) {
  return request({
    url: `/merchant/device/alerts/${id}/resolve`,
    method: 'put'
  })
}

// 忽略告警
export function ignoreAlert(id) {
  return request({
    url: `/merchant/device/alerts/${id}/ignore`,
    method: 'put'
  })
}

// 批量解决告警
export function batchResolveAlerts(ids) {
  return request({
    url: '/merchant/device/alerts/batch/resolve',
    method: 'post',
    data: { ids }
  })
}

// 批量忽略告警
export function batchIgnoreAlerts(ids) {
  return request({
    url: '/merchant/device/alerts/batch/ignore',
    method: 'post',
    data: { ids }
  })
}

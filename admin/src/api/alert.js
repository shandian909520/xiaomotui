import request from '@/utils/request'

export function getAlertList(params) {
  return request({
    url: '/merchant/alerts',
    method: 'get',
    params
  })
}

export function getAlertDetail(id) {
  return request({
    url: `/merchant/alerts/${id}`,
    method: 'get'
  })
}

export function acknowledgeAlert(id, data) {
  return request({
    url: `/merchant/alerts/${id}/acknowledge`,
    method: 'put',
    data
  })
}

export function resolveAlert(id, data) {
  return request({
    url: `/merchant/alerts/${id}/resolve`,
    method: 'put',
    data
  })
}

export function ignoreAlert(id, data) {
  return request({
    url: `/merchant/alerts/${id}/ignore`,
    method: 'put',
    data
  })
}

export function batchActionAlerts(data) {
  return request({
    url: `/merchant/alerts/batch/${data.action}`,
    method: 'post',
    data
  })
}

export function getAlertStats(params) {
  return request({
    url: '/merchant/alerts/stats',
    method: 'get',
    params
  })
}

export function checkDeviceAlerts(params) {
  return request({
    url: '/merchant/alerts/check',
    method: 'post',
    data: params
  })
}

export function getAlertRules(params) {
  return request({
    url: '/merchant/alerts/rules',
    method: 'get',
    params
  })
}

export function updateAlertRule(data) {
  return request({
    url: '/merchant/alerts/rules',
    method: 'put',
    data
  })
}

export function batchUpdateAlertRules(data) {
  return request({
    url: '/merchant/alerts/rules/batch',
    method: 'put',
    data
  })
}

export function resetAlertRule(data) {
  return request({
    url: '/merchant/alerts/rules/reset',
    method: 'post',
    data
  })
}

export function getAlertRuleTemplates() {
  return request({
    url: '/merchant/alerts/rules/templates',
    method: 'get'
  })
}

export function applyAlertRuleTemplate(data) {
  return request({
    url: '/merchant/alerts/rules/templates/apply',
    method: 'post',
    data
  })
}

export function getNotifications(params) {
  return request({
    url: '/merchant/alerts/notifications',
    method: 'get',
    params
  })
}

export function markNotificationRead(data) {
  return request({
    url: '/merchant/alerts/notifications/read',
    method: 'put',
    data
  })
}

export function clearReadNotifications(data) {
  return request({
    url: '/merchant/alerts/notifications/clear',
    method: 'post',
    data
  })
}

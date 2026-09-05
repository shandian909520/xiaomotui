import request from '@/utils/request'

export function getPublishTasks(params) {
  return request.get('/publish/tasks', { params })
}

export function createPublishTask(data) {
  return request.post('/publish/create', data)
}

export function getPublishTaskDetail(id) {
  return request.get(`/publish/task/${id}`)
}

export function retryPublishTask(id) {
  return request.post(`/publish/task/${id}/retry`)
}

export function updateScheduledTask(id, data) {
  return request.put(`/publish/task/${id}/schedule`, data)
}

export function cancelPublishTask(id) {
  return request.post(`/publish/task/${id}/cancel`)
}

export function getPlatformAccounts(params) {
  return request.get('/publish/accounts', { params })
}

export function deletePlatformAccount(id) {
  return request.delete(`/publish/account/${id}`)
}

export function refreshAccountToken(id) {
  return request.post(`/publish/account/${id}/refresh`)
}

export function getPlatformAuthUrl(platform) {
  return request.get(`/publish/oauth/url/${platform}`)
}

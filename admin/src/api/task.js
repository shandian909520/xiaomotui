import request from '@/utils/request'

/**
 * 碰一碰任务引擎 API
 * 后端契约：/api/task/...（request.js baseURL 已含 /api 前缀）
 */

// 任务包列表
export function getBundleList(params) {
  return request.get('/task/bundle/list', { params })
}

// 任务包详情（含动作列表）
export function getBundleDetail(id) {
  return request.get(`/task/bundle/${id}`)
}

// 创建任务包（actions 一次提交）
export function createBundle(data) {
  return request.post('/task/bundle/create', data)
}

// 更新任务包（全量覆盖 actions）
export function updateBundle(id, data) {
  return request.put(`/task/bundle/${id}/update`, data)
}

// 删除任务包
export function deleteBundle(id) {
  return request.delete(`/task/bundle/${id}/delete`)
}

// 全部插件元信息
export function getPluginList() {
  return request.get('/task/bundle/plugins')
}

// 凭证审核队列
export function getProofList(params) {
  return request.get('/task/proof/list', { params })
}

// 凭证审核 {result: 'approved'|'rejected', remark}
export function auditProof(id, data) {
  return request.post(`/task/proof/${id}/audit`, data)
}

// 用户任务实例列表
export function getInstanceList(params) {
  return request.get('/task/instance/list', { params })
}

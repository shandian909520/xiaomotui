import request from '@/utils/request'

// 获取视频剪辑任务列表
export function getVideoTasks(params) {
  return request.get('/admin/video/tasks', { params })
}

// 创建视频剪辑任务
export function createVideoTask(data) {
  return request.post('/admin/video/tasks', data)
}

// 获取视频剪辑任务详情
export function getVideoTaskDetail(id) {
  return request.get(`/admin/video/tasks/${id}`)
}

// 重新生成视频任务
export function retryVideoTask(id) {
  return request.post(`/admin/video/tasks/${id}/retry`)
}

// 获取门店列表
export function getStoreList(params) {
  return request.get('/admin/stores', { params })
}

// 获取素材列表
export function getMaterialList(params) {
  return request.get('/admin/materials', { params })
}

// 获取模板列表
export function getTemplateList(params) {
  return request.get('/admin/templates', { params })
}

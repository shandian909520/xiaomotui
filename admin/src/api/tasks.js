import request from '@/utils/request'

// 获取任务列表
export function getTasks(params) {
  return request.get('/admin/tasks', { params })
}

// 重试任务
export function retryTask(id) {
  return request.post(`/admin/tasks/${id}/retry`)
}

// 获取任务详情
export function getTaskDetail(id) {
  return request.get(`/admin/tasks/${id}`)
}

// 取消任务
export function cancelTask(id) {
  return request.post(`/admin/tasks/${id}/cancel`)
}
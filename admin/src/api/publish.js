import request from '@/utils/request'

// 获取发布记录列表
export function getPublishTasks(params) {
  return request({
    url: '/publish/tasks',
    method: 'get',
    params
  })
}

// 创建发布任务
export function createPublishTask(data) {
  return request({
    url: '/publish/create',
    method: 'post',
    data
  })
}

// 取消发布任务
export function cancelPublishTask(id) {
  return request({
    url: `/publish/${id}/cancel`,
    method: 'post'
  })
}

// 获取任务详情
export function getTaskDetail(id) {
  return request({
    url: `/publish/${id}`,
    method: 'get'
  })
}

import request from '@/utils/request'

// 获取模板列表
export function getTemplateList(params) {
  return request({
    url: '/template/list',
    method: 'get',
    params
  })
}

// 创建模板
export function createTemplate(data) {
  return request({
    url: '/template/create',
    method: 'post',
    data
  })
}

// 更新模板
export function updateTemplate(data) {
  return request({
    url: `/template/update/${data.id}`,
    method: 'post',
    data
  })
}

// 删除模板
export function deleteTemplate(id) {
  return request({
    url: `/template/delete/${id}`,
    method: 'post'
  })
}

// 获取模板详情
export function getTemplateDetail(id) {
  return request({
    url: `/template/detail/${id}`,
    method: 'get'
  })
}

// 获取任务列表
export function getTaskList(params) {
  return request({
    url: '/content/tasks', // 需确认后端路由，目前假设为 content/tasks 或 publish/tasks
    method: 'get',
    params
  })
}

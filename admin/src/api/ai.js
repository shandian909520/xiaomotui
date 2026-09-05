// AI实验室接口
import request from '@/utils/request'

// 获取智能员工列表
export const getStaffList = (params) => {
  return request.get('/admin/ai/staff', { params })
}

// 获取员工详情
export const getStaffDetail = (id) => {
  return request.get(`/admin/ai/staff/${id}`)
}

// 分配任务给员工
export const assignTask = (id, data) => {
  return request.post(`/admin/ai/staff/${id}/assign`, data)
}

// 获取员工能力说明
export const getStaffAbilities = (id) => {
  return request.get(`/admin/ai/staff/${id}/abilities`)
}

// 生成文案
export const generateContent = (data) => {
  return request.post('/admin/ai/generate', data)
}

// 获取AI配置
export const getAiConfig = () => {
  return request.get('/admin/ai/config')
}

// 更新AI配置
export const updateAiConfig = (data) => {
  return request.put('/admin/ai/config', data)
}

// 测试AI连接
export const testAiConnection = (provider) => {
  return request.post('/admin/ai/test', { provider })
}

// 获取可用模型列表
export const getAiModels = () => {
  return request.get('/admin/ai/models')
}

export default {
  getStaffList,
  getStaffDetail,
  assignTask,
  getStaffAbilities,
  generateContent,
  getAiConfig,
  updateAiConfig,
  testAiConnection,
  getAiModels
}

// 活动管理相关接口
import request from '@/utils/request'

// 场景配置相关接口
export const scenesApi = {
  // 获取场景列表
  getScenes(params) {
    return request.get('/admin/activity/scenes', { params })
  },

  // 创建场景
  createScene(data) {
    return request.post('/admin/activity/scenes', data)
  },

  // 更新场景
  updateScene(id, data) {
    return request.put(`/admin/activity/scenes/${id}`, data)
  },

  // 删除场景
  deleteScene(id) {
    return request.delete(`/admin/activity/scenes/${id}`)
  },

  // 启用/禁用场景
  toggleScene(id, enabled) {
    return request.put(`/admin/activity/scenes/${id}/toggle`, { enabled })
  }
}

// 红接口相关接口
export const redpacketsApi = {
  // 获取红包列表
  getRedpackets(params) {
    return request.get('/admin/activity/redpackets', { params })
  },

  // 获取红包余额
  getBalance() {
    return request.get('/admin/activity/redpackets/balance')
  },

  // 发送红包
  sendRedpacket(data) {
    return request.post('/admin/activity/redpackets/send', data)
  },

  // 获取红包规则
  getRules() {
    return request.get('/admin/activity/redpackets/rules')
  },

  // 设置红包规则
  setRules(data) {
    return request.post('/admin/activity/redpackets/rules', data)
  }
}

export default {
  scenesApi,
  redpacketsApi
}

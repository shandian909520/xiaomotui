// 数据监控相关接口
import request from '@/utils/request'

// 话题监控相关接口
export const topicsApi = {
  // 获取话题列表
  getTopics(params) {
    return request.get('/admin/monitor/topics', { params })
  },

  // 获取话题详情
  getTopicDetail(id) {
    return request.get(`/admin/monitor/topics/${id}`)
  },

  // 获取话题热度趋势
  getTopicTrend(id, params) {
    return request.get(`/admin/monitor/topics/${id}/trend`, { params })
  },

  // 导出话题数据
  exportTopic(id, params) {
    return request.get(`/admin/monitor/topics/${id}/export`, { params })
  },

  // 获取平台列表
  getPlatforms() {
    return request.get('/admin/monitor/platforms')
  }
}

export default {
  topicsApi
}

import request from '@/utils/request'

// 漏斗埋点(Agent E) - 商家后台
// 4 卡片漏斗(NFC / H5 / 任务完成 / 加粉) + 按设备聚合 + 按日聚合
export const funnelApi = {
  // 商家级 4 卡片漏斗(给 dashboard 用)
  getMerchantFunnel(params) {
    return request({
      url: '/funnel/merchant',
      method: 'get',
      params
    })
  },

  // 按设备聚合漏斗(给设备详情/分析页用)
  getFunnel(params) {
    return request({
      url: '/funnel/funnel',
      method: 'get',
      params
    })
  },

  // 按设备按日统计
  getDailyStat(params) {
    return request({
      url: '/funnel/daily',
      method: 'get',
      params
    })
  }
}

export default funnelApi
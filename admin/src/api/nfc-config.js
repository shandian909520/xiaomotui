import request from '@/utils/request'

// NFC 设备配置(Agent E)
// 商家后台 3 tab 配置页: 任务配置 / Wi-Fi&二维码 / 私域配置
export const nfcConfigApi = {
  // 拉取设备完整配置(基础信息 + 3 tab 数据 + 今日触发数)
  getConfig(deviceId) {
    return request({
      url: `/admin/nfc/device/${deviceId}/config`,
      method: 'get'
    })
  },

  // 保存设备配置(支持部分 tab 更新)
  saveConfig(deviceId, payload) {
    return request({
      url: `/admin/nfc/device/${deviceId}/config`,
      method: 'put',
      data: payload
    })
  },

  // 拉取聚合页快照(给「任务配置」tab 用)
  getAggregation(deviceId) {
    return request({
      url: `/admin/nfc/device/${deviceId}/aggregation`,
      method: 'get'
    })
  }
}

export default nfcConfigApi
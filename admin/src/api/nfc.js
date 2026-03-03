import request from '@/utils/request'

// NFC触发记录API
export const nfcTriggerApi = {
  // 获取触发记录列表
  getTriggerRecords(params) {
    return request.get('/merchant/nfc/trigger-records', { params })
  },

  // 获取设备触发记录
  getDeviceTriggerRecords(deviceId, params) {
    return request.get(`/merchant/nfc/device/${deviceId}/records`, { params })
  },

  // 获取设备统计
  getDeviceStats(deviceId) {
    return request.get(`/merchant/nfc/device/${deviceId}/stats`)
  }
}

export default nfcTriggerApi

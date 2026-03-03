import request from '@/utils/request'

// 获取仪表盘统计数据
export function getDashboardStats() {
  return request({
    url: '/statistics/dashboard',
    method: 'get'
  })
}

// 获取设备统计 (如果需要单独展示)
export function getDeviceStatistics() {
  return request({
    url: '/nfc/device/status', // 复用设备状态接口
    method: 'get'
  })
}

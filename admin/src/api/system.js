import request from '@/utils/request'

// 系统管理API
export const systemApi = {
  // 用户管理
  getUsers(params) {
    return request.get('/admin/users', { params })
  },
  updateUserStatus(id, status) {
    return request.put(`/admin/users/${id}/status`, { status })
  },

  // 系统设置
  getSettings() {
    return request.get('/admin/settings')
  },
  updateSettings(data) {
    return request.put('/admin/settings', data)
  },

  // 操作日志
  getOperationLogs(params) {
    return request.get('/admin/operation-logs', { params })
  },
  exportOperationLogs(params) {
    return request({
      url: '/admin/operation-logs/export',
      method: 'get',
      params,
      responseType: 'blob'
    })
  }
}

// 券码用户领取API
export const couponUserApi = {
  getCouponUsage(couponId, params) {
    return request.get(`/merchant/coupon/${couponId}/usage`, { params })
  },
  getCouponList(params) {
    return request.get('/merchant/coupon/list', { params })
  }
}

export default { systemApi, couponUserApi }

// API接口统一管理
import request from '@/utils/request'

// 认证相关接口
export const authApi = {
  // 登录 (手机号登录)
  login(data) {
    return request.post('/auth/phone-login', data)
  },

  // 发送验证码
  sendCode(data) {
    return request.post('/auth/send-code', data)
  },

  // 退出登录
  logout() {
    return request.post('/auth/logout')
  },

  // 获取用户信息
  getUserInfo() {
    return request.get('/auth/userinfo')
  }
}

// NFC设备接口
export const nfcApi = {
  // 获取设备列表
  getDevices(params) {
    return request.get('/nfc/devices', { params })
  },

  // 获取设备详情
  getDevice(id) {
    return request.get(`/nfc/devices/${id}`)
  },

  // 创建设备
  createDevice(data) {
    return request.post('/nfc/devices', data)
  },

  // 更新设备
  updateDevice(id, data) {
    return request.put(`/nfc/devices/${id}`, data)
  },

  // 删除设备
  deleteDevice(id) {
    return request.delete(`/nfc/devices/${id}`)
  }
}

// 内容管理接口
export const contentApi = {
  // 获取任务列表
  getTasks(params) {
    return request.get('/content/tasks', { params })
  },

  // 生成内容
  generateContent(data) {
    return request.post('/content/generate', data)
  },

  // 获取模板列表
  getTemplates(params) {
    return request.get('/content/templates', { params })
  }
}

// 券码管理接口
export const couponApi = {
  // 获取券码列表
  getCoupons(params) {
    return request.get('/coupons', { params })
  },

  // 创建券码
  createCoupon(data) {
    return request.post('/coupons', data)
  },

  // 获取用户领取记录
  getUserCoupons(params) {
    return request.get('/coupons/users', { params })
  }
}

// 商户管理接口
export const merchantApi = {
  // 获取商户列表
  getMerchants(params) {
    return request.get('/merchants', { params })
  },

  // 创建商户
  createMerchant(data) {
    return request.post('/merchants', data)
  },

  // 更新商户
  updateMerchant(id, data) {
    return request.put(`/merchants/${id}`, data)
  }
}

// 统计接口
export const statsApi = {
  // 获取仪表盘统计
  getDashboard() {
    return request.get('/stats/dashboard')
  },

  // 获取趋势数据
  getTrends(params) {
    return request.get('/stats/trends', { params })
  }
}

export default {
  authApi,
  nfcApi,
  contentApi,
  couponApi,
  merchantApi,
  statsApi
}

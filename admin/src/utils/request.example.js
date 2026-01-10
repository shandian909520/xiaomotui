/**
 * request.js 使用示例
 * 
 * 这个文件展示了如何使用封装的axios请求模块
 */

import request, { setToken, getToken, removeToken, isAuthenticated } from './request'

// ============ 基本使用示例 ============

// 1. GET 请求
export function getUserInfo() {
  return request.get('/user/info')
}

// 2. GET 请求带参数
export function getUserList(params) {
  return request.get('/user/list', { params })
}

// 3. POST 请求
export function login(data) {
  return request.post('/auth/login', data)
}

// 4. PUT 请求
export function updateUser(id, data) {
  return request.put(`/user/${id}`, data)
}

// 5. DELETE 请求
export function deleteUser(id) {
  return request.delete(`/user/${id}`)
}

// ============ Token 管理示例 ============

// 登录成功后设置token
export async function handleLogin(username, password) {
  try {
    const res = await request.post('/auth/login', {
      username,
      password
    })
    
    // 登录成功，保存token
    if (res.token) {
      setToken(res.token)
      console.log('登录成功')
      return true
    }
  } catch (error) {
    console.error('登录失败:', error)
    return false
  }
}

// 退出登录
export function handleLogout() {
  removeToken()
  console.log('已退出登录')
}

// 检查登录状态
export function checkLoginStatus() {
  if (isAuthenticated()) {
    console.log('已登录，token:', getToken())
  } else {
    console.log('未登录')
  }
}

// ============ 实际API调用示例 ============

// 用户相关API
export const userApi = {
  // 获取用户信息
  getInfo() {
    return request.get('/user/info')
  },
  
  // 获取用户列表
  getList(params) {
    return request.get('/user/list', { params })
  },
  
  // 创建用户
  create(data) {
    return request.post('/user', data)
  },
  
  // 更新用户
  update(id, data) {
    return request.put(`/user/${id}`, data)
  },
  
  // 删除用户
  delete(id) {
    return request.delete(`/user/${id}`)
  }
}

// 商家相关API
export const merchantApi = {
  // 获取商家列表
  getList(params) {
    return request.get('/merchant/list', { params })
  },
  
  // 获取商家详情
  getDetail(id) {
    return request.get(`/merchant/${id}`)
  },
  
  // 创建商家
  create(data) {
    return request.post('/merchant', data)
  },
  
  // 更新商家
  update(id, data) {
    return request.put(`/merchant/${id}`, data)
  }
}

// NFC设备相关API
export const nfcApi = {
  // 获取设备列表
  getDevices(params) {
    return request.get('/nfc/devices', { params })
  },
  
  // 绑定设备
  bindDevice(data) {
    return request.post('/nfc/bind', data)
  },
  
  // 解绑设备
  unbindDevice(deviceId) {
    return request.post('/nfc/unbind', { device_id: deviceId })
  }
}

// ============ 在Vue组件中使用示例 ============

/**
 * 示例：在Vue 3 组件中使用
 * 
 * <script setup>
 * import { ref, onMounted } from 'vue'
 * import { userApi } from '@/utils/request.example'
 * 
 * const userList = ref([])
 * const loading = ref(false)
 * 
 * // 获取用户列表
 * async function fetchUserList() {
 *   loading.value = true
 *   try {
 *     const data = await userApi.getList({ page: 1, pageSize: 10 })
 *     userList.value = data.list
 *   } catch (error) {
 *     console.error('获取用户列表失败:', error)
 *   } finally {
 *     loading.value = false
 *   }
 * }
 * 
 * onMounted(() => {
 *   fetchUserList()
 * })
 * </script>
 */

// ============ 错误处理示例 ============

export async function fetchDataWithErrorHandling() {
  try {
    const data = await request.get('/api/data')
    console.log('数据:', data)
    return data
  } catch (error) {
    // 错误已经在axios拦截器中处理并显示提示
    // 这里可以做额外的处理
    console.error('请求失败:', error)
    return null
  }
}

// ============ 文件上传示例 ============

export function uploadFile(file) {
  const formData = new FormData()
  formData.append('file', file)
  
  return request.post('/upload', formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

// ============ 下载文件示例 ============

export function downloadFile(url, filename) {
  return request.get(url, {
    responseType: 'blob'
  }).then(blob => {
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = filename
    link.click()
    URL.revokeObjectURL(link.href)
  })
}

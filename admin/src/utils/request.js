import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

// Token存储的key
const TOKEN_KEY = 'token'

// 创建axios实例
const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:37080/api',
  timeout: 30000, // 请求超时时间 30秒
  headers: {
    'Content-Type': 'application/json;charset=UTF-8'
  }
})

/**
 * 获取Token
 * @returns {string|null} JWT Token
 */
function getTokenInternal() {
  return localStorage.getItem(TOKEN_KEY)
}

// 请求拦截器
service.interceptors.request.use(
  config => {
    // 自动添加token到请求头
    const token = getTokenInternal()
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`
    }

    // 添加时间戳防止缓存（GET 请求）
    if (config.method === 'get') {
      config.params = {
        ...config.params,
        _t: Date.now()
      }
    }

    return config
  },
  error => {
    console.error('请求错误:', error)
    return Promise.reject(error)
  }
)

// 响应拦截器
service.interceptors.response.use(
  response => {
    const res = response.data

    // 如果返回的状态码不是200，则认为是错误
    if (res.code !== undefined && res.code !== 200) {
      ElMessage({
        message: res.msg || res.message || '请求失败',
        type: 'error',
        duration: 3000
      })

      // 401: Token过期或未登录
      if (res.code === 401) {
        ElMessage({
          message: '登录已过期，请重新登录',
          type: 'warning',
          duration: 2000
        })
        // 清除token并跳转到登录页
        removeToken()
        router.push('/login')
      }

      // 403: 权限不足
      if (res.code === 403) {
        ElMessage.error('权限不足，无法访问')
      }

      // 404: 资源不存在
      if (res.code === 404) {
        ElMessage.error('请求的资源不存在')
      }

      // 500: 服务器错误
      if (res.code === 500) {
        ElMessage.error('服务器错误，请稍后重试')
      }

      return Promise.reject(new Error(res.msg || res.message || '请求失败'))
    } else {
      // 成功响应，返回data字段（如果存在）
      return res.data !== undefined ? res.data : res
    }
  },
  error => {
    console.error('响应错误:', error)

    let message = '网络请求失败'

    if (error.response) {
      // 服务器返回了错误状态码
      const status = error.response.status
      
      switch (status) {
        case 400:
          message = '请求参数错误'
          break
        case 401:
          message = '未授权，请重新登录'
          removeToken()
          router.push('/login')
          break
        case 403:
          message = '权限不足，无法访问'
          break
        case 404:
          message = '请求的资源不存在'
          break
        case 500:
          message = '服务器内部错误'
          break
        case 502:
          message = '网关错误'
          break
        case 503:
          message = '服务不可用'
          break
        case 504:
          message = '网关超时'
          break
        default:
          message = error.response.data?.msg || error.response.data?.message || `请求失败 (${status})`
      }
    } else if (error.request) {
      // 请求已发出，但没有收到响应
      if (error.code === 'ECONNABORTED') {
        message = '请求超时，请稍后重试'
      } else if (error.message === 'Network Error') {
        message = '网络连接失败，请检查网络'
      } else {
        message = '无法连接到服务器'
      }
    } else if (axios.isCancel(error)) {
      // 请求被取消
      console.log('请求已取消:', error.message)
      return Promise.reject(error)
    } else {
      // 其他错误
      message = error.message || '未知错误'
    }

    ElMessage({
      message: message,
      type: 'error',
      duration: 3000
    })

    return Promise.reject(error)
  }
)

/**
 * 设置Token
 * @param {string} token - JWT Token
 */
export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  }
}

/**
 * 获取Token
 * @returns {string|null} JWT Token
 */
export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

/**
 * 删除Token
 */
export function removeToken() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem('user')
}

/**
 * 检查是否已登录
 * @returns {boolean}
 */
export function isAuthenticated() {
  return !!getToken()
}

// 导出axios实例
export default service

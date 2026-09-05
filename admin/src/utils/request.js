import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

// Token存储的key
// ⚠️ 安全提示: localStorage容易受到XSS攻击
// 生产环境建议:
// 1. 使用httpOnly Cookie存储Token(需要后端配合)
// 2. 或使用sessionStorage(会话级别,关闭浏览器后自动清除)
// 3. 确保所有用户输入都经过XSS过滤
// 4. 配置Content-Security-Policy响应头
const TOKEN_KEY = 'token'
const USE_SESSION_STORAGE = false // 设置为true使用sessionStorage

// 创建axios实例
const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 30000, // 请求超时时间 30秒
  headers: {
    'Content-Type': 'application/json;charset=UTF-8'
  }
})

/**
 * 获取存储对象(根据配置选择localStorage或sessionStorage)
 * @returns {Storage}
 */
function getStorage() {
  return USE_SESSION_STORAGE ? sessionStorage : localStorage
}

/**
 * 获取Token
 * @returns {string|null} JWT Token
 */
function getTokenInternal() {
  return getStorage().getItem(TOKEN_KEY)
}

/**
 * 检查字符串是否只包含 ISO-8859-1 合法字符
 * XMLHttpRequest setRequestHeader 只接受 ISO-8859-1 编码范围内的字符
 * @param {string} str
 * @returns {boolean}
 */
function isIso88591(str) {
  if (typeof str !== 'string') return false
  for (let i = 0; i < str.length; i++) {
    const code = str.charCodeAt(i)
    // ISO-8859-1 允许 0x00-0xFF，但 HTTP header 值实践中应避免控制字符 (0x00-0x1F, 0x7F)
    if (code > 0xff) return false
  }
  return true
}

/**
 * 清理 header 值：移除或编码非 ISO-8859-1 字符
 * @param {string} value
 * @returns {string}
 */
function sanitizeHeaderValue(value) {
  if (typeof value !== 'string') return String(value)
  if (isIso88591(value)) return value
  // 包含非 ASCII 字符（如中文），进行 encodeURIComponent 编码
  return encodeURIComponent(value)
}

/**
 * 验证 token 是否为合法的 JWT 格式（只包含 ASCII 字符）
 * JWT 标准格式: header.payload.signature（Base64URL 编码，仅含 A-Z a-z 0-9 - _ .）
 * @param {string} token
 * @returns {boolean}
 */
function isValidJwtToken(token) {
  if (typeof token !== 'string' || !token) return false
  // JWT 由三段 Base64URL 编码的字符串用 . 连接
  // Base64URL 只使用: A-Z a-z 0-9 - _ .
  return /^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/.test(token)
}

// 请求拦截器
service.interceptors.request.use(
  config => {
    // 自动添加token到请求头
    const token = getTokenInternal()
    if (token) {
      // 验证 token 格式，防止非 JWT 值（如中文错误消息）被设置到 header
      if (isValidJwtToken(token)) {
        config.headers['Authorization'] = `Bearer ${token}`
      } else {
        // token 格式异常，清除无效 token 并跳转登录
        console.warn('[Request] 检测到无效的 token 格式，已清除。Token 值:', token.substring(0, 20) + '...')
        removeToken()
        router.push('/login')
        return Promise.reject(new Error('Token 格式无效，请重新登录'))
      }
    }

    // 防御性检查：确保所有自定义 header 值只包含 ISO-8859-1 合法字符
    if (config.headers) {
      const safeHeaders = ['Content-Type', 'Accept', 'Authorization', 'Cache-Control', 'Pragma']
      Object.keys(config.headers).forEach(key => {
        const val = config.headers[key]
        if (typeof val === 'string' && val.length > 0 && !safeHeaders.includes(key)) {
          // 对自定义 header 值进行安全编码
          config.headers[key] = sanitizeHeaderValue(val)
        }
      })
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

// 真正属于"token 失效需要重新登录"的后端错误码
const TOKEN_INVALID_ERRORS = [
  'token_missing',
  'token_invalid',
  'token_expired',
  'unauthorized',
  'token_refresh_failed'
]

function isTokenInvalid(res) {
  if (!res) return false
  const err = res.error || res.errcode || ''
  if (TOKEN_INVALID_ERRORS.includes(err)) return true
  // 没带 error 字段时，把 401 也视作 token 失效（保守兜底）
  return res.code === 401 && !err
}

// 响应拦截器
service.interceptors.response.use(
  response => {
    const res = response.data

    if (res.code !== undefined && res.code !== 200) {
      // token 真的失效才清 token 跳登录
      if (isTokenInvalid(res)) {
        const currentPath = router.currentRoute.value?.path
        if (currentPath !== '/login') {
          removeToken()
          router.push('/login')
          ElMessage({ message: '登录已过期，请重新登录', type: 'warning', duration: 2000 })
        }
        return Promise.reject(new Error('未授权'))
      }

      // 业务错误（包含 merchant_auth_required 等），静默拒绝，由调用方处理
      console.warn('[API]', res.code, res.error || '', res.msg || res.message, response.config?.url)
      return Promise.reject(new Error(res.msg || res.message || '请求失败'))
    }
    return res.data !== undefined ? res.data : res
  },
  error => {
    const isLoginRequest = error.config?.url?.includes('/auth/login')

    if (error.response) {
      const status = error.response.status
      const res = error.response.data

      if (status === 401 && !isLoginRequest) {
        // 只有真正 token 失效才跳登录；merchant_auth_required 等不动
        if (isTokenInvalid(res)) {
          const currentPath = router.currentRoute.value?.path
          if (currentPath !== '/login') {
            removeToken()
            router.push('/login')
            ElMessage({ message: res?.msg || res?.message || '登录已过期，请重新登录', type: 'warning', duration: 2000 })
          }
        } else {
          console.warn('[API] 401 (业务):', res?.error, res?.msg || res?.message, error.config?.url)
        }
        return Promise.reject(error)
      }

      if (status === 404 || status === 500 || status === 502 || status === 503 || status === 504) {
        console.warn(`[API] ${status}:`, error.config?.url)
        return Promise.reject(error)
      }

      ElMessage({ message: res?.msg || res?.message || `请求失败 (${status})`, type: 'error', duration: 3000 })
      return Promise.reject(error)
    }

    console.warn('[API] 网络错误:', error.message)
    return Promise.reject(error)
  }
)

/**
 * 设置Token
 * @param {string} token - JWT Token
 */
export function setToken(token) {
  // 只接受字符串类型的非空值，防止非法值（如对象、含非ASCII字符的字符串）被存储
  if (token && typeof token === 'string') {
    getStorage().setItem(TOKEN_KEY, token)
  } else {
    console.warn('[setToken] 忽略非法 token 值:', typeof token)
  }
}

/**
 * 获取Token
 * @returns {string|null} JWT Token
 */
export function getToken() {
  return getStorage().getItem(TOKEN_KEY)
}

/**
 * 删除Token
 */
export function removeToken() {
  const storage = getStorage()
  storage.removeItem(TOKEN_KEY)
  storage.removeItem('user')
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

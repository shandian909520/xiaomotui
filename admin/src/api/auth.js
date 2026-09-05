import request from '@/utils/request'

/**
 * 管理员登录
 * @param {Object} data - 登录信息
 * @param {string} data.username - 用户名/手机号
 * @param {string} data.password - 密码
 * @param {string} data.code - 验证码(可选)
 * @returns {Promise}
 */
export function login(data) {
  return request({
    url: '/auth/login',
    method: 'post',
    data
  })
}

/**
 * 获取用户信息
 * @returns {Promise}
 */
export function getUserInfo() {
  return request({
    url: '/auth/userinfo',
    method: 'get'
  })
}

/**
 * 退出登录
 * @returns {Promise}
 */
export function logout() {
  return request({
    url: '/auth/logout',
    method: 'post'
  })
}

/**
 * 刷新Token
 * @returns {Promise}
 */
export function refreshToken() {
  return request({
    url: '/auth/refresh',
    method: 'post'
  })
}

/**
 * 获取当前用户权限列表
 * @returns {Promise}
 */
export function getUserPermissions() {
  return request({
    url: '/auth/permissions',
    method: 'get'
  })
}

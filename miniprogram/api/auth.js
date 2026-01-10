const request = require('../utils/request');

/**
 * 微信登录
 */
function login(code) {
  return request.post('/api/auth/login', { code });
}

/**
 * 刷新token
 */
function refreshToken() {
  return request.post('/api/auth/refresh');
}

/**
 * 退出登录
 */
function logout() {
  return request.post('/api/auth/logout');
}

module.exports = {
  login,
  refreshToken,
  logout
};

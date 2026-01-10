const request = require('../utils/request');

/**
 * 获取用户信息
 */
function getUserInfo() {
  return request.get('/api/user/info');
}

/**
 * 更新用户信息
 * @param {object} data - 用户信息
 */
function updateUserInfo(data) {
  return request.post('/api/user/update', data);
}

/**
 * 获取用户统计数据
 */
function getUserStats() {
  return request.get('/api/user/stats');
}

module.exports = {
  getUserInfo,
  updateUserInfo,
  getUserStats
};

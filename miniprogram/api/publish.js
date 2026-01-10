const request = require('../utils/request');

/**
 * 发布内容
 * @param {object} data - 发布参数
 */
function publishContent(data) {
  return request.post('/api/publish/create', data);
}

/**
 * 获取发布任务列表
 * @param {object} params - 查询参数
 */
function getPublishList(params) {
  return request.get('/api/publish/list', params);
}

/**
 * 获取发布任务详情
 * @param {string} taskId - 任务ID
 */
function getPublishDetail(taskId) {
  return request.get('/api/publish/detail', { task_id: taskId });
}

/**
 * 取消发布任务
 * @param {string} taskId - 任务ID
 */
function cancelPublish(taskId) {
  return request.post('/api/publish/cancel', { task_id: taskId });
}

/**
 * 获取平台账号列表
 */
function getPlatformAccounts() {
  return request.get('/api/publish/accounts');
}

/**
 * 授权平台账号
 * @param {object} data - 授权参数
 */
function authorizePlatform(data) {
  return request.post('/api/publish/authorize', data);
}

module.exports = {
  publishContent,
  getPublishList,
  getPublishDetail,
  cancelPublish,
  getPlatformAccounts,
  authorizePlatform
};

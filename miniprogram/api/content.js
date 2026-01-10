const request = require('../utils/request');

/**
 * 生成内容
 * @param {object} data - 生成参数
 */
function generateContent(data) {
  return request.post('/api/content/generate', data);
}

/**
 * 获取内容详情
 * @param {string} taskId - 任务ID
 */
function getContentDetail(taskId) {
  return request.get('/api/content/detail', { task_id: taskId });
}

/**
 * 获取内容列表
 * @param {object} params - 查询参数
 */
function getContentList(params) {
  return request.get('/api/content/list', params);
}

/**
 * 获取内容模板列表
 */
function getTemplateList() {
  return request.get('/api/content/templates');
}

/**
 * 获取任务状态
 * @param {string} taskId - 任务ID
 */
function getTaskStatus(taskId) {
  return request.get('/api/content/status', { task_id: taskId });
}

module.exports = {
  generateContent,
  getContentDetail,
  getContentList,
  getTemplateList,
  getTaskStatus
};

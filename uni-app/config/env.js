/**
 * 环境配置 - STAGING
 * 此文件由 env-config.js 自动生成，请勿手动修改
 * 生成时间: 2025/10/2 12:05:35
 */

export default {
  // 环境标识
  env: 'staging',

  // API配置
  apiBaseUrl: 'https://staging-api.xiaomotui.com',
  uploadUrl: 'https://staging-api.xiaomotui.com/api/upload',
  wsUrl: 'wss://staging-api.xiaomotui.com',

  // H5域名
  h5Domain: 'https://staging.xiaomotui.com',

  // 调试配置
  enableDebug: true,
  enableVConsole: false,

  // 超时配置
  timeout: 30000,
  uploadTimeout: 120000,

  // 缓存配置
  cacheExpire: 3600000, // 1小时

  // 分页配置
  pageSize: 20,
  maxPageSize: 100
};

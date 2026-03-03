/**
 * 环境配置 - DEVELOPMENT
 * 此文件由 env-config.js 自动生成，请勿手动修改
 * 生成时间: 2026/2/7 15:55:27
 */

export default {
  // 环境标识
  env: 'development',

  // API配置
  apiBaseUrl: 'http://localhost:8080',
  uploadUrl: 'http://localhost:8080/api/upload',
  wsUrl: 'ws://localhost:8080',

  // H5域名
  h5Domain: 'http://localhost:8081',

  // 调试配置
  enableDebug: true,
  enableVConsole: true,

  // 超时配置
  timeout: 30000,
  uploadTimeout: 120000,

  // 缓存配置
  cacheExpire: 3600000, // 1小时

  // 分页配置
  pageSize: 20,
  maxPageSize: 100
};

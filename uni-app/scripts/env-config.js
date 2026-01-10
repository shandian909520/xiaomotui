/**
 * 环境配置脚本
 * 用于在构建前切换不同环境的配置
 */

const fs = require('fs');
const path = require('path');

// 环境配置
const envConfigs = {
  development: {
    apiBaseUrl: 'http://localhost:37080',
    h5Domain: 'http://localhost:37075',
    uploadUrl: 'http://localhost:37080/api/upload',
    wsUrl: 'ws://localhost:37080',
    enableDebug: true,
    enableVConsole: true
  },
  staging: {
    apiBaseUrl: 'https://staging-api.xiaomotui.com',
    h5Domain: 'https://staging.xiaomotui.com',
    uploadUrl: 'https://staging-api.xiaomotui.com/api/upload',
    wsUrl: 'wss://staging-api.xiaomotui.com',
    enableDebug: true,
    enableVConsole: false
  },
  production: {
    apiBaseUrl: 'https://api.xiaomotui.com',
    h5Domain: 'https://h5.xiaomotui.com',
    uploadUrl: 'https://api.xiaomotui.com/api/upload',
    wsUrl: 'wss://api.xiaomotui.com',
    enableDebug: false,
    enableVConsole: false
  }
};

// 获取命令行参数
const args = process.argv.slice(2);
const env = args[0] || 'development';

if (!envConfigs[env]) {
  console.error(`❌ 无效的环境: ${env}`);
  console.log('可用环境: development, staging, production');
  process.exit(1);
}

// 配置文件路径
const configPath = path.join(__dirname, '../config/env.js');
const config = envConfigs[env];

// 生成配置文件内容
const configContent = `/**
 * 环境配置 - ${env.toUpperCase()}
 * 此文件由 env-config.js 自动生成，请勿手动修改
 * 生成时间: ${new Date().toLocaleString('zh-CN')}
 */

export default {
  // 环境标识
  env: '${env}',

  // API配置
  apiBaseUrl: '${config.apiBaseUrl}',
  uploadUrl: '${config.uploadUrl}',
  wsUrl: '${config.wsUrl}',

  // H5域名
  h5Domain: '${config.h5Domain}',

  // 调试配置
  enableDebug: ${config.enableDebug},
  enableVConsole: ${config.enableVConsole},

  // 超时配置
  timeout: 30000,
  uploadTimeout: 120000,

  // 缓存配置
  cacheExpire: 3600000, // 1小时

  // 分页配置
  pageSize: 20,
  maxPageSize: 100
};
`;

try {
  // 确保config目录存在
  const configDir = path.dirname(configPath);
  if (!fs.existsSync(configDir)) {
    fs.mkdirSync(configDir, { recursive: true });
  }

  // 写入配置文件
  fs.writeFileSync(configPath, configContent, 'utf-8');

  console.log('✅ 环境配置生成成功!');
  console.log(`📝 环境: ${env.toUpperCase()}`);
  console.log(`📍 配置文件: ${configPath}`);
  console.log(`🌐 API地址: ${config.apiBaseUrl}`);

} catch (error) {
  console.error('❌ 配置文件生成失败:', error.message);
  process.exit(1);
}

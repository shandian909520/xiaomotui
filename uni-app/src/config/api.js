/**
 * API配置文件
 * 统一管理API相关配置，支持多环境切换
 */

// 环境检测
const env = process.env.NODE_ENV || 'development'

// API基础地址配置
const baseUrls = {
	development: '',  // 开发环境通过vite代理转发
	testing: 'http://test.xiaomotui.com',  // 测试环境
	production: 'http://47.113.226.37:8080' // 生产环境
}

// 请求配置
const config = {
	// API基础地址
	baseUrl: baseUrls[env],

	// 请求超时时间（毫秒）
	timeout: 30000,

	// 是否显示loading提示
	showLoading: true,

	// loading提示文本
	loadingText: '加载中...',

	// 请求失败是否自动重试
	enableRetry: true,

	// 请求重试次数
	retryCount: 2,

	// 重试延迟（毫秒）
	retryDelay: 1000,

	// Token存储的key
	tokenKey: 'xiaomotui_token',

	// Token过期时是否自动刷新
	autoRefreshToken: true,

	// 请求头配置
	headers: {
		'Content-Type': 'application/json'
	},

	// 响应成功的状态码
	successCode: 200,

	// Token过期的状态码
	tokenExpiredCode: 401,

	// 需要登录的状态码
	needLoginCode: 403,

	// 是否打印请求日志（开发环境）
	showLog: env === 'development'
}

export default config

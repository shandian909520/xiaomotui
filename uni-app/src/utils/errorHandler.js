/**
 * 全局错误处理器
 * 将技术错误转换为用户友好的提示信息
 */
export default class ErrorHandler {
  /**
   * 处理错误
   * @param {Error|String} error - 错误对象或错误消息
   * @param {String} context - 错误发生的上下文
   * @param {Object} options - 额外配置
   */
  static handle(error, context = '', options = {}) {
    console.error(`[${context}] Error:`, error)

    // 获取友好的错误消息
    const friendlyMessage = this.getFriendlyMessage(error)
    const errorDetails = this.getErrorDetails(error)

    // 显示用户友好提示
    if (options.silent !== true) {
      this.showToast(friendlyMessage, errorDetails)
    }

    // 上报错误到监控平台
    if (options.report !== false) {
      this.reportError(error, context, errorDetails)
    }

    return {
      message: friendlyMessage,
      details: errorDetails,
      canRetry: this.canRetry(error)
    }
  }

  /**
   * 获取用户友好的错误消息
   */
  static getFriendlyMessage(error) {
    const errorString = this.getErrorString(error)

    // 错误消息映射表
    const errorMap = {
      // ========== 网络错误 ==========
      'Network Error': '网络连接失败，请检查网络设置',
      'timeout': '请求超时，请稍后重试',
      'Failed to fetch': '无法连接服务器，请检查网络',
      'ERR_NETWORK': '网络异常，请检查网络连接',
      'ERR_CONNECTION': '连接失败，请检查网络设置',

      // ========== 认证授权错误 ==========
      'Unauthorized': '登录已过期，请重新登录',
      '401': '登录已过期，请重新登录',
      'Forbidden': '您没有权限执行此操作',
      '403': '权限不足，无法执行此操作',
      'Token expired': '登录已过期，请重新登录',
      'Invalid token': '登录信息无效，请重新登录',

      // ========== 资源错误 ==========
      'Not Found': '请求的资源不存在',
      '404': '请求的资源不存在',
      '410': '该资源已被删除',

      // ========== 请求错误 ==========
      'Bad Request': '请求参数错误，请检查输入',
      '400': '请求参数错误',
      'Invalid input': '输入数据格式不正确',
      'Validation failed': '数据验证失败，请检查输入',
      'Missing required': '缺少必填字段',

      // ========== 服务器错误 ==========
      'Internal Server Error': '服务器繁忙，请稍后重试',
      '500': '服务器错误，请稍后重试',
      'Service Unavailable': '服务暂时不可用，请稍后重试',
      '503': '服务暂时不可用',
      'Gateway Timeout': '服务器响应超时，请重试',
      '504': '服务器响应超时',

      // ========== 数据库错误 ==========
      'SQLSTATE': '数据保存失败，请重试',
      'Duplicate entry': '该数据已存在',
      'Deadlock': '操作冲突，请重试',
      'Lock wait timeout': '操作超时，请重试',

      // ========== 业务逻辑错误 - NFC ==========
      '设备不存在': '设备未找到，请确认设备编号是否正确',
      '设备已离线': '设备暂时离线，请稍后重试或联系商家',
      '设备未激活': '设备未激活，请联系商家激活后再试',
      '设备已禁用': '设备已被禁用，请联系商家',
      '触发过于频繁': '操作太快了，请稍后再试',

      // ========== 业务逻辑错误 - 优惠券 ==========
      '优惠券已抢完': '优惠券已被抢光，下次早点来哦',
      '优惠券已过期': '该优惠券已过期',
      '优惠券未开始': '该优惠券活动还未开始',
      '优惠券已领取': '您已经领取过该优惠券了',
      '达到领取上限': '您的领取次数已达上限',

      // ========== 业务逻辑错误 - 内容生成 ==========
      '任务不存在': '生成任务未找到',
      '任务已超时': '生成任务已超时，请重新创建',
      '内容违规': '内容包含违规信息，请修改后重试',
      'AI配额不足': 'AI生成配额不足，请联系管理员',
      '生成失败': 'AI生成失败，请重试',

      // ========== 业务逻辑错误 - 支付 ==========
      '余额不足': '账户余额不足',
      '支付失败': '支付失败，请重试',
      '订单已支付': '该订单已支付',
      '订单已取消': '该订单已取消',

      // ========== 文件上传错误 ==========
      'File too large': '文件太大，请选择较小的文件',
      'Invalid file type': '文件格式不支持',
      'Upload failed': '文件上传失败，请重试',

      // ========== 限流错误 ==========
      'Rate limit exceeded': '操作太频繁，请稍后再试',
      'Too many requests': '请求次数过多，请稍后再试',
      '429': '请求太频繁，请稍后再试'
    }

    // 查找匹配的错误类型（优先精确匹配）
    for (const [keyword, friendlyMsg] of Object.entries(errorMap)) {
      if (errorString === keyword || errorString.includes(keyword)) {
        return friendlyMsg
      }
    }

    // 如果是数字状态码，返回通用消息
    if (/^\d{3}$/.test(errorString)) {
      return '请求失败，请稍后重试'
    }

    // 默认错误提示
    return '操作失败，请稍后重试'
  }

  /**
   * 获取错误详情
   */
  static getErrorDetails(error) {
    const details = {
      timestamp: new Date().toISOString(),
      type: this.getErrorType(error),
      code: this.getErrorCode(error),
      originalMessage: this.getErrorString(error),
      stack: error?.stack || null
    }

    // 提取HTTP响应详情
    if (error?.response) {
      details.statusCode = error.response.status
      details.statusText = error.response.statusText
      details.responseData = error.response.data
    }

    // 提取网络错误详情
    if (error?.request) {
      details.requestUrl = error.request.url
      details.requestMethod = error.request.method
    }

    return details
  }

  /**
   * 获取错误类型
   */
  static getErrorType(error) {
    const errorString = this.getErrorString(error)

    if (errorString.includes('Network') || errorString.includes('timeout')) {
      return 'NETWORK_ERROR'
    }
    if (errorString.includes('401') || errorString.includes('Unauthorized')) {
      return 'AUTH_ERROR'
    }
    if (errorString.includes('403') || errorString.includes('Forbidden')) {
      return 'PERMISSION_ERROR'
    }
    if (errorString.includes('404') || errorString.includes('Not Found')) {
      return 'NOT_FOUND_ERROR'
    }
    if (errorString.includes('500') || errorString.includes('Internal Server')) {
      return 'SERVER_ERROR'
    }
    if (errorString.includes('Validation') || errorString.includes('Invalid')) {
      return 'VALIDATION_ERROR'
    }
    if (errorString.includes('SQLSTATE') || errorString.includes('Duplicate')) {
      return 'DATABASE_ERROR'
    }

    return 'UNKNOWN_ERROR'
  }

  /**
   * 获取错误代码
   */
  static getErrorCode(error) {
    if (error?.response?.status) {
      return error.response.status
    }
    if (error?.code) {
      return error.code
    }
    return null
  }

  /**
   * 获取错误字符串
   */
  static getErrorString(error) {
    if (typeof error === 'string') {
      return error
    }
    if (error?.message) {
      return error.message
    }
    if (error?.response?.data?.message) {
      return error.response.data.message
    }
    if (error?.response?.statusText) {
      return error.response.statusText
    }
    return String(error)
  }

  /**
   * 判断错误是否可重试
   */
  static canRetry(error) {
    const retryableTypes = [
      'NETWORK_ERROR',
      'SERVER_ERROR',
      'timeout',
      '500',
      '502',
      '503',
      '504'
    ]

    const errorType = this.getErrorType(error)
    const errorString = this.getErrorString(error)

    return retryableTypes.some(type =>
      errorType === type || errorString.includes(type)
    )
  }

  /**
   * 显示Toast提示
   */
  static showToast(message, details) {
    uni.showToast({
      title: message,
      icon: 'none',
      duration: 3000,
      mask: false
    })

    // 如果是认证错误，延迟跳转到登录页
    if (details.type === 'AUTH_ERROR') {
      setTimeout(() => {
        uni.reLaunch({
          url: '/pages/auth/index'
        })
      }, 1500)
    }
  }

  /**
   * 上报错误到监控平台
   */
  static reportError(error, context, details) {
    // 过滤不需要上报的错误
    if (this.shouldSkipReport(error)) {
      return
    }

    try {
      // 准备上报数据
      const reportData = {
        context,
        details,
        userAgent: navigator.userAgent || '',
        platform: uni.getSystemInfoSync().platform,
        appVersion: getApp().globalData?.version || 'unknown',
        userId: getApp().globalData?.userId || null,
        timestamp: Date.now()
      }

      // 上报到后端日志系统
      uni.request({
        url: '/api/log/error',
        method: 'POST',
        data: reportData,
        fail: (err) => {
          console.error('Error reporting failed:', err)
        }
      })

      // TODO: 可选择上报到第三方监控平台（如Sentry）
      // if (window.Sentry) {
      //   Sentry.captureException(error, {
      //     contexts: { details, context }
      //   })
      // }

    } catch (reportError) {
      console.error('Failed to report error:', reportError)
    }
  }

  /**
   * 判断是否跳过错误上报
   */
  static shouldSkipReport(error) {
    const errorString = this.getErrorString(error)

    // 跳过的错误类型
    const skipPatterns = [
      '401',  // 登录过期很常见，不需要上报
      'Unauthorized',
      '用户取消',  // 用户主动取消操作
      'cancel',
      'abort'
    ]

    return skipPatterns.some(pattern =>
      errorString.includes(pattern)
    )
  }

  /**
   * 显示错误对话框（用于关键错误）
   */
  static showErrorDialog(error, options = {}) {
    const friendlyMessage = this.getFriendlyMessage(error)
    const canRetry = this.canRetry(error)

    return new Promise((resolve) => {
      uni.showModal({
        title: options.title || '操作失败',
        content: friendlyMessage,
        showCancel: canRetry,
        confirmText: canRetry ? '重试' : '确定',
        cancelText: '取消',
        success: (res) => {
          if (res.confirm) {
            resolve('retry')
          } else if (res.cancel) {
            resolve('cancel')
          }
        },
        fail: () => {
          resolve('cancel')
        }
      })
    })
  }

  /**
   * 包装异步函数，自动处理错误
   */
  static async withErrorHandling(asyncFunc, context = '', options = {}) {
    try {
      return await asyncFunc()
    } catch (error) {
      const result = this.handle(error, context, options)

      // 如果可重试且用户选择重试
      if (result.canRetry && options.retryable) {
        const action = await this.showErrorDialog(error, {
          title: '操作失败'
        })

        if (action === 'retry') {
          // 递归重试
          return this.withErrorHandling(asyncFunc, context, options)
        }
      }

      throw error
    }
  }
}

// 导出单例实例
export const errorHandler = ErrorHandler

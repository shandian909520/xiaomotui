/**
 * 成功反馈辅助工具
 * 提供视觉、听觉和触觉的成功反馈
 */

class FeedbackHelper {
  /**
   * 显示成功反馈（全套）
   * @param {String} message - 成功消息
   * @param {Object} options - 配置选项
   */
  static success(message = '操作成功', options = {}) {
    const {
      vibrate = true,      // 是否震动
      sound = false,       // 是否播放声音（小程序不支持）
      animation = true,    // 是否显示动画
      duration = 2000,     // Toast显示时长
      icon = 'success'     // 图标类型
    } = options

    // 1. 显示Toast提示
    uni.showToast({
      title: message,
      icon: icon,
      duration: duration,
      mask: false
    })

    // 2. 震动反馈
    if (vibrate) {
      this.vibrate('success')
    }

    // 3. 声音反馈（H5支持，小程序不支持）
    // #ifdef H5
    if (sound) {
      this.playSound('success')
    }
    // #endif

    // 记录反馈日志
    console.log('[SuccessFeedback]', message, options)
  }

  /**
   * 震动反馈
   * @param {String} type - 反馈类型: success, warning, error
   */
  static vibrate(type = 'success') {
    try {
      // #ifdef APP-PLUS || H5
      if (typeof navigator !== 'undefined' && navigator.vibrate) {
        const patterns = {
          'success': [50],           // 短震一次
          'warning': [50, 100, 50],  // 两次短震
          'error': [100, 50, 100]    // 强震
        }
        navigator.vibrate(patterns[type] || patterns.success)
      }
      // #endif

      // #ifdef MP-WEIXIN || MP-ALIPAY
      uni.vibrateShort({
        type: type === 'error' ? 'heavy' : 'light'
      })
      // #endif
    } catch (e) {
      console.warn('震动反馈失败:', e)
    }
  }

  /**
   * 播放声音反馈（仅H5）
   * @param {String} type - 声音类型
   */
  static playSound(type = 'success') {
    // #ifdef H5
    try {
      // 使用Web Audio API播放简单的提示音
      const audioContext = new (window.AudioContext || window.webkitAudioContext)()
      const oscillator = audioContext.createOscillator()
      const gainNode = audioContext.createGain()

      oscillator.connect(gainNode)
      gainNode.connect(audioContext.destination)

      // 不同类型的音调
      const frequencies = {
        'success': 880,  // A5 - 明快的高音
        'warning': 440,  // A4 - 中音
        'error': 220     // A3 - 低音
      }

      oscillator.frequency.value = frequencies[type] || frequencies.success
      oscillator.type = 'sine'

      // 渐变音量
      gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
      gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2)

      oscillator.start(audioContext.currentTime)
      oscillator.stop(audioContext.currentTime + 0.2)
    } catch (e) {
      console.warn('播放声音失败:', e)
    }
    // #endif
  }

  /**
   * 显示警告反馈
   * @param {String} message - 警告消息
   * @param {Object} options - 配置选项
   */
  static warning(message = '请注意', options = {}) {
    const {
      vibrate = true,
      duration = 2500
    } = options

    uni.showToast({
      title: message,
      icon: 'none',
      duration: duration
    })

    if (vibrate) {
      this.vibrate('warning')
    }
  }

  /**
   * 显示错误反馈
   * @param {String} message - 错误消息
   * @param {Object} options - 配置选项
   */
  static error(message = '操作失败', options = {}) {
    const {
      vibrate = true,
      duration = 3000
    } = options

    uni.showToast({
      title: message,
      icon: 'none',
      duration: duration
    })

    if (vibrate) {
      this.vibrate('error')
    }
  }

  /**
   * 加载中反馈
   * @param {String} message - 加载消息
   */
  static loading(message = '加载中...') {
    uni.showLoading({
      title: message,
      mask: true
    })
  }

  /**
   * 隐藏加载反馈
   */
  static hideLoading() {
    uni.hideLoading()
  }

  /**
   * 显示Modal确认框
   * @param {String} title - 标题
   * @param {String} content - 内容
   * @param {Object} options - 配置选项
   * @returns {Promise}
   */
  static confirm(title = '提示', content = '', options = {}) {
    const {
      confirmText = '确定',
      cancelText = '取消',
      confirmColor = '#007AFF',
      cancelColor = '#666666'
    } = options

    return new Promise((resolve) => {
      uni.showModal({
        title,
        content,
        confirmText,
        cancelText,
        confirmColor,
        cancelColor,
        success: (res) => {
          resolve(res.confirm)
        },
        fail: () => {
          resolve(false)
        }
      })
    })
  }

  /**
   * 操作成功 + 自动跳转
   * @param {String} message - 成功消息
   * @param {String} url - 跳转URL
   * @param {Number} delay - 延迟时长（毫秒）
   */
  static successAndNavigate(message, url, delay = 1500) {
    this.success(message)

    setTimeout(() => {
      if (url.startsWith('/')) {
        uni.navigateTo({ url })
      } else {
        uni.redirectTo({ url: `/${url}` })
      }
    }, delay)
  }

  /**
   * 操作成功 + 返回上一页
   * @param {String} message - 成功消息
   * @param {Number} delay - 延迟时长（毫秒）
   * @param {Number} delta - 返回层数
   */
  static successAndBack(message, delay = 1500, delta = 1) {
    this.success(message)

    setTimeout(() => {
      uni.navigateBack({ delta })
    }, delay)
  }

  /**
   * 操作成功 + 刷新页面
   * @param {String} message - 成功消息
   * @param {Function} callback - 刷新回调
   * @param {Number} delay - 延迟时长（毫秒）
   */
  static successAndRefresh(message, callback, delay = 1000) {
    this.success(message)

    setTimeout(() => {
      if (typeof callback === 'function') {
        callback()
      }
    }, delay)
  }

  /**
   * 复制成功反馈
   * @param {String} content - 复制的内容
   */
  static copySuccess(content = '') {
    uni.setClipboardData({
      data: content,
      success: () => {
        this.success('已复制到剪贴板', { vibrate: true })
      },
      fail: () => {
        this.error('复制失败')
      }
    })
  }

  /**
   * 分享成功反馈
   */
  static shareSuccess() {
    this.success('分享成功', {
      vibrate: true,
      icon: 'success'
    })
  }

  /**
   * 保存成功反馈
   */
  static saveSuccess() {
    this.success('保存成功', {
      vibrate: true,
      icon: 'success'
    })
  }

  /**
   * 删除成功反馈
   */
  static deleteSuccess() {
    this.success('删除成功', {
      vibrate: true,
      icon: 'success'
    })
  }

  /**
   * 提交成功反馈
   */
  static submitSuccess() {
    this.success('提交成功', {
      vibrate: true,
      icon: 'success'
    })
  }

  /**
   * 发布成功反馈
   */
  static publishSuccess() {
    this.success('发布成功', {
      vibrate: true,
      icon: 'success'
    })
  }

  /**
   * 绑定成功反馈
   */
  static bindSuccess() {
    this.success('绑定成功', {
      vibrate: true,
      icon: 'success'
    })
  }

  /**
   * 取消成功反馈
   */
  static cancelSuccess() {
    this.success('已取消', {
      vibrate: false,
      icon: 'none'
    })
  }
}

export default FeedbackHelper

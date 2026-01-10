/**
 * 全局加载状态管理器
 *
 * 统一管理应用中的加载状态，避免加载提示重叠或遗漏
 * 支持多层级加载状态跟踪
 */

class LoadingManager {
  constructor() {
    // 加载状态栈 - 支持嵌套加载
    this.loadingStack = []

    // 当前显示的加载提示
    this.currentToast = null

    // 加载状态映射表
    this.loadingStates = new Map()

    // 默认配置
    this.defaultConfig = {
      title: '加载中...',
      mask: true,
      duration: 0 // 0表示不自动关闭
    }
  }

  /**
   * 显示加载提示
   * @param {String} title - 加载提示文字
   * @param {String} key - 唯一标识符(可选)
   * @param {Object} config - 额外配置
   * @returns {String} 加载标识
   */
  show(title = '加载中...', key = null, config = {}) {
    const loadingKey = key || this._generateKey()

    // 如果该key已经在加载中，不重复显示
    if (this.loadingStates.has(loadingKey)) {
      console.warn(`[LoadingManager] Key "${loadingKey}" is already loading`)
      return loadingKey
    }

    // 记录加载状态
    this.loadingStates.set(loadingKey, {
      title,
      startTime: Date.now(),
      config
    })

    // 添加到加载栈
    this.loadingStack.push(loadingKey)

    // 显示加载提示
    this._showToast(title, config)

    return loadingKey
  }

  /**
   * 隐藏加载提示
   * @param {String} key - 加载标识
   */
  hide(key) {
    if (!key) {
      console.warn('[LoadingManager] Hide called without key')
      return
    }

    // 从加载状态中移除
    const loadingState = this.loadingStates.get(key)
    if (loadingState) {
      const duration = Date.now() - loadingState.startTime
      console.log(`[LoadingManager] Loading "${loadingState.title}" completed in ${duration}ms`)
      this.loadingStates.delete(key)
    }

    // 从栈中移除
    const index = this.loadingStack.indexOf(key)
    if (index > -1) {
      this.loadingStack.splice(index, 1)
    }

    // 如果没有其他加载任务，关闭加载提示
    if (this.loadingStack.length === 0) {
      this._hideToast()
    } else {
      // 显示栈顶的加载任务
      const topKey = this.loadingStack[this.loadingStack.length - 1]
      const topState = this.loadingStates.get(topKey)
      if (topState) {
        this._showToast(topState.title, topState.config)
      }
    }
  }

  /**
   * 隐藏所有加载提示
   */
  hideAll() {
    this.loadingStates.clear()
    this.loadingStack = []
    this._hideToast()
  }

  /**
   * 包装异步函数，自动管理加载状态
   * @param {Function} asyncFn - 异步函数
   * @param {String} title - 加载提示文字
   * @param {Object} config - 配置项
   * @returns {Promise}
   */
  async wrap(asyncFn, title = '加载中...', config = {}) {
    const key = this.show(title, null, config)

    try {
      const result = await asyncFn()
      this.hide(key)
      return result
    } catch (error) {
      this.hide(key)
      throw error
    }
  }

  /**
   * 获取当前是否有加载任务
   * @returns {Boolean}
   */
  isLoading() {
    return this.loadingStack.length > 0
  }

  /**
   * 获取当前加载任务数量
   * @returns {Number}
   */
  getLoadingCount() {
    return this.loadingStack.length
  }

  /**
   * 显示Toast
   * @private
   */
  _showToast(title, config = {}) {
    try {
      // 先关闭之前的toast
      if (this.currentToast) {
        uni.hideLoading()
      }

      const finalConfig = {
        ...this.defaultConfig,
        ...config,
        title
      }

      uni.showLoading(finalConfig)
      this.currentToast = title
    } catch (error) {
      console.error('[LoadingManager] Failed to show toast:', error)
    }
  }

  /**
   * 隐藏Toast
   * @private
   */
  _hideToast() {
    try {
      uni.hideLoading()
      this.currentToast = null
    } catch (error) {
      console.error('[LoadingManager] Failed to hide toast:', error)
    }
  }

  /**
   * 生成唯一key
   * @private
   */
  _generateKey() {
    return `loading_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
  }
}

// 创建单例
const loadingManager = new LoadingManager()

/**
 * 加载装饰器函数
 * 用于Vue方法，自动添加加载状态
 *
 * @param {String} title - 加载提示文字
 * @param {Object} config - 配置项
 * @returns {Function} 装饰器函数
 *
 * @example
 * methods: {
 *   @withLoading('提交中...')
 *   async submitForm() {
 *     await api.submit(this.formData)
 *   }
 * }
 */
export function withLoading(title = '加载中...', config = {}) {
  return function(target, propertyKey, descriptor) {
    const originalMethod = descriptor.value

    descriptor.value = async function(...args) {
      return loadingManager.wrap(
        () => originalMethod.apply(this, args),
        title,
        config
      )
    }

    return descriptor
  }
}

/**
 * Mixin - 为Vue组件添加loading辅助方法
 *
 * @example
 * import { LoadingMixin } from '@/utils/LoadingManager'
 *
 * export default {
 *   mixins: [LoadingMixin],
 *   methods: {
 *     async loadData() {
 *       const key = this.$loading.show('加载数据中...')
 *       try {
 *         await api.getData()
 *       } finally {
 *         this.$loading.hide(key)
 *       }
 *     }
 *   }
 * }
 */
export const LoadingMixin = {
  beforeCreate() {
    this.$loading = loadingManager
  },

  beforeUnmount() {
    // 组件销毁时清理该组件创建的加载状态
    // 这里可以扩展为跟踪每个组件的loading keys
    if (loadingManager.isLoading()) {
      console.warn('[LoadingMixin] Component unmounted with active loading states')
    }
  }
}

/**
 * 便捷方法 - 常见的加载场景
 */
export const LoadingHelper = {
  /**
   * 数据加载
   */
  data(title = '加载数据中...') {
    return loadingManager.show(title)
  },

  /**
   * 提交表单
   */
  submit(title = '提交中...') {
    return loadingManager.show(title)
  },

  /**
   * 保存数据
   */
  save(title = '保存中...') {
    return loadingManager.show(title)
  },

  /**
   * 删除数据
   */
  delete(title = '删除中...') {
    return loadingManager.show(title)
  },

  /**
   * 上传文件
   */
  upload(title = '上传中...') {
    return loadingManager.show(title)
  },

  /**
   * 下载文件
   */
  download(title = '下载中...') {
    return loadingManager.show(title)
  },

  /**
   * AI生成
   */
  generate(title = 'AI生成中...') {
    return loadingManager.show(title, null, { mask: true })
  },

  /**
   * 刷新数据
   */
  refresh(title = '刷新中...') {
    return loadingManager.show(title)
  },

  /**
   * 隐藏加载
   */
  hide(key) {
    loadingManager.hide(key)
  },

  /**
   * 隐藏所有加载
   */
  hideAll() {
    loadingManager.hideAll()
  }
}

// 导出单例
export default loadingManager

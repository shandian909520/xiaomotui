<template>
  <view class="trigger-page">
    <!-- 顶部导航栏 -->
    <view class="nav-bar">
      <view class="nav-title">NFC触发</view>
      <view class="nav-action" @tap="handleScan" v-if="!isTriggering">
        <text class="scan-icon">📷</text>
        <text>扫码</text>
      </view>
    </view>

    <!-- 新手引导 -->
    <view class="first-time-guide" v-if="showGuide">
      <view class="guide-mask" @tap="skipGuide"></view>
      <view class="guide-content">
        <view class="guide-header">
          <text class="guide-title">🎯 如何使用碰一碰</text>
          <text class="guide-close" @tap="skipGuide">✕</text>
        </view>

        <view class="guide-steps">
          <view class="guide-step">
            <view class="step-number">1</view>
            <view class="step-content">
              <text class="step-title">📱 靠近设备</text>
              <text class="step-desc">将手机背面靠近NFC设备（距离<5cm）</text>
              <image class="step-image" src="/static/guide/nfc-touch.png" mode="aspectFit" />
            </view>
          </view>

          <view class="guide-step">
            <view class="step-number">2</view>
            <view class="step-content">
              <text class="step-title">✨ 自动触发</text>
              <text class="step-desc">手机震动后即可看到生成的内容</text>
              <image class="step-image" src="/static/guide/auto-trigger.png" mode="aspectFit" />
            </view>
          </view>

          <view class="guide-step">
            <view class="step-number">3</view>
            <view class="step-content">
              <text class="step-title">📷 备选方式</text>
              <text class="step-desc">手机不支持NFC？点击"扫码"按钮扫描二维码</text>
              <image class="step-image" src="/static/guide/scan-qr.png" mode="aspectFit" />
            </view>
          </view>
        </view>

        <view class="guide-footer">
          <button class="guide-skip-btn" @tap="skipGuide">跳过</button>
          <button class="guide-start-btn" @tap="startUsing">我知道了</button>
        </view>

        <view class="guide-checkbox">
          <checkbox :checked="dontShowAgain" @change="onCheckboxChange" />
          <text>不再提示</text>
        </view>
      </view>
    </view>

    <!-- 主内容区 -->
    <view class="content-wrapper">
      <!-- 未触发状态 -->
      <view class="idle-state" v-if="!deviceCode && !isTriggering">
        <view class="idle-icon">📱</view>
        <view class="idle-title">准备触发</view>
        <view class="idle-desc">
          使用NFC碰一碰或扫描设备二维码
        </view>
        <button class="scan-btn primary-btn" @tap="handleScan">
          扫描设备码
        </button>
        <button class="help-btn" @tap="showHelpGuide">
          <text>❓ 如何使用</text>
        </button>
      </view>

      <!-- 触发中状态 -->
      <view class="triggering-state" v-if="isTriggering">
        <!-- 设备信息 -->
        <view class="device-info" v-if="deviceInfo">
          <view class="device-icon">🏪</view>
          <view class="device-name">{{ deviceInfo.name }}</view>
          <view class="device-code">设备码: {{ deviceCode }}</view>
        </view>

        <!-- 任务状态 -->
        <view class="task-status">
          <view class="status-icon" :class="statusClass">
            <text v-if="taskStatus === 'pending'">⏳</text>
            <text v-if="taskStatus === 'processing'">⚙️</text>
            <text v-if="taskStatus === 'completed'">✅</text>
            <text v-if="taskStatus === 'failed'">❌</text>
          </view>
          <view class="status-text">{{ statusText }}</view>
        </view>

        <!-- AI进度可视化组件 -->
        <view class="ai-progress-section" v-if="taskStatus === 'processing' || taskStatus === 'pending'">
          <ai-progress
            :progress="progress"
            :steps="progressSteps"
            :elapsedTime="elapsedTime"
            :remainingTime="remainingTime"
            :currentStepName="currentStepName"
            :taskStatus="taskStatus"
          ></ai-progress>
        </view>

        <!-- 生成信息 -->
        <view class="generation-info" v-if="generationInfo">
          <view class="info-item" v-if="generationInfo.content_type">
            <text class="info-label">内容类型:</text>
            <text class="info-value">{{ formatContentType(generationInfo.content_type) }}</text>
          </view>
          <view class="info-item" v-if="generationInfo.platform">
            <text class="info-label">目标平台:</text>
            <text class="info-value">{{ generationInfo.platform }}</text>
          </view>
          <view class="info-item" v-if="generationInfo.generation_time">
            <text class="info-label">生成时间:</text>
            <text class="info-value">{{ generationInfo.generation_time }}秒</text>
          </view>
        </view>

        <!-- 错误信息（增强版） -->
        <view class="error-message" v-if="errorMessage">
          <view class="error-icon">{{ errorInfo.icon || '⚠️' }}</view>
          <view class="error-content">
            <text class="error-title">{{ errorInfo.message || errorMessage }}</text>
            <text class="error-solution" v-if="errorInfo.solution">
              💡 {{ errorInfo.solution }}
            </text>
            <text class="error-device-code" v-if="errorInfo.contact_merchant && deviceCode">
              设备编号：{{ deviceCode }}
            </text>
          </view>
        </view>

        <!-- 操作按钮 -->
        <view class="action-buttons">
          <!-- 完成状态 -->
          <button
            class="primary-btn"
            v-if="taskStatus === 'completed'"
            @tap="handleViewContent"
          >
            查看内容
          </button>

          <!-- 失败状态 -->
          <button
            class="primary-btn"
            v-if="taskStatus === 'failed' && errorInfo.retry"
            @tap="handleRetry"
          >
            重新触发
          </button>

          <!-- 联系商家按钮 -->
          <button
            class="secondary-btn"
            v-if="taskStatus === 'failed' && errorInfo.contact_merchant"
            @tap="contactMerchant"
          >
            联系商家
          </button>

          <!-- 处理中状态 -->
          <button
            class="secondary-btn"
            v-if="taskStatus === 'processing' || taskStatus === 'pending'"
            @tap="handleCancel"
          >
            取消任务
          </button>
        </view>
      </view>

      <!-- 已触发状态（显示设备信息） -->
      <view class="device-ready-state" v-if="deviceCode && !isTriggering">
        <view class="device-card">
          <view class="device-icon-large">📱</view>
          <view class="device-name-large">{{ deviceInfo?.name || '未知设备' }}</view>
          <view class="device-code-text">{{ deviceCode }}</view>
        </view>
        <button class="trigger-btn primary-btn" @tap="handleTrigger">
          触发内容生成
        </button>
        <button class="secondary-btn" @tap="handleReset">
          重新扫码
        </button>
      </view>
    </view>

    <!-- 底部提示 -->
    <view class="footer-hint" v-if="!isTriggering">
      <text>提示: 请确保设备已联网且配置正确</text>
    </view>

    <!-- 加载提示 -->
    <view class="loading-overlay" v-if="isLoading">
      <view class="loading-box">
        <view class="loading-spinner"></view>
        <view class="loading-text">{{ loadingText }}</view>
      </view>
    </view>

    <!-- 错误详情弹窗 -->
    <error-detail
      :visible="showErrorDetail"
      :errorInfo="errorDetail"
      @close="closeErrorDetail"
      @retry="retryAfterError"
      @contact="contactMerchant"
    />
  </view>
</template>

<script>
import api from '../../api/index.js'
import AiProgress from '../../components/ai-progress/ai-progress.vue'
import ErrorDetail from '../../components/error-detail/error-detail.vue'
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  components: {
    AiProgress,
    ErrorDetail
  },

  data() {
    return {
      deviceCode: '',           // 设备码
      deviceInfo: null,         // 设备信息
      isTriggering: false,      // 是否正在触发
      isLoading: false,         // 是否显示加载
      loadingText: '加载中...',  // 加载提示文本

      // 错误详情弹窗
      showErrorDetail: false,   // 是否显示错误详情
      errorDetail: {            // 错误详情信息
        code: '',
        message: '',
        solution: '',
        icon: '❌',
        retry: false,
        contact_merchant: false
      },
      lastFailedAction: null,   // 最后失败的操作（用于重试）

      // 新手引导相关
      showGuide: false,         // 是否显示引导
      dontShowAgain: false,     // 不再提示

      // 任务相关
      taskId: '',               // 任务ID
      taskStatus: '',           // 任务状态: pending/processing/completed/failed
      progress: 0,              // 进度 0-100
      generationInfo: null,     // 生成信息
      errorMessage: '',         // 错误信息
      errorInfo: {},            // 详细错误信息（图标、解决方案、是否可重试等）

      // AI进度详细信息
      progressSteps: [
        { step: 1, name: '分析需求', icon: '🔍', status: 'pending', weight: 10 },
        { step: 2, name: '调用AI模型', icon: '🤖', status: 'pending', weight: 50 },
        { step: 3, name: '生成内容', icon: '✨', status: 'pending', weight: 30 },
        { step: 4, name: '质量检查', icon: '✅', status: 'pending', weight: 10 }
      ],
      currentStepName: '等待处理',
      elapsedTime: 0,
      remainingTime: 0,
      startTime: 0,

      // 轮询相关
      pollingTimer: null,       // 轮询定时器
      pollingInterval: 2000,    // 轮询间隔(ms)
      maxPollingCount: 150,     // 最大轮询次数 (5分钟)
      currentPollingCount: 0    // 当前轮询次数
    }
  },

  onLoad() {
    // 检查是否需要显示新手引导
    this.checkFirstTime()
  },

  computed: {
    // 状态文本
    statusText() {
      const statusMap = {
        pending: '任务等待中...',
        processing: '正在生成内容...',
        completed: '生成完成!',
        failed: '生成失败'
      }
      return statusMap[this.taskStatus] || '未知状态'
    },

    // 状态样式类
    statusClass() {
      return `status-${this.taskStatus}`
    }
  },

  onLoad(options) {
    console.log('页面加载参数:', options)

    // 从参数获取设备码
    if (options.device_code) {
      this.deviceCode = options.device_code
      this.loadDeviceInfo()
    } else if (options.code) {
      // 兼容扫码跳转
      this.deviceCode = options.code
      this.loadDeviceInfo()
    }

    // 如果有task_id参数，直接进入轮询状态
    if (options.task_id) {
      this.taskId = options.task_id
      this.isTriggering = true
      this.startPolling()
    }
  },

  onUnload() {
    // 页面卸载时清除定时器
    this.stopPolling()
  },

  methods: {
    /**
     * 检查是否首次使用
     */
    checkFirstTime() {
      try {
        const hasShownGuide = uni.getStorageSync('nfc_guide_shown')
        if (!hasShownGuide) {
          this.showGuide = true
        }
      } catch (e) {
        console.error('检查新手引导失败:', e)
      }
    },

    /**
     * 跳过引导
     */
    skipGuide() {
      this.showGuide = false

      if (this.dontShowAgain) {
        try {
          uni.setStorageSync('nfc_guide_shown', true)
        } catch (e) {
          console.error('保存引导状态失败:', e)
        }
      }
    },

    /**
     * 开始使用
     */
    startUsing() {
      this.showGuide = false

      try {
        uni.setStorageSync('nfc_guide_shown', true)
      } catch (e) {
        console.error('保存引导状态失败:', e)
      }

      // 震动反馈
      uni.vibrateShort()
    },

    /**
     * 显示帮助引导
     */
    showHelpGuide() {
      this.showGuide = true
    },

    /**
     * 复选框变化
     */
    onCheckboxChange(e) {
      this.dontShowAgain = e.detail.value.length > 0
    },

    /**
     * 扫描二维码
     */
    async handleScan() {
      try {
        // #ifdef MP-WEIXIN || MP-ALIPAY || APP-PLUS
        const res = await uni.scanCode({
          scanType: ['qrCode', 'barCode']
        })

        console.log('扫码结果:', res)

        // 解析设备码
        const result = res.result

        // 如果是完整URL，提取device_code参数
        if (result.includes('device_code=')) {
          const match = result.match(/device_code=([^&]+)/)
          if (match) {
            this.deviceCode = match[1]
          }
        } else {
          // 直接使用扫码结果作为设备码
          this.deviceCode = result
        }

        if (this.deviceCode) {
          await this.loadDeviceInfo()
        } else {
          FeedbackHelper.warning('无效的设备码', { vibrate: true })
        }
        // #endif

        // #ifdef H5
        FeedbackHelper.warning('H5暂不支持扫码', {
          vibrate: false,
          duration: 2000
        })
        // #endif
      } catch (error) {
        console.error('扫码失败:', error)
        if (error.errMsg && !error.errMsg.includes('cancel')) {
          FeedbackHelper.error('扫码失败', { vibrate: true })
        }
      }
    },

    /**
     * 加载设备信息
     */
    async loadDeviceInfo() {
      if (!this.deviceCode) return

      this.isLoading = true
      this.loadingText = '加载设备信息...'

      try {
        const res = await api.nfc.getDeviceDetail(this.deviceCode)
        console.log('设备信息:', res)
        this.deviceInfo = res

        FeedbackHelper.success('设备信息加载成功', {
          vibrate: false,
          duration: 1500
        })
      } catch (error) {
        console.error('加载设备信息失败:', error)
        this.showErrorDetail(error, () => this.loadDeviceInfo())
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 触发内容生成
     */
    async handleTrigger() {
      if (!this.deviceCode) {
        FeedbackHelper.warning('请先扫描设备码', { vibrate: true })
        return
      }

      this.isLoading = true
      this.loadingText = '触发中...'

      try {
        // 获取用户位置信息（可选）
        let location = null
        try {
          const locationRes = await uni.getLocation({
            type: 'gcj02'
          })
          location = {
            latitude: locationRes.latitude,
            longitude: locationRes.longitude
          }
        } catch (e) {
          console.log('获取位置失败:', e)
        }

        // 调用触发接口
        const res = await api.nfc.trigger(this.deviceCode, {
          user_location: location,
          trigger_source: 'manual', // 手动触发
          platform: 'wechat' // 平台标识
        })

        console.log('触发结果:', res)

        // 保存任务ID
        this.taskId = res.content_task_id || res.task_id

        if (!this.taskId) {
          throw new Error('未返回任务ID')
        }

        // 进入触发状态
        this.isTriggering = true
        this.taskStatus = 'pending'
        this.progress = 0
        this.errorMessage = ''

        // 开始轮询任务状态
        this.startPolling()

        FeedbackHelper.success('触发成功', { vibrate: true })
      } catch (error) {
        console.error('触发失败:', error)

        // 提取详细错误信息
        if (error.data && error.data.data) {
          this.errorInfo = {
            icon: error.data.data.icon || '❌',
            message: error.message || error.data.message || '触发失败',
            solution: error.data.data.solution || '',
            retry: error.data.data.retry !== false,
            contact_merchant: error.data.data.contact_merchant || false
          }
        } else {
          this.errorInfo = {
            icon: '❌',
            message: error.message || '触发失败',
            solution: '请检查网络连接后重试',
            retry: true,
            contact_merchant: false
          }
        }

        this.errorMessage = this.errorInfo.message
        this.taskStatus = 'failed'
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 开始轮询任务状态
     */
    startPolling() {
      if (!this.taskId) return

      // 清除已有定时器
      this.stopPolling()

      // 重置轮询计数
      this.currentPollingCount = 0

      // 立即查询一次
      this.queryTaskStatus()

      // 设置定时器
      this.pollingTimer = setInterval(() => {
        this.currentPollingCount++

        // 超过最大轮询次数，停止轮询
        if (this.currentPollingCount >= this.maxPollingCount) {
          this.stopPolling()
          this.taskStatus = 'failed'
          this.errorMessage = '任务超时，请重试'
          uni.showModal({
            title: '提示',
            content: '任务超时，请稍后重试',
            showCancel: false
          })
          return
        }

        this.queryTaskStatus()
      }, this.pollingInterval)
    },

    /**
     * 停止轮询
     */
    stopPolling() {
      if (this.pollingTimer) {
        clearInterval(this.pollingTimer)
        this.pollingTimer = null
      }
    },

    /**
     * 查询任务状态
     */
    async queryTaskStatus() {
      if (!this.taskId) return

      try {
        const res = await api.content.getTaskStatus(this.taskId)
        console.log('任务状态:', res)

        // 更新状态
        this.taskStatus = res.status
        this.progress = res.progress || 0
        this.generationInfo = res

        // 更新进度详细信息
        if (res.progress_details && res.progress_details.length > 0) {
          this.progressSteps = res.progress_details
        }
        this.currentStepName = res.step_name || '等待处理'
        this.elapsedTime = res.elapsed_time || 0
        this.remainingTime = res.estimated_remaining_time || 0

        // 任务完成或失败，停止轮询
        if (res.status === 'completed') {
          this.stopPolling()
          this.progress = 100

          // 播放完成提示音
          // #ifdef MP-WEIXIN
          uni.vibrateShort()
          // #endif

          FeedbackHelper.success('生成完成', {
            vibrate: true,
            duration: 2000
          })
        } else if (res.status === 'failed') {
          this.stopPolling()
          this.errorMessage = res.error_message || '生成失败'

          uni.showModal({
            title: '生成失败',
            content: this.errorMessage,
            confirmText: '重新触发',
            success: (modalRes) => {
              if (modalRes.confirm) {
                this.handleRetry()
              }
            }
          })
        }
      } catch (error) {
        console.error('查询任务状态失败:', error)

        // 网络错误不停止轮询，继续重试
        if (this.currentPollingCount < 3) {
          console.log('继续重试...')
        } else {
          // 多次失败后停止轮询
          this.stopPolling()
          this.taskStatus = 'failed'
          this.errorMessage = '网络异常，请检查网络连接'
        }
      }
    },

    /**
     * 查看生成内容
     */
    handleViewContent() {
      if (!this.taskId) return

      // 跳转到内容预览页
      uni.navigateTo({
        url: `/pages/content/preview?task_id=${this.taskId}`
      })
    },

    /**
     * 重新触发
     */
    async handleRetry() {
      // 重置状态
      this.taskId = ''
      this.taskStatus = ''
      this.progress = 0
      this.errorMessage = ''
      this.generationInfo = null
      this.isTriggering = false

      // 重新触发
      await this.handleTrigger()
    },

    /**
     * 取消任务
     */
    async handleCancel() {
      uni.showModal({
        title: '确认取消',
        content: '确定要取消当前任务吗?',
        success: async (res) => {
          if (res.confirm) {
            try {
              // 停止轮询
              this.stopPolling()

              // 调用取消接口
              if (this.taskId) {
                await api.content.cancelTask(this.taskId)
              }

              // 重置状态
              this.isTriggering = false
              this.taskId = ''
              this.taskStatus = ''
              this.progress = 0

              FeedbackHelper.cancelSuccess()
            } catch (error) {
              console.error('取消任务失败:', error)
              FeedbackHelper.error('取消失败', { vibrate: true })
            }
          }
        }
      })
    },

    /**
     * 重置页面
     */
    handleReset() {
      this.deviceCode = ''
      this.deviceInfo = null
      this.isTriggering = false
      this.taskId = ''
      this.taskStatus = ''
      this.progress = 0
      this.errorMessage = ''
      this.generationInfo = null
      this.stopPolling()
    },

    /**
     * 格式化内容类型
     */
    formatContentType(type) {
      const typeMap = {
        'TEXT': '文本内容',
        'IMAGE': '图片内容',
        'VIDEO': '视频内容',
        'MIXED': '混合内容'
      }
      return typeMap[type] || type
    },

    /**
     * 显示错误详情
     * @param {Object} error - 错误对象（从API返回）
     * @param {Function} retryAction - 重试时执行的操作
     */
    showErrorDetail(error, retryAction = null) {
      console.log('显示错误详情:', error)

      // 从API错误响应中提取详细信息
      if (error.data) {
        this.errorDetail = {
          code: error.data.code || error.code || 'UNKNOWN_ERROR',
          message: error.data.message || error.message || '操作失败',
          solution: error.data.solution || '请稍后重试',
          icon: error.data.icon || '❌',
          retry: error.data.retry !== undefined ? error.data.retry : true,
          contact_merchant: error.data.contact_merchant || false
        }
      } else {
        // 兜底：使用默认错误信息
        this.errorDetail = {
          code: error.code || 'UNKNOWN_ERROR',
          message: error.message || '操作失败',
          solution: '请检查网络连接后重试',
          icon: '❌',
          retry: true,
          contact_merchant: false
        }
      }

      this.lastFailedAction = retryAction
      this.showErrorDetail = true
    },

    /**
     * 关闭错误详情弹窗
     */
    closeErrorDetail() {
      this.showErrorDetail = false
      this.lastFailedAction = null
    },

    /**
     * 重试失败的操作
     */
    async retryAfterError() {
      if (this.lastFailedAction && typeof this.lastFailedAction === 'function') {
        try {
          await this.lastFailedAction()
        } catch (error) {
          console.error('重试失败:', error)
          this.showErrorDetail(error, this.lastFailedAction)
        }
      } else {
        FeedbackHelper.warning('无可重试的操作', { vibrate: false })
      }
    },

    /**
     * 联系商家
     */
    contactMerchant() {
      if (!this.deviceInfo || !this.deviceInfo.merchant) {
        FeedbackHelper.warning('商家信息不可用', { vibrate: true })
        return
      }

      const merchant = this.deviceInfo.merchant

      // 显示联系方式选择
      uni.showActionSheet({
        itemList: [
          merchant.phone ? `拨打电话: ${merchant.phone}` : null,
          merchant.wechat ? `复制微信号: ${merchant.wechat}` : null,
          '查看商家详情'
        ].filter(Boolean),
        success: (res) => {
          const index = res.tapIndex

          if (index === 0 && merchant.phone) {
            // 拨打电话
            uni.makePhoneCall({
              phoneNumber: merchant.phone
            })
          } else if (index === 1 && merchant.wechat) {
            // 复制微信号
            uni.setClipboardData({
              data: merchant.wechat,
              success: () => {
                FeedbackHelper.copySuccess(merchant.wechat)
              }
            })
          } else {
            // 查看商家详情
            uni.navigateTo({
              url: `/pages/merchant/detail?id=${merchant.id}`
            })
          }
        }
      })
    },

    /**
     * 联系商家
     */
    async contactMerchant() {
      if (!this.deviceInfo || !this.deviceInfo.merchant) {
        FeedbackHelper.warning('无法获取商家信息', { vibrate: true })
        return
      }

      const merchant = this.deviceInfo.merchant

      // 构建联系方式选项
      const itemList = []
      if (merchant.phone) {
        itemList.push('拨打电话：' + merchant.phone)
      }
      if (merchant.wechat) {
        itemList.push('复制微信：' + merchant.wechat)
      }
      itemList.push('返回重试')

      uni.showActionSheet({
        itemList,
        success: (res) => {
          const index = res.tapIndex

          if (index === 0 && merchant.phone) {
            // 拨打电话
            uni.makePhoneCall({
              phoneNumber: merchant.phone,
              fail: (err) => {
                console.error('拨打电话失败:', err)
              }
            })
          } else if (itemList[index].includes('微信') && merchant.wechat) {
            // 复制微信号
            uni.setClipboardData({
              data: merchant.wechat,
              success: () => {
                FeedbackHelper.copySuccess(merchant.wechat)
              }
            })
          }
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.trigger-page {
  min-height: 100vh;
  background: linear-gradient(180deg, #f8f9ff 0%, #ffffff 50%);
  padding-bottom: 100rpx;
}

// 导航栏
.nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 30rpx;
  background: #ffffff;
  border-bottom: 1rpx solid #e5e7eb;

  .nav-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
  }

  .nav-action {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 12rpx 24rpx;
    background: #6366f1;
    color: #ffffff;
    border-radius: 20rpx;
    font-size: 14px;

    .scan-icon {
      font-size: 18px;
    }
  }
}

// 内容区域
.content-wrapper {
  padding: 40rpx 30rpx;
}

// 空闲状态
.idle-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 100rpx 40rpx;

  .idle-icon {
    font-size: 100px;
    margin-bottom: 40rpx;
  }

  .idle-title {
    font-size: 24px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 20rpx;
  }

  .idle-desc {
    font-size: 14px;
    color: #6b7280;
    text-align: center;
    line-height: 1.6;
    margin-bottom: 60rpx;
  }

  .scan-btn {
    width: 400rpx;
  }
}

// 设备准备状态
.device-ready-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60rpx 40rpx;

  .device-card {
    background: #ffffff;
    border-radius: 20rpx;
    padding: 60rpx 40rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.06);
    text-align: center;
    margin-bottom: 60rpx;
    width: 100%;

    .device-icon-large {
      font-size: 80px;
      margin-bottom: 30rpx;
    }

    .device-name-large {
      font-size: 20px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 16rpx;
    }

    .device-code-text {
      font-size: 14px;
      color: #6b7280;
      font-family: monospace;
    }
  }

  .trigger-btn {
    width: 400rpx;
    margin-bottom: 20rpx;
  }

  .secondary-btn {
    width: 400rpx;
  }
}

// 触发中状态
.triggering-state {
  display: flex;
  flex-direction: column;
  align-items: center;

  .device-info {
    background: #ffffff;
    border-radius: 16rpx;
    padding: 40rpx;
    width: 100%;
    text-align: center;
    margin-bottom: 40rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

    .device-icon {
      font-size: 48px;
      margin-bottom: 20rpx;
    }

    .device-name {
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 12rpx;
    }

    .device-code {
      font-size: 12px;
      color: #9ca3af;
      font-family: monospace;
    }
  }

  .task-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 40rpx;

    .status-icon {
      font-size: 60px;
      margin-bottom: 20rpx;
      animation: pulse 2s ease-in-out infinite;

      &.status-processing {
        animation: rotate 2s linear infinite;
      }
    }

    .status-text {
      font-size: 16px;
      color: #4b5563;
      font-weight: 500;
    }
  }

  // AI进度组件区域
  .ai-progress-section {
    width: 100%;
    margin-bottom: 40rpx;
  }

  .progress-wrapper {
    width: 100%;
    margin-bottom: 40rpx;

    .progress-bar {
      width: 100%;
      height: 12rpx;
      background: #e5e7eb;
      border-radius: 6rpx;
      overflow: hidden;
      margin-bottom: 16rpx;

      .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
        border-radius: 6rpx;
        transition: width 0.3s ease;
      }
    }

    .progress-text {
      text-align: center;
      font-size: 14px;
      color: #6b7280;
      font-weight: 600;
    }
  }

  .generation-info {
    width: 100%;
    background: #f9fafb;
    border-radius: 12rpx;
    padding: 30rpx;
    margin-bottom: 40rpx;

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16rpx 0;
      border-bottom: 1rpx solid #e5e7eb;

      &:last-child {
        border-bottom: none;
      }

      .info-label {
        font-size: 14px;
        color: #6b7280;
      }

      .info-value {
        font-size: 14px;
        color: #1f2937;
        font-weight: 500;
      }
    }
  }

  .error-message {
    width: 100%;
    background: #fef2f2;
    border: 1rpx solid #fecaca;
    border-radius: 12rpx;
    padding: 30rpx;
    margin-bottom: 40rpx;
    display: flex;
    align-items: flex-start;
    gap: 16rpx;

    .error-icon {
      font-size: 24px;
      flex-shrink: 0;
    }

    .error-text {
      flex: 1;
      font-size: 14px;
      color: #dc2626;
      line-height: 1.6;
    }
  }

  .action-buttons {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 20rpx;

    button {
      width: 100%;
    }
  }
}

// 底部提示
.footer-hint {
  position: fixed;
  bottom: 40rpx;
  left: 0;
  right: 0;
  text-align: center;
  font-size: 12px;
  color: #9ca3af;
  padding: 0 30rpx;
}

// 加载遮罩
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;

  .loading-box {
    background: #ffffff;
    border-radius: 16rpx;
    padding: 60rpx 80rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30rpx;

    .loading-spinner {
      width: 60rpx;
      height: 60rpx;
      border: 4rpx solid #e5e7eb;
      border-top-color: #6366f1;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .loading-text {
      font-size: 14px;
      color: #6b7280;
    }
  }
}

// 按钮样式
.primary-btn {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: #ffffff;
  border: none;
  border-radius: 12rpx;
  padding: 24rpx 48rpx;
  font-size: 16px;
  font-weight: 600;

  &:active {
    opacity: 0.8;
  }
}

.secondary-btn {
  background: #ffffff;
  color: #6b7280;
  border: 1rpx solid #d1d5db;
  border-radius: 12rpx;
  padding: 24rpx 48rpx;
  font-size: 16px;

  &:active {
    background: #f9fafb;
  }
}

// 动画
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>

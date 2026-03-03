<template>
  <view class="promo-page">
    <!-- 自定义导航栏 -->
    <view class="nav-bar" :style="{ paddingTop: statusBarHeight + 'px' }">
      <view class="nav-content">
        <text class="nav-title">帮TA推广</text>
      </view>
    </view>

    <!-- 加载中 -->
    <view class="loading-state" v-if="loading">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>

    <!-- 错误状态 -->
    <view class="error-state" v-else-if="errorMsg">
      <text class="error-icon">!</text>
      <text class="error-text">{{ errorMsg }}</text>
      <button class="retry-btn" @tap="loadPromoInfo">重试</button>
    </view>

    <!-- 主内容 -->
    <view class="main-content" v-else-if="promoData">
      <!-- 商家信息卡片 -->
      <view class="merchant-card">
        <image
          class="merchant-logo"
          :src="promoData.merchant.logo || '/static/default-logo.png'"
          mode="aspectFill"
        />
        <view class="merchant-info">
          <text class="merchant-name">{{ promoData.merchant.name }}</text>
          <text class="merchant-desc">{{ promoData.merchant.description || '欢迎来店消费' }}</text>
        </view>
      </view>

      <!-- 视频预览区 -->
      <view class="video-section">
        <view class="section-title">推广视频</view>
        <view class="video-wrapper">
          <video
            class="promo-video"
            :src="promoData.video.url"
            :poster="promoData.video.thumbnail"
            controls
            object-fit="contain"
            :show-fullscreen-btn="true"
            :enable-progress-gesture="true"
          />
        </view>
        <text class="video-title" v-if="promoData.video.title">{{ promoData.video.title }}</text>
      </view>

      <!-- 文案预览区 -->
      <view class="copy-section">
        <view class="section-header">
          <text class="section-title">推广文案</text>
          <text class="copy-btn" @tap="handleCopyText">复制</text>
        </view>
        <view class="copy-content">
          <text class="copy-text">{{ fullCopyText }}</text>
        </view>
        <view class="tags-row" v-if="promoData.tags && promoData.tags.length">
          <text class="tag-item" v-for="(tag, i) in promoData.tags" :key="i">{{ tag }}</text>
        </view>
      </view>

      <!-- 奖励提示 -->
      <view class="reward-section" v-if="promoData.reward">
        <view class="reward-card">
          <view class="reward-icon-wrap">
            <text class="reward-icon">&#127873;</text>
          </view>
          <view class="reward-info">
            <text class="reward-title">发布后可领取</text>
            <text class="reward-name">{{ promoData.reward.title }}</text>
          </view>
        </view>
      </view>

      <!-- 操作按钮区 -->
      <view class="action-section">
        <button
          class="action-btn douyin-btn"
          @tap="handlePublish('douyin')"
          :disabled="publishing"
        >
          <text class="btn-text">{{ publishing && publishPlatform === 'douyin' ? '处理中...' : '发到抖音' }}</text>
        </button>
        <button
          class="action-btn kuaishou-btn"
          @tap="handlePublish('kuaishou')"
          :disabled="publishing"
        >
          <text class="btn-text">{{ publishing && publishPlatform === 'kuaishou' ? '处理中...' : '发到快手' }}</text>
        </button>
      </view>

      <!-- 已发布确认 -->
      <view class="confirm-section" v-if="videoSaved">
        <view class="confirm-divider">
          <view class="divider-line"></view>
          <text class="divider-text">视频已保存到相册</text>
          <view class="divider-line"></view>
        </view>
        <button
          class="confirm-btn"
          @tap="handleConfirmPublish"
          :disabled="confirming || claimed || confirmCountdown > 0"
        >
          <text class="btn-text">{{ confirmBtnText }}</text>
        </button>
      </view>

      <!-- 领取成功弹窗 -->
      <view class="reward-modal" v-if="showRewardModal" @tap="showRewardModal = false">
        <view class="reward-modal-content" @tap.stop>
          <text class="reward-modal-icon">&#127881;</text>
          <text class="reward-modal-title">领取成功!</text>
          <text class="reward-modal-desc" v-if="rewardInfo">
            {{ rewardInfo.title }}
          </text>
          <text class="reward-modal-code" v-if="rewardInfo && rewardInfo.coupon_code">
            优惠券码: {{ rewardInfo.coupon_code }}
          </text>
          <button class="reward-modal-btn" @tap="showRewardModal = false">
            我知道了
          </button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'
import { saveVideoAndLaunch, copyToClipboard } from '../../utils/platformLauncher.js'

export default {
  data() {
    return {
      statusBarHeight: 20,
      deviceCode: '',
      loading: true,
      errorMsg: '',
      promoData: null,
      triggerId: null,
      triggerTime: 0,  // 触发时间戳

      // 发布状态
      publishing: false,
      publishPlatform: '',
      videoSaved: false,
      lastPlatform: '',

      // 确认状态
      confirming: false,
      claimed: false,
      showRewardModal: false,
      rewardInfo: null,

      // 等待倒计时
      confirmCountdown: 0,
      countdownTimer: null,
    }
  },

  computed: {
    fullCopyText() {
      if (!this.promoData) return ''
      let text = this.promoData.copywriting || ''
      if (this.promoData.tags && this.promoData.tags.length) {
        text += ' ' + this.promoData.tags.join(' ')
      }
      return text
    },

    confirmBtnText() {
      if (this.claimed) return '已领取奖励'
      if (this.confirming) return '确认中...'
      if (this.confirmCountdown > 0) return `请先发布视频 (${this.confirmCountdown}s)`
      return '我已发布，领取奖励'
    },
  },

  onLoad(options) {
    // 获取状态栏高度
    const sysInfo = uni.getSystemInfoSync()
    this.statusBarHeight = sysInfo.statusBarHeight || 20

    // 从URL参数获取设备码
    if (options.device_code) {
      this.deviceCode = options.device_code
    } else if (options.code) {
      this.deviceCode = options.code
    }

    if (this.deviceCode) {
      this.loadPromoInfo()
    } else {
      this.loading = false
      this.errorMsg = '缺少设备码参数'
    }
  },

  onUnload() {
    if (this.countdownTimer) {
      clearInterval(this.countdownTimer)
      this.countdownTimer = null
    }
  },

  methods: {
    /**
     * 加载推广信息
     */
    async loadPromoInfo() {
      this.loading = true
      this.errorMsg = ''

      try {
        const res = await api.promo.getPromoInfo(this.deviceCode)

        if (res.type !== 'promo') {
          // 非推广模式，提示错误
          this.errorMsg = '该设备未配置推广功能'
          return
        }

        this.promoData = res
        this.triggerId = res.trigger_id
        this.triggerTime = Math.floor(Date.now() / 1000)

        // 启动确认倒计时（60秒后才能领取）
        this.startConfirmCountdown()

        // 检查是否已领取奖励
        if (this.triggerId) {
          this.checkRewardStatus()
        }
      } catch (err) {
        console.error('加载推广信息失败:', err)
        this.errorMsg = err.message || '加载失败，请重试'
      } finally {
        this.loading = false
      }
    },

    /**
     * 检查奖励领取状态
     */
    async checkRewardStatus() {
      try {
        const res = await api.promo.getRewardStatus(this.triggerId)
        if (res.total > 0) {
          this.claimed = true
          this.videoSaved = true
        }
      } catch (e) {
        // 忽略错误，不影响页面展示
      }
    },

    /**
     * 复制文案
     */
    async handleCopyText() {
      const success = await copyToClipboard(this.fullCopyText)
      if (success) {
        uni.showToast({ title: '文案已复制', icon: 'success' })
      }
    },

    /**
     * 处理发布操作：下载视频 + 复制文案 + 唤起APP
     */
    async handlePublish(platform) {
      if (this.publishing) return

      this.publishing = true
      this.publishPlatform = platform

      try {
        const result = await saveVideoAndLaunch(
          platform,
          this.promoData.video.url,
          this.fullCopyText
        )

        if (result.success || result.steps.download) {
          this.videoSaved = true
          this.lastPlatform = platform
        }

        if (!result.steps.download) {
          uni.showToast({ title: result.error || '操作失败', icon: 'none' })
        }
      } catch (err) {
        console.error('发布操作失败:', err)
        uni.showToast({ title: '操作失败，请重试', icon: 'none' })
      } finally {
        this.publishing = false
        this.publishPlatform = ''
      }
    },

    /**
     * 启动确认倒计时
     */
    startConfirmCountdown() {
      this.confirmCountdown = 60
      if (this.countdownTimer) clearInterval(this.countdownTimer)
      this.countdownTimer = setInterval(() => {
        this.confirmCountdown--
        if (this.confirmCountdown <= 0) {
          clearInterval(this.countdownTimer)
          this.countdownTimer = null
        }
      }, 1000)
    },

    /**
     * 确认已发布，领取奖励
     */
    async handleConfirmPublish() {
      if (this.confirming || this.claimed) return
      if (this.confirmCountdown > 0) {
        uni.showToast({ title: '请先发布视频后再领取', icon: 'none' })
        return
      }

      this.confirming = true

      try {
        const res = await api.promo.confirmPublish({
          device_code: this.deviceCode,
          platform: this.lastPlatform || 'douyin',
          trigger_id: this.triggerId,
        })

        if (res.status === 'already_claimed') {
          this.claimed = true
          uni.showToast({ title: '您已领取过奖励', icon: 'none' })
          return
        }

        // 领取成功
        this.claimed = true
        this.rewardInfo = res.coupon
        this.showRewardModal = true
      } catch (err) {
        console.error('确认发布失败:', err)
        uni.showToast({ title: err.message || '操作失败', icon: 'none' })
      } finally {
        this.confirming = false
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.promo-page {
  min-height: 100vh;
  background: #f5f5f7;
}

// 导航栏
.nav-bar {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  .nav-content {
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .nav-title {
    font-size: 17px;
    font-weight: 600;
    color: #fff;
  }
}

// 加载状态
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 200rpx;

  .loading-spinner {
    width: 60rpx;
    height: 60rpx;
    border: 4rpx solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }
  .loading-text {
    margin-top: 20rpx;
    font-size: 14px;
    color: #9ca3af;
  }
}

// 错误状态
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 200rpx;

  .error-icon {
    width: 80rpx;
    height: 80rpx;
    line-height: 80rpx;
    text-align: center;
    background: #fef2f2;
    color: #ef4444;
    border-radius: 50%;
    font-size: 40rpx;
    font-weight: bold;
  }
  .error-text {
    margin-top: 24rpx;
    font-size: 15px;
    color: #6b7280;
  }
  .retry-btn {
    margin-top: 40rpx;
    width: 240rpx;
    height: 80rpx;
    line-height: 80rpx;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 12rpx;
    font-size: 15px;
  }
}

// 主内容
.main-content {
  padding: 24rpx;
}

// 商家信息卡片
.merchant-card {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 16rpx;
  padding: 28rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);

  .merchant-logo {
    width: 88rpx;
    height: 88rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
    background: #f3f4f6;
  }
  .merchant-info {
    margin-left: 24rpx;
    flex: 1;
    overflow: hidden;
  }
  .merchant-name {
    font-size: 17px;
    font-weight: 600;
    color: #1f2937;
    display: block;
  }
  .merchant-desc {
    font-size: 13px;
    color: #9ca3af;
    margin-top: 6rpx;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

// 视频区域
.video-section {
  background: #fff;
  border-radius: 16rpx;
  padding: 28rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);

  .video-wrapper {
    margin-top: 16rpx;
    border-radius: 12rpx;
    overflow: hidden;
  }
  .promo-video {
    width: 100%;
    height: 400rpx;
  }
  .video-title {
    font-size: 14px;
    color: #6b7280;
    margin-top: 12rpx;
    display: block;
  }
}

// 文案区域
.copy-section {
  background: #fff;
  border-radius: 16rpx;
  padding: 28rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .copy-btn {
    font-size: 13px;
    color: #6366f1;
    padding: 8rpx 20rpx;
    background: #eef2ff;
    border-radius: 8rpx;
  }
  .copy-content {
    margin-top: 16rpx;
    padding: 20rpx;
    background: #f9fafb;
    border-radius: 10rpx;
  }
  .copy-text {
    font-size: 14px;
    color: #374151;
    line-height: 1.7;
  }
  .tags-row {
    margin-top: 16rpx;
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
  }
  .tag-item {
    font-size: 12px;
    color: #6366f1;
    background: #eef2ff;
    padding: 6rpx 16rpx;
    border-radius: 6rpx;
  }
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

// 奖励区域
.reward-section {
  margin-bottom: 24rpx;

  .reward-card {
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 16rpx;
    padding: 28rpx;
  }
  .reward-icon-wrap {
    width: 72rpx;
    height: 72rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.6);
    border-radius: 12rpx;
    flex-shrink: 0;
  }
  .reward-icon {
    font-size: 32rpx;
  }
  .reward-info {
    margin-left: 20rpx;
  }
  .reward-title {
    font-size: 13px;
    color: #92400e;
    display: block;
  }
  .reward-name {
    font-size: 17px;
    font-weight: 600;
    color: #78350f;
    margin-top: 4rpx;
    display: block;
  }
}

// 操作按钮
.action-section {
  display: flex;
  gap: 20rpx;
  margin-bottom: 32rpx;

  .action-btn {
    flex: 1;
    height: 96rpx;
    border: none;
    border-radius: 14rpx;
    display: flex;
    align-items: center;
    justify-content: center;

    .btn-text {
      font-size: 16px;
      font-weight: 600;
      color: #fff;
    }

    &[disabled] {
      opacity: 0.6;
    }
  }
  .douyin-btn {
    background: linear-gradient(135deg, #111827 0%, #374151 100%);
  }
  .kuaishou-btn {
    background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
  }
}

// 确认发布区
.confirm-section {
  padding-bottom: 60rpx;

  .confirm-divider {
    display: flex;
    align-items: center;
    margin-bottom: 28rpx;

    .divider-line {
      flex: 1;
      height: 1rpx;
      background: #e5e7eb;
    }
    .divider-text {
      padding: 0 20rpx;
      font-size: 12px;
      color: #9ca3af;
    }
  }
  .confirm-btn {
    width: 100%;
    height: 96rpx;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    border: none;
    border-radius: 14rpx;
    font-size: 16px;
    font-weight: 600;

    &[disabled] {
      opacity: 0.6;
    }
  }
}

// 领取成功弹窗
.reward-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;

  .reward-modal-content {
    width: 560rpx;
    background: #fff;
    border-radius: 24rpx;
    padding: 60rpx 40rpx;
    text-align: center;
  }
  .reward-modal-icon {
    font-size: 80rpx;
    display: block;
  }
  .reward-modal-title {
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
    margin-top: 20rpx;
    display: block;
  }
  .reward-modal-desc {
    font-size: 15px;
    color: #6b7280;
    margin-top: 16rpx;
    display: block;
  }
  .reward-modal-code {
    font-size: 14px;
    color: #6366f1;
    background: #eef2ff;
    padding: 16rpx 24rpx;
    border-radius: 10rpx;
    margin-top: 24rpx;
    display: inline-block;
    font-family: monospace;
  }
  .reward-modal-btn {
    width: 360rpx;
    height: 88rpx;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
    margin-top: 40rpx;
  }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>

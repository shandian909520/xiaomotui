<template>
  <view class="hub-header">
    <!-- 封面 -->
    <view class="cover-wrap">
      <image
        class="cover"
        :src="bundle.cover"
        mode="aspectFill"
        v-if="bundle && bundle.cover"
      />
      <view class="cover cover-placeholder" v-else>
        <text class="cover-placeholder-icon">🎁</text>
      </view>
      <view class="cover-mask"></view>
      <view class="header-text">
        <view class="title">{{ bundle && bundle.title ? bundle.title : '完成任务领奖励' }}</view>
        <view class="subtitle" v-if="bundle && bundle.subtitle">{{ bundle.subtitle }}</view>
      </view>
    </view>

    <!-- 进度 + 倒计时 -->
    <view class="status-bar">
      <view class="progress-info">
        <text class="progress-label">任务进度</text>
        <text class="progress-num">{{ completedCount }}/{{ totalCount }}</text>
      </view>
      <view class="progress-track">
        <view class="progress-fill" :style="{ width: progressPercent + '%' }"></view>
      </view>
      <view class="countdown" :class="{ 'countdown-expired': isExpired }">
        <text v-if="isExpired">已过期</text>
        <text v-else-if="countdownText">剩余时间 {{ countdownText }}</text>
        <text v-else-if="expiredAt">长期有效</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'HubHeader',

  props: {
    // 任务包信息 {title, subtitle, cover, reward_type, ...}
    bundle: {
      type: Object,
      default: null
    },
    // 任务实例 {status, progress, reward_status, expired_at}
    instance: {
      type: Object,
      default: null
    },
    // 动作总数（由页面传入，避免组件自己数）
    totalCount: {
      type: Number,
      default: 0
    },
    // 已完成动作数
    completedCount: {
      type: Number,
      default: 0
    }
  },

  data() {
    return {
      countdownTimer: null,
      now: Date.now()
    }
  },

  computed: {
    progressPercent() {
      if (!this.totalCount) return 0
      return Math.min(100, Math.round((this.completedCount / this.totalCount) * 100))
    },

    expiredAt() {
      const at = this.instance && this.instance.expired_at
      if (!at) return 0
      const ts = new Date(String(at).replace(/-/g, '/')).getTime()
      return isNaN(ts) ? 0 : ts
    },

    isExpired() {
      if (!this.expiredAt) return false
      return this.now >= this.expiredAt
    },

    countdownText() {
      if (!this.expiredAt || this.isExpired) return ''
      let diff = Math.floor((this.expiredAt - this.now) / 1000)
      if (diff <= 0) return ''
      const days = Math.floor(diff / 86400)
      diff %= 86400
      const hours = Math.floor(diff / 3600)
      diff %= 3600
      const minutes = Math.floor(diff / 60)
      const seconds = diff % 60
      const pad = (n) => (n < 10 ? '0' + n : '' + n)
      if (days > 0) {
        return `${days}天${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
      }
      return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
    }
  },

  mounted() {
    this.startCountdown()
  },

  beforeUnmount() {
    this.stopCountdown()
  },

  methods: {
    startCountdown() {
      this.stopCountdown()
      this.countdownTimer = setInterval(() => {
        this.now = Date.now()
      }, 1000)
    },

    stopCountdown() {
      if (this.countdownTimer) {
        clearInterval(this.countdownTimer)
        this.countdownTimer = null
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.hub-header {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.06);
}

.cover-wrap {
  position: relative;
  width: 100%;
  height: 300rpx;

  .cover {
    width: 100%;
    height: 100%;
    display: block;
  }

  .cover-placeholder {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;

    .cover-placeholder-icon {
      font-size: 80rpx;
    }
  }

  .cover-mask {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 160rpx;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);
  }

  .header-text {
    position: absolute;
    left: 30rpx;
    right: 30rpx;
    bottom: 20rpx;
    color: #ffffff;

    .title {
      font-size: 36rpx;
      font-weight: bold;
    }

    .subtitle {
      margin-top: 8rpx;
      font-size: 26rpx;
      opacity: 0.9;
    }
  }
}

.status-bar {
  padding: 24rpx 30rpx 30rpx;

  .progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .progress-label {
      font-size: 26rpx;
      color: #6b7280;
    }

    .progress-num {
      font-size: 28rpx;
      font-weight: bold;
      color: #6366f1;
    }
  }

  .progress-track {
    margin-top: 16rpx;
    height: 14rpx;
    background: #eef0f4;
    border-radius: 8rpx;
    overflow: hidden;

    .progress-fill {
      height: 100%;
      border-radius: 8rpx;
      background: linear-gradient(90deg, #6366f1, #8b5cf6);
      transition: width 0.3s ease;
    }
  }

  .countdown {
    margin-top: 16rpx;
    font-size: 24rpx;
    color: #9ca3af;

    &.countdown-expired {
      color: #ef4444;
      font-weight: bold;
    }
  }
}
</style>

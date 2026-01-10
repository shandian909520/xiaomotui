<template>
  <view class="error-page">
    <view class="error-content">
      <view class="error-icon">🔍</view>
      <view class="error-code">404</view>
      <view class="error-title">页面不存在</view>
      <view class="error-desc">抱歉，您访问的页面不存在或已被删除</view>

      <view class="error-actions">
        <button class="btn-primary" @tap="goHome">返回首页</button>
        <button class="btn-secondary" @tap="goBack">返回上页</button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      countdown: 5
    }
  },

  onLoad() {
    // 5秒后自动返回首页
    this.startCountdown()
  },

  methods: {
    startCountdown() {
      const timer = setInterval(() => {
        this.countdown--
        if (this.countdown <= 0) {
          clearInterval(timer)
          this.goHome()
        }
      }, 1000)
    },

    goHome() {
      uni.switchTab({
        url: '/pages/index/index'
      })
    },

    goBack() {
      uni.navigateBack({
        fail: () => {
          this.goHome()
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f8f9fa;
  padding: 40rpx;
}

.error-content {
  text-align: center;
  width: 100%;
  max-width: 600rpx;
}

.error-icon {
  font-size: 120rpx;
  margin-bottom: 40rpx;
}

.error-code {
  font-size: 80rpx;
  font-weight: bold;
  color: #6366f1;
  margin-bottom: 20rpx;
}

.error-title {
  font-size: 36rpx;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 16rpx;
}

.error-desc {
  font-size: 28rpx;
  color: #64748b;
  line-height: 1.6;
  margin-bottom: 60rpx;
}

.error-actions {
  display: flex;
  flex-direction: column;
  gap: 20rpx;

  button {
    width: 100%;
    padding: 28rpx;
    border-radius: 12rpx;
    font-size: 32rpx;
    border: none;
  }

  .btn-primary {
    background-color: #6366f1;
    color: #ffffff;
  }

  .btn-secondary {
    background-color: #e2e8f0;
    color: #475569;
  }
}
</style>

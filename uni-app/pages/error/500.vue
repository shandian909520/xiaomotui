<template>
  <view class="error-page">
    <view class="error-content">
      <view class="error-icon">⚠️</view>
      <view class="error-code">500</view>
      <view class="error-title">服务器错误</view>
      <view class="error-desc">服务器开小差了，请稍后再试</view>

      <view class="error-details" v-if="errorMessage">
        <view class="details-title">错误详情:</view>
        <view class="details-content">{{ errorMessage }}</view>
      </view>

      <view class="error-actions">
        <button class="btn-primary" @tap="retry">重试</button>
        <button class="btn-secondary" @tap="goHome">返回首页</button>
      </view>

      <view class="error-tips">
        <text>如果问题持续存在，请联系客服</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      errorMessage: ''
    }
  },

  onLoad(options) {
    if (options.message) {
      this.errorMessage = decodeURIComponent(options.message)
    }
  },

  methods: {
    retry() {
      // 重新加载当前页面
      uni.navigateBack({
        fail: () => {
          this.goHome()
        }
      })
    },

    goHome() {
      uni.switchTab({
        url: '/pages/index/index'
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
  color: #ef4444;
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
  margin-bottom: 40rpx;
}

.error-details {
  background-color: #fff;
  border-radius: 12rpx;
  padding: 24rpx;
  margin-bottom: 40rpx;
  text-align: left;

  .details-title {
    font-size: 28rpx;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 12rpx;
  }

  .details-content {
    font-size: 24rpx;
    color: #64748b;
    line-height: 1.6;
    word-break: break-all;
  }
}

.error-actions {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
  margin-bottom: 40rpx;

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

.error-tips {
  font-size: 24rpx;
  color: #94a3b8;
}
</style>

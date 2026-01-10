<template>
  <view class="error-page">
    <view class="error-content">
      <view class="error-icon">📡</view>
      <view class="error-title">网络连接失败</view>
      <view class="error-desc">请检查您的网络设置后重试</view>

      <view class="network-status">
        <view class="status-item">
          <text class="status-label">网络状态:</text>
          <text class="status-value" :class="networkClass">{{ networkText }}</text>
        </view>
      </view>

      <view class="error-actions">
        <button class="btn-primary" @tap="checkNetwork">检查网络</button>
        <button class="btn-secondary" @tap="retry">重新加载</button>
        <button class="btn-secondary" @tap="goHome">返回首页</button>
      </view>

      <view class="error-tips">
        <view class="tip-item">• 检查WiFi或移动数据是否开启</view>
        <view class="tip-item">• 尝试切换网络环境</view>
        <view class="tip-item">• 检查是否开启了飞行模式</view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      networkType: 'none',
      isConnected: false
    }
  },

  computed: {
    networkText() {
      const typeMap = {
        'wifi': 'WiFi已连接',
        '2g': '2G网络',
        '3g': '3G网络',
        '4g': '4G网络',
        '5g': '5G网络',
        'none': '无网络连接',
        'unknown': '网络异常'
      }
      return typeMap[this.networkType] || '未知'
    },

    networkClass() {
      return this.isConnected ? 'status-online' : 'status-offline'
    }
  },

  onLoad() {
    this.checkNetwork()
  },

  methods: {
    checkNetwork() {
      uni.showLoading({
        title: '检查中...'
      })

      uni.getNetworkType({
        success: (res) => {
          this.networkType = res.networkType
          this.isConnected = res.networkType !== 'none'

          uni.hideLoading()

          if (this.isConnected) {
            uni.showToast({
              title: '网络已连接',
              icon: 'success'
            })
          } else {
            uni.showToast({
              title: '未连接到网络',
              icon: 'none'
            })
          }
        },
        fail: () => {
          uni.hideLoading()
          uni.showToast({
            title: '检查失败',
            icon: 'none'
          })
        }
      })
    },

    retry() {
      if (!this.isConnected) {
        uni.showToast({
          title: '请先连接网络',
          icon: 'none'
        })
        return
      }

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

.network-status {
  background-color: #fff;
  border-radius: 12rpx;
  padding: 24rpx;
  margin-bottom: 40rpx;

  .status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .status-label {
      font-size: 28rpx;
      color: #64748b;
    }

    .status-value {
      font-size: 28rpx;
      font-weight: 600;

      &.status-online {
        color: #10b981;
      }

      &.status-offline {
        color: #ef4444;
      }
    }
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
  text-align: left;
  padding: 24rpx;
  background-color: #fff;
  border-radius: 12rpx;

  .tip-item {
    font-size: 24rpx;
    color: #64748b;
    line-height: 2;
  }
}
</style>

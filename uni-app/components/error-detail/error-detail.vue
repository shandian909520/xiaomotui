<template>
  <view class="error-detail-modal" v-if="visible" @click="handleMaskClick">
    <view class="error-content" @click.stop>
      <!-- 错误图标 -->
      <view class="error-icon">
        <text class="icon-emoji">{{ errorInfo.icon || '❌' }}</text>
      </view>

      <!-- 错误标题 -->
      <view class="error-title">
        <text>{{ errorInfo.message || '操作失败' }}</text>
      </view>

      <!-- 错误代码 -->
      <view class="error-code" v-if="errorInfo.code">
        <text>错误代码: {{ errorInfo.code }}</text>
      </view>

      <!-- 解决方案 -->
      <view class="error-solution" v-if="errorInfo.solution">
        <view class="solution-label">
          <text>💡 解决方案</text>
        </view>
        <view class="solution-content">
          <text>{{ errorInfo.solution }}</text>
        </view>
      </view>

      <!-- 操作按钮 -->
      <view class="error-actions">
        <!-- 联系商家按钮（如果需要） -->
        <button
          v-if="errorInfo.contact_merchant"
          class="action-btn contact-btn"
          @click="handleContact"
        >
          📞 联系商家
        </button>

        <!-- 重试按钮（如果可重试） -->
        <button
          v-if="errorInfo.retry"
          class="action-btn retry-btn"
          @click="handleRetry"
        >
          🔄 重试
        </button>

        <!-- 关闭按钮 -->
        <button
          class="action-btn close-btn"
          :class="{ 'full-width': !errorInfo.retry && !errorInfo.contact_merchant }"
          @click="handleClose"
        >
          {{ errorInfo.retry || errorInfo.contact_merchant ? '关闭' : '我知道了' }}
        </button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'ErrorDetail',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    errorInfo: {
      type: Object,
      default: () => ({
        code: '',
        message: '',
        solution: '',
        icon: '❌',
        retry: false,
        contact_merchant: false
      })
    }
  },
  methods: {
    /**
     * 点击遮罩层（可选择是否关闭）
     */
    handleMaskClick() {
      // 可以选择点击遮罩不关闭，或者关闭
      // this.handleClose()
    },

    /**
     * 关闭弹窗
     */
    handleClose() {
      this.$emit('close')
    },

    /**
     * 重试操作
     */
    handleRetry() {
      this.$emit('retry')
      this.handleClose()
    },

    /**
     * 联系商家
     */
    handleContact() {
      this.$emit('contact')
      // 可以选择是否关闭弹窗
      // this.handleClose()
    }
  }
}
</script>

<style lang="scss" scoped>
.error-detail-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.error-content {
  width: 80%;
  max-width: 600rpx;
  background: white;
  border-radius: 24rpx;
  padding: 60rpx 40rpx 40rpx;
  animation: slideUp 0.3s ease;
  box-shadow: 0 8rpx 40rpx rgba(0, 0, 0, 0.15);
}

@keyframes slideUp {
  from {
    transform: translateY(100rpx);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.error-icon {
  text-align: center;
  margin-bottom: 30rpx;

  .icon-emoji {
    font-size: 100rpx;
    line-height: 1;
  }
}

.error-title {
  text-align: center;
  margin-bottom: 20rpx;

  text {
    font-size: 36rpx;
    font-weight: 600;
    color: #333;
  }
}

.error-code {
  text-align: center;
  margin-bottom: 30rpx;

  text {
    font-size: 24rpx;
    color: #999;
  }
}

.error-solution {
  background: #f8f9fa;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 40rpx;

  .solution-label {
    margin-bottom: 16rpx;

    text {
      font-size: 28rpx;
      font-weight: 600;
      color: #333;
    }
  }

  .solution-content {
    text {
      font-size: 26rpx;
      line-height: 1.6;
      color: #666;
    }
  }
}

.error-actions {
  display: flex;
  gap: 20rpx;

  .action-btn {
    flex: 1;
    height: 88rpx;
    line-height: 88rpx;
    border-radius: 12rpx;
    font-size: 30rpx;
    font-weight: 500;
    border: none;
    transition: all 0.3s;

    &.full-width {
      flex: 1 1 100%;
    }

    &.retry-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;

      &:active {
        opacity: 0.8;
        transform: scale(0.98);
      }
    }

    &.contact-btn {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;

      &:active {
        opacity: 0.8;
        transform: scale(0.98);
      }
    }

    &.close-btn {
      background: #f5f5f5;
      color: #666;

      &:active {
        opacity: 0.8;
        transform: scale(0.98);
      }

      &.full-width {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
      }
    }
  }
}
</style>

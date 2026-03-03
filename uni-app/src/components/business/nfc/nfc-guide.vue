<template>
  <view class="first-time-guide" v-if="visible">
    <view class="guide-mask" @tap="handleSkip"></view>
    <view class="guide-content">
      <view class="guide-header">
        <text class="guide-title">🎯 如何使用碰一碰</text>
        <text class="guide-close" @tap="handleSkip">✕</text>
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
        <button class="guide-skip-btn" @tap="handleSkip">跳过</button>
        <button class="guide-start-btn" @tap="handleStart">我知道了</button>
      </view>

      <view class="guide-checkbox">
        <checkbox :checked="dontShowAgain" @change="onCheckboxChange" />
        <text>不再提示</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'NfcGuide',

  props: {
    visible: {
      type: Boolean,
      default: false
    }
  },

  data() {
    return {
      dontShowAgain: false
    }
  },

  methods: {
    handleSkip() {
      this.$emit('skip')
    },

    handleStart() {
      this.$emit('start')
    },

    onCheckboxChange(e) {
      this.dontShowAgain = e.detail.value.length > 0
      this.$emit('dont-show', this.dontShowAgain)
    }
  }
}
</script>

<style lang="scss" scoped>
.first-time-guide {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;

  .guide-mask {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
  }

  .guide-content {
    position: relative;
    margin: 120rpx 30rpx;
    background: #ffffff;
    border-radius: 20rpx;
    padding: 40rpx;
    z-index: 1;

    .guide-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40rpx;

      .guide-title {
        font-size: 20px;
        font-weight: 600;
        color: #1f2937;
      }

      .guide-close {
        font-size: 24px;
        color: #9ca3af;
        padding: 0 10rpx;
      }
    }

    .guide-steps {
      margin-bottom: 40rpx;

      .guide-step {
        display: flex;
        gap: 20rpx;
        margin-bottom: 40rpx;

        &:last-child {
          margin-bottom: 0;
        }

        .step-number {
          width: 48rpx;
          height: 48rpx;
          background: #6366f1;
          color: #ffffff;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 16px;
          font-weight: 600;
          flex-shrink: 0;
        }

        .step-content {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 12rpx;

          .step-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
          }

          .step-desc {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
          }

          .step-image {
            width: 100%;
            height: 200rpx;
            border-radius: 12rpx;
            background: #f3f4f6;
            margin-top: 12rpx;
          }
        }
      }
    }

    .guide-footer {
      display: flex;
      gap: 20rpx;
      margin-bottom: 30rpx;

      button {
        flex: 1;
        border: none;
        border-radius: 12rpx;
        padding: 24rpx;
        font-size: 16px;
        font-weight: 600;

        &:active {
          opacity: 0.8;
        }
      }

      .guide-skip-btn {
        background: #f3f4f6;
        color: #6b7280;
      }

      .guide-start-btn {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: #ffffff;
      }
    }

    .guide-checkbox {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12rpx;

      text {
        font-size: 14px;
        color: #6b7280;
      }
    }
  }
}
</style>

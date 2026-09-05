<template>
  <view class="action-card" :class="{ 'action-card-done': isCompleted }">
    <!-- 主行：图标 + 名称 + 状态徽标 -->
    <view class="card-main">
      <view class="action-icon">
        <image v-if="action.action_icon" :src="action.action_icon" mode="aspectFill" class="icon-img" />
        <text v-else class="icon-default">🎯</text>
      </view>
      <view class="action-info">
        <view class="action-name">{{ action.action_name }}</view>
        <view class="action-required" v-if="action.required">
          <text class="required-tag">必做</text>
        </view>
      </view>
      <view class="state-badge" :class="'badge-' + (state || 'PENDING')">
        {{ stateText }}
      </view>
    </view>

    <!-- 未通过提示 -->
    <view class="reject-tip" v-if="state === 'REJECTED'">
      <text>⚠️ 凭证审核未通过，请重新上传</text>
    </view>

    <!-- 展开的卡片区域：开始后展示 -->
    <view class="card-body" v-if="isStarted && mergedCard">
      <!-- scheme 类型 -->
      <block v-if="mergedCard.jump_type === 'scheme'">
        <view class="guide-steps" v-if="mergedCard.guide_steps && mergedCard.guide_steps.length">
          <view class="step" v-for="(step, idx) in mergedCard.guide_steps" :key="idx">
            <text class="step-index">{{ idx + 1 }}</text>
            <text class="step-text">{{ step }}</text>
          </view>
        </view>

        <!-- #ifdef MP-WEIXIN -->
        <view class="env-tip">当前在小程序内，无法直接打开APP，请使用下方口令或二维码</view>
        <!-- #endif -->

        <!-- #ifndef MP-WEIXIN -->
        <button class="card-btn primary-btn" @tap="handleOpenApp" v-if="mergedCard.scheme_url">
          打开APP
        </button>
        <!-- #endif -->
      </block>

      <!-- qrcode 类型 -->
      <block v-if="mergedCard.jump_type === 'qrcode' && mergedCard.qrcode_url">
        <view class="qrcode-wrap">
          <image class="qrcode" :src="mergedCard.qrcode_url" mode="aspectFit" />
          <view class="qrcode-tip">长按识别二维码完成操作</view>
        </view>
      </block>

      <!-- 复制口令 -->
      <button
        class="card-btn copy-btn"
        v-if="mergedCard.copy_text"
        @tap="handleCopy"
      >
        复制口令
      </button>
    </view>

    <!-- 底部操作按钮 -->
    <view class="card-footer">
      <!-- 已完成 -->
      <view class="done-mark" v-if="isCompleted">
        <text class="done-check">✓</text>
        <text>已完成</text>
      </view>

      <!-- 审核中 -->
      <view class="verifying-mark" v-else-if="state === 'VERIFYING'">
        <text>审核中，请稍候…</text>
      </view>

      <!-- 未通过：重新上传 -->
      <button class="card-btn primary-btn" v-else-if="state === 'REJECTED'" @tap="handleUpload">
        重新上传凭证
      </button>

      <!-- 领券类：直接完成 -->
      <button
        class="card-btn primary-btn"
        v-else-if="isClaimCoupon"
        @tap="handleStart"
      >
        领取优惠券
      </button>

      <!-- 信任模式：开始后可上传凭证 -->
      <button
        class="card-btn upload-btn"
        v-else-if="needProof && isStarted"
        @tap="handleUpload"
      >
        上传凭证
      </button>

      <!-- 默认：去完成 -->
      <button
        class="card-btn primary-btn"
        v-else
        @tap="handleStart"
        :disabled="isExpired"
      >
        {{ isStarted ? '继续完成' : '去完成' }}
      </button>
    </view>
  </view>
</template>

<script>
import envDetect from './env-detect.js'

// 需要上传凭证的插件（信任模式；回调类插件降级后同样走凭证）
const PROOF_PLUGINS = [
  'douyin_publish',
  'kuaishou_publish',
  'xiaohongshu_publish',
  'moments_share',           // 朋友圈分享
  'group_share',             // 群分享
  'official_account_follow', // 关注公众号（回调降级时）
  'wework_add_friend'        // 加企微（回调降级时）
]

export default {
  name: 'ActionCard',

  props: {
    // 动作对象 {id, plugin_key, action_name, action_icon, required, card}
    action: {
      type: Object,
      required: true
    },
    // 进度状态 PENDING/STARTED/VERIFYING/COMPLETED/REJECTED
    state: {
      type: String,
      default: 'PENDING'
    },
    // start 接口返回的最新卡片数据（覆盖 action.card）
    card: {
      type: Object,
      default: null
    },
    // 实例是否已过期
    expired: {
      type: Boolean,
      default: false
    }
  },

  computed: {
    mergedCard() {
      return this.card || (this.action && this.action.card) || null
    },

    isCompleted() {
      return this.state === 'COMPLETED'
    },

    isStarted() {
      return !!this.state && this.state !== 'PENDING'
    },

    isExpired() {
      return this.expired
    },

    isClaimCoupon() {
      return this.action && this.action.plugin_key === 'claim_coupon'
    },

    needProof() {
      if (!this.action) return false
      if (typeof this.action.need_proof === 'boolean') return this.action.need_proof
      if (this.mergedCard && typeof this.mergedCard.need_proof === 'boolean') {
        return this.mergedCard.need_proof
      }
      return PROOF_PLUGINS.includes(this.action.plugin_key)
    },

    stateText() {
      const map = {
        PENDING: '待完成',
        STARTED: '进行中',
        VERIFYING: '审核中',
        COMPLETED: '已完成',
        REJECTED: '未通过'
      }
      return map[this.state] || '待完成'
    }
  },

  methods: {
    /**
     * 去完成 / 继续完成 / 领券
     */
    handleStart() {
      if (this.isExpired) {
        uni.showToast({ title: '任务已过期', icon: 'none' })
        return
      }
      this.$emit('start', this.action)
    },

    /**
     * 打开APP（scheme 唤起，2.5秒无响应提示降级）
     */
    handleOpenApp() {
      const card = this.mergedCard
      if (!card || !card.scheme_url) return

      const suggestion = envDetect.suggestJump(card)

      // #ifdef MP-WEIXIN
      uni.showToast({ title: '小程序内无法打开APP，请使用口令或二维码', icon: 'none', duration: 2500 })
      return
      // #endif

      // #ifdef H5
      if (suggestion !== 'scheme') {
        uni.showToast({
          title: suggestion === 'copy' ? '微信内无法直接打开，请复制口令' : '请使用二维码方式',
          icon: 'none',
          duration: 2000
        })
        if (suggestion === 'copy' && card.copy_text) {
          this.handleCopy()
        }
        return
      }

      // 记录时间点，2.5秒后页面仍可见则认为唤起失败
      const startTs = Date.now()
      const timer = setTimeout(() => {
        // 页面被隐藏说明已跳转成功（visibilitychange 会在切走时触发）
        const hidden = typeof document !== 'undefined' && document.hidden
        if (!hidden && Date.now() - startTs >= 2400) {
          uni.showModal({
            title: '未打开APP？',
            content: '若未成功跳转，可复制口令前往APP粘贴完成',
            confirmText: '复制口令',
            success: (res) => {
              if (res.confirm && card.copy_text) {
                this.handleCopy()
              }
            }
          })
        }
      }, 2500)

      try {
        window.location.href = card.scheme_url
      } catch (e) {
        clearTimeout(timer)
        uni.showToast({ title: '跳转失败，请使用口令或二维码', icon: 'none' })
      }
      // #endif

      // #ifdef APP-PLUS
      plus.runtime.openURL(card.scheme_url, () => {
        uni.showToast({ title: '打开APP失败，请使用口令或二维码', icon: 'none' })
      })
      // #endif
    },

    /**
     * 复制口令
     */
    handleCopy() {
      const card = this.mergedCard
      if (!card || !card.copy_text) return
      uni.setClipboardData({
        data: card.copy_text,
        success: () => {
          uni.showToast({ title: '口令已复制，请去APP粘贴', icon: 'none', duration: 2500 })
        }
      })
    },

    /**
     * 上传凭证（选图 → 压缩 → 上传）
     */
    handleUpload() {
      uni.chooseImage({
        count: 1,
        sizeType: ['compressed'],
        sourceType: ['album', 'camera'],
        success: (res) => {
          const filePath = res.tempFilePaths && res.tempFilePaths[0]
          if (!filePath) return
          this.$emit('upload', this.action, filePath)
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.action-card {
  background: #ffffff;
  border-radius: 20rpx;
  margin: 20rpx;
  padding: 28rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  &.action-card-done {
    opacity: 0.75;
  }
}

.card-main {
  display: flex;
  align-items: center;

  .action-icon {
    width: 88rpx;
    height: 88rpx;
    border-radius: 20rpx;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;

    .icon-img {
      width: 100%;
      height: 100%;
    }

    .icon-default {
      font-size: 44rpx;
    }
  }

  .action-info {
    flex: 1;
    margin-left: 24rpx;
    display: flex;
    align-items: center;

    .action-name {
      font-size: 30rpx;
      font-weight: bold;
      color: #1f2937;
    }

    .required-tag {
      margin-left: 12rpx;
      padding: 4rpx 12rpx;
      font-size: 20rpx;
      color: #ef4444;
      background: #fef2f2;
      border-radius: 8rpx;
    }
  }

  .state-badge {
    padding: 8rpx 20rpx;
    border-radius: 999rpx;
    font-size: 22rpx;

    &.badge-PENDING {
      color: #9ca3af;
      background: #f3f4f6;
    }

    &.badge-STARTED {
      color: #6366f1;
      background: #eef2ff;
    }

    &.badge-VERIFYING {
      color: #d97706;
      background: #fffbeb;
    }

    &.badge-COMPLETED {
      color: #16a34a;
      background: #f0fdf4;
    }

    &.badge-REJECTED {
      color: #ef4444;
      background: #fef2f2;
    }
  }
}

.reject-tip {
  margin-top: 16rpx;
  padding: 16rpx 20rpx;
  background: #fef2f2;
  border-radius: 12rpx;
  font-size: 24rpx;
  color: #ef4444;
}

.card-body {
  margin-top: 24rpx;
  padding-top: 24rpx;
  border-top: 1rpx solid #f3f4f6;

  .guide-steps {
    .step {
      display: flex;
      align-items: flex-start;
      margin-bottom: 14rpx;

      .step-index {
        width: 36rpx;
        height: 36rpx;
        border-radius: 50%;
        background: #6366f1;
        color: #ffffff;
        font-size: 22rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-right: 14rpx;
        margin-top: 2rpx;
      }

      .step-text {
        font-size: 26rpx;
        color: #4b5563;
        line-height: 40rpx;
      }
    }
  }

  .env-tip {
    padding: 16rpx 20rpx;
    background: #fffbeb;
    border-radius: 12rpx;
    font-size: 24rpx;
    color: #d97706;
    margin-bottom: 16rpx;
  }

  .qrcode-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16rpx 0;

    .qrcode {
      width: 320rpx;
      height: 320rpx;
    }

    .qrcode-tip {
      margin-top: 16rpx;
      font-size: 24rpx;
      color: #9ca3af;
    }
  }
}

.card-footer {
  margin-top: 24rpx;

  .card-btn {
    height: 80rpx;
    line-height: 80rpx;
    border-radius: 40rpx;
    font-size: 28rpx;
    text-align: center;
    margin: 0;

    &.primary-btn {
      background: linear-gradient(90deg, #6366f1, #8b5cf6);
      color: #ffffff;
    }

    &.copy-btn {
      margin-top: 16rpx;
      background: #eef2ff;
      color: #6366f1;
    }

    &.upload-btn {
      background: #ecfdf5;
      color: #16a34a;
      border: 1rpx solid #a7f3d0;
    }

    &[disabled] {
      opacity: 0.5;
      color: #ffffff;
    }
  }

  .done-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #16a34a;
    font-size: 28rpx;

    .done-check {
      width: 40rpx;
      height: 40rpx;
      border-radius: 50%;
      background: #16a34a;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24rpx;
      margin-right: 12rpx;
    }
  }

  .verifying-mark {
    text-align: center;
    color: #d97706;
    font-size: 26rpx;
  }
}
</style>

<template>
  <view class="reward-panel" v-if="rewardType && rewardType !== 'none'">
    <view class="reward-card" :class="'reward-' + rewardType">
      <!-- 红包 -->
      <block v-if="rewardType === 'redpacket'">
        <view class="redpacket-icon">🧧</view>
        <view class="reward-amount" v-if="amountText">
          <text class="amount-symbol">¥</text>
          <text class="amount-num">{{ amountText }}</text>
        </view>
        <view class="reward-name">完成任务开红包</view>
      </block>

      <!-- 优惠券 -->
      <block v-else-if="rewardType === 'coupon'">
        <view class="coupon-icon">🎫</view>
        <view class="reward-name">
          {{ couponName || '完成任务领优惠券' }}
        </view>
        <view class="reward-desc" v-if="couponDesc">{{ couponDesc }}</view>
      </block>

      <!-- 积分 -->
      <block v-else-if="rewardType === 'points'">
        <view class="points-icon">⭐</view>
        <view class="reward-name">
          {{ pointsText ? pointsText + ' 积分' : '完成任务得积分' }}
        </view>
      </block>

      <!-- 领取按钮区 -->
      <view class="reward-action">
        <!-- 任务未完成 -->
        <view class="reward-tip" v-if="instanceStatus !== 'COMPLETED'">
          完成全部任务后可领取
        </view>

        <!-- 可领取 -->
        <button
          class="claim-btn"
          v-else-if="rewardStatus === 'PENDING'"
          @tap="handleClaim"
          :disabled="claiming"
        >
          {{ claiming ? '领取中...' : '领取奖励' }}
        </button>

        <!-- 已发放 -->
        <view class="issued-mark" v-else-if="rewardStatus === 'ISSUED'">
          <text>✓ 奖励已发放</text>
        </view>

        <!-- 发放失败 -->
        <view class="failed-mark" v-else-if="rewardStatus === 'FAILED'">
          <text>奖励发放失败，请联系商家</text>
        </view>

        <!-- 其他/未知状态 -->
        <view class="reward-tip" v-else>
          {{ rewardStatusText }}
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'RewardPanel',

  props: {
    // 任务包 {reward_type, reward_config}
    bundle: {
      type: Object,
      default: null
    },
    // 任务实例 {status, reward_status}
    instance: {
      type: Object,
      default: null
    },
    // 是否正在领取（防重复点击）
    claiming: {
      type: Boolean,
      default: false
    }
  },

  computed: {
    rewardType() {
      return (this.bundle && this.bundle.reward_type) || 'none'
    },

    rewardConfig() {
      return (this.bundle && this.bundle.reward_config) || {}
    },

    instanceStatus() {
      return (this.instance && this.instance.status) || ''
    },

    rewardStatus() {
      return (this.instance && this.instance.reward_status) || 'PENDING'
    },

    amountText() {
      const amount = this.rewardConfig.amount || this.rewardConfig.value
      if (amount === undefined || amount === null) return ''
      return String(amount)
    },

    couponName() {
      return this.rewardConfig.name || this.rewardConfig.title || ''
    },

    couponDesc() {
      return this.rewardConfig.desc || this.rewardConfig.description || ''
    },

    pointsText() {
      const points = this.rewardConfig.points || this.rewardConfig.value
      return points !== undefined && points !== null ? String(points) : ''
    },

    rewardStatusText() {
      const map = {
        PENDING: '完成全部任务后可领取',
        ISSUED: '奖励已发放',
        FAILED: '奖励发放失败，请联系商家'
      }
      return map[this.rewardStatus] || ''
    }
  },

  methods: {
    handleClaim() {
      this.$emit('claim')
    }
  }
}
</script>

<style lang="scss" scoped>
.reward-panel {
  margin: 20rpx;
}

.reward-card {
  border-radius: 20rpx;
  padding: 40rpx 30rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.08);

  &.reward-redpacket {
    background: linear-gradient(135deg, #ef4444, #f97316);
    color: #ffffff;
  }

  &.reward-coupon {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
  }

  &.reward-points {
    background: linear-gradient(135deg, #d97706, #f59e0b);
    color: #ffffff;
  }
}

.redpacket-icon,
.coupon-icon,
.points-icon {
  font-size: 72rpx;
}

.reward-amount {
  margin-top: 12rpx;

  .amount-symbol {
    font-size: 36rpx;
  }

  .amount-num {
    font-size: 72rpx;
    font-weight: bold;
  }
}

.reward-name {
  margin-top: 12rpx;
  font-size: 32rpx;
  font-weight: bold;
}

.reward-desc {
  margin-top: 8rpx;
  font-size: 24rpx;
  opacity: 0.9;
}

.reward-action {
  margin-top: 28rpx;
  width: 100%;

  .reward-tip {
    text-align: center;
    font-size: 24rpx;
    opacity: 0.85;
  }

  .claim-btn {
    height: 84rpx;
    line-height: 84rpx;
    border-radius: 42rpx;
    background: #ffffff;
    color: #ef4444;
    font-size: 30rpx;
    font-weight: bold;
    margin: 0;
    width: 100%;

    &[disabled] {
      opacity: 0.7;
    }
  }

  .issued-mark {
    text-align: center;
    font-size: 28rpx;
    font-weight: bold;
  }

  .failed-mark {
    text-align: center;
    font-size: 26rpx;
    opacity: 0.95;
  }
}
</style>

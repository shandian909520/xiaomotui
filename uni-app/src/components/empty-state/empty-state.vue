<template>
  <view class="empty-state">
    <slot>
      <text class="empty-icon">{{ icon }}</text>
      <text class="empty-title">{{ title }}</text>
      <text class="empty-desc" v-if="description">{{ description }}</text>
    </slot>
    <slot name="button">
      <button
        v-if="btnText"
        class="empty-btn"
        :class="`btn-${btnType}`"
        @tap="$emit('action')"
      >{{ btnText }}</button>
    </slot>
  </view>
</template>

<script>
export default {
  name: 'empty-state',
  props: {
    icon: {
      type: String,
      default: '\uD83D\uDCE6'
    },
    title: {
      type: String,
      default: '暂无数据'
    },
    description: {
      type: String,
      default: ''
    },
    btnText: {
      type: String,
      default: ''
    },
    btnType: {
      type: String,
      default: 'primary'
    }
  }
}
</script>

<style lang="scss" scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 60rpx;
}

.empty-icon {
  font-size: 80rpx;
  margin-bottom: 24rpx;
}

.empty-title {
  font-size: 16px;
  font-weight: 500;
  color: #6b7280;
  margin-bottom: 12rpx;
}

.empty-desc {
  font-size: 13px;
  color: #9ca3af;
  text-align: center;
  line-height: 1.5;
  margin-bottom: 16rpx;
}

.empty-btn {
  margin-top: 30rpx;
  padding: 20rpx 60rpx;
  border: none;
  border-radius: 24rpx;
  font-size: 14px;
  font-weight: 500;

  &.btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    box-shadow: 0 4rpx 12rpx rgba(99, 102, 241, 0.3);
  }

  &.btn-plain {
    background: #f3f4f6;
    color: #6366f1;
  }
}
</style>

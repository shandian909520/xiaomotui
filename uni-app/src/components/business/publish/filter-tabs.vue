<template>
  <scroll-view class="filter-tabs" scroll-x>
    <view class="tabs-wrapper">
      <view
        class="tab-item"
        :class="{ active: current === item.value }"
        v-for="item in tabs"
        :key="item.value"
        @tap="$emit('change', item.value)"
      >
        <text class="tab-text">{{ item.label }}</text>
        <text class="tab-count" v-if="item.count">{{ item.count }}</text>
      </view>
    </view>
  </scroll-view>
</template>

<script>
export default {
  name: 'FilterTabs',
  props: {
    // 标签列表
    tabs: {
      type: Array,
      default: () => []
    },
    // 当前选中值
    current: {
      type: String,
      default: 'ALL'
    }
  }
}
</script>

<style lang="scss" scoped>
.filter-tabs {
  width: 100%;
  white-space: nowrap;
  background: #ffffff;
  padding: 20rpx 0;
  margin-bottom: 20rpx;

  .tabs-wrapper {
    display: inline-flex;
    padding: 0 30rpx;
    gap: 16rpx;
  }

  .tab-item {
    display: inline-flex;
    align-items: center;
    gap: 8rpx;
    padding: 12rpx 24rpx;
    background: #f3f4f6;
    border-radius: 24rpx;
    transition: all 0.3s;

    &.active {
      background: #6366f1;

      .tab-text {
        color: #ffffff;
      }

      .tab-count {
        background: rgba(255, 255, 255, 0.3);
        color: #ffffff;
      }
    }

    .tab-text {
      font-size: 14px;
      color: #6b7280;
      font-weight: 500;
    }

    .tab-count {
      padding: 2rpx 12rpx;
      background: #e5e7eb;
      border-radius: 12rpx;
      font-size: 12px;
      color: #6b7280;
      font-weight: 600;
    }
  }
}
</style>

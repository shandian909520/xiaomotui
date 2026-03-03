<template>
  <view v-if="loading" class="skeleton-wrapper" :class="{ animate }">
    <!-- list 类型 -->
    <template v-if="type === 'list'">
      <view class="skeleton-item" v-for="i in rows" :key="i">
        <view class="skeleton-avatar" v-if="avatar"></view>
        <view class="skeleton-content">
          <view class="skeleton-title" v-if="title"></view>
          <view class="skeleton-line" :style="{ width: getLineWidth(i) }"></view>
          <view class="skeleton-line short" v-if="i === rows"></view>
        </view>
      </view>
    </template>

    <!-- card 类型 -->
    <template v-if="type === 'card'">
      <view class="skeleton-card" v-for="i in rows" :key="i">
        <view class="skeleton-cover"></view>
        <view class="skeleton-card-body">
          <view class="skeleton-title"></view>
          <view class="skeleton-line"></view>
          <view class="skeleton-line short"></view>
        </view>
      </view>
    </template>

    <!-- profile 类型 -->
    <template v-if="type === 'profile'">
      <view class="skeleton-profile">
        <view class="skeleton-avatar large"></view>
        <view class="skeleton-profile-info">
          <view class="skeleton-title wide"></view>
          <view class="skeleton-line"></view>
        </view>
      </view>
      <view class="skeleton-stats">
        <view class="skeleton-stat" v-for="i in 3" :key="i">
          <view class="skeleton-stat-num"></view>
          <view class="skeleton-stat-label"></view>
        </view>
      </view>
    </template>

    <!-- detail 类型 -->
    <template v-if="type === 'detail'">
      <view class="skeleton-detail-cover"></view>
      <view class="skeleton-detail-body">
        <view class="skeleton-title wide"></view>
        <view class="skeleton-line" v-for="i in rows" :key="i"></view>
        <view class="skeleton-line short"></view>
      </view>
    </template>
  </view>
  <slot v-else></slot>
</template>

<script>
export default {
  name: 'skeleton',
  props: {
    type: {
      type: String,
      default: 'list'
    },
    rows: {
      type: Number,
      default: 3
    },
    avatar: {
      type: Boolean,
      default: false
    },
    title: {
      type: Boolean,
      default: true
    },
    animate: {
      type: Boolean,
      default: true
    },
    loading: {
      type: Boolean,
      default: true
    }
  },
  methods: {
    getLineWidth(index) {
      const widths = ['100%', '80%', '60%', '90%', '70%']
      return widths[(index - 1) % widths.length]
    }
  }
}
</script>

<style lang="scss" scoped>
.skeleton-wrapper {
  padding: 30rpx;
}

// 闪烁动画
.animate {
  .skeleton-avatar,
  .skeleton-title,
  .skeleton-line,
  .skeleton-cover,
  .skeleton-detail-cover,
  .skeleton-stat-num,
  .skeleton-stat-label {
    background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
  }
}

// 基础元素
.skeleton-avatar {
  width: 80rpx;
  height: 80rpx;
  border-radius: 50%;
  background: #e5e7eb;
  flex-shrink: 0;

  &.large {
    width: 120rpx;
    height: 120rpx;
  }
}

.skeleton-title {
  height: 32rpx;
  background: #e5e7eb;
  border-radius: 8rpx;
  width: 60%;
  margin-bottom: 20rpx;

  &.wide {
    width: 80%;
  }
}

.skeleton-line {
  height: 24rpx;
  background: #e5e7eb;
  border-radius: 6rpx;
  width: 100%;
  margin-bottom: 16rpx;

  &.short {
    width: 40%;
  }
}

// list 类型
.skeleton-item {
  display: flex;
  gap: 24rpx;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #f3f4f6;

  &:last-child {
    border-bottom: none;
  }
}

.skeleton-content {
  flex: 1;
}

// card 类型
.skeleton-card {
  background: #ffffff;
  border-radius: 12rpx;
  overflow: hidden;
  margin-bottom: 20rpx;
}

.skeleton-cover {
  width: 100%;
  height: 300rpx;
  background: #e5e7eb;
}

.skeleton-card-body {
  padding: 24rpx;
}

// profile 类型
.skeleton-profile {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 40rpx 0;
}

.skeleton-profile-info {
  flex: 1;
}

.skeleton-stats {
  display: flex;
  justify-content: space-around;
  padding: 30rpx 0;
  margin-top: 20rpx;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 16rpx;
}

.skeleton-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12rpx;
}

.skeleton-stat-num {
  width: 80rpx;
  height: 40rpx;
  background: #e5e7eb;
  border-radius: 8rpx;
}

.skeleton-stat-label {
  width: 60rpx;
  height: 24rpx;
  background: #e5e7eb;
  border-radius: 6rpx;
}

// detail 类型
.skeleton-detail-cover {
  width: 100%;
  height: 400rpx;
  background: #e5e7eb;
  border-radius: 12rpx;
  margin-bottom: 30rpx;
}

.skeleton-detail-body {
  padding: 0 10rpx;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>

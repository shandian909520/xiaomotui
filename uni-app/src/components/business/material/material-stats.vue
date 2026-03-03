<template>
  <view class="stats-section">
    <view class="section-header">
      <text class="section-icon">📊</text>
      <text class="section-title">使用统计</text>
    </view>
    <view class="stats-grid">
      <view class="stat-card">
        <text class="stat-value">{{ usageCount }}</text>
        <text class="stat-label">使用次数</text>
      </view>
      <view class="stat-card">
        <text class="stat-value">{{ downloadCount }}</text>
        <text class="stat-label">下载次数</text>
      </view>
      <view class="stat-card">
        <text class="stat-value">{{ shareCount }}</text>
        <text class="stat-label">分享次数</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'MaterialStats',

  props: {
    usageCount: {
      type: Number,
      default: 0
    },
    downloadCount: {
      type: Number,
      default: 0
    },
    shareCount: {
      type: Number,
      default: 0
    }
  }
}
</script>

<style lang="scss" scoped>
.stats-section {
  background: #ffffff;
  padding: 30rpx;
  margin-bottom: 20rpx;

  .section-header {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 20rpx;

    .section-icon {
      font-size: 20px;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
    }
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20rpx;

    .stat-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 30rpx 20rpx;
      background: #f9fafb;
      border-radius: 12rpx;
      gap: 12rpx;

      .stat-value {
        font-size: 24px;
        font-weight: 600;
        color: #6366f1;
      }

      .stat-label {
        font-size: 12px;
        color: #6b7280;
      }
    }
  }
}
</style>

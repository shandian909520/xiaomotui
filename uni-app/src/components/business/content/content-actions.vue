<template>
  <view class="action-section">
    <view class="action-row">
      <button class="action-btn primary" @tap="$emit('download')" v-if="contentType === 'VIDEO'">
        <text class="btn-icon">⬇️</text>
        <text>下载视频</text>
      </button>
      <button class="action-btn primary" @tap="$emit('download')" v-if="contentType === 'IMAGE'">
        <text class="btn-icon">⬇️</text>
        <text>下载图片</text>
      </button>
      <button class="action-btn" @tap="$emit('save')">
        <text class="btn-icon">💾</text>
        <text>保存到相册</text>
      </button>
    </view>

    <view class="action-row">
      <button class="action-btn" @tap="$emit('share')">
        <text class="btn-icon">📤</text>
        <text>分享内容</text>
      </button>
      <button class="action-btn" @tap="$emit('regenerate')">
        <text class="btn-icon">🔄</text>
        <text>重新生成</text>
      </button>
    </view>

    <button class="publish-btn" @tap="$emit('publish')">
      <text class="btn-icon">🚀</text>
      <text>立即发布</text>
    </button>
  </view>
</template>

<script>
export default {
  props: {
    contentType: { type: String, default: '' }
  }
}
</script>

<style lang="scss" scoped>
.action-section {
  padding: 30rpx;

  .action-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20rpx;
    margin-bottom: 20rpx;

    .action-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8rpx;
      padding: 24rpx;
      background: #ffffff;
      color: #374151;
      border: 1rpx solid #e5e7eb;
      border-radius: 12rpx;
      font-size: 14px;

      &.primary {
        background: #6366f1;
        color: #ffffff;
        border-color: #6366f1;
      }

      .btn-icon { font-size: 18px; }
    }
  }

  .publish-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12rpx;
    width: 100%;
    padding: 28rpx;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    border: none;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
    box-shadow: 0 4rpx 20rpx rgba(99, 102, 241, 0.3);

    .btn-icon { font-size: 20px; }
  }
}
</style>

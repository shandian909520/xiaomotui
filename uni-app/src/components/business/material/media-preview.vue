<template>
  <view class="media-section">
    <!-- 视频预览 -->
    <view class="video-wrapper" v-if="type === 'VIDEO'">
      <video
        class="video-player"
        :src="fileUrl"
        :poster="coverUrl"
        :controls="true"
        :show-center-play-btn="true"
        :show-fullscreen-btn="true"
        :enable-progress-gesture="true"
        @error="onVideoError"
        @timeupdate="onVideoTimeUpdate"
      />
      <view class="video-info">
        <text class="video-duration" v-if="videoDuration">{{ formatDuration(videoDuration) }}</text>
        <text class="video-size" v-if="fileSize">{{ formatFileSize(fileSize) }}</text>
      </view>
    </view>

    <!-- 图片预览 -->
    <image
      v-else-if="type === 'IMAGE'"
      class="preview-image"
      :src="fileUrl"
      mode="aspectFit"
      @tap="handlePreview"
    />

    <!-- 文本预览 -->
    <view v-else-if="type === 'TEXT'" class="text-preview">
      <text class="text-content">{{ content }}</text>
    </view>

    <!-- 模板预览 -->
    <view v-else-if="type === 'TEMPLATE'" class="template-preview">
      <text class="template-icon">📄</text>
      <text class="template-name">{{ title }}</text>
    </view>
  </view>
</template>

<script>
export default {
  name: 'MediaPreview',

  props: {
    type: {
      type: String,
      default: ''
    },
    fileUrl: {
      type: String,
      default: ''
    },
    coverUrl: {
      type: String,
      default: ''
    },
    content: {
      type: String,
      default: ''
    },
    title: {
      type: String,
      default: ''
    },
    fileSize: {
      type: Number,
      default: 0
    }
  },

  data() {
    return {
      videoDuration: 0
    }
  },

  methods: {
    /**
     * 视频错误处理
     */
    onVideoError(e) {
      this.$emit('video-error', e)
    },

    /**
     * 视频时间更新
     */
    onVideoTimeUpdate(e) {
      this.videoDuration = e.detail.duration
    },

    /**
     * 处理图片预览
     */
    handlePreview() {
      this.$emit('preview')
    },

    /**
     * 格式化时长
     */
    formatDuration(seconds) {
      if (!seconds) return '00:00'
      const mins = Math.floor(seconds / 60)
      const secs = Math.floor(seconds % 60)
      return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    },

    /**
     * 格式化文件大小
     */
    formatFileSize(bytes) {
      if (!bytes) return '-'
      if (bytes < 1024) return bytes + 'B'
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
      return (bytes / 1024 / 1024).toFixed(1) + 'MB'
    }
  }
}
</script>

<style lang="scss" scoped>
.media-section {
  width: 100%;
  background: #000000;
  position: relative;

  .video-wrapper {
    position: relative;
    width: 100%;

    .video-player {
      width: 100%;
      height: 600rpx;
    }

    .video-info {
      position: absolute;
      bottom: 20rpx;
      right: 20rpx;
      display: flex;
      gap: 16rpx;

      .video-duration,
      .video-size {
        padding: 8rpx 16rpx;
        background: rgba(0, 0, 0, 0.6);
        border-radius: 20rpx;
        color: #ffffff;
        font-size: 12px;
        backdrop-filter: blur(10rpx);
      }
    }
  }

  .preview-image {
    width: 100%;
    min-height: 400rpx;
    background: #000000;
  }

  .text-preview {
    padding: 60rpx;
    min-height: 400rpx;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

    .text-content {
      font-size: 16px;
      color: #ffffff;
      line-height: 1.8;
      white-space: pre-wrap;
    }
  }

  .template-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400rpx;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    gap: 30rpx;

    .template-icon {
      font-size: 80px;
    }

    .template-name {
      font-size: 20px;
      font-weight: 600;
      color: #ffffff;
    }
  }
}
</style>

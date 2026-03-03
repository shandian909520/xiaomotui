<template>
  <view class="video-section" v-if="videoUrl">
    <video
      class="video-player"
      :src="videoUrl"
      :poster="posterUrl"
      :controls="true"
      :show-center-play-btn="true"
      :show-fullscreen-btn="true"
      :show-play-btn="true"
      :enable-progress-gesture="true"
      @error="onVideoError"
      @timeupdate="onTimeUpdate"
      @play="onVideoPlay"
      @pause="onVideoPause"
      @ended="onVideoEnded"
    />

    <view class="video-info-card">
      <view class="video-duration" v-if="duration">
        <text class="icon">⏱️</text>
        <text>{{ formatDuration(duration) }}</text>
      </view>
      <view class="video-size" v-if="fileSize">
        <text class="icon">📦</text>
        <text>{{ formatFileSize(fileSize) }}</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  props: {
    videoUrl: { type: String, default: '' },
    posterUrl: { type: String, default: '' },
    fileSize: { type: Number, default: 0 }
  },

  data() {
    return {
      duration: 0,
      currentTime: 0,
      isPlaying: false
    }
  },

  methods: {
    onVideoPlay() {
      this.isPlaying = true
      this.$emit('play')
    },

    onVideoPause() {
      this.isPlaying = false
    },

    onVideoEnded() {
      this.isPlaying = false
      this.$emit('ended')
    },

    onTimeUpdate(e) {
      this.currentTime = e.detail.currentTime
      this.duration = e.detail.duration
      this.$emit('timeupdate', e.detail)
    },

    onVideoError(e) {
      console.error('视频播放错误:', e)
      this.$emit('error', e)
      uni.showToast({
        title: '视频加载失败',
        icon: 'none',
        duration: 2000
      })
    },

    formatDuration(seconds) {
      const mins = Math.floor(seconds / 60)
      const secs = Math.floor(seconds % 60)
      return `${mins}:${secs.toString().padStart(2, '0')}`
    },

    formatFileSize(bytes) {
      if (bytes < 1024) return bytes + 'B'
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
      return (bytes / 1024 / 1024).toFixed(1) + 'MB'
    }
  }
}
</script>

<style lang="scss" scoped>
.video-section {
  position: relative;
  width: 100%;
  background: #000000;

  .video-player {
    width: 100%;
    height: 600rpx;
  }

  .video-info-card {
    position: absolute;
    bottom: 20rpx;
    right: 20rpx;
    display: flex;
    gap: 16rpx;

    .video-duration,
    .video-size {
      display: flex;
      align-items: center;
      gap: 8rpx;
      padding: 8rpx 16rpx;
      background: rgba(0, 0, 0, 0.6);
      border-radius: 20rpx;
      color: #ffffff;
      font-size: 12px;
      backdrop-filter: blur(10rpx);

      .icon {
        font-size: 14px;
      }
    }
  }
}
</style>

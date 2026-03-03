<template>
  <view class="preview-container">
    <!-- 视频播放器区域 -->
    <video-player
      v-if="contentData.type === 'VIDEO' && contentData.video_url"
      :videoUrl="contentData.video_url"
      :posterUrl="contentData.poster_url || contentData.cover_url"
      :fileSize="contentData.file_size || 0"
      @play="onVideoPlay"
      @ended="onVideoEnded"
      @timeupdate="onTimeUpdate"
      @error="onVideoError"
    />

    <!-- 图片预览区域 -->
    <view class="image-section" v-if="contentData.type === 'IMAGE' && contentData.image_url">
      <image
        class="preview-image"
        :src="contentData.image_url"
        mode="aspectFit"
        @tap="previewImage"
      />
    </view>

    <!-- 内容信息区域 -->
    <view class="info-section">
      <view class="info-header">
        <view class="info-title">{{ contentData.title || '内容详情' }}</view>
        <view class="info-badge" :class="`badge-${contentData.status}`">
          {{ formatStatus(contentData.status) }}
        </view>
      </view>

      <view class="meta-info">
        <view class="meta-item" v-if="contentData.create_time">
          <text class="meta-icon">🕒</text>
          <text class="meta-text">{{ formatTime(contentData.create_time) }}</text>
        </view>
        <view class="meta-item" v-if="contentData.ai_provider">
          <text class="meta-icon">🤖</text>
          <text class="meta-text">{{ contentData.ai_provider }}</text>
        </view>
        <view class="meta-item" v-if="contentData.generation_time">
          <text class="meta-icon">⚡</text>
          <text class="meta-text">{{ contentData.generation_time }}秒生成</text>
        </view>
      </view>

      <view class="description" v-if="contentData.description">
        {{ contentData.description }}
      </view>
    </view>

    <!-- 文案内容区域 -->
    <view class="text-section" v-if="contentData.text_content || contentData.copywriting">
      <view class="section-header">
        <text class="section-icon">📝</text>
        <text class="section-title">营销文案</text>
      </view>

      <view class="text-content">
        {{ contentData.text_content || contentData.copywriting }}
      </view>

      <view class="text-actions">
        <button class="copy-btn" @tap="copyText">
          <text class="btn-icon">📋</text>
          <text>复制文案</text>
        </button>
      </view>
    </view>

    <!-- 标签区域 -->
    <view class="tags-section" v-if="contentData.tags && contentData.tags.length">
      <view class="section-header">
        <text class="section-icon">🏷️</text>
        <text class="section-title">内容标签</text>
      </view>
      <view class="tags-list">
        <view class="tag-item" v-for="(tag, index) in contentData.tags" :key="index">
          #{{ tag }}
        </view>
      </view>
    </view>

    <!-- 反馈区域 -->
    <content-feedback
      :contentId="taskId"
      :submitted="feedbackSubmitted"
      @feedback="onFeedbackSubmit"
      @regenerate="regenerateWithFeedback"
    />

    <!-- 操作按钮区域 -->
    <content-actions
      :contentType="contentData.type || ''"
      @download="contentData.type === 'VIDEO' ? downloadVideo() : downloadImage()"
      @save="saveToAlbum"
      @share="shareContent"
      @regenerate="regenerate"
      @publish="publishNow"
    />

    <!-- 加载遮罩 -->
    <view class="loading-overlay" v-if="isLoading">
      <view class="loading-box">
        <view class="loading-spinner"></view>
        <view class="loading-text">{{ loadingText }}</view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'
import VideoPlayer from '../../components/business/content/video-player.vue'
import ContentFeedback from '../../components/business/content/content-feedback.vue'
import ContentActions from '../../components/business/content/content-actions.vue'

export default {
  components: { VideoPlayer, ContentFeedback, ContentActions },
  data() {
    return {
      taskId: '',
      contentData: {},

      // 视频播放相关
      currentTime: 0,
      duration: 0,
      isPlaying: false,

      // 加载状态
      isLoading: false,
      loadingText: '加载中...',

      // 反馈相关
      feedbackSubmitted: false  // 是否已提交反馈
    }
  },

  onLoad(options) {
    console.log('预览页面参数:', options)

    if (options.task_id) {
      this.taskId = options.task_id
      this.loadContentData()
    } else {
      uni.showModal({
        title: '提示',
        content: '缺少任务ID参数',
        showCancel: false,
        success: () => {
          uni.navigateBack()
        }
      })
    }
  },

  onShareAppMessage() {
    // 微信小程序分享配置
    return {
      title: this.contentData.title || '查看我的内容',
      path: `/pages/content/preview?task_id=${this.taskId}`,
      imageUrl: this.contentData.poster_url || this.contentData.cover_url || this.contentData.image_url
    }
  },

  methods: {
    /**
     * 加载内容数据
     */
    async loadContentData() {
      this.isLoading = true
      this.loadingText = '加载内容中...'

      try {
        const res = await api.content.getTaskDetail(this.taskId)
        console.log('内容详情:', res)

        // 解析output_data
        if (res.output_data) {
          if (typeof res.output_data === 'string') {
            try {
              res.output_data = JSON.parse(res.output_data)
            } catch (e) {
              console.error('解析output_data失败:', e)
            }
          }

          // 合并output_data到主对象
          this.contentData = {
            ...res,
            ...res.output_data
          }
        } else {
          this.contentData = res
        }

        // 确保标签是数组
        if (this.contentData.tags && typeof this.contentData.tags === 'string') {
          try {
            this.contentData.tags = JSON.parse(this.contentData.tags)
          } catch (e) {
            this.contentData.tags = this.contentData.tags.split(',')
          }
        }

        console.log('处理后的内容数据:', this.contentData)
      } catch (error) {
        console.error('加载内容失败:', error)
        uni.showModal({
          title: '加载失败',
          content: error.message || '无法加载内容数据',
          confirmText: '返回',
          showCancel: false,
          success: () => {
            uni.navigateBack()
          }
        })
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 视频播放事件
     */
    onVideoPlay() {
      this.isPlaying = true
      console.log('视频开始播放')
    },

    onVideoEnded() {
      this.isPlaying = false
      console.log('视频播放完成')
    },

    onTimeUpdate(e) {
      this.currentTime = e.detail.currentTime
      this.duration = e.detail.duration
    },

    onVideoError(e) {
      console.error('视频播放错误:', e)
      uni.showToast({
        title: '视频加载失败',
        icon: 'none',
        duration: 2000
      })
    },

    /**
     * 接收子组件反馈数据
     */
    onFeedbackSubmit(feedbackData) {
      console.log('收到反馈数据:', feedbackData)
      this.feedbackSubmitted = true
    },

    /**
     * 图片预览
     */
    previewImage() {
      if (!this.contentData.image_url) return

      uni.previewImage({
        urls: [this.contentData.image_url],
        current: this.contentData.image_url
      })
    },

    /**
     * 下载视频
     */
    async downloadVideo() {
      if (!this.contentData.video_url) {
        uni.showToast({
          title: '视频地址不存在',
          icon: 'none'
        })
        return
      }

      this.isLoading = true
      this.loadingText = '下载中...'

      try {
        // #ifdef H5
        // H5下载方式
        const a = document.createElement('a')
        a.href = this.contentData.video_url
        a.download = `video_${this.taskId}.mp4`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)

        uni.showToast({
          title: '开始下载',
          icon: 'success'
        })
        // #endif

        // #ifdef MP-WEIXIN || MP-ALIPAY
        // 小程序下载方式
        const downloadRes = await uni.downloadFile({
          url: this.contentData.video_url
        })

        if (downloadRes.statusCode === 200) {
          // 保存到相册
          await uni.saveVideoToPhotosAlbum({
            filePath: downloadRes.tempFilePath
          })

          uni.showToast({
            title: '已保存到相册',
            icon: 'success'
          })
        }
        // #endif

        // #ifdef APP-PLUS
        // APP下载方式
        const savePath = '_downloads/video_' + this.taskId + '.mp4'
        const downloadTask = plus.downloader.createDownload(
          this.contentData.video_url,
          { filename: savePath }
        )

        downloadTask.start()

        downloadTask.addEventListener('statechanged', (task, status) => {
          if (status === 200) {
            uni.showToast({
              title: '下载完成',
              icon: 'success'
            })
          }
        })
        // #endif
      } catch (error) {
        console.error('下载失败:', error)

        if (error.errMsg && error.errMsg.includes('auth deny')) {
          uni.showModal({
            title: '提示',
            content: '需要授权访问相册',
            success: (res) => {
              if (res.confirm) {
                uni.openSetting()
              }
            }
          })
        } else {
          uni.showToast({
            title: '下载失败',
            icon: 'none'
          })
        }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 下载图片
     */
    async downloadImage() {
      if (!this.contentData.image_url) {
        uni.showToast({
          title: '图片地址不存在',
          icon: 'none'
        })
        return
      }

      this.isLoading = true
      this.loadingText = '下载中...'

      try {
        // #ifdef H5
        const a = document.createElement('a')
        a.href = this.contentData.image_url
        a.download = `image_${this.taskId}.jpg`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)

        uni.showToast({
          title: '开始下载',
          icon: 'success'
        })
        // #endif

        // #ifdef MP-WEIXIN || MP-ALIPAY || APP-PLUS
        const downloadRes = await uni.downloadFile({
          url: this.contentData.image_url
        })

        if (downloadRes.statusCode === 200) {
          await uni.saveImageToPhotosAlbum({
            filePath: downloadRes.tempFilePath
          })

          uni.showToast({
            title: '已保存到相册',
            icon: 'success'
          })
        }
        // #endif
      } catch (error) {
        console.error('下载失败:', error)

        if (error.errMsg && error.errMsg.includes('auth deny')) {
          uni.showModal({
            title: '提示',
            content: '需要授权访问相册',
            success: (res) => {
              if (res.confirm) {
                uni.openSetting()
              }
            }
          })
        } else {
          uni.showToast({
            title: '下载失败',
            icon: 'none'
          })
        }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 保存到相册
     */
    async saveToAlbum() {
      const contentType = this.contentData.type
      const fileUrl = contentType === 'VIDEO' ? this.contentData.video_url : this.contentData.image_url

      if (!fileUrl) {
        uni.showToast({
          title: '文件地址不存在',
          icon: 'none'
        })
        return
      }

      try {
        await api.content.saveToAlbum(fileUrl, contentType === 'VIDEO' ? 'video' : 'image')
      } catch (error) {
        console.error('保存失败:', error)
      }
    },

    /**
     * 分享内容
     */
    async shareContent() {
      // #ifdef MP-WEIXIN
      // 微信小程序使用分享面板
      uni.showShareMenu({
        withShareTicket: true,
        menus: ['shareAppMessage', 'shareTimeline']
      })

      uni.showToast({
        title: '点击右上角分享',
        icon: 'none'
      })
      // #endif

      // #ifdef H5
      try {
        const shareData = {
          title: this.contentData.title || '查看我的内容',
          description: this.contentData.text_content || this.contentData.description || '',
          url: window.location.href
        }

        await api.content.shareContent(shareData)
      } catch (error) {
        console.error('分享失败:', error)
      }
      // #endif

      // #ifdef APP-PLUS
      try {
        const shareOptions = {
          type: this.contentData.type === 'VIDEO' ? 'video' : 'image',
          href: this.contentData.video_url || this.contentData.image_url,
          content: this.contentData.text_content || '',
          title: this.contentData.title || ''
        }

        plus.share.sendWithSystem(shareOptions, () => {
          uni.showToast({
            title: '分享成功',
            icon: 'success'
          })
        }, (error) => {
          console.error('分享失败:', error)
          uni.showToast({
            title: '分享失败',
            icon: 'none'
          })
        })
      } catch (error) {
        console.error('分享失败:', error)
      }
      // #endif
    },

    /**
     * 复制文案
     */
    copyText() {
      const text = this.contentData.text_content || this.contentData.copywriting

      if (!text) {
        uni.showToast({
          title: '无文案内容',
          icon: 'none'
        })
        return
      }

      uni.setClipboardData({
        data: text,
        success: () => {
          uni.showToast({
            title: '文案已复制',
            icon: 'success'
          })
        },
        fail: () => {
          uni.showToast({
            title: '复制失败',
            icon: 'none'
          })
        }
      })
    },

    /**
     * 重新生成
     */
    async regenerate() {
      const res = await uni.showModal({
        title: '确认重新生成',
        content: '将消耗1次生成次数，确定重新生成吗？'
      })

      if (!res.confirm) return

      this.isLoading = true
      this.loadingText = '创建任务中...'

      try {
        const result = await api.content.regenerate(this.taskId)
        console.log('重新生成结果:', result)

        uni.showToast({
          title: '任务已创建',
          icon: 'success',
          duration: 1500
        })

        // 跳转到触发页面查看进度
        setTimeout(() => {
          uni.redirectTo({
            url: `/pages/nfc/trigger?task_id=${result.task_id || result.id}`
          })
        }, 1500)
      } catch (error) {
        console.error('重新生成失败:', error)
        uni.showModal({
          title: '操作失败',
          content: error.message || '重新生成失败，请稍后重试',
          showCancel: false
        })
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 根据反馈重新生成
     */
    async regenerateWithFeedback() {
      try {
        const confirmRes = await uni.showModal({
          title: '确认重新生成',
          content: '将根据您的反馈重新生成内容，是否继续？'
        })

        if (!confirmRes.confirm) return

        uni.showLoading({ title: '正在重新生成...' })

        // 调用重新生成API
        const res = await api.content.regenerateContent(this.taskId, {
          regenerate_reason: '根据用户反馈重新生成'
        })

        uni.hideLoading()

        // 跳转到触发页面，显示新任务的进度
        uni.redirectTo({
          url: `/pages/nfc/trigger?task_id=${res.task_id}`
        })
      } catch (error) {
        console.error('重新生成失败:', error)
        uni.hideLoading()
        uni.showToast({
          title: error.message || '重新生成失败',
          icon: 'none'
        })
      }
    },

    /**
     * 立即发布
     */
    publishNow() {
      uni.navigateTo({
        url: `/pages/publish/settings?task_id=${this.taskId}`
      })
    },

    /**
     * 格式化时间
     */
    formatTime(timeStr) {
      if (!timeStr) return ''

      const date = new Date(timeStr)
      const now = new Date()
      const diff = now - date

      // 1分钟内
      if (diff < 60000) {
        return '刚刚'
      }
      // 1小时内
      if (diff < 3600000) {
        return Math.floor(diff / 60000) + '分钟前'
      }
      // 今天
      if (date.toDateString() === now.toDateString()) {
        return '今天 ' + date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
      }
      // 昨天
      const yesterday = new Date(now)
      yesterday.setDate(yesterday.getDate() - 1)
      if (date.toDateString() === yesterday.toDateString()) {
        return '昨天 ' + date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
      }
      // 其他
      return date.toLocaleDateString('zh-CN') + ' ' + date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
    },

    /**
     * 格式化状态
     */
    formatStatus(status) {
      const statusMap = {
        'PENDING': '等待中',
        'PROCESSING': '生成中',
        'COMPLETED': '已完成',
        'FAILED': '失败'
      }
      return statusMap[status] || status
    }
  }
}
</script>

<style lang="scss" scoped>
.preview-container {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: 40rpx;
}

// 图片预览区域
.image-section {
  width: 100%;
  background: #ffffff;

  .preview-image {
    width: 100%;
    min-height: 400rpx;
  }
}

// 内容信息区域
.info-section {
  background: #ffffff;
  padding: 30rpx;
  margin-bottom: 20rpx;

  .info-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20rpx;

    .info-title {
      flex: 1;
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
      line-height: 1.4;
    }

    .info-badge {
      padding: 6rpx 16rpx;
      border-radius: 20rpx;
      font-size: 12px;
      font-weight: 500;
      white-space: nowrap;
      margin-left: 20rpx;

      &.badge-COMPLETED {
        background: #d1fae5;
        color: #065f46;
      }

      &.badge-PROCESSING {
        background: #dbeafe;
        color: #1e40af;
      }

      &.badge-PENDING {
        background: #fef3c7;
        color: #92400e;
      }

      &.badge-FAILED {
        background: #fee2e2;
        color: #991b1b;
      }
    }
  }

  .meta-info {
    display: flex;
    flex-wrap: wrap;
    gap: 24rpx;
    margin-bottom: 20rpx;

    .meta-item {
      display: flex;
      align-items: center;
      gap: 8rpx;
      font-size: 14px;
      color: #6b7280;

      .meta-icon {
        font-size: 16px;
      }

      .meta-text {
        color: #6b7280;
      }
    }
  }

  .description {
    font-size: 14px;
    color: #4b5563;
    line-height: 1.6;
  }
}

// 文案区域
.text-section {
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

  .text-content {
    padding: 24rpx;
    background: #f9fafb;
    border-radius: 12rpx;
    font-size: 14px;
    color: #374151;
    line-height: 1.8;
    margin-bottom: 20rpx;
    white-space: pre-wrap;
  }

  .text-actions {
    display: flex;
    justify-content: flex-end;

    .copy-btn {
      display: flex;
      align-items: center;
      gap: 8rpx;
      padding: 16rpx 32rpx;
      background: #6366f1;
      color: #ffffff;
      border: none;
      border-radius: 8rpx;
      font-size: 14px;

      .btn-icon {
        font-size: 16px;
      }
    }
  }
}

// 标签区域
.tags-section {
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

  .tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;

    .tag-item {
      padding: 8rpx 20rpx;
      background: #f3f4f6;
      color: #6366f1;
      border-radius: 20rpx;
      font-size: 14px;
      font-weight: 500;
    }
  }
}

// 加载遮罩
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;

  .loading-box {
    background: #ffffff;
    border-radius: 16rpx;
    padding: 60rpx 80rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30rpx;

    .loading-spinner {
      width: 60rpx;
      height: 60rpx;
      border: 4rpx solid #e5e7eb;
      border-top-color: #6366f1;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .loading-text {
      font-size: 14px;
      color: #6b7280;
    }
  }
}

// 动画
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>

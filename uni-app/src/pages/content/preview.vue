<template>
  <view class="preview-container">
    <!-- 视频播放器区域 -->
    <view class="video-section" v-if="contentData.type === 'VIDEO' && contentData.video_url">
      <video
        class="video-player"
        :src="contentData.video_url"
        :poster="contentData.poster_url || contentData.cover_url"
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

      <!-- 视频信息卡片 -->
      <view class="video-info-card">
        <view class="video-duration" v-if="duration">
          <text class="icon">⏱️</text>
          <text>{{ formatDuration(duration) }}</text>
        </view>
        <view class="video-size" v-if="contentData.file_size">
          <text class="icon">📦</text>
          <text>{{ formatFileSize(contentData.file_size) }}</text>
        </view>
      </view>
    </view>

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
    <view class="feedback-section">
      <view class="section-header">
        <text class="section-icon">💬</text>
        <text class="section-title">内容反馈</text>
      </view>

      <!-- 点赞/点踩按钮 -->
      <view class="feedback-buttons" v-if="!feedbackSubmitted">
        <button
          class="feedback-btn like-btn"
          :class="{ active: feedbackType === 'like' }"
          @tap="handleFeedback('like')"
        >
          <text class="btn-icon">{{ feedbackType === 'like' ? '👍' : '👍🏻' }}</text>
          <text class="btn-text">满意</text>
        </button>
        <button
          class="feedback-btn dislike-btn"
          :class="{ active: feedbackType === 'dislike' }"
          @tap="handleFeedback('dislike')"
        >
          <text class="btn-icon">{{ feedbackType === 'dislike' ? '👎' : '👎🏻' }}</text>
          <text class="btn-text">不满意</text>
        </button>
      </view>

      <!-- 反馈原因选择 (仅点踩时显示) -->
      <view class="feedback-reasons" v-if="feedbackType === 'dislike' && !feedbackSubmitted">
        <view class="reasons-title">请选择不满意的原因（可多选）:</view>
        <view class="reasons-list">
          <view
            v-for="(reason, index) in dislikeReasons"
            :key="index"
            class="reason-item"
            :class="{ selected: selectedReasons.includes(index) }"
            @tap="toggleReason(index)"
          >
            <text class="reason-checkbox">
              {{ selectedReasons.includes(index) ? '☑️' : '☐' }}
            </text>
            <text class="reason-text">{{ reason }}</text>
          </view>
        </view>

        <!-- 其他原因输入 -->
        <view class="other-reason-input">
          <textarea
            v-model="otherReason"
            placeholder="其他原因（选填）"
            maxlength="200"
            class="reason-textarea"
          />
          <text class="char-count">{{ otherReason.length }}/200</text>
        </view>

        <!-- 提交按钮 -->
        <button class="submit-feedback-btn" @tap="submitFeedback">
          提交反馈
        </button>
      </view>

      <!-- 反馈成功提示 -->
      <view class="feedback-success" v-if="feedbackSubmitted">
        <text class="success-icon">✅</text>
        <text class="success-text">感谢您的反馈！</text>
        <text class="success-desc" v-if="feedbackType === 'like'">
          您的满意是我们最大的动力
        </text>
        <text class="success-desc" v-else>
          我们会根据您的建议持续优化
        </text>
      </view>

      <!-- 重新生成提示（点踩且已提交后显示） -->
      <view class="regenerate-hint" v-if="feedbackType === 'dislike' && feedbackSubmitted">
        <button class="regenerate-btn-inline" @tap="regenerateWithFeedback">
          <text class="btn-icon">🔄</text>
          <text class="btn-text">根据反馈重新生成</text>
        </button>
      </view>
    </view>

    <!-- 操作按钮区域 -->
    <view class="action-section">
      <view class="action-row">
        <button class="action-btn primary" @tap="downloadVideo" v-if="contentData.type === 'VIDEO'">
          <text class="btn-icon">⬇️</text>
          <text>下载视频</text>
        </button>
        <button class="action-btn primary" @tap="downloadImage" v-if="contentData.type === 'IMAGE'">
          <text class="btn-icon">⬇️</text>
          <text>下载图片</text>
        </button>
        <button class="action-btn" @tap="saveToAlbum">
          <text class="btn-icon">💾</text>
          <text>保存到相册</text>
        </button>
      </view>

      <view class="action-row">
        <button class="action-btn" @tap="shareContent">
          <text class="btn-icon">📤</text>
          <text>分享内容</text>
        </button>
        <button class="action-btn" @tap="regenerate">
          <text class="btn-icon">🔄</text>
          <text>重新生成</text>
        </button>
      </view>

      <button class="publish-btn" @tap="publishNow">
        <text class="btn-icon">🚀</text>
        <text>立即发布</text>
      </button>
    </view>

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

export default {
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
      feedbackType: '',         // 反馈类型: like/dislike
      feedbackSubmitted: false,  // 是否已提交反馈
      selectedReasons: [],      // 选中的不满意原因
      otherReason: '',          // 其他原因

      // 不满意原因列表
      dislikeReasons: [
        '内容与需求不符',
        '质量不够好',
        '创意不够新颖',
        '文案表达不准确',
        '画面/视频效果差',
        '时长/篇幅不合适',
        '缺少关键信息',
        '风格不符合预期'
      ]
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

    onVideoPause() {
      this.isPlaying = false
      console.log('视频暂停')
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
     * 处理反馈
     */
    handleFeedback(type) {
      if (this.feedbackSubmitted) return

      this.feedbackType = type

      // 如果是点赞，直接提交
      if (type === 'like') {
        this.submitFeedback()
      }
      // 如果是点踩，显示原因选择
    },

    /**
     * 切换原因选择
     */
    toggleReason(index) {
      const pos = this.selectedReasons.indexOf(index)
      if (pos > -1) {
        this.selectedReasons.splice(pos, 1)
      } else {
        this.selectedReasons.push(index)
      }
    },

    /**
     * 提交反馈
     */
    async submitFeedback() {
      if (this.feedbackSubmitted) return

      // 点踩时需要选择至少一个原因
      if (this.feedbackType === 'dislike' && this.selectedReasons.length === 0 && !this.otherReason) {
        uni.showToast({
          title: '请至少选择一个原因',
          icon: 'none'
        })
        return
      }

      try {
        uni.showLoading({ title: '提交中...' })

        // 构建反馈数据
        const feedbackData = {
          task_id: this.taskId,
          feedback_type: this.feedbackType,
          reasons: this.feedbackType === 'dislike'
            ? this.selectedReasons.map(i => this.dislikeReasons[i])
            : [],
          other_reason: this.otherReason,
          submit_time: new Date().toISOString()
        }

        console.log('提交反馈:', feedbackData)

        // 调用API提交反馈
        await api.content.submitFeedback(feedbackData)

        this.feedbackSubmitted = true

        // 震动反馈
        uni.vibrateShort()

        uni.showToast({
          title: '反馈提交成功',
          icon: 'success'
        })
      } catch (error) {
        console.error('提交反馈失败:', error)
        uni.showToast({
          title: '提交失败，请重试',
          icon: 'none'
        })
      } finally {
        uni.hideLoading()
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

        // 调用重新生成API，携带反馈信息
        const res = await api.content.regenerateContent(this.taskId, {
          regenerate_reason: this.selectedReasons.map(i => this.dislikeReasons[i]).join('、'),
          adjust_params: {
            feedback_type: this.feedbackType,
            user_feedback: this.otherReason
          }
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
     * 格式化时长
     */
    formatDuration(seconds) {
      const mins = Math.floor(seconds / 60)
      const secs = Math.floor(seconds % 60)
      return `${mins}:${secs.toString().padStart(2, '0')}`
    },

    /**
     * 格式化文件大小
     */
    formatFileSize(bytes) {
      if (bytes < 1024) return bytes + 'B'
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
      return (bytes / 1024 / 1024).toFixed(1) + 'MB'
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

// 视频播放区域
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

// 反馈区域
.feedback-section {
  background: #ffffff;
  padding: 30rpx;
  margin-bottom: 20rpx;

  .section-header {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 30rpx;

    .section-icon {
      font-size: 20px;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
    }
  }

  .feedback-buttons {
    display: flex;
    gap: 30rpx;
    margin-bottom: 30rpx;

    .feedback-btn {
      flex: 1;
      height: 100rpx;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border-radius: 12rpx;
      border: 2rpx solid #e5e7eb;
      background: #fff;
      font-size: 14px;
      color: #6b7280;
      transition: all 0.3s;

      .btn-icon {
        font-size: 24px;
        margin-bottom: 8rpx;
      }

      &.like-btn.active {
        border-color: #10b981;
        background: #d1fae5;
        color: #059669;
      }

      &.dislike-btn.active {
        border-color: #ef4444;
        background: #fee2e2;
        color: #dc2626;
      }
    }
  }

  .feedback-reasons {
    .reasons-title {
      font-size: 14px;
      color: #1f2937;
      margin-bottom: 20rpx;
    }

    .reasons-list {
      margin-bottom: 30rpx;

      .reason-item {
        padding: 20rpx;
        background: #f9fafb;
        border-radius: 12rpx;
        margin-bottom: 16rpx;
        display: flex;
        align-items: center;
        border: 2rpx solid transparent;
        transition: all 0.3s;

        &.selected {
          background: #fef3c7;
          border-color: #f59e0b;
        }

        .reason-checkbox {
          font-size: 16px;
          margin-right: 16rpx;
        }

        .reason-text {
          flex: 1;
          font-size: 14px;
          color: #1f2937;
        }
      }
    }

    .other-reason-input {
      margin-bottom: 30rpx;
      position: relative;

      .reason-textarea {
        width: 100%;
        min-height: 150rpx;
        padding: 20rpx;
        background: #f9fafb;
        border-radius: 12rpx;
        font-size: 14px;
        color: #1f2937;
        border: 2rpx solid #e5e7eb;

        &:focus {
          border-color: #6366f1;
        }
      }

      .char-count {
        position: absolute;
        right: 20rpx;
        bottom: 10rpx;
        font-size: 12px;
        color: #9ca3af;
      }
    }

    .submit-feedback-btn {
      width: 100%;
      height: 88rpx;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border-radius: 12rpx;
      font-size: 16px;
      font-weight: 600;
      border: none;
    }
  }

  .feedback-success {
    padding: 60rpx 30rpx;
    display: flex;
    flex-direction: column;
    align-items: center;

    .success-icon {
      font-size: 50px;
      margin-bottom: 30rpx;
    }

    .success-text {
      font-size: 16px;
      font-weight: 600;
      color: #059669;
      margin-bottom: 16rpx;
    }

    .success-desc {
      font-size: 13px;
      color: #6b7280;
    }
  }

  .regenerate-hint {
    margin-top: 30rpx;

    .regenerate-btn-inline {
      width: 100%;
      height: 88rpx;
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: #fff;
      border-radius: 12rpx;
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12rpx;
      border: none;

      .btn-icon {
        font-size: 18px;
      }
    }
  }
}

// 操作按钮区域
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

      .btn-icon {
        font-size: 18px;
      }
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

    .btn-icon {
      font-size: 20px;
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

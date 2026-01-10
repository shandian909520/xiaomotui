<template>
  <view class="material-detail-container">
    <!-- 加载中状态 -->
    <view class="loading-wrapper" v-if="isLoading">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>

    <!-- 内容区域 -->
    <view class="content-wrapper" v-else>
      <!-- 媒体预览区域 -->
      <view class="media-section">
        <!-- 视频预览 -->
        <view class="video-wrapper" v-if="materialData.type === 'VIDEO'">
          <video
            class="video-player"
            :src="materialData.file_url"
            :poster="materialData.cover_url"
            :controls="true"
            :show-center-play-btn="true"
            :show-fullscreen-btn="true"
            :enable-progress-gesture="true"
            @error="onVideoError"
            @timeupdate="onVideoTimeUpdate"
          />
          <view class="video-info">
            <text class="video-duration" v-if="videoDuration">{{ formatDuration(videoDuration) }}</text>
            <text class="video-size" v-if="materialData.file_size">{{ formatFileSize(materialData.file_size) }}</text>
          </view>
        </view>

        <!-- 图片预览 -->
        <image
          v-else-if="materialData.type === 'IMAGE'"
          class="preview-image"
          :src="materialData.file_url"
          mode="aspectFit"
          @tap="previewImage"
        />

        <!-- 文本预览 -->
        <view v-else-if="materialData.type === 'TEXT'" class="text-preview">
          <text class="text-content">{{ materialData.content }}</text>
        </view>

        <!-- 模板预览 -->
        <view v-else-if="materialData.type === 'TEMPLATE'" class="template-preview">
          <text class="template-icon">📄</text>
          <text class="template-name">{{ materialData.title }}</text>
        </view>
      </view>

      <!-- 基本信息区域 -->
      <view class="info-section">
        <view class="info-header">
          <text class="info-title">{{ materialData.title || '未命名素材' }}</text>
          <view class="info-badge" :class="`badge-${materialData.status}`">
            {{ formatStatus(materialData.status) }}
          </view>
        </view>

        <view class="info-row">
          <view class="info-item">
            <text class="info-icon">🗂️</text>
            <text class="info-label">分类：</text>
            <text class="info-value">{{ materialData.category_name || '未分类' }}</text>
          </view>
          <view class="info-item">
            <text class="info-icon">⭐</text>
            <text class="info-label">评分：</text>
            <text class="info-value">{{ materialData.rating || 0 }}/5</text>
          </view>
        </view>

        <view class="info-row">
          <view class="info-item">
            <text class="info-icon">👁️</text>
            <text class="info-label">浏览：</text>
            <text class="info-value">{{ materialData.view_count || 0 }}次</text>
          </view>
          <view class="info-item">
            <text class="info-icon">💗</text>
            <text class="info-label">收藏：</text>
            <text class="info-value">{{ materialData.favorite_count || 0 }}次</text>
          </view>
        </view>

        <view class="info-row">
          <view class="info-item full-width">
            <text class="info-icon">🕒</text>
            <text class="info-label">上传时间：</text>
            <text class="info-value">{{ formatTime(materialData.create_time) }}</text>
          </view>
        </view>

        <view class="description" v-if="materialData.description">
          <text class="description-title">素材描述</text>
          <text class="description-text">{{ materialData.description }}</text>
        </view>
      </view>

      <!-- 标签区域 -->
      <view class="tags-section" v-if="materialData.tags && materialData.tags.length">
        <view class="section-header">
          <text class="section-icon">🏷️</text>
          <text class="section-title">标签</text>
        </view>
        <view class="tags-list">
          <view class="tag-item" v-for="(tag, index) in materialData.tags" :key="index">
            #{{ tag }}
          </view>
        </view>
      </view>

      <!-- 使用统计区域 -->
      <view class="stats-section">
        <view class="section-header">
          <text class="section-icon">📊</text>
          <text class="section-title">使用统计</text>
        </view>
        <view class="stats-grid">
          <view class="stat-card">
            <text class="stat-value">{{ materialData.usage_count || 0 }}</text>
            <text class="stat-label">使用次数</text>
          </view>
          <view class="stat-card">
            <text class="stat-value">{{ materialData.download_count || 0 }}</text>
            <text class="stat-label">下载次数</text>
          </view>
          <view class="stat-card">
            <text class="stat-value">{{ materialData.share_count || 0 }}</text>
            <text class="stat-label">分享次数</text>
          </view>
        </view>
      </view>

      <!-- 相关素材区域 -->
      <view class="related-section" v-if="relatedMaterials.length">
        <view class="section-header">
          <text class="section-icon">🔗</text>
          <text class="section-title">相关素材</text>
        </view>
        <scroll-view class="related-scroll" scroll-x>
          <view class="related-list">
            <view
              class="related-item"
              v-for="item in relatedMaterials"
              :key="item.id"
              @tap="viewRelatedMaterial(item.id)"
            >
              <image class="related-image" :src="item.cover_url || item.file_url" mode="aspectFill" />
              <text class="related-title">{{ item.title }}</text>
            </view>
          </view>
        </scroll-view>
      </view>

      <!-- 操作按钮区域 -->
      <view class="action-section">
        <view class="action-row">
          <button class="action-btn" :class="{ active: isFavorite }" @tap="toggleFavorite">
            <text class="btn-icon">{{ isFavorite ? '❤️' : '🤍' }}</text>
            <text>{{ isFavorite ? '已收藏' : '收藏' }}</text>
          </button>
          <button class="action-btn" @tap="downloadMaterial">
            <text class="btn-icon">⬇️</text>
            <text>下载</text>
          </button>
          <button class="action-btn" @tap="shareMaterial">
            <text class="btn-icon">📤</text>
            <text>分享</text>
          </button>
        </view>

        <button class="use-btn" @tap="useMaterial">
          <text class="btn-icon">✨</text>
          <text>使用素材</text>
        </button>
      </view>
    </view>

    <!-- 加载遮罩 -->
    <view class="loading-overlay" v-if="actionLoading">
      <view class="loading-box">
        <view class="loading-spinner"></view>
        <text class="loading-text">{{ loadingText }}</text>
      </view>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'

export default {
  data() {
    return {
      materialId: '',
      materialData: {},
      relatedMaterials: [],

      // 视频相关
      videoDuration: 0,

      // 状态
      isLoading: true,
      actionLoading: false,
      loadingText: '',
      isFavorite: false,
    }
  },

  onLoad(options) {
    console.log('素材详情页面参数:', options)

    if (options.id) {
      this.materialId = options.id
      this.loadMaterialDetail()
    } else {
      uni.showModal({
        title: '提示',
        content: '缺少素材ID参数',
        showCancel: false,
        success: () => {
          uni.navigateBack()
        }
      })
    }
  },

  onShareAppMessage() {
    return {
      title: this.materialData.title || '查看素材',
      path: `/pages/material/detail?id=${this.materialId}`,
      imageUrl: this.materialData.cover_url || this.materialData.file_url
    }
  },

  methods: {
    /**
     * 加载素材详情
     */
    async loadMaterialDetail() {
      this.isLoading = true

      try {
        // 尝试调用API获取素材详情
        if (typeof api.material?.getDetail === 'function') {
          const res = await api.material.getDetail(this.materialId)
          this.materialData = res

          // 解析标签
          if (this.materialData.tags && typeof this.materialData.tags === 'string') {
            try {
              this.materialData.tags = JSON.parse(this.materialData.tags)
            } catch (e) {
              this.materialData.tags = this.materialData.tags.split(',')
            }
          }

          // 加载相关素材
          this.loadRelatedMaterials()
        } else {
          // API不存在，使用模拟数据
          this.materialData = this.generateMockMaterialData()
          this.relatedMaterials = this.generateMockRelatedMaterials()
        }

        // 检查是否已收藏
        this.checkFavoriteStatus()
      } catch (error) {
        console.error('加载素材详情失败:', error)

        // 失败时使用模拟数据
        this.materialData = this.generateMockMaterialData()
        this.relatedMaterials = this.generateMockRelatedMaterials()
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 加载相关素材
     */
    async loadRelatedMaterials() {
      try {
        if (typeof api.material?.getRelated === 'function') {
          const res = await api.material.getRelated(this.materialId)
          this.relatedMaterials = res.list || res || []
        } else {
          this.relatedMaterials = this.generateMockRelatedMaterials()
        }
      } catch (error) {
        console.error('加载相关素材失败:', error)
        this.relatedMaterials = this.generateMockRelatedMaterials()
      }
    },

    /**
     * 检查收藏状态
     */
    async checkFavoriteStatus() {
      try {
        if (typeof api.material?.checkFavorite === 'function') {
          const res = await api.material.checkFavorite(this.materialId)
          this.isFavorite = res.is_favorite || false
        } else {
          // 从本地存储检查
          const favorites = uni.getStorageSync('material_favorites') || []
          this.isFavorite = favorites.includes(this.materialId)
        }
      } catch (error) {
        console.error('检查收藏状态失败:', error)
      }
    },

    /**
     * 切换收藏
     */
    async toggleFavorite() {
      try {
        if (typeof api.material?.toggleFavorite === 'function') {
          const res = await api.material.toggleFavorite(this.materialId)
          this.isFavorite = res.is_favorite

          uni.showToast({
            title: this.isFavorite ? '已收藏' : '已取消收藏',
            icon: 'success'
          })
        } else {
          // 使用本地存储
          let favorites = uni.getStorageSync('material_favorites') || []

          if (this.isFavorite) {
            // 取消收藏
            favorites = favorites.filter(id => id !== this.materialId)
            this.isFavorite = false
          } else {
            // 添加收藏
            favorites.push(this.materialId)
            this.isFavorite = true
          }

          uni.setStorageSync('material_favorites', favorites)

          uni.showToast({
            title: this.isFavorite ? '已收藏' : '已取消收藏',
            icon: 'success'
          })
        }
      } catch (error) {
        console.error('切换收藏失败:', error)
        uni.showToast({
          title: '操作失败',
          icon: 'none'
        })
      }
    },

    /**
     * 下载素材
     */
    async downloadMaterial() {
      if (!this.materialData.file_url) {
        uni.showToast({
          title: '文件地址不存在',
          icon: 'none'
        })
        return
      }

      this.actionLoading = true
      this.loadingText = '下载中...'

      try {
        const fileUrl = this.materialData.file_url
        const fileType = this.materialData.type

        // #ifdef H5
        // H5下载方式
        const a = document.createElement('a')
        a.href = fileUrl
        a.download = `material_${this.materialId}.${this.getFileExtension(fileType)}`
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
          url: fileUrl
        })

        if (downloadRes.statusCode === 200) {
          if (fileType === 'VIDEO') {
            await uni.saveVideoToPhotosAlbum({
              filePath: downloadRes.tempFilePath
            })
          } else if (fileType === 'IMAGE') {
            await uni.saveImageToPhotosAlbum({
              filePath: downloadRes.tempFilePath
            })
          }

          uni.showToast({
            title: '已保存到相册',
            icon: 'success'
          })
        }
        // #endif

        // 更新下载次数
        this.materialData.download_count = (this.materialData.download_count || 0) + 1
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
        this.actionLoading = false
      }
    },

    /**
     * 分享素材
     */
    async shareMaterial() {
      // #ifdef MP-WEIXIN
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
          title: this.materialData.title || '查看素材',
          url: window.location.href
        }

        if (navigator.share) {
          await navigator.share(shareData)
        } else {
          // 复制链接
          await uni.setClipboardData({
            data: window.location.href
          })
          uni.showToast({
            title: '链接已复制',
            icon: 'success'
          })
        }

        // 更新分享次数
        this.materialData.share_count = (this.materialData.share_count || 0) + 1
      } catch (error) {
        console.error('分享失败:', error)
      }
      // #endif
    },

    /**
     * 使用素材
     */
    useMaterial() {
      uni.showActionSheet({
        itemList: ['用于AI生成', '直接发布', '添加到素材库'],
        success: (res) => {
          const index = res.tapIndex

          if (index === 0) {
            // 跳转到AI生成页面，带上素材ID
            uni.navigateTo({
              url: `/pages/content/generate?material_id=${this.materialId}`
            })
          } else if (index === 1) {
            // 跳转到发布设置页面
            uni.navigateTo({
              url: `/pages/publish/settings?material_id=${this.materialId}`
            })
          } else if (index === 2) {
            // 添加到素材库
            this.addToLibrary()
          }
        }
      })
    },

    /**
     * 添加到素材库
     */
    async addToLibrary() {
      try {
        if (typeof api.material?.addToLibrary === 'function') {
          await api.material.addToLibrary(this.materialId)
        }

        uni.showToast({
          title: '已添加到素材库',
          icon: 'success'
        })
      } catch (error) {
        console.error('添加失败:', error)
        uni.showToast({
          title: '添加失败',
          icon: 'none'
        })
      }
    },

    /**
     * 查看相关素材
     */
    viewRelatedMaterial(materialId) {
      uni.redirectTo({
        url: `/pages/material/detail?id=${materialId}`
      })
    },

    /**
     * 预览图片
     */
    previewImage() {
      if (!this.materialData.file_url) return

      uni.previewImage({
        urls: [this.materialData.file_url],
        current: this.materialData.file_url
      })
    },

    /**
     * 视频错误处理
     */
    onVideoError(e) {
      console.error('视频加载错误:', e)
      uni.showToast({
        title: '视频加载失败',
        icon: 'none'
      })
    },

    /**
     * 视频时间更新
     */
    onVideoTimeUpdate(e) {
      this.videoDuration = e.detail.duration
    },

    /**
     * 生成模拟素材数据
     */
    generateMockMaterialData() {
      const types = ['IMAGE', 'VIDEO', 'TEXT', 'TEMPLATE']
      const type = types[Math.floor(Math.random() * types.length)]

      const mockData = {
        id: this.materialId,
        title: '优质素材示例',
        type: type,
        status: 'ACTIVE',
        description: '这是一个优质的素材示例，包含完整的信息和高质量的内容。适用于各种营销场景，能够有效提升转化率。',
        category_name: '营销素材',
        rating: 4.5,
        view_count: 1234,
        favorite_count: 56,
        usage_count: 89,
        download_count: 234,
        share_count: 45,
        file_size: 2048576,
        tags: ['营销', '推广', '热门', '高质量'],
        create_time: new Date(Date.now() - 86400000 * 7).toISOString()
      }

      if (type === 'IMAGE') {
        mockData.file_url = `https://picsum.photos/400/600?random=${this.materialId}`
        mockData.cover_url = mockData.file_url
      } else if (type === 'VIDEO') {
        mockData.file_url = 'https://www.w3schools.com/html/mov_bbb.mp4'
        mockData.cover_url = `https://picsum.photos/400/600?random=${this.materialId}`
      } else if (type === 'TEXT') {
        mockData.content = '这是一段精心编写的营销文案，具有很强的感染力和转化力。\n\n通过精准的文字表达，能够快速抓住用户的注意力，激发购买欲望。\n\n适用于多种营销场景。'
      }

      return mockData
    },

    /**
     * 生成模拟相关素材
     */
    generateMockRelatedMaterials() {
      const materials = []
      for (let i = 1; i <= 5; i++) {
        materials.push({
          id: `related_${i}`,
          title: `相关素材 ${i}`,
          cover_url: `https://picsum.photos/200/300?random=${i}`,
          file_url: `https://picsum.photos/200/300?random=${i}`,
        })
      }
      return materials
    },

    /**
     * 格式化文件大小
     */
    formatFileSize(bytes) {
      if (!bytes) return '-'
      if (bytes < 1024) return bytes + 'B'
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
      return (bytes / 1024 / 1024).toFixed(1) + 'MB'
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
     * 格式化时间
     */
    formatTime(timeStr) {
      if (!timeStr) return '-'

      const date = new Date(timeStr)
      const now = new Date()
      const diff = now - date

      if (diff < 60000) return '刚刚'
      if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前'
      if (date.toDateString() === now.toDateString()) {
        return '今天 ' + date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
      }

      const yesterday = new Date(now)
      yesterday.setDate(yesterday.getDate() - 1)
      if (date.toDateString() === yesterday.toDateString()) {
        return '昨天 ' + date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
      }

      return date.toLocaleDateString('zh-CN')
    },

    /**
     * 格式化状态
     */
    formatStatus(status) {
      const statusMap = {
        'ACTIVE': '正常',
        'DISABLED': '已禁用',
        'DELETED': '已删除'
      }
      return statusMap[status] || status
    },

    /**
     * 获取文件扩展名
     */
    getFileExtension(type) {
      const extMap = {
        'IMAGE': 'jpg',
        'VIDEO': 'mp4',
        'TEXT': 'txt',
        'TEMPLATE': 'json'
      }
      return extMap[type] || 'file'
    }
  }
}
</script>

<style lang="scss" scoped>
.material-detail-container {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: 40rpx;
}

// 加载状态
.loading-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
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

// 媒体预览区域
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

// 基本信息区域
.info-section {
  background: #ffffff;
  padding: 30rpx;
  margin-bottom: 20rpx;

  .info-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30rpx;

    .info-title {
      flex: 1;
      font-size: 20px;
      font-weight: 600;
      color: #1f2937;
    }

    .info-badge {
      padding: 6rpx 16rpx;
      border-radius: 20rpx;
      font-size: 12px;
      font-weight: 500;
      margin-left: 20rpx;

      &.badge-ACTIVE {
        background: #d1fae5;
        color: #065f46;
      }

      &.badge-DISABLED {
        background: #fee2e2;
        color: #991b1b;
      }
    }
  }

  .info-row {
    display: flex;
    gap: 30rpx;
    margin-bottom: 20rpx;

    .info-item {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 8rpx;
      font-size: 14px;

      &.full-width {
        flex: none;
        width: 100%;
      }

      .info-icon {
        font-size: 16px;
      }

      .info-label {
        color: #6b7280;
      }

      .info-value {
        color: #1f2937;
        font-weight: 500;
      }
    }
  }

  .description {
    margin-top: 30rpx;
    padding-top: 30rpx;
    border-top: 1rpx solid #e5e7eb;

    .description-title {
      display: block;
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 16rpx;
    }

    .description-text {
      display: block;
      font-size: 14px;
      color: #4b5563;
      line-height: 1.6;
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

// 统计区域
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

// 相关素材区域
.related-section {
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

  .related-scroll {
    width: 100%;
    white-space: nowrap;
  }

  .related-list {
    display: inline-flex;
    gap: 20rpx;

    .related-item {
      display: inline-flex;
      flex-direction: column;
      width: 200rpx;
      gap: 12rpx;

      .related-image {
        width: 200rpx;
        height: 200rpx;
        border-radius: 12rpx;
        background: #f3f4f6;
      }

      .related-title {
        font-size: 14px;
        color: #4b5563;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }
  }
}

// 操作按钮区域
.action-section {
  padding: 30rpx;

  .action-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20rpx;
    margin-bottom: 20rpx;

    .action-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12rpx;
      padding: 24rpx;
      background: #ffffff;
      color: #374151;
      border: 1rpx solid #e5e7eb;
      border-radius: 12rpx;
      font-size: 14px;

      &.active {
        background: #fef2f2;
        border-color: #ef4444;
        color: #ef4444;
      }

      .btn-icon {
        font-size: 24px;
      }
    }
  }

  .use-btn {
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

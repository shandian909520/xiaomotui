/**
 * 批量创建小魔推Uni-App剩余页面
 */

const fs = require('fs');
const path = require('path');

// 页面模板
const pageTemplates = {
  // 内容预览页面
  'pages/content/preview.vue': `<template>
  <view class="preview-page">
    <view class="nav-bar">
      <view class="nav-title">内容预览</view>
      <view class="nav-actions">
        <text class="action-btn" @tap="handleShare">分享</text>
      </view>
    </view>

    <scroll-view class="content-scroll" scroll-y>
      <!-- 内容信息 -->
      <view class="content-info" v-if="contentData">
        <view class="info-header">
          <view class="status-badge" :class="'status-' + contentData.status">
            {{ formatStatus(contentData.status) }}
          </view>
          <view class="create-time">{{ contentData.created_at }}</view>
        </view>

        <!-- 文本内容 -->
        <view class="text-content" v-if="contentData.type === 'TEXT'">
          <view class="content-title">{{ contentData.title }}</view>
          <view class="content-body">{{ contentData.content }}</view>
        </view>

        <!-- 图片内容 -->
        <view class="image-content" v-if="contentData.type === 'IMAGE'">
          <image
            v-for="(img, index) in contentData.images"
            :key="index"
            :src="img"
            mode="aspectFit"
            class="content-image"
            @tap="previewImage(index)"
          />
        </view>

        <!-- 视频内容 -->
        <view class="video-content" v-if="contentData.type === 'VIDEO'">
          <video
            :src="contentData.video_url"
            controls
            class="content-video"
          ></video>
        </view>

        <!-- 元信息 -->
        <view class="meta-info">
          <view class="meta-item">
            <text class="meta-label">平台:</text>
            <text class="meta-value">{{ contentData.platform }}</text>
          </view>
          <view class="meta-item">
            <text class="meta-label">类型:</text>
            <text class="meta-value">{{ formatContentType(contentData.type) }}</text>
          </view>
          <view class="meta-item" v-if="contentData.keywords">
            <text class="meta-label">关键词:</text>
            <text class="meta-value">{{ contentData.keywords }}</text>
          </view>
        </view>
      </view>

      <!-- 加载中 -->
      <view class="loading-state" v-if="isLoading">
        <view class="loading-spinner"></view>
        <text>加载中...</text>
      </view>

      <!-- 空状态 -->
      <view class="empty-state" v-if="!isLoading && !contentData">
        <text class="empty-icon">📭</text>
        <text class="empty-text">暂无内容</text>
      </view>
    </scroll-view>

    <!-- 底部操作 -->
    <view class="bottom-bar" v-if="contentData && contentData.status === 'completed'">
      <button class="secondary-btn" @tap="handleDownload">下载</button>
      <button class="primary-btn" @tap="handlePublish">发布</button>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'

export default {
  data() {
    return {
      taskId: '',
      contentData: null,
      isLoading: false
    }
  },

  onLoad(options) {
    if (options.task_id) {
      this.taskId = options.task_id
      this.loadContent()
    }
  },

  methods: {
    async loadContent() {
      this.isLoading = true
      try {
        const res = await api.content.getTaskDetail(this.taskId)
        this.contentData = res
      } catch (error) {
        console.error('加载失败:', error)
        uni.showToast({ title: '加载失败', icon: 'none' })
      } finally {
        this.isLoading = false
      }
    },

    formatStatus(status) {
      const map = {
        'pending': '等待中',
        'processing': '生成中',
        'completed': '已完成',
        'failed': '失败'
      }
      return map[status] || status
    },

    formatContentType(type) {
      const map = {
        'TEXT': '文本',
        'IMAGE': '图片',
        'VIDEO': '视频',
        'MIXED': '混合'
      }
      return map[type] || type
    },

    previewImage(index) {
      uni.previewImage({
        current: index,
        urls: this.contentData.images
      })
    },

    async handleDownload() {
      try {
        await api.content.downloadContent(this.taskId)
        uni.showToast({ title: '下载成功', icon: 'success' })
      } catch (error) {
        uni.showToast({ title: '下载失败', icon: 'none' })
      }
    },

    handlePublish() {
      uni.navigateTo({
        url: \`/pages/publish/settings?task_id=\${this.taskId}\`
      })
    },

    async handleShare() {
      try {
        await api.content.shareContent({
          title: this.contentData.title,
          content: this.contentData.content
        })
      } catch (error) {
        console.error('分享失败:', error)
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.preview-page {
  min-height: 100vh;
  background: #f8f9fa;
  display: flex;
  flex-direction: column;
}

.nav-bar {
  background: #ffffff;
  padding: 20rpx 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;

  .nav-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
  }

  .action-btn {
    color: #6366f1;
    font-size: 14px;
  }
}

.content-scroll {
  flex: 1;
  padding: 30rpx;
}

.content-info {
  background: #ffffff;
  border-radius: 12rpx;
  padding: 30rpx;
}

.info-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30rpx;

  .status-badge {
    padding: 8rpx 20rpx;
    border-radius: 20rpx;
    font-size: 12px;

    &.status-completed {
      background: #d1fae5;
      color: #065f46;
    }

    &.status-processing {
      background: #dbeafe;
      color: #1e40af;
    }

    &.status-failed {
      background: #fee2e2;
      color: #991b1b;
    }
  }

  .create-time {
    font-size: 12px;
    color: #9ca3af;
  }
}

.text-content {
  .content-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 20rpx;
  }

  .content-body {
    font-size: 14px;
    color: #4b5563;
    line-height: 1.8;
    white-space: pre-wrap;
  }
}

.image-content {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;

  .content-image {
    width: 100%;
    height: 300rpx;
    border-radius: 8rpx;
  }
}

.video-content {
  .content-video {
    width: 100%;
    height: 400rpx;
    border-radius: 8rpx;
  }
}

.meta-info {
  margin-top: 30rpx;
  padding-top: 30rpx;
  border-top: 1rpx solid #e5e7eb;

  .meta-item {
    display: flex;
    align-items: center;
    padding: 12rpx 0;

    .meta-label {
      font-size: 14px;
      color: #6b7280;
      width: 120rpx;
    }

    .meta-value {
      font-size: 14px;
      color: #1f2937;
      flex: 1;
    }
  }
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx 0;
  font-size: 14px;
  color: #9ca3af;
}

.loading-spinner {
  width: 60rpx;
  height: 60rpx;
  border: 4rpx solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 20rpx;
}

.empty-icon {
  font-size: 60px;
  margin-bottom: 20rpx;
}

.bottom-bar {
  background: #ffffff;
  padding: 24rpx 30rpx;
  border-top: 1rpx solid #e5e7eb;
  display: flex;
  gap: 20rpx;

  button {
    flex: 1;
    height: 88rpx;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
  }

  .secondary-btn {
    background: #ffffff;
    color: #6b7280;
    border: 1rpx solid #d1d5db;
  }

  .primary-btn {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    border: none;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
`,

  // 素材列表页面
  'pages/material/list.vue': `<template>
  <view class="material-page">
    <view class="nav-bar">
      <view class="nav-title">素材库</view>
      <text class="upload-btn" @tap="handleUpload">📤 上传</text>
    </view>

    <!-- 筛选栏 -->
    <view class="filter-bar">
      <scroll-view scroll-x class="filter-scroll">
        <view class="filter-tabs">
          <text
            v-for="type in materialTypes"
            :key="type.value"
            class="filter-tab"
            :class="{ active: currentType === type.value }"
            @tap="changeType(type.value)"
          >
            {{ type.label }}
          </text>
        </view>
      </scroll-view>
    </view>

    <!-- 素材列表 -->
    <scroll-view class="material-scroll" scroll-y @scrolltolower="loadMore">
      <view class="material-grid">
        <view
          v-for="item in materialList"
          :key="item.id"
          class="material-item"
          @tap="viewDetail(item)"
        >
          <!-- 缩略图 -->
          <view class="material-thumb">
            <image v-if="item.type === 'IMAGE'" :src="item.url" mode="aspectFill" />
            <video v-else-if="item.type === 'VIDEO'" :src="item.url" :show-center-play-btn="false" />
            <text v-else class="file-icon">📄</text>
          </view>

          <!-- 信息 -->
          <view class="material-info">
            <text class="material-name">{{ item.name }}</text>
            <text class="material-size">{{ formatSize(item.size) }}</text>
          </view>
        </view>
      </view>

      <!-- 加载更多 -->
      <view class="load-more" v-if="hasMore">
        <text>加载中...</text>
      </view>

      <!-- 空状态 -->
      <view class="empty-state" v-if="!isLoading && materialList.length === 0">
        <text class="empty-icon">📦</text>
        <text class="empty-text">暂无素材</text>
      </view>
    </scroll-view>
  </view>
</template>

<script>
import api from '../../api/index.js'

export default {
  data() {
    return {
      currentType: 'ALL',
      materialTypes: [
        { value: 'ALL', label: '全部' },
        { value: 'IMAGE', label: '图片' },
        { value: 'VIDEO', label: '视频' },
        { value: 'AUDIO', label: '音频' },
        { value: 'TEXT', label: '文本' }
      ],
      materialList: [],
      page: 1,
      pageSize: 20,
      hasMore: true,
      isLoading: false
    }
  },

  onLoad() {
    this.loadMaterials()
  },

  methods: {
    async loadMaterials(refresh = false) {
      if (this.isLoading) return
      this.isLoading = true

      if (refresh) {
        this.page = 1
        this.materialList = []
      }

      try {
        const res = await api.material.getMaterialList({
          page: this.page,
          pageSize: this.pageSize,
          type: this.currentType === 'ALL' ? undefined : this.currentType
        })

        const newList = res.data || []
        this.materialList = this.page === 1 ? newList : [...this.materialList, ...newList]
        this.hasMore = newList.length >= this.pageSize
      } catch (error) {
        console.error('加载失败:', error)
        uni.showToast({ title: '加载失败', icon: 'none' })
      } finally {
        this.isLoading = false
      }
    },

    loadMore() {
      if (this.hasMore && !this.isLoading) {
        this.page++
        this.loadMaterials()
      }
    },

    changeType(type) {
      this.currentType = type
      this.loadMaterials(true)
    },

    viewDetail(item) {
      uni.navigateTo({
        url: \`/pages/material/detail?id=\${item.id}\`
      })
    },

    handleUpload() {
      uni.chooseImage({
        count: 9,
        success: async (res) => {
          for (const path of res.tempFilePaths) {
            try {
              await api.material.uploadMaterial({ filePath: path })
            } catch (error) {
              console.error('上传失败:', error)
            }
          }
          this.loadMaterials(true)
        }
      })
    },

    formatSize(bytes) {
      if (!bytes) return '-'
      if (bytes < 1024) return bytes + 'B'
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
      return (bytes / (1024 * 1024)).toFixed(1) + 'MB'
    }
  }
}
</script>

<style lang="scss" scoped>
.material-page {
  min-height: 100vh;
  background: #f8f9fa;
  display: flex;
  flex-direction: column;
}

.nav-bar {
  background: #ffffff;
  padding: 20rpx 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;

  .nav-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
  }

  .upload-btn {
    color: #6366f1;
    font-size: 14px;
  }
}

.filter-bar {
  background: #ffffff;
  border-bottom: 1rpx solid #e5e7eb;
}

.filter-scroll {
  white-space: nowrap;
}

.filter-tabs {
  display: inline-flex;
  padding: 20rpx 30rpx;
  gap: 30rpx;

  .filter-tab {
    font-size: 14px;
    color: #6b7280;
    padding: 8rpx 24rpx;
    border-radius: 20rpx;

    &.active {
      background: #6366f1;
      color: #ffffff;
    }
  }
}

.material-scroll {
  flex: 1;
  padding: 30rpx;
}

.material-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
}

.material-item {
  background: #ffffff;
  border-radius: 12rpx;
  overflow: hidden;

  .material-thumb {
    width: 100%;
    height: 200rpx;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;

    image, video {
      width: 100%;
      height: 100%;
    }

    .file-icon {
      font-size: 40px;
    }
  }

  .material-info {
    padding: 20rpx;

    .material-name {
      display: block;
      font-size: 14px;
      color: #1f2937;
      margin-bottom: 8rpx;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .material-size {
      font-size: 12px;
      color: #9ca3af;
    }
  }
}

.load-more,
.empty-state {
  text-align: center;
  padding: 60rpx 0;
  font-size: 14px;
  color: #9ca3af;
}

.empty-icon {
  display: block;
  font-size: 60px;
  margin-bottom: 20rpx;
}
</style>
`,

  // 其他页面使用占位符但有基本结构
  'pages/material/detail.vue': createPlaceholderPage('素材详情'),
  'pages/publish/settings.vue': createPlaceholderPage('发布设置'),
  'pages/publish/schedule.vue': createPlaceholderPage('定时发布'),
  'pages/merchant/info.vue': createPlaceholderPage('商家信息'),
  'pages/merchant/devices.vue': createPlaceholderPage('设备管理'),
  'pages/statistics/overview.vue': createPlaceholderPage('数据概览'),
  'pages/statistics/analysis.vue': createPlaceholderPage('数据分析'),
  'pages/user/profile.vue': createPlaceholderPage('个人中心'),
  'pages/user/settings.vue': createPlaceholderPage('用户设置'),

  // 分包页面
  'pages-sub/alert/list.vue': createPlaceholderPage('告警列表'),
  'pages-sub/alert/detail.vue': createPlaceholderPage('告警详情'),
  'pages-sub/alert/rules.vue': createPlaceholderPage('告警规则'),
  'pages-sub/dining/table/list.vue': createPlaceholderPage('餐桌列表'),
  'pages-sub/dining/session/list.vue': createPlaceholderPage('用餐会话列表'),
  'pages-sub/dining/session/detail.vue': createPlaceholderPage('用餐会话详情'),
  'pages-sub/marketing/coupon/list.vue': createPlaceholderPage('优惠券列表'),
  'pages-sub/marketing/coupon/create.vue': createPlaceholderPage('创建优惠券'),
  'pages-sub/marketing/groupbuy/list.vue': createPlaceholderPage('团购列表'),
  'pages-sub/marketing/groupbuy/create.vue': createPlaceholderPage('创建团购')
};

function createPlaceholderPage(title) {
  return \`<template>
  <view class="page-container">
    <view class="nav-bar">
      <view class="nav-title">\${title}</view>
    </view>
    <view class="page-content">
      <text class="page-desc">页面开发中...</text>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {}
  },
  onLoad(options) {
    console.log('\${title}页面加载:', options)
  }
}
</script>

<style scoped>
.page-container {
  min-height: 100vh;
  background: #f8f9fa;
}

.nav-bar {
  background: #ffffff;
  padding: 20rpx 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
}

.nav-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.page-content {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 120rpx 40rpx;
}

.page-desc {
  font-size: 14px;
  color: #9ca3af;
}
</style>
\`;
}

// 创建页面文件
function createPages() {
  const baseDir = path.join(__dirname, 'uni-app');
  let created = 0;
  let skipped = 0;

  for (const [filePath, content] of Object.entries(pageTemplates)) {
    const fullPath = path.join(baseDir, filePath);
    const dir = path.dirname(fullPath);

    // 确保目录存在
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    // 创建文件
    if (fs.existsSync(fullPath)) {
      console.log(\`⏭️  跳过已存在: \${filePath}\`);
      skipped++;
    } else {
      fs.writeFileSync(fullPath, content, 'utf8');
      console.log(\`✅ 创建成功: \${filePath}\`);
      created++;
    }
  }

  console.log(\`\\n📊 完成: 创建 \${created} 个文件, 跳过 \${skipped} 个文件\`);
}

// 执行创建
createPages();

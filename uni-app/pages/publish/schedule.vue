<template>
  <view class="schedule-page">
    <!-- 页面标题 -->
    <view class="page-header">
      <view class="header-title">定时发布</view>
      <view class="header-subtitle">管理所有定时发布任务</view>
    </view>

    <!-- 加载中 -->
    <view class="loading-wrapper" v-if="isLoading && !scheduledTasks.length">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>

    <!-- 内容区域 -->
    <view class="content-wrapper" v-else>
      <!-- 筛选标签 -->
      <scroll-view class="filter-tabs" scroll-x>
        <view class="tabs-wrapper">
          <view
            class="tab-item"
            :class="{ active: currentFilter === item.value }"
            v-for="item in filterTabs"
            :key="item.value"
            @tap="switchFilter(item.value)"
          >
            <text class="tab-text">{{ item.label }}</text>
            <text class="tab-count" v-if="item.count">{{ item.count }}</text>
          </view>
        </view>
      </scroll-view>

      <!-- 任务列表 -->
      <scroll-view
        class="task-list"
        scroll-y
        @scrolltolower="loadMore"
        :refresher-enabled="true"
        :refresher-triggered="isRefreshing"
        @refresherrefresh="handleRefresh"
      >
        <view class="task-cards" v-if="filteredTasks.length">
          <view
            class="task-card"
            v-for="task in filteredTasks"
            :key="task.id"
            @tap="viewTaskDetail(task)"
          >
            <!-- 卡片头部 -->
            <view class="card-header">
              <view class="task-status" :class="`status-${task.status}`">
                {{ formatStatus(task.status) }}
              </view>
              <view class="task-time">
                <text class="time-icon">⏰</text>
                <text class="time-text">{{ formatScheduleTime(task.scheduled_time) }}</text>
              </view>
            </view>

            <!-- 内容预览 -->
            <view class="card-content">
              <view class="content-media" v-if="task.cover_url || task.poster_url">
                <image
                  class="media-image"
                  :src="task.cover_url || task.poster_url"
                  mode="aspectFill"
                />
                <view class="media-type-badge">
                  {{ formatContentType(task.content_type) }}
                </view>
              </view>

              <view class="content-info">
                <view class="content-title">{{ task.title || '未命名内容' }}</view>
                <view class="content-description">
                  {{ task.description || task.text_content || '暂无描述' }}
                </view>
              </view>
            </view>

            <!-- 平台信息 -->
            <view class="card-platforms">
              <view class="platforms-label">
                <text class="label-icon">📱</text>
                <text class="label-text">发布平台：</text>
              </view>
              <view class="platforms-list">
                <view class="platform-tag" v-for="platform in task.platforms" :key="platform">
                  <text class="platform-icon">{{ getPlatformIcon(platform) }}</text>
                  <text class="platform-name">{{ getPlatformName(platform) }}</text>
                </view>
              </view>
            </view>

            <!-- 操作按钮 -->
            <view class="card-actions">
              <button
                class="action-btn"
                v-if="task.status === 'PENDING'"
                @tap.stop="editTask(task)"
              >
                <text class="btn-icon">✏️</text>
                <text>编辑</text>
              </button>
              <button
                class="action-btn"
                v-if="task.status === 'PENDING'"
                @tap.stop="publishNow(task)"
              >
                <text class="btn-icon">🚀</text>
                <text>立即发布</text>
              </button>
              <button
                class="action-btn danger"
                v-if="task.status === 'PENDING'"
                @tap.stop="cancelTask(task)"
              >
                <text class="btn-icon">❌</text>
                <text>取消</text>
              </button>
              <button
                class="action-btn"
                v-if="task.status === 'PUBLISHED' || task.status === 'COMPLETED'"
                @tap.stop="viewPublishResult(task)"
              >
                <text class="btn-icon">📊</text>
                <text>查看结果</text>
              </button>
              <button
                class="action-btn"
                v-if="task.status === 'FAILED'"
                @tap.stop="retryTask(task)"
              >
                <text class="btn-icon">🔄</text>
                <text>重试</text>
              </button>
            </view>
          </view>
        </view>

        <!-- 空状态 -->
        <view class="empty-state" v-else>
          <view class="empty-icon">⏰</view>
          <view class="empty-title">暂无定时任务</view>
          <view class="empty-tip">{{ getEmptyTip() }}</view>
          <button class="empty-btn" @tap="createNewTask">创建定时任务</button>
        </view>

        <!-- 加载更多 -->
        <view class="load-more" v-if="filteredTasks.length && hasMore">
          <view class="loading-spinner small"></view>
          <text class="load-more-text">加载更多...</text>
        </view>

        <!-- 没有更多 -->
        <view class="no-more" v-if="filteredTasks.length && !hasMore">
          <text class="no-more-text">没有更多了</text>
        </view>
      </scroll-view>
    </view>

    <!-- 悬浮创建按钮 -->
    <view class="fab-button" @tap="createNewTask">
      <text class="fab-icon">➕</text>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'

export default {
  data() {
    return {
      // 任务列表
      scheduledTasks: [],
      filteredTasks: [],

      // 筛选
      currentFilter: 'ALL',
      filterTabs: [
        { label: '全部', value: 'ALL', count: 0 },
        { label: '待发布', value: 'PENDING', count: 0 },
        { label: '已发布', value: 'PUBLISHED', count: 0 },
        { label: '已完成', value: 'COMPLETED', count: 0 },
        { label: '已取消', value: 'CANCELLED', count: 0 },
        { label: '失败', value: 'FAILED', count: 0 },
      ],

      // 分页
      page: 1,
      pageSize: 20,
      hasMore: true,

      // 状态
      isLoading: false,
      isRefreshing: false,
    }
  },

  onLoad() {
    this.loadScheduledTasks()
  },

  onShow() {
    // 页面显示时刷新数据
    if (this.scheduledTasks.length > 0) {
      this.handleRefresh()
    }
  },

  methods: {
    /**
     * 加载定时任务列表
     */
    async loadScheduledTasks(refresh = false) {
      if (refresh) {
        this.page = 1
        this.hasMore = true
        this.isRefreshing = true
      } else {
        this.isLoading = true
      }

      try {
        // 尝试调用API
        if (typeof api.publish?.getScheduledTasks === 'function') {
          const res = await api.publish.getScheduledTasks({
            page: this.page,
            pageSize: this.pageSize,
            status: this.currentFilter === 'ALL' ? '' : this.currentFilter
          })

          const tasks = res.data || res.list || []

          if (refresh) {
            this.scheduledTasks = tasks
          } else {
            this.scheduledTasks = [...this.scheduledTasks, ...tasks]
          }

          this.hasMore = tasks.length >= this.pageSize
          this.page++
        } else {
          // 使用模拟数据
          const mockTasks = this.generateMockTasks()
          if (refresh) {
            this.scheduledTasks = mockTasks
          } else if (this.scheduledTasks.length === 0) {
            this.scheduledTasks = mockTasks
          }
          this.hasMore = false
        }

        // 更新筛选数据
        this.updateFilteredTasks()
        this.updateFilterCounts()
      } catch (error) {
        console.error('加载定时任务失败:', error)

        // 失败时使用模拟数据
        if (this.scheduledTasks.length === 0) {
          this.scheduledTasks = this.generateMockTasks()
          this.updateFilteredTasks()
          this.updateFilterCounts()
        }
      } finally {
        this.isLoading = false
        this.isRefreshing = false
      }
    },

    /**
     * 更新筛选后的任务列表
     */
    updateFilteredTasks() {
      if (this.currentFilter === 'ALL') {
        this.filteredTasks = this.scheduledTasks
      } else {
        this.filteredTasks = this.scheduledTasks.filter(
          task => task.status === this.currentFilter
        )
      }
    },

    /**
     * 更新筛选标签计数
     */
    updateFilterCounts() {
      this.filterTabs.forEach(tab => {
        if (tab.value === 'ALL') {
          tab.count = this.scheduledTasks.length
        } else {
          tab.count = this.scheduledTasks.filter(task => task.status === tab.value).length
        }
      })
    },

    /**
     * 切换筛选
     */
    switchFilter(filter) {
      this.currentFilter = filter
      this.updateFilteredTasks()
    },

    /**
     * 下拉刷新
     */
    handleRefresh() {
      this.loadScheduledTasks(true)
    },

    /**
     * 加载更多
     */
    loadMore() {
      if (!this.hasMore || this.isLoading) return
      this.loadScheduledTasks()
    },

    /**
     * 查看任务详情
     */
    viewTaskDetail(task) {
      uni.navigateTo({
        url: `/pages/publish/detail?id=${task.id}`
      })
    },

    /**
     * 编辑任务
     */
    editTask(task) {
      uni.navigateTo({
        url: `/pages/publish/settings?task_id=${task.content_task_id}&schedule_id=${task.id}`
      })
    },

    /**
     * 立即发布
     */
    async publishNow(task) {
      const res = await uni.showModal({
        title: '确认发布',
        content: '确定要立即发布这条内容吗？'
      })

      if (!res.confirm) return

      try {
        uni.showLoading({ title: '发布中...', mask: true })

        if (typeof api.publish?.publishNow === 'function') {
          await api.publish.publishNow(task.id)
        }

        uni.showToast({
          title: '发布成功',
          icon: 'success'
        })

        // 刷新列表
        this.handleRefresh()
      } catch (error) {
        console.error('发布失败:', error)
        uni.showModal({
          title: '发布失败',
          content: error.message || '发布失败，请稍后重试',
          showCancel: false
        })
      } finally {
        uni.hideLoading()
      }
    },

    /**
     * 取消任务
     */
    async cancelTask(task) {
      const res = await uni.showModal({
        title: '确认取消',
        content: '确定要取消这个定时任务吗？'
      })

      if (!res.confirm) return

      try {
        uni.showLoading({ title: '取消中...', mask: true })

        if (typeof api.publish?.cancelScheduledTask === 'function') {
          await api.publish.cancelScheduledTask(task.id)
        }

        uni.showToast({
          title: '已取消',
          icon: 'success'
        })

        // 刷新列表
        this.handleRefresh()
      } catch (error) {
        console.error('取消失败:', error)
        uni.showModal({
          title: '取消失败',
          content: error.message || '取消失败，请稍后重试',
          showCancel: false
        })
      } finally {
        uni.hideLoading()
      }
    },

    /**
     * 重试任务
     */
    async retryTask(task) {
      const res = await uni.showModal({
        title: '确认重试',
        content: '确定要重新发布这条内容吗？'
      })

      if (!res.confirm) return

      try {
        uni.showLoading({ title: '创建任务中...', mask: true })

        if (typeof api.publish?.retryScheduledTask === 'function') {
          await api.publish.retryScheduledTask(task.id)
        }

        uni.showToast({
          title: '任务已创建',
          icon: 'success'
        })

        // 刷新列表
        this.handleRefresh()
      } catch (error) {
        console.error('重试失败:', error)
        uni.showModal({
          title: '重试失败',
          content: error.message || '重试失败，请稍后重试',
          showCancel: false
        })
      } finally {
        uni.hideLoading()
      }
    },

    /**
     * 查看发布结果
     */
    viewPublishResult(task) {
      uni.navigateTo({
        url: `/pages/publish/result?id=${task.id}`
      })
    },

    /**
     * 创建新任务
     */
    createNewTask() {
      uni.navigateTo({
        url: '/pages/content/generate'
      })
    },

    /**
     * 生成模拟任务数据
     */
    generateMockTasks() {
      const statuses = ['PENDING', 'PUBLISHED', 'COMPLETED', 'FAILED', 'CANCELLED']
      const contentTypes = ['VIDEO', 'IMAGE', 'TEXT']
      const platforms = [
        ['douyin', 'xiaohongshu'],
        ['wechat', 'channels'],
        ['douyin'],
        ['xiaohongshu', 'wechat']
      ]

      const tasks = []
      const now = Date.now()

      for (let i = 1; i <= 15; i++) {
        const status = statuses[i % statuses.length]
        const scheduledTime = new Date(now + (i - 5) * 3600000) // 前5个已过期，后10个未来时间

        tasks.push({
          id: `task_${i}`,
          content_task_id: `content_${i}`,
          title: `定时发布任务 ${i}`,
          description: `这是第 ${i} 个定时发布任务的描述内容，包含了详细的任务说明。`,
          text_content: `任务 ${i} 的文案内容`,
          content_type: contentTypes[i % contentTypes.length],
          cover_url: `https://picsum.photos/200/300?random=${i}`,
          poster_url: `https://picsum.photos/200/300?random=${i}`,
          platforms: platforms[i % platforms.length],
          status: i <= 5 ? statuses[Math.floor(Math.random() * statuses.length)] : 'PENDING',
          scheduled_time: scheduledTime.toISOString(),
          create_time: new Date(now - i * 86400000).toISOString()
        })
      }

      return tasks
    },

    /**
     * 格式化状态
     */
    formatStatus(status) {
      const statusMap = {
        'PENDING': '待发布',
        'PUBLISHED': '已发布',
        'COMPLETED': '已完成',
        'CANCELLED': '已取消',
        'FAILED': '失败'
      }
      return statusMap[status] || status
    },

    /**
     * 格式化内容类型
     */
    formatContentType(type) {
      const typeMap = {
        'VIDEO': '视频',
        'IMAGE': '图片',
        'TEXT': '文字'
      }
      return typeMap[type] || type
    },

    /**
     * 格式化定时时间
     */
    formatScheduleTime(timeStr) {
      if (!timeStr) return '-'

      const date = new Date(timeStr)
      const now = new Date()
      const diff = date - now

      // 已过期
      if (diff < 0) {
        const absDiff = Math.abs(diff)
        if (absDiff < 3600000) {
          return Math.floor(absDiff / 60000) + '分钟前'
        } else if (absDiff < 86400000) {
          return Math.floor(absDiff / 3600000) + '小时前'
        } else {
          return Math.floor(absDiff / 86400000) + '天前'
        }
      }

      // 未来时间
      if (diff < 3600000) {
        return Math.floor(diff / 60000) + '分钟后'
      } else if (diff < 86400000) {
        return Math.floor(diff / 3600000) + '小时后'
      } else {
        const days = Math.floor(diff / 86400000)
        return days + '天后'
      }
    },

    /**
     * 获取平台图标
     */
    getPlatformIcon(platform) {
      const icons = {
        douyin: '🎵',
        xiaohongshu: '📕',
        wechat: '💬',
        channels: '📹',
        weibo: '📱',
        kuaishou: '🎬'
      }
      return icons[platform] || '📱'
    },

    /**
     * 获取平台名称
     */
    getPlatformName(platform) {
      const names = {
        douyin: '抖音',
        xiaohongshu: '小红书',
        wechat: '微信',
        channels: '视频号',
        weibo: '微博',
        kuaishou: '快手'
      }
      return names[platform] || platform
    },

    /**
     * 获取空状态提示
     */
    getEmptyTip() {
      const tips = {
        'ALL': '暂无定时发布任务',
        'PENDING': '暂无待发布任务',
        'PUBLISHED': '暂无已发布任务',
        'COMPLETED': '暂无已完成任务',
        'CANCELLED': '暂无已取消任务',
        'FAILED': '暂无失败任务'
      }
      return tips[this.currentFilter] || '暂无任务'
    }
  }
}
</script>

<style lang="scss" scoped>
.schedule-page {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: 40rpx;
}

// 页面头部
.page-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 40rpx 30rpx;
  color: #ffffff;

  .header-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 12rpx;
  }

  .header-subtitle {
    font-size: 14px;
    opacity: 0.9;
  }
}

// 加载状态
.loading-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx 0;

  .loading-spinner {
    width: 60rpx;
    height: 60rpx;
    border: 4rpx solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 30rpx;
  }

  .loading-text {
    font-size: 14px;
    color: #6b7280;
  }
}

// 筛选标签
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

// 任务列表
.task-list {
  height: calc(100vh - 300rpx);
  padding: 0 30rpx;
}

.task-cards {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.task-card {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  // 卡片头部
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;

    .task-status {
      padding: 8rpx 20rpx;
      border-radius: 20rpx;
      font-size: 12px;
      font-weight: 600;

      &.status-PENDING {
        background: #fef3c7;
        color: #92400e;
      }

      &.status-PUBLISHED {
        background: #dbeafe;
        color: #1e40af;
      }

      &.status-COMPLETED {
        background: #d1fae5;
        color: #065f46;
      }

      &.status-CANCELLED {
        background: #f3f4f6;
        color: #6b7280;
      }

      &.status-FAILED {
        background: #fee2e2;
        color: #991b1b;
      }
    }

    .task-time {
      display: flex;
      align-items: center;
      gap: 8rpx;
      font-size: 14px;
      color: #6b7280;

      .time-icon {
        font-size: 16px;
      }
    }
  }

  // 内容预览
  .card-content {
    display: flex;
    gap: 20rpx;
    margin-bottom: 24rpx;

    .content-media {
      position: relative;
      width: 160rpx;
      height: 160rpx;
      border-radius: 12rpx;
      overflow: hidden;
      flex-shrink: 0;

      .media-image {
        width: 100%;
        height: 100%;
      }

      .media-type-badge {
        position: absolute;
        bottom: 8rpx;
        right: 8rpx;
        padding: 4rpx 12rpx;
        background: rgba(0, 0, 0, 0.7);
        color: #ffffff;
        border-radius: 12rpx;
        font-size: 10px;
        backdrop-filter: blur(10rpx);
      }
    }

    .content-info {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 12rpx;

      .content-title {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
      }

      .content-description {
        font-size: 14px;
        color: #6b7280;
        line-height: 1.5;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
      }
    }
  }

  // 平台信息
  .card-platforms {
    margin-bottom: 24rpx;

    .platforms-label {
      display: flex;
      align-items: center;
      gap: 8rpx;
      margin-bottom: 16rpx;

      .label-icon {
        font-size: 16px;
      }

      .label-text {
        font-size: 14px;
        color: #6b7280;
      }
    }

    .platforms-list {
      display: flex;
      flex-wrap: wrap;
      gap: 12rpx;

      .platform-tag {
        display: flex;
        align-items: center;
        gap: 8rpx;
        padding: 8rpx 16rpx;
        background: #f3f4f6;
        border-radius: 20rpx;

        .platform-icon {
          font-size: 16px;
        }

        .platform-name {
          font-size: 12px;
          color: #6366f1;
          font-weight: 500;
        }
      }
    }
  }

  // 操作按钮
  .card-actions {
    display: flex;
    gap: 12rpx;

    .action-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8rpx;
      padding: 16rpx;
      background: #f3f4f6;
      color: #6b7280;
      border: none;
      border-radius: 8rpx;
      font-size: 14px;

      &.danger {
        background: #fee2e2;
        color: #991b1b;
      }

      .btn-icon {
        font-size: 16px;
      }
    }
  }
}

// 空状态
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 0;

  .empty-icon {
    font-size: 100px;
    margin-bottom: 30rpx;
    opacity: 0.5;
  }

  .empty-title {
    font-size: 18px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12rpx;
  }

  .empty-tip {
    font-size: 14px;
    color: #9ca3af;
    margin-bottom: 40rpx;
  }

  .empty-btn {
    padding: 20rpx 60rpx;
    background: #6366f1;
    color: #ffffff;
    border: none;
    border-radius: 24rpx;
    font-size: 14px;
  }
}

// 加载更多
.load-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16rpx;
  padding: 40rpx 0;

  .loading-spinner.small {
    width: 40rpx;
    height: 40rpx;
    border: 3rpx solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  .load-more-text {
    font-size: 14px;
    color: #9ca3af;
  }
}

.no-more {
  padding: 40rpx 0;
  text-align: center;

  .no-more-text {
    font-size: 14px;
    color: #9ca3af;
  }
}

// 悬浮按钮
.fab-button {
  position: fixed;
  bottom: 100rpx;
  right: 40rpx;
  width: 100rpx;
  height: 100rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8rpx 24rpx rgba(99, 102, 241, 0.4);
  z-index: 100;

  .fab-icon {
    font-size: 32px;
    color: #ffffff;
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

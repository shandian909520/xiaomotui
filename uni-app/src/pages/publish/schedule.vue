<template>
  <view class="schedule-page">
    <!-- 页面标题 -->
    <view class="page-header">
      <view class="header-title">定时发布</view>
      <view class="header-subtitle">管理所有定时发布任务</view>
    </view>

    <!-- 加载中 -->
    <skeleton type="card" :rows="3" :loading="isLoading && !scheduledTasks.length" />

    <!-- 内容区域 -->
    <view class="content-wrapper" v-if="!isLoading || scheduledTasks.length">
      <!-- 筛选标签 -->
      <filter-tabs
        :tabs="filterTabs"
        :current="currentFilter"
        @change="switchFilter"
      />

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
          <schedule-task-card
            v-for="task in filteredTasks"
            :key="task.id"
            :task="task"
            @view="viewTaskDetail"
            @edit="editTask"
            @publish="publishNow"
            @cancel="cancelTask"
            @retry="retryTask"
            @view-result="viewPublishResult"
          />
        </view>

        <!-- 空状态 -->
        <empty-state
          v-else
          icon="⏰"
          title="暂无定时任务"
          :description="getEmptyTip()"
          btnText="创建定时任务"
          @action="createNewTask"
        />

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
import FilterTabs from '../../components/business/publish/filter-tabs.vue'
import ScheduleTaskCard from '../../components/business/publish/schedule-task-card.vue'

export default {
  components: { FilterTabs, ScheduleTaskCard },
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

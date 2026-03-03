<template>
  <view class="notifications-page">
    <!-- 顶部操作栏 -->
    <view class="top-bar">
      <view class="top-actions">
        <view class="read-all-btn" @tap="markAllRead" v-if="unreadCount > 0">
          <text class="btn-icon">✓</text>
          <text>全部已读</text>
        </view>
      </view>
    </view>

    <!-- 类型筛选 -->
    <scroll-view class="type-tabs" scroll-x>
      <view class="tabs-wrapper">
        <view
          class="tab-item"
          :class="{ active: currentType === item.value }"
          v-for="item in typeTabs"
          :key="item.value"
          @tap="switchType(item.value)"
        >
          <text class="tab-text">{{ item.label }}</text>
          <view class="tab-dot" v-if="item.unread > 0"></view>
        </view>
      </view>
    </scroll-view>

    <!-- 骨架屏 -->
    <skeleton type="list" :rows="5" :avatar="true" :loading="isLoading && !list.length" />

    <!-- 通知列表 -->
    <scroll-view
      v-if="!isLoading || list.length"
      class="notification-list"
      scroll-y
      @scrolltolower="loadMore"
    >
      <empty-state
        v-if="!isLoading && list.length === 0"
        icon="🔔"
        title="暂无通知"
        description="暂时没有新消息"
      />

      <view v-else>
        <view
          class="notification-item"
          :class="{ unread: !item.is_read }"
          v-for="item in list"
          :key="item.id"
          @tap="handleNotificationTap(item)"
        >
          <view class="notification-icon" :class="`icon-${item.type}`">
            {{ getTypeIcon(item.type) }}
          </view>
          <view class="notification-content">
            <view class="notification-header">
              <text class="notification-title">{{ item.title }}</text>
              <text class="notification-time">{{ formatTime(item.created_at) }}</text>
            </view>
            <text class="notification-body">{{ item.content }}</text>
          </view>
          <view class="unread-dot" v-if="!item.is_read"></view>
        </view>
      </view>

      <view class="load-more" v-if="isLoadingMore">
        <text>加载中...</text>
      </view>
      <view class="no-more" v-if="finished && list.length > 0">
        <text>没有更多了</text>
      </view>
    </scroll-view>
  </view>
</template>

<script>
import api from '../../api/index.js'

export default {
  data() {
    return {
      currentType: '',
      typeTabs: [
        { label: '全部', value: '', unread: 0 },
        { label: '系统', value: 'system', unread: 0 },
        { label: '告警', value: 'alert', unread: 0 },
        { label: '内容', value: 'content', unread: 0 },
        { label: '活动', value: 'activity', unread: 0 }
      ],

      list: [],
      page: 1,
      pageSize: 20,
      finished: false,
      isLoading: false,
      isLoadingMore: false,
      unreadCount: 0
    }
  },

  onLoad() {
    this.loadNotifications()
    this.loadUnreadCount()
  },

  onPullDownRefresh() {
    this.refresh()
  },

  methods: {
    async loadNotifications(loadMore = false) {
      if (loadMore) {
        if (this.isLoadingMore || this.finished) return
        this.isLoadingMore = true
      } else {
        this.isLoading = true
      }

      try {
        const res = await api.user.getNotifications({
          page: this.page,
          pageSize: this.pageSize,
          type: this.currentType || undefined
        })

        const items = (res && res.data && res.data.list) || (res && res.list) || []

        if (loadMore) {
          this.list = [...this.list, ...items]
        } else {
          this.list = items
        }

        if (items.length < this.pageSize) {
          this.finished = true
        }
        this.page++
      } catch (e) {
        console.error('加载通知失败:', e)
        if (!loadMore && this.list.length === 0) {
          this.list = this.getMockData()
          this.finished = true
        }
      } finally {
        this.isLoading = false
        this.isLoadingMore = false
      }
    },

    async loadUnreadCount() {
      try {
        const res = await api.user.getUnreadCount()
        this.unreadCount = (res && res.data && res.data.count) || (res && res.count) || 0
      } catch (e) {
        this.unreadCount = 0
      }
    },

    refresh() {
      this.page = 1
      this.list = []
      this.finished = false
      this.loadNotifications()
      this.loadUnreadCount()
      uni.stopPullDownRefresh()
    },

    loadMore() {
      this.loadNotifications(true)
    },

    switchType(type) {
      this.currentType = type
      this.page = 1
      this.list = []
      this.finished = false
      this.loadNotifications()
    },

    async handleNotificationTap(item) {
      if (!item.is_read) {
        try {
          await api.user.markNotificationRead(item.id)
          item.is_read = true
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        } catch (e) {
          console.error('标记已读失败:', e)
        }
      }

      // 根据类型跳转
      if (item.target_url) {
        uni.navigateTo({ url: item.target_url })
      }
    },

    async markAllRead() {
      try {
        uni.showLoading({ title: '处理中...', mask: true })
        await api.user.markAllNotificationsRead()
        this.list.forEach(item => { item.is_read = true })
        this.unreadCount = 0
        uni.showToast({ title: '已全部标记为已读', icon: 'success' })
      } catch (e) {
        console.error('全部已读失败:', e)
        uni.showToast({ title: '操作失败', icon: 'none' })
      } finally {
        uni.hideLoading()
      }
    },

    getTypeIcon(type) {
      const icons = {
        system: '🔧',
        alert: '🚨',
        content: '📄',
        activity: '🎉'
      }
      return icons[type] || '🔔'
    },

    formatTime(timeStr) {
      if (!timeStr) return ''
      const date = new Date(timeStr)
      const now = new Date()
      const diff = now - date

      if (diff < 60000) return '刚刚'
      if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前'
      if (diff < 86400000) return Math.floor(diff / 3600000) + '小时前'
      if (diff < 604800000) return Math.floor(diff / 86400000) + '天前'

      const m = date.getMonth() + 1
      const d = date.getDate()
      return `${m}月${d}日`
    },

    getMockData() {
      const types = ['system', 'alert', 'content', 'activity']
      const titles = ['系统升级通知', '设备离线告警', '内容审核通过', '新活动上线']
      const contents = [
        '系统将于今晚22:00进行升级维护',
        '设备NFC-001已离线超过30分钟',
        '您提交的内容已通过审核',
        '参与新年营销活动赢取积分'
      ]
      return Array.from({ length: 8 }, (_, i) => ({
        id: i + 1,
        type: types[i % 4],
        title: titles[i % 4],
        content: contents[i % 4],
        is_read: i > 2,
        created_at: new Date(Date.now() - i * 3600000 * 3).toISOString(),
        target_url: ''
      }))
    }
  }
}
</script>

<style lang="scss" scoped>
.notifications-page {
  min-height: 100vh;
  background: #f8f9fa;
  display: flex;
  flex-direction: column;
}

// 顶部操作栏
.top-bar {
  background: #ffffff;
  padding: 20rpx 30rpx;
  border-bottom: 1rpx solid #f3f4f6;

  .top-actions {
    display: flex;
    justify-content: flex-end;
  }

  .read-all-btn {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 12rpx 24rpx;
    background: #f0f0ff;
    color: #6366f1;
    border-radius: 20rpx;
    font-size: 13px;
    font-weight: 500;

    .btn-icon {
      font-size: 14px;
    }
  }
}

// 类型筛选
.type-tabs {
  width: 100%;
  white-space: nowrap;
  background: #ffffff;
  border-bottom: 1rpx solid #f3f4f6;

  .tabs-wrapper {
    display: inline-flex;
    padding: 20rpx 30rpx;
    gap: 20rpx;
  }

  .tab-item {
    position: relative;
    display: inline-flex;
    align-items: center;
    padding: 12rpx 28rpx;
    background: #f3f4f6;
    border-radius: 24rpx;

    &.active {
      background: #6366f1;

      .tab-text {
        color: #ffffff;
      }
    }

    .tab-text {
      font-size: 14px;
      color: #6b7280;
      font-weight: 500;
    }

    .tab-dot {
      position: absolute;
      top: 8rpx;
      right: 8rpx;
      width: 12rpx;
      height: 12rpx;
      background: #ef4444;
      border-radius: 50%;
    }
  }
}

// 通知列表
.notification-list {
  flex: 1;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 20rpx;
  padding: 30rpx;
  background: #ffffff;
  border-bottom: 1rpx solid #f3f4f6;

  &.unread {
    background: #fefce8;
  }
}

.notification-icon {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  flex-shrink: 0;
  background: #f3f4f6;

  &.icon-system {
    background: #dbeafe;
  }

  &.icon-alert {
    background: #fee2e2;
  }

  &.icon-content {
    background: #d1fae5;
  }

  &.icon-activity {
    background: #fef3c7;
  }
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10rpx;
}

.notification-title {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  margin-right: 16rpx;
}

.notification-time {
  font-size: 12px;
  color: #9ca3af;
  flex-shrink: 0;
}

.notification-body {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.unread-dot {
  width: 16rpx;
  height: 16rpx;
  background: #ef4444;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 12rpx;
}

// 加载更多
.load-more,
.no-more {
  text-align: center;
  padding: 30rpx;
  font-size: 13px;
  color: #9ca3af;
}
</style>

<template>
  <view class="task-card" @tap="$emit('view', task)">
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
          lazy-load
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
        @tap.stop="$emit('edit', task)"
      >
        <text class="btn-icon">✏️</text>
        <text>编辑</text>
      </button>
      <button
        class="action-btn"
        v-if="task.status === 'PENDING'"
        @tap.stop="$emit('publish', task)"
      >
        <text class="btn-icon">🚀</text>
        <text>立即发布</text>
      </button>
      <button
        class="action-btn danger"
        v-if="task.status === 'PENDING'"
        @tap.stop="$emit('cancel', task)"
      >
        <text class="btn-icon">❌</text>
        <text>取消</text>
      </button>
      <button
        class="action-btn"
        v-if="task.status === 'PUBLISHED' || task.status === 'COMPLETED'"
        @tap.stop="$emit('view-result', task)"
      >
        <text class="btn-icon">📊</text>
        <text>查看结果</text>
      </button>
      <button
        class="action-btn"
        v-if="task.status === 'FAILED'"
        @tap.stop="$emit('retry', task)"
      >
        <text class="btn-icon">🔄</text>
        <text>重试</text>
      </button>
    </view>
  </view>
</template>

<script>
export default {
  name: 'ScheduleTaskCard',
  props: {
    // 任务对象
    task: {
      type: Object,
      required: true
    }
  },
  methods: {
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
    }
  }
}
</script>

<style lang="scss" scoped>
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
</style>

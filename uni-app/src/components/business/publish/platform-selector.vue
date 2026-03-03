<template>
  <view class="platform-selector">
    <view class="section-header">
      <text class="section-icon">📱</text>
      <text class="section-title">选择发布平台</text>
      <text class="section-tip">（至少选择一个）</text>
    </view>

    <view class="platform-list" v-if="accounts.length">
      <view
        class="platform-card"
        :class="{ 'platform-selected': isPlatformSelected(account.id) }"
        v-for="account in accounts"
        :key="account.id"
        @tap="handleToggle(account)"
      >
        <view class="platform-checkbox">
          <view class="checkbox-icon" v-if="isPlatformSelected(account.id)">✓</view>
        </view>

        <view class="platform-info">
          <view class="platform-header">
            <text class="platform-icon">{{ getPlatformIcon(account.platform) }}</text>
            <text class="platform-name">{{ getPlatformName(account.platform) }}</text>
          </view>
          <view class="platform-account">
            <text class="account-nickname">{{ account.nickname || account.account_name }}</text>
            <view class="account-badge" :class="`badge-${account.status}`">
              {{ account.status === 'ACTIVE' ? '已授权' : '已失效' }}
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 无账号提示 -->
    <view class="empty-platforms" v-else>
      <view class="empty-icon">📱</view>
      <view class="empty-title">暂无授权平台</view>
      <view class="empty-tip">请先授权至少一个发布平台</view>
      <button class="empty-btn" @tap="handleAddPlatform">去授权</button>
    </view>

    <!-- 添加平台按钮 -->
    <view class="add-platform-btn" v-if="accounts.length" @tap="handleAddPlatform">
      <text class="btn-icon">➕</text>
      <text>添加更多平台</text>
    </view>
  </view>
</template>

<script>
export default {
  name: 'PlatformSelector',

  props: {
    accounts: {
      type: Array,
      default: () => []
    },
    selectedIds: {
      type: Array,
      default: () => []
    }
  },

  methods: {
    isPlatformSelected(id) {
      return this.selectedIds.includes(id)
    },

    handleToggle(account) {
      this.$emit('toggle', account)
    },

    handleAddPlatform() {
      this.$emit('add-platform')
    },

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
.platform-selector {
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

    .section-tip {
      font-size: 12px;
      color: #9ca3af;
      margin-left: 8rpx;
    }
  }
}

.platform-list {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.platform-card {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 24rpx;
  background: #f9fafb;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;
  transition: all 0.3s;

  &.platform-selected {
    background: #ede9fe;
    border-color: #6366f1;
    box-shadow: 0 4rpx 12rpx rgba(99, 102, 241, 0.1);
  }

  .platform-checkbox {
    width: 48rpx;
    height: 48rpx;
    border: 2rpx solid #d1d5db;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    flex-shrink: 0;

    .checkbox-icon {
      color: #6366f1;
      font-size: 20px;
      font-weight: bold;
    }
  }

  &.platform-selected .platform-checkbox {
    border-color: #6366f1;
    background: #6366f1;

    .checkbox-icon {
      color: #ffffff;
    }
  }

  .platform-info {
    flex: 1;

    .platform-header {
      display: flex;
      align-items: center;
      gap: 12rpx;
      margin-bottom: 12rpx;

      .platform-icon {
        font-size: 22px;
      }

      .platform-name {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
      }
    }

    .platform-account {
      display: flex;
      align-items: center;
      gap: 16rpx;

      .account-nickname {
        font-size: 14px;
        color: #6b7280;
      }

      .account-badge {
        padding: 4rpx 12rpx;
        border-radius: 12rpx;
        font-size: 12px;
        font-weight: 500;

        &.badge-ACTIVE {
          background: #d1fae5;
          color: #065f46;
        }

        &.badge-EXPIRED {
          background: #fee2e2;
          color: #991b1b;
        }
      }
    }
  }
}

.empty-platforms {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 0;

  .empty-icon {
    font-size: 80px;
    margin-bottom: 30rpx;
    opacity: 0.5;
  }

  .empty-title {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12rpx;
  }

  .empty-tip {
    font-size: 14px;
    color: #9ca3af;
    margin-bottom: 30rpx;
  }

  .empty-btn {
    padding: 20rpx 60rpx;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    border-radius: 24rpx;
    font-size: 14px;
    font-weight: 500;
    border: none;
  }
}

.add-platform-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  padding: 24rpx;
  margin-top: 20rpx;
  background: #f9fafb;
  border: 2rpx dashed #d1d5db;
  border-radius: 12rpx;
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;

  .btn-icon {
    font-size: 18px;
  }
}
</style>

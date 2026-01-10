<template>
  <view class="page-container">
    <!-- 顶部欢迎卡片 -->
    <view class="welcome-card card">
      <view class="welcome-title">欢迎使用小魔推碰一碰</view>
      <view class="welcome-desc">NFC智能营销助手，让营销更简单</view>
    </view>

    <!-- 快捷功能 -->
    <view class="quick-actions">
      <view class="section-title">快捷功能</view>
      <view class="action-grid">
        <view class="action-item" @tap="navigateTo('/pages/nfc/trigger')">
          <view class="action-icon">📱</view>
          <view class="action-text">NFC触发</view>
        </view>
        <view class="action-item" @tap="navigateTo('/pages/content/generate')">
          <view class="action-icon">✨</view>
          <view class="action-text">AI生成</view>
        </view>
        <view class="action-item" @tap="navigateTo('/pages/publish/settings')">
          <view class="action-icon">🚀</view>
          <view class="action-text">发布内容</view>
        </view>
        <view class="action-item" @tap="navigateTo('/pages/merchant/devices')">
          <view class="action-icon">⚙️</view>
          <view class="action-text">设备管理</view>
        </view>
      </view>
    </view>

    <!-- 数据概览 -->
    <view class="stats-overview">
      <view class="section-title">今日数据</view>
      <view class="stats-grid">
        <view class="stat-item">
          <view class="stat-value">{{ todayStats.triggers }}</view>
          <view class="stat-label">触发次数</view>
        </view>
        <view class="stat-item">
          <view class="stat-value">{{ todayStats.contents }}</view>
          <view class="stat-label">内容生成</view>
        </view>
        <view class="stat-item">
          <view class="stat-value">{{ todayStats.publishes }}</view>
          <view class="stat-label">发布数量</view>
        </view>
        <view class="stat-item">
          <view class="stat-value">{{ todayStats.visitors }}</view>
          <view class="stat-label">访客数</view>
        </view>
      </view>
    </view>

    <!-- 最近活动 -->
    <view class="recent-activity">
      <view class="section-title">最近活动</view>
      <view class="activity-list">
        <view class="activity-item" v-for="(item, index) in recentActivities" :key="index">
          <view class="activity-time">{{ item.time }}</view>
          <view class="activity-desc">{{ item.desc }}</view>
        </view>
        <view class="empty-state" v-if="recentActivities.length === 0">
          <text>暂无活动记录</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      todayStats: {
        triggers: 0,
        contents: 0,
        publishes: 0,
        visitors: 0
      },
      recentActivities: []
    }
  },
  onLoad() {
    this.checkLogin()
    this.loadData()
  },
  onPullDownRefresh() {
    this.loadData()
    setTimeout(() => {
      uni.stopPullDownRefresh()
    }, 1000)
  },
  methods: {
    checkLogin() {
      const token = uni.getStorageSync('token')
      if (!token) {
        uni.showModal({
          title: '提示',
          content: '请先登录',
          success: (res) => {
            if (res.confirm) {
              uni.navigateTo({
                url: '/pages/user/login'
              })
            }
          }
        })
      }
    },
    loadData() {
      // 加载今日统计数据
      this.loadTodayStats()
      // 加载最近活动
      this.loadRecentActivities()
    },
    async loadTodayStats() {
      try {
        // TODO: 调用API获取今日统计数据
        // const res = await this.$api.statistics.getToday()
        // this.todayStats = res.data

        // 临时模拟数据
        this.todayStats = {
          triggers: 128,
          contents: 45,
          publishes: 23,
          visitors: 567
        }
      } catch (error) {
        console.error('加载统计数据失败:', error)
      }
    },
    async loadRecentActivities() {
      try {
        // TODO: 调用API获取最近活动
        // const res = await this.$api.activities.getRecent()
        // this.recentActivities = res.data

        // 临时模拟数据
        this.recentActivities = [
          {
            time: '10:30',
            desc: 'NFC设备001触发内容生成'
          },
          {
            time: '09:15',
            desc: '成功发布内容到抖音平台'
          },
          {
            time: '昨天',
            desc: '新增素材15个'
          }
        ]
      } catch (error) {
        console.error('加载活动记录失败:', error)
      }
    },
    navigateTo(url) {
      uni.navigateTo({
        url: url
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.page-container {
  padding: 20rpx;
}

.welcome-card {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: #ffffff;
  margin-bottom: 30rpx;
  padding: 40rpx;
}

.welcome-title {
  font-size: 22px;
  font-weight: 600;
  margin-bottom: 12rpx;
}

.welcome-desc {
  font-size: 14px;
  opacity: 0.9;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 20rpx;
}

.quick-actions {
  margin-bottom: 30rpx;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20rpx;
}

.action-item {
  background-color: #ffffff;
  border-radius: 12rpx;
  padding: 30rpx 20rpx;
  text-align: center;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
}

.action-item:active {
  background-color: #f8f9fa;
}

.action-icon {
  font-size: 32px;
  margin-bottom: 12rpx;
}

.action-text {
  font-size: 12px;
  color: #475569;
}

.stats-overview {
  margin-bottom: 30rpx;
}

.stats-grid {
  background-color: #ffffff;
  border-radius: 12rpx;
  padding: 30rpx;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 24px;
  font-weight: 600;
  color: #6366f1;
  margin-bottom: 8rpx;
}

.stat-label {
  font-size: 12px;
  color: #64748b;
}

.recent-activity {
  margin-bottom: 30rpx;
}

.activity-list {
  background-color: #ffffff;
  border-radius: 12rpx;
  overflow: hidden;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
}

.activity-item {
  padding: 24rpx;
  border-bottom: 1rpx solid #e2e8f0;
  display: flex;
  align-items: center;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-time {
  font-size: 12px;
  color: #94a3b8;
  margin-right: 20rpx;
  min-width: 80rpx;
}

.activity-desc {
  font-size: 14px;
  color: #475569;
  flex: 1;
}

.empty-state {
  padding: 60rpx;
  text-align: center;
  color: #94a3b8;
  font-size: 14px;
}
</style>

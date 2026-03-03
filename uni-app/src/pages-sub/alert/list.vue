<template>
  <view class="page-container">
    <!-- 导航栏 -->
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">告警列表</text>
      <view class="nav-action"></view>
    </view>

    <!-- 统计卡片 -->
    <view class="stats-row">
      <view class="stat-item">
        <text class="stat-num danger">{{ stats.pending }}</text>
        <text class="stat-label">待处理</text>
      </view>
      <view class="stat-item">
        <text class="stat-num warning">{{ stats.processing }}</text>
        <text class="stat-label">处理中</text>
      </view>
      <view class="stat-item">
        <text class="stat-num success">{{ stats.resolved }}</text>
        <text class="stat-label">已解决</text>
      </view>
      <view class="stat-item">
        <text class="stat-num">{{ stats.total }}</text>
        <text class="stat-label">总计</text>
      </view>
    </view>

    <!-- 筛选栏 -->
    <view class="filter-bar">
      <scroll-view class="filter-tabs" scroll-x>
        <view
          class="filter-tab"
          :class="{ active: currentStatus === item.value }"
          v-for="item in statusTabs"
          :key="item.value"
          @tap="switchStatus(item.value)"
        >
          {{ item.label }}
        </view>
      </scroll-view>
      <view class="filter-level" @tap="showLevelPicker = true">
        <text>{{ currentLevelLabel }}</text>
        <text class="arrow-down">▾</text>
      </view>
    </view>

    <!-- 告警列表 -->
    <scroll-view class="alert-list" scroll-y @scrolltolower="loadMore">
      <view v-if="loading && list.length === 0" class="loading-state">
        <text class="loading-text">加载中...</text>
      </view>

      <empty-state
        v-else-if="list.length === 0"
        icon="🔔"
        title="暂无告警记录"
      />

      <view
        v-else
        class="alert-card"
        v-for="item in list"
        :key="item.id"
        @tap="viewDetail(item)"
      >
        <view class="alert-header">
          <view class="alert-level" :class="`level-${item.level}`">
            {{ formatLevel(item.level) }}
          </view>
          <view class="alert-status" :class="`status-${item.status}`">
            {{ formatStatus(item.status) }}
          </view>
        </view>
        <text class="alert-title">{{ item.title }}</text>
        <text class="alert-desc">{{ item.message }}</text>
        <view class="alert-footer">
          <text class="alert-device">{{ item.device_name || '未知设备' }}</text>
          <text class="alert-time">{{ item.created_at }}</text>
        </view>
      </view>

      <view v-if="loading && list.length > 0" class="load-more">
        <text>加载中...</text>
      </view>
      <view v-if="finished && list.length > 0" class="load-more">
        <text>没有更多了</text>
      </view>
    </scroll-view>

    <!-- 级别筛选弹窗 -->
    <view v-if="showLevelPicker" class="picker-mask" @tap="showLevelPicker = false">
      <view class="picker-content" @tap.stop>
        <view
          class="picker-item"
          :class="{ active: currentLevel === item.value }"
          v-for="item in levelOptions"
          :key="item.value"
          @tap="selectLevel(item.value)"
        >
          {{ item.label }}
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '@/api/index.js'
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  data() {
    return {
      stats: { pending: 0, processing: 0, resolved: 0, total: 0 },
      currentStatus: '',
      currentLevel: '',
      showLevelPicker: false,
      list: [],
      page: 1,
      limit: 15,
      loading: false,
      finished: false,

      statusTabs: [
        { label: '全部', value: '' },
        { label: '待处理', value: 'pending' },
        { label: '处理中', value: 'processing' },
        { label: '已解决', value: 'resolved' },
        { label: '已忽略', value: 'ignored' }
      ],
      levelOptions: [
        { label: '全部级别', value: '' },
        { label: '严重', value: 'critical' },
        { label: '警告', value: 'warning' },
        { label: '提示', value: 'info' }
      ]
    }
  },

  computed: {
    currentLevelLabel() {
      const found = this.levelOptions.find(i => i.value === this.currentLevel)
      return found ? found.label : '全部级别'
    }
  },

  onLoad() {
    this.loadList()
    this.loadStats()
  },

  onPullDownRefresh() {
    this.refreshList()
  },

  methods: {
    goBack() {
      uni.navigateBack()
    },

    async loadStats() {
      try {
        const res = await api.alert.getStatistics()
        if (res && res.data) {
          this.stats = { ...this.stats, ...res.data }
        }
      } catch (e) {
        this.stats = { pending: 3, processing: 1, resolved: 12, total: 16 }
      }
    },

    async loadList() {
      if (this.loading || this.finished) return
      this.loading = true
      try {
        const res = await api.alert.getList({
          page: this.page,
          limit: this.limit,
          status: this.currentStatus,
          level: this.currentLevel
        })
        const items = (res && res.data && res.data.list) || []
        if (items.length < this.limit) {
          this.finished = true
        }
        this.list = [...this.list, ...items]
        this.page++
      } catch (e) {
        if (this.list.length === 0) {
          this.list = this.getMockData()
          this.finished = true
        }
      } finally {
        this.loading = false
      }
    },

    refreshList() {
      this.page = 1
      this.list = []
      this.finished = false
      this.loadList()
      this.loadStats()
      uni.stopPullDownRefresh()
    },

    loadMore() {
      this.loadList()
    },

    switchStatus(status) {
      this.currentStatus = status
      this.refreshList()
    },

    selectLevel(level) {
      this.currentLevel = level
      this.showLevelPicker = false
      this.refreshList()
    },

    viewDetail(item) {
      uni.navigateTo({
        url: `/pages-sub/alert/detail?id=${item.id}`
      })
    },

    formatLevel(level) {
      const map = { critical: '严重', warning: '警告', info: '提示' }
      return map[level] || level
    },

    formatStatus(status) {
      const map = { pending: '待处理', processing: '处理中', resolved: '已解决', ignored: '已忽略' }
      return map[status] || status
    },

    getMockData() {
      const levels = ['critical', 'warning', 'info']
      const statuses = ['pending', 'processing', 'resolved']
      const titles = ['设备离线告警', '电量低告警', '触发异常告警', '网络连接异常', '设备温度过高']
      return Array.from({ length: 8 }, (_, i) => ({
        id: i + 1,
        level: levels[i % 3],
        status: statuses[i % 3],
        title: titles[i % titles.length],
        message: `设备 NFC${String(i + 1).padStart(4, '0')} 检测到异常状态，请及时处理`,
        device_name: `NFC设备-${i + 1}`,
        created_at: '2025-01-15 14:30'
      }))
    }
  }
}
</script>

<style scoped>
.page-container { min-height: 100vh; background: #f5f5f5; display: flex; flex-direction: column; }

.navbar { position: sticky; top: 0; z-index: 999; display: flex; align-items: center; justify-content: space-between; padding: 20rpx 30rpx; background: #fff; border-bottom: 1rpx solid #e5e7eb; }
.nav-back { width: 60rpx; font-size: 20px; color: #374151; }
.nav-title { flex: 1; font-size: 18px; font-weight: 600; color: #1f2937; text-align: center; }
.nav-action { width: 60rpx; }

.stats-row { display: flex; padding: 20rpx; gap: 15rpx; }
.stat-item { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 24rpx 0; background: #fff; border-radius: 12rpx; }
.stat-num { font-size: 24px; font-weight: bold; color: #374151; }
.stat-num.danger { color: #dc2626; }
.stat-num.warning { color: #f59e0b; }
.stat-num.success { color: #16a34a; }
.stat-label { font-size: 12px; color: #9ca3af; margin-top: 8rpx; }

.filter-bar { display: flex; align-items: center; padding: 0 20rpx 20rpx; gap: 15rpx; }
.filter-tabs { flex: 1; white-space: nowrap; }
.filter-tab { display: inline-block; padding: 12rpx 24rpx; margin-right: 12rpx; border-radius: 20rpx; font-size: 13px; background: #fff; color: #6b7280; }
.filter-tab.active { background: #6366f1; color: #fff; }
.filter-level { display: flex; align-items: center; gap: 6rpx; padding: 12rpx 20rpx; background: #fff; border-radius: 20rpx; font-size: 13px; color: #6b7280; }
.arrow-down { font-size: 12px; }

.alert-list { flex: 1; padding: 0 20rpx 20rpx; }

.loading-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0; }
.loading-text { font-size: 14px; color: #9ca3af; }

.alert-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.alert-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16rpx; }
.alert-level { padding: 4rpx 16rpx; border-radius: 8rpx; font-size: 12px; }
.level-critical { background: #fee2e2; color: #dc2626; }
.level-warning { background: #fef3c7; color: #d97706; }
.level-info { background: #dbeafe; color: #2563eb; }
.alert-status { padding: 4rpx 16rpx; border-radius: 8rpx; font-size: 12px; }
.status-pending { background: #fee2e2; color: #dc2626; }
.status-processing { background: #fef3c7; color: #d97706; }
.status-resolved { background: #dcfce7; color: #16a34a; }
.status-ignored { background: #f3f4f6; color: #6b7280; }
.alert-title { display: block; font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 10rpx; }
.alert-desc { display: block; font-size: 13px; color: #6b7280; line-height: 1.5; margin-bottom: 16rpx; }
.alert-footer { display: flex; justify-content: space-between; align-items: center; }
.alert-device { font-size: 12px; color: #6366f1; }
.alert-time { font-size: 12px; color: #9ca3af; }

.load-more { text-align: center; padding: 20rpx; font-size: 13px; color: #9ca3af; }

.picker-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: rgba(0,0,0,0.5); display: flex; align-items: flex-end; }
.picker-content { width: 100%; background: #fff; border-radius: 20rpx 20rpx 0 0; padding: 30rpx; }
.picker-item { padding: 24rpx 20rpx; font-size: 15px; color: #374151; border-bottom: 1rpx solid #f3f4f6; }
.picker-item.active { color: #6366f1; font-weight: 600; }
</style>

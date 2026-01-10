<template>
  <view class="overview-container">
    <view class="navbar">
      <image class="nav-back" src="/static/icon/back.png" @tap="goBack" />
      <text class="nav-title">数据总览</text>
    </view>

    <scroll-view class="content" scroll-y>
      <!-- 日期选择 -->
      <view class="date-selector">
        <view class="date-tabs">
          <view v-for="item in dateTabs" :key="item.value"
                class="date-tab" :class="{ active: currentPeriod === item.value }"
                @tap="switchPeriod(item.value)">
            {{ item.label }}
          </view>
        </view>
      </view>

      <!-- 核心指标 -->
      <view class="metrics-grid">
        <view class="metric-card" v-for="metric in coreMetrics" :key="metric.key">
          <text class="metric-icon">{{ metric.icon }}</text>
          <text class="metric-value">{{ metric.value }}</text>
          <text class="metric-label">{{ metric.label }}</text>
          <text class="metric-trend" :class="metric.trend > 0 ? 'up' : 'down'">
            {{ metric.trend > 0 ? '↑' : '↓' }} {{ Math.abs(metric.trend) }}%
          </text>
        </view>
      </view>

      <!-- 趋势图 -->
      <view class="chart-section">
        <view class="section-header">
          <text class="section-title">访问趋势</text>
        </view>
        <view class="chart-placeholder">
          <text>📈 图表区域</text>
        </view>
      </view>

      <!-- 设备统计 -->
      <view class="device-stats">
        <view class="section-header">
          <text class="section-title">设备统计</text>
        </view>
        <view class="stat-list">
          <view class="stat-item" v-for="item in deviceStats" :key="item.name">
            <view class="stat-info">
              <text class="stat-name">{{ item.name }}</text>
              <text class="stat-count">{{ item.count }}次</text>
            </view>
            <view class="stat-bar">
              <view class="stat-fill" :style="{ width: item.percent + '%' }"></view>
            </view>
          </view>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      currentPeriod: 'today',
      dateTabs: [
        { label: '今日', value: 'today' },
        { label: '本周', value: 'week' },
        { label: '本月', value: 'month' }
      ],
      coreMetrics: [
        { key: 'scan', icon: '👆', label: '扫码次数', value: '1,234', trend: 12.5 },
        { key: 'user', icon: '👥', label: '访问用户', value: '856', trend: 8.3 },
        { key: 'content', icon: '📄', label: '内容浏览', value: '3,456', trend: 15.2 },
        { key: 'conversion', icon: '💰', label: '转化率', value: '23%', trend: -2.1 }
      ],
      deviceStats: [
        { name: 'NFC设备-1', count: 456, percent: 85 },
        { name: 'NFC设备-2', count: 328, percent: 61 },
        { name: 'NFC设备-3', count: 245, percent: 46 },
        { name: 'NFC设备-4', count: 189, percent: 35 }
      ]
    }
  },
  methods: {
    goBack() {
      uni.navigateBack()
    },
    switchPeriod(period) {
      this.currentPeriod = period
      this.loadData()
    },
    loadData() {
      // 模拟加载数据
    }
  }
}
</script>

<style scoped>
.overview-container { min-height: 100vh; background: #f5f5f5; display: flex; flex-direction: column; }
.navbar { position: sticky; top: 0; z-index: 999; display: flex; align-items: center; padding: 20rpx 30rpx; background: #fff; border-bottom: 1rpx solid #e5e7eb; }
.nav-back { width: 40rpx; height: 40rpx; margin-right: 20rpx; }
.nav-title { font-size: 18px; font-weight: 600; color: #1f2937; }
.content { flex: 1; padding: 20rpx; }
.date-selector { margin-bottom: 20rpx; }
.date-tabs { display: flex; gap: 15rpx; }
.date-tab { flex: 1; padding: 15rpx; text-align: center; background: #fff; border-radius: 12rpx; font-size: 14px; color: #6b7280; }
.date-tab.active { background: #6366f1; color: #fff; }
.metrics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20rpx; margin-bottom: 20rpx; }
.metric-card { background: #fff; border-radius: 16rpx; padding: 30rpx; text-align: center; }
.metric-icon { display: block; font-size: 40rpx; margin-bottom: 10rpx; }
.metric-value { display: block; font-size: 24px; font-weight: bold; color: #1f2937; margin-bottom: 8rpx; }
.metric-label { display: block; font-size: 12px; color: #6b7280; margin-bottom: 10rpx; }
.metric-trend { font-size: 12px; }
.metric-trend.up { color: #16a34a; }
.metric-trend.down { color: #dc2626; }
.chart-section, .device-stats { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.section-header { margin-bottom: 20rpx; }
.section-title { font-size: 16px; font-weight: 600; color: #1f2937; }
.chart-placeholder { height: 400rpx; display: flex; align-items: center; justify-content: center; background: #f9fafb; border-radius: 12rpx; font-size: 16px; color: #9ca3af; }
.stat-list { }
.stat-item { margin-bottom: 30rpx; }
.stat-item:last-child { margin-bottom: 0; }
.stat-info { display: flex; justify-content: space-between; margin-bottom: 10rpx; }
.stat-name { font-size: 14px; color: #6b7280; }
.stat-count { font-size: 14px; font-weight: 600; color: #1f2937; }
.stat-bar { height: 12rpx; background: #f3f4f6; border-radius: 6rpx; overflow: hidden; }
.stat-fill { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); transition: width 0.3s; }
</style>

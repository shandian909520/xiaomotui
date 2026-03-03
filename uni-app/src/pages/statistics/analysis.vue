<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">数据分析</text>
      <view class="nav-action"></view>
    </view>

    <!-- 时间筛选 -->
    <view class="time-bar">
      <view
        class="time-tab"
        :class="{ active: currentPeriod === p.value }"
        v-for="p in periods"
        :key="p.value"
        @tap="switchPeriod(p.value)"
      >{{ p.label }}</view>
    </view>

    <scroll-view class="content-scroll" scroll-y>
      <view v-if="loading" class="loading-state">
        <text>加载中...</text>
      </view>

      <template v-else>
        <!-- 概览卡片 -->
        <view class="overview-row">
          <view class="overview-card">
            <text class="ov-val">{{ overview.triggers }}</text>
            <text class="ov-label">触发次数</text>
            <text class="ov-trend" :class="overview.triggerTrend >= 0 ? 'up' : 'down'">
              {{ overview.triggerTrend >= 0 ? '+' : '' }}{{ overview.triggerTrend }}%
            </text>
          </view>
          <view class="overview-card">
            <text class="ov-val">{{ overview.contents }}</text>
            <text class="ov-label">内容生成</text>
            <text class="ov-trend" :class="overview.contentTrend >= 0 ? 'up' : 'down'">
              {{ overview.contentTrend >= 0 ? '+' : '' }}{{ overview.contentTrend }}%
            </text>
          </view>
        </view>
        <view class="overview-row">
          <view class="overview-card">
            <text class="ov-val">{{ overview.publishes }}</text>
            <text class="ov-label">发布数</text>
            <text class="ov-trend" :class="overview.publishTrend >= 0 ? 'up' : 'down'">
              {{ overview.publishTrend >= 0 ? '+' : '' }}{{ overview.publishTrend }}%
            </text>
          </view>
          <view class="overview-card">
            <text class="ov-val">{{ overview.users }}</text>
            <text class="ov-label">活跃用户</text>
            <text class="ov-trend" :class="overview.userTrend >= 0 ? 'up' : 'down'">
              {{ overview.userTrend >= 0 ? '+' : '' }}{{ overview.userTrend }}%
            </text>
          </view>
        </view>

        <!-- 趋势图（简易柱状图） -->
        <view class="chart-card">
          <text class="chart-title">触发趋势</text>
          <view class="bar-chart">
            <view class="bar-item" v-for="(d, idx) in trendData" :key="idx">
              <view class="bar-fill" :style="{ height: getBarHeight(d.value) + 'rpx' }"></view>
              <text class="bar-label">{{ d.label }}</text>
            </view>
          </view>
        </view>

        <!-- 设备排行 -->
        <view class="rank-card">
          <text class="rank-title">设备触发排行</text>
          <view class="rank-item" v-for="(item, idx) in deviceRank" :key="item.id">
            <text class="rank-num" :class="{ top: idx < 3 }">{{ idx + 1 }}</text>
            <text class="rank-name">{{ item.name }}</text>
            <view class="rank-bar-wrap">
              <view class="rank-bar" :style="{ width: getRankWidth(item.count) + '%' }"></view>
            </view>
            <text class="rank-count">{{ item.count }}次</text>
          </view>
        </view>

        <!-- 内容类型分布 -->
        <view class="dist-card">
          <text class="dist-title">内容类型分布</text>
          <view class="dist-list">
            <view class="dist-item" v-for="item in contentDist" :key="item.type">
              <view class="dist-color" :style="{ background: item.color }"></view>
              <text class="dist-label">{{ item.label }}</text>
              <text class="dist-val">{{ item.count }}</text>
              <text class="dist-pct">{{ item.percent }}%</text>
            </view>
          </view>
        </view>

        <!-- 转化漏斗 -->
        <view class="funnel-card">
          <text class="funnel-title">转化漏斗</text>
          <view class="funnel-list">
            <view class="funnel-step" v-for="(step, idx) in funnelData" :key="idx">
              <view class="funnel-bar" :style="{ width: step.percent + '%' }">
                <text class="funnel-text">{{ step.label }}</text>
              </view>
              <text class="funnel-num">{{ step.count }} ({{ step.percent }}%)</text>
            </view>
          </view>
        </view>
      </template>
    </scroll-view>
  </view>
</template>

<script>
import api from '@/api/index.js'

export default {
  data() {
    return {
      loading: true,
      currentPeriod: 'week',
      periods: [
        { label: '今日', value: 'today' },
        { label: '本周', value: 'week' },
        { label: '本月', value: 'month' }
      ],
      overview: {},
      trendData: [],
      deviceRank: [],
      contentDist: [],
      funnelData: []
    }
  },

  onLoad() { this.loadData() },

  methods: {
    goBack() { uni.navigateBack() },

    switchPeriod(val) {
      this.currentPeriod = val
      this.loadData()
    },

    async loadData() {
      this.loading = true
      try {
        const res = await api.statistics.getOverview({ type: this.currentPeriod })
        if (res && res.data) {
          this.overview = res.data.overview || {}
          this.trendData = res.data.trend || []
          this.deviceRank = res.data.deviceRank || []
          this.contentDist = res.data.contentDist || []
          this.funnelData = res.data.funnel || []
        }
      } catch (e) {
        this.loadMockData()
      } finally {
        this.loading = false
      }
    },

    getBarHeight(val) {
      const max = Math.max(...this.trendData.map(d => d.value), 1)
      return Math.round((val / max) * 200)
    },

    getRankWidth(count) {
      const max = Math.max(...this.deviceRank.map(d => d.count), 1)
      return Math.round((count / max) * 100)
    },

    loadMockData() {
      this.overview = {
        triggers: 1256, triggerTrend: 12.5,
        contents: 348, contentTrend: 8.3,
        publishes: 186, publishTrend: -3.2,
        users: 89, userTrend: 15.7
      }
      this.trendData = [
        { label: '周一', value: 156 },
        { label: '周二', value: 203 },
        { label: '周三', value: 178 },
        { label: '周四', value: 245 },
        { label: '周五', value: 198 },
        { label: '周六', value: 312 },
        { label: '周日', value: 267 }
      ]
      this.deviceRank = [
        { id: 1, name: 'NFC-A001', count: 356 },
        { id: 2, name: 'NFC-A002', count: 289 },
        { id: 3, name: 'NFC-B001', count: 234 },
        { id: 4, name: 'NFC-B002', count: 178 },
        { id: 5, name: 'NFC-C001', count: 145 }
      ]
      this.contentDist = [
        { type: 'article', label: '图文', count: 156, percent: 45, color: '#6366f1' },
        { type: 'video', label: '视频', count: 89, percent: 26, color: '#8b5cf6' },
        { type: 'image', label: '图片', count: 67, percent: 19, color: '#a78bfa' },
        { type: 'other', label: '其他', count: 36, percent: 10, color: '#c4b5fd' }
      ]
      this.funnelData = [
        { label: 'NFC触发', count: 1256, percent: 100 },
        { label: '内容生成', count: 348, percent: 28 },
        { label: '内容发布', count: 186, percent: 15 },
        { label: '用户互动', count: 89, percent: 7 }
      ]
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

.time-bar { display: flex; padding: 20rpx; gap: 12rpx; }
.time-tab { flex: 1; text-align: center; padding: 16rpx 0; border-radius: 12rpx; font-size: 14px; background: #fff; color: #6b7280; }
.time-tab.active { background: #6366f1; color: #fff; }

.content-scroll { flex: 1; padding: 0 20rpx 20rpx; }
.loading-state { display: flex; align-items: center; justify-content: center; padding: 120rpx 0; font-size: 14px; color: #9ca3af; }

.overview-row { display: flex; gap: 16rpx; margin-bottom: 16rpx; }
.overview-card { flex: 1; background: #fff; border-radius: 16rpx; padding: 24rpx; }
.ov-val { display: block; font-size: 24px; font-weight: 700; color: #1f2937; }
.ov-label { display: block; font-size: 12px; color: #9ca3af; margin-top: 6rpx; }
.ov-trend { display: inline-block; font-size: 12px; margin-top: 8rpx; padding: 2rpx 10rpx; border-radius: 6rpx; }
.ov-trend.up { color: #16a34a; background: #dcfce7; }
.ov-trend.down { color: #dc2626; background: #fee2e2; }

.chart-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.chart-title { display: block; font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 24rpx; }
.bar-chart { display: flex; align-items: flex-end; gap: 12rpx; height: 240rpx; }
.bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
.bar-fill { width: 100%; background: linear-gradient(180deg, #6366f1, #8b5cf6); border-radius: 6rpx 6rpx 0 0; min-height: 8rpx; }
.bar-label { font-size: 11px; color: #9ca3af; margin-top: 8rpx; }

.rank-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.rank-title { display: block; font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 24rpx; }
.rank-item { display: flex; align-items: center; gap: 12rpx; padding: 12rpx 0; }
.rank-num { width: 40rpx; height: 40rpx; line-height: 40rpx; text-align: center; border-radius: 8rpx; font-size: 13px; font-weight: 600; color: #6b7280; background: #f3f4f6; }
.rank-num.top { background: #6366f1; color: #fff; }
.rank-name { width: 160rpx; font-size: 13px; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rank-bar-wrap { flex: 1; height: 16rpx; background: #f3f4f6; border-radius: 8rpx; overflow: hidden; }
.rank-bar { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 8rpx; }
.rank-count { font-size: 12px; color: #6b7280; white-space: nowrap; }

.dist-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.dist-title { display: block; font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 24rpx; }
.dist-list { }
.dist-item { display: flex; align-items: center; gap: 12rpx; padding: 12rpx 0; }
.dist-color { width: 24rpx; height: 24rpx; border-radius: 6rpx; flex-shrink: 0; }
.dist-label { flex: 1; font-size: 14px; color: #1f2937; }
.dist-val { font-size: 14px; font-weight: 600; color: #1f2937; }
.dist-pct { font-size: 12px; color: #9ca3af; width: 80rpx; text-align: right; }

.funnel-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.funnel-title { display: block; font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 24rpx; }
.funnel-list { }
.funnel-step { display: flex; align-items: center; gap: 16rpx; margin-bottom: 16rpx; }
.funnel-bar { height: 64rpx; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 8rpx; display: flex; align-items: center; padding-left: 16rpx; min-width: 120rpx; }
.funnel-text { font-size: 12px; color: #fff; white-space: nowrap; }
.funnel-num { font-size: 12px; color: #6b7280; white-space: nowrap; }
</style>

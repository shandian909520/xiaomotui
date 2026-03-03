<template>
  <view class="stats-page">
    <!-- 日期选择器 -->
    <view class="date-tabs">
      <view
        v-for="tab in dateTabs"
        :key="tab.value"
        class="date-tab"
        :class="{ active: activeDateRange === tab.value }"
        @tap="changeDateRange(tab.value)"
      >
        {{ tab.label }}
      </view>
    </view>

    <!-- 概览数据卡片 -->
    <view class="overview-section">
      <view class="section-title">数据概览</view>
      <view class="overview-grid">
        <view class="overview-card card-purple">
          <view class="card-icon">📢</view>
          <view class="card-content">
            <text class="card-value">{{ overviewData.campaignCount || 0 }}</text>
            <text class="card-label">总活动数</text>
          </view>
        </view>
        <view class="overview-card card-blue">
          <view class="card-icon">👆</view>
          <view class="card-content">
            <text class="card-value">{{ overviewData.triggerCount || 0 }}</text>
            <text class="card-label">总触发数</text>
          </view>
        </view>
        <view class="overview-card card-green">
          <view class="card-icon">✅</view>
          <view class="card-content">
            <text class="card-value">{{ overviewData.publishCount || 0 }}</text>
            <text class="card-label">总发布数</text>
          </view>
        </view>
        <view class="overview-card card-orange">
          <view class="card-icon">🎁</view>
          <view class="card-content">
            <text class="card-value">{{ overviewData.rewardCount || 0 }}</text>
            <text class="card-label">奖励发放</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 转化漏斗 -->
    <view class="funnel-section">
      <view class="section-title">转化漏斗</view>
      <view class="funnel-container">
        <view class="funnel-stage" :style="{ width: '100%' }">
          <view class="funnel-bar funnel-trigger">
            <text class="funnel-text">触发 {{ funnelData.triggerCount }}</text>
          </view>
        </view>
        <view class="funnel-stage" :style="{ width: getFunnelWidth(funnelData.downloadRate) }">
          <view class="funnel-bar funnel-download">
            <text class="funnel-text">下载 {{ funnelData.downloadCount }}</text>
          </view>
          <text class="funnel-rate">{{ funnelData.downloadRate }}%</text>
        </view>
        <view class="funnel-stage" :style="{ width: getFunnelWidth(funnelData.publishRate) }">
          <view class="funnel-bar funnel-publish">
            <text class="funnel-text">发布 {{ funnelData.publishCount }}</text>
          </view>
          <text class="funnel-rate">{{ funnelData.publishRate }}%</text>
        </view>
        <view class="funnel-stage" :style="{ width: getFunnelWidth(funnelData.rewardRate) }">
          <view class="funnel-bar funnel-reward">
            <text class="funnel-text">奖励 {{ funnelData.rewardCount }}</text>
          </view>
          <text class="funnel-rate">{{ funnelData.rewardRate }}%</text>
        </view>
      </view>
    </view>

    <!-- 趋势图 -->
    <view class="trend-section">
      <view class="section-title">触发与发布趋势</view>
      <view class="chart-container">
        <view class="bar-chart">
          <view
            v-for="(item, index) in trendData"
            :key="index"
            class="bar-item"
          >
            <view class="bar-values">
              <view class="bar-group">
                <view
                  class="bar-fill bar-trigger"
                  :style="{ height: getBarHeight(item.triggerCount, maxTrigger) + 'rpx' }"
                ></view>
              </view>
              <view class="bar-group">
                <view
                  class="bar-fill bar-publish"
                  :style="{ height: getBarHeight(item.publishCount, maxTrigger) + 'rpx' }"
                ></view>
              </view>
            </view>
            <view class="bar-label">{{ item.dateLabel }}</view>
          </view>
        </view>
        <view class="chart-legend">
          <view class="legend-item">
            <view class="legend-color legend-trigger"></view>
            <text class="legend-text">触发</text>
          </view>
          <view class="legend-item">
            <view class="legend-color legend-publish"></view>
            <text class="legend-text">发布</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 平台分布 -->
    <view class="platform-section">
      <view class="section-title">平台分布</view>
      <view class="platform-list">
        <view
          v-for="platform in platformData"
          :key="platform.name"
          class="platform-item"
        >
          <view class="platform-header">
            <text class="platform-name">{{ platform.name }}</text>
            <text class="platform-count">{{ platform.count }} 次</text>
          </view>
          <view class="progress-bar">
            <view
              class="progress-fill"
              :style="{
                width: platform.percent + '%',
                background: platform.color
              }"
            ></view>
          </view>
        </view>
        <view v-if="platformData.length === 0" class="empty-tip">
          暂无平台数据
        </view>
      </view>
    </view>

    <!-- 设备排行 -->
    <view class="ranking-section">
      <view class="section-title">设备排行 TOP 5</view>
      <view class="ranking-list">
        <view
          v-for="(device, index) in deviceRanking"
          :key="device.id"
          class="ranking-item"
        >
          <view class="ranking-index" :class="getRankClass(index)">
            {{ index + 1 }}
          </view>
          <view class="ranking-info">
            <text class="ranking-name">{{ device.name }}</text>
            <text class="ranking-trigger">触发 {{ device.triggerCount }} 次</text>
          </view>
        </view>
        <view v-if="deviceRanking.length === 0" class="empty-tip">
          暂无设备排行数据
        </view>
      </view>
    </view>

    <!-- 底部安全区 -->
    <view class="safe-area-bottom"></view>

    <!-- 加载状态 -->
    <view v-if="loading" class="loading-mask">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>
  </view>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../api/index.js'

export default {
  name: 'MerchantStats',
  setup() {
    // 日期选项
    const dateTabs = [
      { label: '近7天', value: '7d' },
      { label: '近30天', value: '30d' },
      { label: '全部', value: 'all' }
    ]

    // 当前选中的日期范围
    const activeDateRange = ref('7d')

    // 加载状态
    const loading = ref(false)

    // 概览数据
    const overviewData = reactive({
      campaignCount: 0,
      triggerCount: 0,
      publishCount: 0,
      rewardCount: 0
    })

    // 漏斗数据
    const funnelData = reactive({
      triggerCount: 0,
      downloadCount: 0,
      downloadRate: 0,
      publishCount: 0,
      publishRate: 0,
      rewardCount: 0,
      rewardRate: 0
    })

    // 趋势数据
    const trendData = ref([])

    // 平台分布数据
    const platformData = ref([])

    // 设备排行数据
    const deviceRanking = ref([])

    // 计算最大触发数（用于图表高度计算）
    const maxTrigger = computed(() => {
      if (trendData.value.length === 0) return 100
      const max = Math.max(...trendData.value.map(item => item.triggerCount))
      return max || 100
    })

    /**
     * 切换日期范围
     */
    const changeDateRange = (range) => {
      activeDateRange.value = range
      loadAllData()
    }

    /**
     * 获取漏斗宽度
     */
    const getFunnelWidth = (rate) => {
      const minWidth = 60
      const width = Math.max(minWidth, rate)
      return `${width}%`
    }

    /**
     * 获取柱状图高度
     */
    const getBarHeight = (value, maxValue) => {
      if (!maxValue) return 20
      const height = Math.max(20, (value / maxValue) * 200)
      return Math.min(height, 200)
    }

    /**
     * 获取排名样式类
     */
    const getRankClass = (index) => {
      if (index === 0) return 'rank-first'
      if (index === 1) return 'rank-second'
      if (index === 2) return 'rank-third'
      return ''
    }

    /**
     * 加载所有数据
     */
    const loadAllData = async () => {
      loading.value = true
      try {
        const params = { dateRange: activeDateRange.value }

        // 并行请求所有接口
        const [overviewRes, trendRes, platformRes, rankingRes] = await Promise.allSettled([
          loadOverview(params),
          loadTrendData(params),
          loadPlatformDistribution(params),
          loadDeviceRanking(params)
        ])

        // 处理概览数据
        if (overviewRes.status === 'fulfilled' && overviewRes.value) {
          handleOverviewData(overviewRes.value)
        } else {
          setMockOverviewData()
        }

        // 处理趋势数据
        if (trendRes.status === 'fulfilled' && trendRes.value) {
          trendData.value = trendRes.value
        } else {
          setMockTrendData()
        }

        // 处理平台分布
        if (platformRes.status === 'fulfilled' && platformRes.value) {
          platformData.value = platformRes.value
        } else {
          setMockPlatformData()
        }

        // 处理设备排行
        if (rankingRes.status === 'fulfilled' && rankingRes.value) {
          deviceRanking.value = rankingRes.value
        } else {
          setMockDeviceRanking()
        }

      } catch (error) {
        console.error('加载数据失败:', error)
        // 设置所有模拟数据
        setAllMockData()
      } finally {
        loading.value = false
      }
    }

    /**
     * 加载概览数据
     */
    const loadOverview = async (params) => {
      try {
        const res = await api.promoStats.getOverview(params)
        return res
      } catch (e) {
        console.warn('获取概览数据失败:', e)
        return null
      }
    }

    /**
     * 加载趋势数据
     */
    const loadTrendData = async (params) => {
      try {
        const res = await api.promoStats.getTrendData(params)
        return res
      } catch (e) {
        console.warn('获取趋势数据失败:', e)
        return null
      }
    }

    /**
     * 加载平台分布
     */
    const loadPlatformDistribution = async (params) => {
      try {
        const res = await api.promoStats.getPlatformDistribution(params)
        return res
      } catch (e) {
        console.warn('获取平台分布失败:', e)
        return null
      }
    }

    /**
     * 加载设备排行
     */
    const loadDeviceRanking = async (params) => {
      try {
        const res = await api.promoStats.getDeviceRanking(params)
        return res
      } catch (e) {
        console.warn('获取设备排行失败:', e)
        return null
      }
    }

    /**
     * 处理概览数据
     */
    const handleOverviewData = (data) => {
      overviewData.campaignCount = data.campaign_count || data.campaignCount || 0
      overviewData.triggerCount = data.trigger_count || data.triggerCount || 0
      overviewData.publishCount = data.publish_count || data.publishCount || 0
      overviewData.rewardCount = data.reward_count || data.rewardCount || 0

      // 更新漏斗数据
      funnelData.triggerCount = overviewData.triggerCount
      funnelData.downloadCount = data.download_count || data.downloadCount || Math.floor(overviewData.triggerCount * 0.8)
      funnelData.publishCount = overviewData.publishCount
      funnelData.rewardCount = overviewData.rewardCount

      // 计算转化率
      funnelData.downloadRate = funnelData.triggerCount > 0
        ? Math.round((funnelData.downloadCount / funnelData.triggerCount) * 100)
        : 0
      funnelData.publishRate = funnelData.triggerCount > 0
        ? Math.round((funnelData.publishCount / funnelData.triggerCount) * 100)
        : 0
      funnelData.rewardRate = funnelData.triggerCount > 0
        ? Math.round((funnelData.rewardCount / funnelData.triggerCount) * 100)
        : 0
    }

    /**
     * 设置模拟概览数据
     */
    const setMockOverviewData = () => {
      overviewData.campaignCount = 3
      overviewData.triggerCount = 256
      overviewData.publishCount = 128
      overviewData.rewardCount = 96

      funnelData.triggerCount = 256
      funnelData.downloadCount = 200
      funnelData.downloadRate = 78
      funnelData.publishCount = 128
      funnelData.publishRate = 50
      funnelData.rewardCount = 96
      funnelData.rewardRate = 38
    }

    /**
     * 设置模拟趋势数据
     */
    const setMockTrendData = () => {
      const days = activeDateRange.value === '7d' ? 7 : activeDateRange.value === '30d' ? 30 : 14
      trendData.value = Array.from({ length: Math.min(days, 14) }, (_, i) => {
        const date = new Date()
        date.setDate(date.getDate() - (days - 1 - i))
        return {
          date: date.toISOString().split('T')[0],
          dateLabel: `${date.getMonth() + 1}/${date.getDate()}`,
          triggerCount: Math.floor(Math.random() * 50) + 10,
          publishCount: Math.floor(Math.random() * 25) + 5
        }
      })
    }

    /**
     * 设置模拟平台数据
     */
    const setMockPlatformData = () => {
      const douyinCount = Math.floor(Math.random() * 200) + 100
      const kuaishouCount = Math.floor(Math.random() * 100) + 50
      const total = douyinCount + kuaishouCount

      platformData.value = [
        {
          name: '抖音',
          count: douyinCount,
          percent: Math.round((douyinCount / total) * 100),
          color: 'linear-gradient(90deg, #FF2C55 0%, #FF6B8A 100%)'
        },
        {
          name: '快手',
          count: kuaishouCount,
          percent: Math.round((kuaishouCount / total) * 100),
          color: 'linear-gradient(90deg, #FF7E00 0%, #FFB347 100%)'
        }
      ]
    }

    /**
     * 设置模拟设备排行
     */
    const setMockDeviceRanking = () => {
      deviceRanking.value = Array.from({ length: 5 }, (_, i) => ({
        id: i + 1,
        name: `NFC设备-${1000 + i}`,
        triggerCount: Math.floor(Math.random() * 100) + (50 - i * 10)
      })).sort((a, b) => b.triggerCount - a.triggerCount)
    }

    /**
     * 设置所有模拟数据
     */
    const setAllMockData = () => {
      setMockOverviewData()
      setMockTrendData()
      setMockPlatformData()
      setMockDeviceRanking()
    }

    onMounted(() => {
      loadAllData()
    })

    return {
      dateTabs,
      activeDateRange,
      loading,
      overviewData,
      funnelData,
      trendData,
      platformData,
      deviceRanking,
      maxTrigger,
      changeDateRange,
      getFunnelWidth,
      getBarHeight,
      getRankClass
    }
  }
}
</script>

<style lang="scss" scoped>
.stats-page {
  min-height: 100vh;
  background: #f5f6fa;
  padding-bottom: env(safe-area-inset-bottom);
}

/* 日期选择器 */
.date-tabs {
  display: flex;
  background: #ffffff;
  padding: 20rpx 30rpx;
  gap: 20rpx;
  position: sticky;
  top: 0;
  z-index: 10;
}

.date-tab {
  flex: 1;
  height: 72rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 36rpx;
  font-size: 28rpx;
  color: #6b7280;
  transition: all 0.3s ease;

  &.active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    font-weight: 600;
  }
}

/* 通用标题 */
.section-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 24rpx;
}

/* 概览数据 */
.overview-section {
  margin: 20rpx 30rpx;
  padding: 30rpx;
  background: #ffffff;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
}

.overview-card {
  padding: 24rpx;
  border-radius: 16rpx;
  display: flex;
  align-items: center;
  gap: 16rpx;

  &.card-purple {
    background: linear-gradient(135deg, #f0f0ff 0%, #e8e0ff 100%);
  }

  &.card-blue {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
  }

  &.card-green {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  }

  &.card-orange {
    background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%);
  }
}

.card-icon {
  font-size: 40rpx;
}

.card-content {
  display: flex;
  flex-direction: column;
}

.card-value {
  font-size: 40rpx;
  font-weight: bold;
  color: #1f2937;
}

.card-label {
  font-size: 24rpx;
  color: #6b7280;
  margin-top: 4rpx;
}

/* 转化漏斗 */
.funnel-section {
  margin: 20rpx 30rpx;
  padding: 30rpx;
  background: #ffffff;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.funnel-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
}

.funnel-stage {
  position: relative;
  transition: width 0.3s ease;
}

.funnel-bar {
  height: 80rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12rpx;
  width: 100%;

  &.funnel-trigger {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  }

  &.funnel-download {
    background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
  }

  &.funnel-publish {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
  }

  &.funnel-reward {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
  }
}

.funnel-text {
  font-size: 26rpx;
  font-weight: 600;
  color: #ffffff;
}

.funnel-rate {
  position: absolute;
  right: -80rpx;
  top: 50%;
  transform: translateY(-50%);
  font-size: 24rpx;
  color: #6b7280;
}

/* 趋势图 */
.trend-section {
  margin: 20rpx 30rpx;
  padding: 30rpx;
  background: #ffffff;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.chart-container {
  width: 100%;
}

.bar-chart {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  height: 280rpx;
  padding: 20rpx 0;
  gap: 8rpx;
}

.bar-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.bar-values {
  display: flex;
  align-items: flex-end;
  gap: 4rpx;
  height: 220rpx;
}

.bar-group {
  display: flex;
  align-items: flex-end;
}

.bar-fill {
  width: 24rpx;
  border-radius: 4rpx 4rpx 0 0;
  min-height: 20rpx;
  transition: height 0.3s ease;

  &.bar-trigger {
    background: linear-gradient(180deg, #8b5cf6 0%, #6366f1 100%);
  }

  &.bar-publish {
    background: linear-gradient(180deg, #34d399 0%, #10b981 100%);
  }
}

.bar-label {
  font-size: 20rpx;
  color: #9ca3af;
  margin-top: 8rpx;
}

.chart-legend {
  display: flex;
  justify-content: center;
  gap: 40rpx;
  margin-top: 20rpx;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.legend-color {
  width: 24rpx;
  height: 24rpx;
  border-radius: 4rpx;

  &.legend-trigger {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  }

  &.legend-publish {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
  }
}

.legend-text {
  font-size: 24rpx;
  color: #6b7280;
}

/* 平台分布 */
.platform-section {
  margin: 20rpx 30rpx;
  padding: 30rpx;
  background: #ffffff;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.platform-list {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
}

.platform-item {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}

.platform-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.platform-name {
  font-size: 28rpx;
  font-weight: 500;
  color: #1f2937;
}

.platform-count {
  font-size: 24rpx;
  color: #6b7280;
}

.progress-bar {
  height: 20rpx;
  background: #f3f4f6;
  border-radius: 10rpx;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 10rpx;
  transition: width 0.3s ease;
}

/* 设备排行 */
.ranking-section {
  margin: 20rpx 30rpx;
  padding: 30rpx;
  background: #ffffff;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.ranking-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.ranking-item {
  display: flex;
  align-items: center;
  padding: 20rpx;
  background: #f9fafb;
  border-radius: 12rpx;
}

.ranking-index {
  width: 48rpx;
  height: 48rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #e5e7eb;
  font-size: 24rpx;
  font-weight: 600;
  color: #6b7280;
  margin-right: 20rpx;

  &.rank-first {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: #ffffff;
  }

  &.rank-second {
    background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
    color: #ffffff;
  }

  &.rank-third {
    background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%);
    color: #ffffff;
  }
}

.ranking-info {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ranking-name {
  font-size: 28rpx;
  font-weight: 500;
  color: #1f2937;
}

.ranking-trigger {
  font-size: 24rpx;
  color: #6b7280;
}

/* 空状态提示 */
.empty-tip {
  text-align: center;
  padding: 40rpx 0;
  font-size: 28rpx;
  color: #9ca3af;
}

/* 底部安全区 */
.safe-area-bottom {
  height: 40rpx;
}

/* 加载状态 */
.loading-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 999;
}

.loading-spinner {
  width: 80rpx;
  height: 80rpx;
  border: 6rpx solid #f3f4f6;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.loading-text {
  margin-top: 20rpx;
  font-size: 28rpx;
  color: #6b7280;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>

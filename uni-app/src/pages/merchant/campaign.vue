<template>
  <view class="campaign-page">
    <!-- 顶部标签栏 -->
    <view class="tab-bar">
      <view
        v-for="tab in tabs"
        :key="tab.value"
        class="tab-item"
        :class="{ active: currentTab === tab.value }"
        @tap="switchTab(tab.value)"
      >
        {{ tab.label }}
        <text v-if="tab.count" class="tab-count">({{ tab.count }})</text>
      </view>
    </view>

    <!-- 活动列表 -->
    <scroll-view
      class="campaign-list"
      scroll-y
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      @refresherrefresh="onRefresh"
      @scrolltolower="loadMore"
    >
      <!-- 活动卡片 -->
      <view class="campaign-cards" v-if="campaignList.length > 0">
        <view
          v-for="item in campaignList"
          :key="item.id"
          class="campaign-card"
          @tap="goToDetail(item.id)"
        >
          <!-- 卡片头部 -->
          <view class="card-header">
            <view class="campaign-name">{{ item.name }}</view>
            <view class="campaign-status" :class="`status-${item.status}`">
              {{ getStatusText(item.status) }}
            </view>
          </view>

          <!-- 卡片内容 -->
          <view class="card-content">
            <view class="info-row">
              <view class="info-item">
                <text class="info-icon">🎬</text>
                <text class="info-value">{{ item.variant_count || 0 }} 个变体</text>
              </view>
              <view class="info-item">
                <text class="info-icon">📱</text>
                <text class="info-value">{{ item.device_count || 0 }} 台设备</text>
              </view>
            </view>

            <view class="time-row">
              <text class="time-label">活动时间:</text>
              <text class="time-value">{{ formatTimeRange(item.start_time, item.end_time) }}</text>
            </view>
          </view>

          <!-- 卡片底部 -->
          <view class="card-footer">
            <view class="stat-item">
              <text class="stat-value">{{ item.trigger_count || 0 }}</text>
              <text class="stat-label">触发</text>
            </view>
            <view class="stat-item">
              <text class="stat-value">{{ item.download_count || 0 }}</text>
              <text class="stat-label">下载</text>
            </view>
            <view class="stat-item">
              <text class="stat-value">{{ item.publish_count || 0 }}</text>
              <text class="stat-label">发布</text>
            </view>
            <view class="stat-arrow">
              <text>></text>
            </view>
          </view>
        </view>
      </view>

      <!-- 空状态 -->
      <empty-state
        v-else-if="!loading"
        icon="📢"
        title="暂无活动"
        description="点击下方按钮创建推广活动"
        btnText="创建活动"
        @action="goToCreate"
      />

      <!-- 加载更多 -->
      <view class="load-more" v-if="hasMore && campaignList.length > 0">
        <view class="loading-spinner" v-if="loading"></view>
        <text class="load-more-text">{{ loading ? '加载中...' : '上拉加载更多' }}</text>
      </view>
      <view class="no-more" v-else-if="campaignList.length > 0">
        <text>没有更多了</text>
      </view>

      <!-- 底部安全区 -->
      <view class="safe-area-bottom"></view>
    </scroll-view>

    <!-- 浮动创建按钮 -->
    <view class="fab-button" @tap="goToCreate">
      <text class="fab-icon">+</text>
    </view>
  </view>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import api from '../../api/index.js'

export default {
  name: 'MerchantCampaign',
  setup() {
    // 标签数据
    const tabs = ref([
      { value: 'all', label: '全部', count: 0 },
      { value: 'active', label: '进行中', count: 0 },
      { value: 'ended', label: '已结束', count: 0 }
    ])
    const currentTab = ref('all')

    // 活动列表
    const campaignList = ref([])
    const page = ref(1)
    const pageSize = 20
    const hasMore = ref(true)
    const loading = ref(false)
    const refreshing = ref(false)

    /**
     * 加载活动列表
     */
    const loadCampaignList = async (refresh = false) => {
      if (loading.value) return

      if (refresh) {
        page.value = 1
        campaignList.value = []
        hasMore.value = true
      }

      loading.value = true

      try {
        const params = {
          page: page.value,
          pageSize,
          status: currentTab.value === 'all' ? '' : currentTab.value
        }

        const res = await api.promoCampaign.getList(params)
        const newList = res.data || res.list || []

        if (refresh) {
          campaignList.value = newList
        } else {
          campaignList.value = [...campaignList.value, ...newList]
        }

        hasMore.value = newList.length >= pageSize
        page.value++

        // 更新标签计数
        if (res.counts) {
          tabs.value[0].count = res.counts.total || 0
          tabs.value[1].count = res.counts.active || 0
          tabs.value[2].count = res.counts.ended || 0
        }
      } catch (error) {
        console.error('加载活动列表失败:', error)
        // 使用模拟数据
        const mockData = generateMockData()
        if (refresh) {
          campaignList.value = mockData
        } else {
          campaignList.value = [...campaignList.value, ...mockData]
        }
        hasMore.value = false

        // 更新模拟计数
        tabs.value[0].count = mockData.length
        tabs.value[1].count = mockData.filter(item => item.status === 'active').length
        tabs.value[2].count = mockData.filter(item => item.status === 'ended').length
      } finally {
        loading.value = false
        refreshing.value = false
      }
    }

    /**
     * 生成模拟数据
     */
    const generateMockData = () => {
      const statuses = ['active', 'active', 'active', 'ended', 'ended']
      return Array.from({ length: 5 }, (_, i) => ({
        id: Date.now() + i,
        name: `推广活动 ${page.value}-${i + 1}`,
        description: '这是一个推广活动描述',
        status: statuses[i],
        variant_count: Math.floor(Math.random() * 10) + 1,
        device_count: Math.floor(Math.random() * 20),
        trigger_count: Math.floor(Math.random() * 100),
        download_count: Math.floor(Math.random() * 50),
        publish_count: Math.floor(Math.random() * 30),
        start_time: new Date(Date.now() - Math.random() * 7 * 24 * 3600000).toISOString(),
        end_time: new Date(Date.now() + Math.random() * 7 * 24 * 3600000).toISOString()
      }))
    }

    /**
     * 切换标签
     */
    const switchTab = (tab) => {
      if (currentTab.value === tab) return
      currentTab.value = tab
      loadCampaignList(true)
    }

    /**
     * 下拉刷新
     */
    const onRefresh = () => {
      refreshing.value = true
      loadCampaignList(true)
    }

    /**
     * 加载更多
     */
    const loadMore = () => {
      if (!hasMore.value || loading.value) return
      loadCampaignList()
    }

    /**
     * 获取状态文本
     */
    const getStatusText = (status) => {
      const statusMap = {
        'active': '进行中',
        'pending': '待开始',
        'ended': '已结束',
        'draft': '草稿'
      }
      return statusMap[status] || status
    }

    /**
     * 格式化时间范围
     */
    const formatTimeRange = (startTime, endTime) => {
      if (!startTime && !endTime) return '未设置'

      const formatDate = (dateStr) => {
        if (!dateStr) return ''
        const date = new Date(dateStr)
        return `${date.getMonth() + 1}/${date.getDate()}`
      }

      const start = formatDate(startTime)
      const end = formatDate(endTime)

      if (start && end) {
        return `${start} - ${end}`
      }
      return start || end
    }

    /**
     * 跳转详情页
     */
    const goToDetail = (id) => {
      uni.navigateTo({
        url: `/pages/merchant/campaign-detail?id=${id}`
      })
    }

    /**
     * 跳转创建页
     */
    const goToCreate = () => {
      uni.navigateTo({
        url: '/pages/merchant/campaign-edit'
      })
    }

    onMounted(() => {
      loadCampaignList(true)
    })

    return {
      tabs,
      currentTab,
      campaignList,
      hasMore,
      loading,
      refreshing,
      switchTab,
      onRefresh,
      loadMore,
      getStatusText,
      formatTimeRange,
      goToDetail,
      goToCreate
    }
  }
}
</script>

<style lang="scss" scoped>
.campaign-page {
  min-height: 100vh;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
}

/* 标签栏 */
.tab-bar {
  display: flex;
  background: #ffffff;
  padding: 0 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
}

.tab-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 30rpx 0;
  font-size: 15px;
  color: #6b7280;
  position: relative;
}

.tab-item.active {
  color: #6366f1;
  font-weight: 600;
}

.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 48rpx;
  height: 6rpx;
  background: #6366f1;
  border-radius: 3rpx;
}

.tab-count {
  font-size: 12px;
  margin-left: 8rpx;
}

/* 活动列表 */
.campaign-list {
  flex: 1;
  padding: 20rpx;
}

.campaign-cards {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.campaign-card {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.05);
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.campaign-name {
  font-size: 17px;
  font-weight: 600;
  color: #1f2937;
  flex: 1;
  margin-right: 16rpx;
}

.campaign-status {
  padding: 8rpx 20rpx;
  border-radius: 20rpx;
  font-size: 12px;
  font-weight: 500;
  flex-shrink: 0;

  &.status-active {
    background: #dcfce7;
    color: #16a34a;
  }

  &.status-pending {
    background: #fef3c7;
    color: #d97706;
  }

  &.status-ended {
    background: #f3f4f6;
    color: #6b7280;
  }

  &.status-draft {
    background: #e0e7ff;
    color: #6366f1;
  }
}

.card-content {
  margin-bottom: 20rpx;
}

.info-row {
  display: flex;
  gap: 30rpx;
  margin-bottom: 16rpx;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.info-icon {
  font-size: 14px;
}

.info-value {
  font-size: 14px;
  color: #4b5563;
}

.time-row {
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.time-label {
  font-size: 12px;
  color: #9ca3af;
}

.time-value {
  font-size: 12px;
  color: #6b7280;
}

.card-footer {
  display: flex;
  align-items: center;
  padding-top: 20rpx;
  border-top: 1rpx solid #f3f4f6;
}

.stat-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.stat-label {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 4rpx;
}

.stat-arrow {
  color: #9ca3af;
  font-size: 16px;
  padding: 0 10rpx;
}

/* 加载状态 */
.load-more,
.no-more {
  text-align: center;
  padding: 40rpx 0;
  font-size: 14px;
  color: #9ca3af;
}

.load-more {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
}

.loading-spinner {
  width: 40rpx;
  height: 40rpx;
  border: 4rpx solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 浮动按钮 */
.fab-button {
  position: fixed;
  right: 40rpx;
  bottom: calc(120rpx + env(safe-area-inset-bottom));
  width: 112rpx;
  height: 112rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8rpx 24rpx rgba(99, 102, 241, 0.4);
  z-index: 100;
}

.fab-icon {
  font-size: 48rpx;
  color: #ffffff;
  line-height: 1;
}

/* 底部安全区 */
.safe-area-bottom {
  height: 40rpx;
}
</style>

<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">优惠券</text>
      <text class="nav-action" @tap="goCreate">+ 创建</text>
    </view>

    <!-- 筛选栏 -->
    <view class="filter-bar">
      <scroll-view class="filter-tabs" scroll-x>
        <view
          class="filter-tab"
          :class="{ active: currentTab === t.value }"
          v-for="t in tabs"
          :key="t.value"
          @tap="switchTab(t.value)"
        >{{ t.label }}</view>
      </scroll-view>
    </view>

    <!-- 列表 -->
    <scroll-view class="coupon-list" scroll-y @scrolltolower="loadMore">
      <view v-if="loading && list.length === 0" class="loading-state">
        <text>加载中...</text>
      </view>

      <view v-else-if="list.length === 0" class="empty-state">
        <text class="empty-icon">🎫</text>
        <text class="empty-text">暂无优惠券</text>
      </view>

      <view v-else class="coupon-card" v-for="item in list" :key="item.id">
        <view class="coupon-left">
          <text class="coupon-amount" v-if="item.type === 'fixed'">¥{{ item.discount }}</text>
          <text class="coupon-amount" v-else>{{ item.discount }}折</text>
          <text class="coupon-condition">满{{ item.min_amount || 0 }}可用</text>
        </view>
        <view class="coupon-right">
          <text class="coupon-name">{{ item.name }}</text>
          <text class="coupon-date">{{ item.start_date }} ~ {{ item.end_date }}</text>
          <view class="coupon-meta">
            <text class="coupon-stock">剩余 {{ item.remaining }}/{{ item.total }}</text>
            <view class="coupon-status" :class="`s-${item.status}`">{{ formatStatus(item.status) }}</view>
          </view>
        </view>
      </view>

      <view v-if="finished && list.length > 0" class="load-more">
        <text>没有更多了</text>
      </view>
    </scroll-view>
  </view>
</template>

<script>
import api from '@/api/index.js'
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  data() {
    return {
      currentTab: '',
      tabs: [
        { label: '全部', value: '' },
        { label: '进行中', value: 'active' },
        { label: '已结束', value: 'expired' },
        { label: '已停用', value: 'disabled' }
      ],
      list: [],
      page: 1,
      limit: 15,
      loading: false,
      finished: false
    }
  },

  onLoad() {
    this.loadList()
  },

  onPullDownRefresh() {
    this.refresh()
  },

  methods: {
    goBack() { uni.navigateBack() },

    goCreate() {
      uni.navigateTo({ url: '/pages-sub/marketing/coupon/create' })
    },

    switchTab(val) {
      this.currentTab = val
      this.refresh()
    },

    refresh() {
      this.page = 1
      this.list = []
      this.finished = false
      this.loadList()
      uni.stopPullDownRefresh()
    },

    async loadList() {
      if (this.loading || this.finished) return
      this.loading = true
      try {
        const res = await api.coupon.getList({ page: this.page, limit: this.limit, status: this.currentTab })
        const items = (res && res.data && res.data.list) || []
        if (items.length < this.limit) this.finished = true
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

    loadMore() { this.loadList() },

    formatStatus(s) {
      return { active: '进行中', expired: '已结束', disabled: '已停用' }[s] || s
    },

    getMockData() {
      return [
        { id: 1, name: '新用户满减券', type: 'fixed', discount: 10, min_amount: 50, total: 100, remaining: 67, start_date: '2025-01-01', end_date: '2025-03-31', status: 'active' },
        { id: 2, name: '会员专享折扣', type: 'percent', discount: 8.5, min_amount: 100, total: 50, remaining: 23, start_date: '2025-01-15', end_date: '2025-02-28', status: 'active' },
        { id: 3, name: '节日满减券', type: 'fixed', discount: 20, min_amount: 100, total: 200, remaining: 0, start_date: '2024-12-01', end_date: '2024-12-31', status: 'expired' },
        { id: 4, name: '首单立减', type: 'fixed', discount: 5, min_amount: 0, total: 500, remaining: 312, start_date: '2025-01-01', end_date: '2025-06-30', status: 'active' }
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
.nav-action { font-size: 15px; color: #6366f1; }

.filter-bar { padding: 20rpx; }
.filter-tabs { white-space: nowrap; }
.filter-tab { display: inline-block; padding: 12rpx 28rpx; margin-right: 12rpx; border-radius: 20rpx; font-size: 13px; background: #fff; color: #6b7280; }
.filter-tab.active { background: #6366f1; color: #fff; }

.coupon-list { flex: 1; padding: 0 20rpx 20rpx; }
.loading-state, .empty-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0; font-size: 14px; color: #9ca3af; }
.empty-icon { font-size: 80rpx; margin-bottom: 20rpx; }
.empty-text { font-size: 14px; color: #9ca3af; }

.coupon-card { display: flex; background: #fff; border-radius: 16rpx; margin-bottom: 20rpx; overflow: hidden; }
.coupon-left { width: 200rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; padding: 24rpx 0; }
.coupon-amount { font-size: 24px; font-weight: bold; }
.coupon-condition { font-size: 11px; opacity: 0.8; margin-top: 6rpx; }
.coupon-right { flex: 1; padding: 24rpx; display: flex; flex-direction: column; justify-content: space-between; }
.coupon-name { font-size: 15px; font-weight: 600; color: #1f2937; }
.coupon-date { font-size: 12px; color: #9ca3af; margin-top: 8rpx; }
.coupon-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 12rpx; }
.coupon-stock { font-size: 12px; color: #6b7280; }
.coupon-status { padding: 2rpx 12rpx; border-radius: 6rpx; font-size: 11px; }
.s-active { background: #dcfce7; color: #16a34a; }
.s-expired { background: #f3f4f6; color: #6b7280; }
.s-disabled { background: #fee2e2; color: #dc2626; }

.load-more { text-align: center; padding: 20rpx; font-size: 13px; color: #9ca3af; }
</style>

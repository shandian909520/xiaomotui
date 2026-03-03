<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">我的优惠券</text>
      <view class="nav-action"></view>
    </view>

    <!-- 状态筛选 -->
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

    <!-- 优惠券列表 -->
    <scroll-view class="coupon-list" scroll-y @scrolltolower="loadMore">
      <view v-if="loading && list.length === 0" class="loading-state">
        <text>加载中...</text>
      </view>

      <empty-state
        v-else-if="list.length === 0"
        icon="🎫"
        title="暂无优惠券"
        btnText="去领券"
        @action="goReceive"
      />

      <view v-else class="coupon-item" v-for="item in list" :key="item.id" @tap="handleCouponClick(item)">
        <view class="coupon-main" :class="`status-${item.status}`">
          <view class="coupon-left">
            <text class="coupon-amount" v-if="item.coupon.type === 'fixed'">¥{{ item.coupon.discount }}</text>
            <text class="coupon-amount" v-else>{{ item.coupon.discount }}折</text>
            <text class="coupon-condition">满{{ item.coupon.min_amount || 0 }}可用</text>
          </view>
          <view class="coupon-right">
            <text class="coupon-name">{{ item.coupon.name }}</text>
            <text class="coupon-desc" v-if="item.coupon.description">{{ item.coupon.description }}</text>
            <text class="coupon-date">有效期至 {{ item.expire_time }}</text>
          </view>
          <view class="coupon-badge" v-if="item.status !== 'unused'">
            {{ formatStatus(item.status) }}
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
      currentTab: 'unused',
      tabs: [
        { label: '未使用', value: 'unused' },
        { label: '已使用', value: 'used' },
        { label: '已过期', value: 'expired' }
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

    goReceive() {
      uni.navigateTo({ url: '/pages/coupon/receive' })
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
        const res = await api.coupon.myList({
          page: this.page,
          limit: this.limit,
          status: this.currentTab
        })
        const items = (res && res.data && res.data.list) || []
        if (items.length < this.limit) this.finished = true
        this.list = [...this.list, ...items]
        this.page++
      } catch (e) {
        FeedbackHelper.error('加载失败')
      } finally {
        this.loading = false
      }
    },

    loadMore() { this.loadList() },

    handleCouponClick(item) {
      if (item.status === 'unused') {
        uni.navigateTo({
          url: `/pages/coupon/use?id=${item.id}`
        })
      }
    },

    formatStatus(s) {
      return { unused: '未使用', used: '已使用', expired: '已过期' }[s] || s
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

.filter-bar { padding: 20rpx; background: #fff; }
.filter-tabs { white-space: nowrap; }
.filter-tab { display: inline-block; padding: 12rpx 28rpx; margin-right: 12rpx; border-radius: 20rpx; font-size: 13px; background: #f3f4f6; color: #6b7280; }
.filter-tab.active { background: #6366f1; color: #fff; }

.coupon-list { flex: 1; padding: 20rpx; }
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0; }

.coupon-item { margin-bottom: 20rpx; }
.coupon-main { position: relative; display: flex; background: #fff; border-radius: 16rpx; overflow: hidden; }
.coupon-main.status-used, .coupon-main.status-expired { opacity: 0.5; }
.coupon-left { width: 200rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; padding: 32rpx 0; }
.coupon-amount { font-size: 28px; font-weight: bold; }
.coupon-condition { font-size: 11px; opacity: 0.8; margin-top: 8rpx; }
.coupon-right { flex: 1; padding: 24rpx; display: flex; flex-direction: column; justify-content: center; }
.coupon-name { font-size: 16px; font-weight: 600; color: #1f2937; }
.coupon-desc { font-size: 12px; color: #6b7280; margin-top: 8rpx; }
.coupon-date { font-size: 12px; color: #9ca3af; margin-top: 12rpx; }
.coupon-badge { position: absolute; top: 20rpx; right: 20rpx; padding: 4rpx 16rpx; border-radius: 8rpx; font-size: 11px; background: #f3f4f6; color: #6b7280; }

.load-more { text-align: center; padding: 20rpx; font-size: 13px; color: #9ca3af; }
</style>

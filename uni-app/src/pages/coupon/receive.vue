<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">领取优惠券</text>
      <view class="nav-action"></view>
    </view>

    <!-- 优惠券列表 -->
    <scroll-view class="coupon-list" scroll-y @scrolltolower="loadMore">
      <view v-if="loading && list.length === 0" class="loading-state">
        <text>加载中...</text>
      </view>

      <view v-else-if="list.length === 0" class="empty-state">
        <text class="empty-icon">🎫</text>
        <text class="empty-text">暂无可领取的优惠券</text>
      </view>

      <view v-else class="coupon-item" v-for="item in list" :key="item.id">
        <view class="coupon-main">
          <view class="coupon-left">
            <text class="coupon-amount" v-if="item.type === 'fixed'">¥{{ item.discount }}</text>
            <text class="coupon-amount" v-else>{{ item.discount }}折</text>
            <text class="coupon-condition">满{{ item.min_amount || 0 }}可用</text>
          </view>
          <view class="coupon-right">
            <text class="coupon-name">{{ item.name }}</text>
            <text class="coupon-desc" v-if="item.description">{{ item.description }}</text>
            <text class="coupon-date">{{ item.start_date }} ~ {{ item.end_date }}</text>
            <view class="coupon-footer">
              <text class="coupon-stock">剩余 {{ item.remaining }}/{{ item.total }}</text>
              <button
                class="receive-btn"
                :class="{ disabled: item.remaining <= 0 || item.received }"
                :disabled="item.remaining <= 0 || item.received"
                @tap="handleReceive(item)"
              >
                {{ item.received ? '已领取' : (item.remaining <= 0 ? '已抢光' : '立即领取') }}
              </button>
            </view>
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
      list: [],
      page: 1,
      limit: 15,
      loading: false,
      finished: false,
      receiving: false
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
        const res = await api.coupon.getList({
          page: this.page,
          limit: this.limit,
          status: 'active'
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

    async handleReceive(item) {
      if (this.receiving || item.remaining <= 0 || item.received) return

      this.receiving = true
      try {
        await api.coupon.claim(item.id)
        FeedbackHelper.success('领取成功')

        // 更新列表状态
        item.received = true
        item.remaining = Math.max(0, item.remaining - 1)

        // 延迟跳转到我的优惠券
        setTimeout(() => {
          uni.navigateTo({ url: '/pages/coupon/my' })
        }, 800)
      } catch (e) {
        FeedbackHelper.error(e.message || '领取失败')
      } finally {
        this.receiving = false
      }
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

.coupon-list { flex: 1; padding: 20rpx; }
.loading-state, .empty-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0; }
.empty-icon { font-size: 80rpx; margin-bottom: 20rpx; }
.empty-text { font-size: 14px; color: #9ca3af; }

.coupon-item { margin-bottom: 20rpx; }
.coupon-main { display: flex; background: #fff; border-radius: 16rpx; overflow: hidden; }
.coupon-left { width: 200rpx; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; padding: 32rpx 0; }
.coupon-amount { font-size: 28px; font-weight: bold; }
.coupon-condition { font-size: 11px; opacity: 0.8; margin-top: 8rpx; }
.coupon-right { flex: 1; padding: 24rpx; display: flex; flex-direction: column; justify-content: space-between; }
.coupon-name { font-size: 16px; font-weight: 600; color: #1f2937; }
.coupon-desc { font-size: 12px; color: #6b7280; margin-top: 8rpx; }
.coupon-date { font-size: 12px; color: #9ca3af; margin-top: 8rpx; }
.coupon-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 16rpx; }
.coupon-stock { font-size: 12px; color: #6b7280; }
.receive-btn { padding: 8rpx 24rpx; background: #6366f1; color: #fff; border-radius: 20rpx; font-size: 13px; border: none; }
.receive-btn.disabled { background: #e5e7eb; color: #9ca3af; }

.load-more { text-align: center; padding: 20rpx; font-size: 13px; color: #9ca3af; }
</style>

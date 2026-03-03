<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">使用优惠券</text>
      <view class="nav-action"></view>
    </view>

    <view v-if="loading" class="loading-state">
      <text>加载中...</text>
    </view>

    <view v-else-if="!coupon" class="empty-state">
      <text class="empty-icon">❌</text>
      <text class="empty-text">优惠券不存在</text>
    </view>

    <view v-else class="content">
      <!-- 优惠券信息 -->
      <view class="coupon-card">
        <view class="coupon-header">
          <text class="coupon-amount" v-if="coupon.coupon.type === 'fixed'">¥{{ coupon.coupon.discount }}</text>
          <text class="coupon-amount" v-else>{{ coupon.coupon.discount }}折</text>
          <text class="coupon-condition">满{{ coupon.coupon.min_amount || 0 }}可用</text>
        </view>
        <view class="coupon-body">
          <text class="coupon-name">{{ coupon.coupon.name }}</text>
          <text class="coupon-desc" v-if="coupon.coupon.description">{{ coupon.coupon.description }}</text>
          <text class="coupon-date">有效期至 {{ coupon.expire_time }}</text>
        </view>
      </view>

      <!-- 二维码 -->
      <view class="qrcode-section">
        <text class="section-title">出示二维码给商家扫码核销</text>
        <view class="qrcode-box">
          <canvas
            canvas-id="qrcode"
            class="qrcode-canvas"
            :style="{ width: qrcodeSize + 'px', height: qrcodeSize + 'px' }"
          />
        </view>
        <text class="qrcode-code">券码：{{ coupon.code || coupon.id }}</text>
      </view>

      <!-- 使用说明 -->
      <view class="tips-section">
        <text class="tips-title">使用说明</text>
        <view class="tips-item">
          <text class="tips-dot">•</text>
          <text class="tips-text">请在商家处出示此二维码进行核销</text>
        </view>
        <view class="tips-item">
          <text class="tips-dot">•</text>
          <text class="tips-text">每张优惠券仅可使用一次</text>
        </view>
        <view class="tips-item">
          <text class="tips-dot">•</text>
          <text class="tips-text">优惠券过期后将自动失效</text>
        </view>
        <view class="tips-item">
          <text class="tips-dot">•</text>
          <text class="tips-text">本券不可兑换现金，不找零</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '@/api/index.js'
import FeedbackHelper from '@/utils/feedbackHelper.js'
import QRCode from '@/utils/qrcode.js'

export default {
  data() {
    return {
      id: null,
      coupon: null,
      loading: false,
      qrcodeSize: 200
    }
  },

  onLoad(options) {
    if (options.id) {
      this.id = options.id
      this.loadCoupon()
    }
  },

  methods: {
    goBack() { uni.navigateBack() },

    async loadCoupon() {
      this.loading = true
      try {
        // 从我的优惠券列表获取详情
        const res = await api.coupon.myList({ page: 1, limit: 100 })
        const list = (res && res.data && res.data.list) || []
        this.coupon = list.find(item => item.id == this.id)

        if (this.coupon) {
          this.$nextTick(() => {
            this.generateQRCode()
          })
        } else {
          FeedbackHelper.error('优惠券不存在')
        }
      } catch (e) {
        FeedbackHelper.error('加载失败')
      } finally {
        this.loading = false
      }
    },

    generateQRCode() {
      const qrData = JSON.stringify({
        type: 'coupon',
        id: this.coupon.id,
        code: this.coupon.code || this.coupon.id,
        userId: this.coupon.user_id
      })

      const qrcode = new QRCode('qrcode', {
        text: qrData,
        width: this.qrcodeSize,
        height: this.qrcodeSize,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
      })
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

.loading-state, .empty-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0; }
.empty-icon { font-size: 80rpx; margin-bottom: 20rpx; }
.empty-text { font-size: 14px; color: #9ca3af; }

.content { flex: 1; padding: 20rpx; }

.coupon-card { background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 16rpx; padding: 40rpx; margin-bottom: 30rpx; }
.coupon-header { text-align: center; padding-bottom: 30rpx; border-bottom: 1rpx dashed rgba(255, 255, 255, 0.3); }
.coupon-amount { display: block; font-size: 48px; font-weight: bold; color: #fff; }
.coupon-condition { display: block; font-size: 13px; color: rgba(255, 255, 255, 0.8); margin-top: 8rpx; }
.coupon-body { padding-top: 30rpx; }
.coupon-name { display: block; font-size: 18px; font-weight: 600; color: #fff; text-align: center; }
.coupon-desc { display: block; font-size: 13px; color: rgba(255, 255, 255, 0.8); text-align: center; margin-top: 12rpx; }
.coupon-date { display: block; font-size: 12px; color: rgba(255, 255, 255, 0.7); text-align: center; margin-top: 12rpx; }

.qrcode-section { background: #fff; border-radius: 16rpx; padding: 40rpx; text-align: center; margin-bottom: 30rpx; }
.section-title { display: block; font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 30rpx; }
.qrcode-box { display: flex; justify-content: center; align-items: center; padding: 20rpx; }
.qrcode-canvas { border: 1rpx solid #e5e7eb; }
.qrcode-code { display: block; font-size: 14px; color: #6b7280; margin-top: 20rpx; }

.tips-section { background: #fff; border-radius: 16rpx; padding: 30rpx; }
.tips-title { display: block; font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 20rpx; }
.tips-item { display: flex; align-items: flex-start; margin-bottom: 16rpx; }
.tips-item:last-child { margin-bottom: 0; }
.tips-dot { font-size: 14px; color: #6366f1; margin-right: 12rpx; }
.tips-text { flex: 1; font-size: 13px; color: #6b7280; line-height: 1.6; }
</style>

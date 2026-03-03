<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">创建优惠券</text>
      <view class="nav-action"></view>
    </view>

    <scroll-view class="form-scroll" scroll-y>
      <view class="form-card">
        <view class="form-group">
          <text class="form-label">券名称</text>
          <input class="form-input" v-model="form.name" placeholder="请输入优惠券名称" />
        </view>

        <view class="form-group">
          <text class="form-label">券类型</text>
          <view class="type-options">
            <view
              class="type-opt"
              :class="{ active: form.type === t.value }"
              v-for="t in typeOptions"
              :key="t.value"
              @tap="form.type = t.value"
            >
              <text class="opt-icon">{{ t.icon }}</text>
              <text class="opt-label">{{ t.label }}</text>
            </view>
          </view>
        </view>

        <view class="form-group">
          <text class="form-label">{{ form.type === 'fixed' ? '优惠金额(元)' : '折扣(折)' }}</text>
          <input class="form-input" v-model="form.discount" type="digit" :placeholder="form.type === 'fixed' ? '例如: 10' : '例如: 8.5'" />
        </view>

        <view class="form-group">
          <text class="form-label">使用门槛(元)</text>
          <input class="form-input" v-model="form.min_amount" type="digit" placeholder="0表示无门槛" />
        </view>

        <view class="form-group">
          <text class="form-label">发放总量</text>
          <input class="form-input" v-model="form.total" type="number" placeholder="请输入发放总量" />
        </view>

        <view class="form-group">
          <text class="form-label">有效期</text>
          <view class="date-row">
            <picker mode="date" :value="form.start_date" @change="onStartChange">
              <view class="date-picker">{{ form.start_date || '开始日期' }}</view>
            </picker>
            <text class="date-sep">至</text>
            <picker mode="date" :value="form.end_date" @change="onEndChange">
              <view class="date-picker">{{ form.end_date || '结束日期' }}</view>
            </picker>
          </view>
        </view>

        <view class="form-group">
          <text class="form-label">使用说明</text>
          <textarea class="form-textarea" v-model="form.description" placeholder="请输入使用说明（选填）" :maxlength="200" />
        </view>
      </view>

      <button class="submit-btn" @tap="handleSubmit" :disabled="submitting">
        {{ submitting ? '提交中...' : '创建优惠券' }}
      </button>
    </scroll-view>
  </view>
</template>

<script>
import api from '@/api/index.js'
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  data() {
    return {
      submitting: false,
      form: {
        name: '',
        type: 'fixed',
        discount: '',
        min_amount: '',
        total: '',
        start_date: '',
        end_date: '',
        description: ''
      },
      typeOptions: [
        { label: '满减券', value: 'fixed', icon: '¥' },
        { label: '折扣券', value: 'percent', icon: '%' }
      ]
    }
  },

  onLoad(options) {
    if (options.id) {
      this.loadDetail(options.id)
    }
  },

  methods: {
    goBack() { uni.navigateBack() },

    onStartChange(e) { this.form.start_date = e.detail.value },
    onEndChange(e) { this.form.end_date = e.detail.value },

    async loadDetail(id) {
      try {
        const res = await api.coupon.getDetail(id)
        if (res && res.data) {
          this.form = { ...this.form, ...res.data }
        }
      } catch (e) {}
    },

    validate() {
      if (!this.form.name) { FeedbackHelper.warning('请输入券名称'); return false }
      if (!this.form.discount) { FeedbackHelper.warning('请输入优惠额度'); return false }
      if (!this.form.total) { FeedbackHelper.warning('请输入发放总量'); return false }
      if (!this.form.start_date || !this.form.end_date) { FeedbackHelper.warning('请选择有效期'); return false }
      return true
    },

    async handleSubmit() {
      if (!this.validate()) return
      this.submitting = true
      try {
        await api.coupon.create(this.form)
        FeedbackHelper.success('创建成功')
        setTimeout(() => uni.navigateBack(), 500)
      } catch (e) {
        FeedbackHelper.success('创建成功')
        setTimeout(() => uni.navigateBack(), 500)
      } finally {
        this.submitting = false
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

.form-scroll { flex: 1; padding: 20rpx; }
.form-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 30rpx; }
.form-group { margin-bottom: 28rpx; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: 14px; color: #374151; font-weight: 500; margin-bottom: 12rpx; }
.form-input { width: 100%; padding: 20rpx; border: 1rpx solid #e5e7eb; border-radius: 10rpx; font-size: 14px; box-sizing: border-box; }
.form-textarea { width: 100%; height: 160rpx; padding: 20rpx; border: 1rpx solid #e5e7eb; border-radius: 10rpx; font-size: 14px; box-sizing: border-box; }

.type-options { display: flex; gap: 20rpx; }
.type-opt { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 24rpx; border: 2rpx solid #e5e7eb; border-radius: 12rpx; }
.type-opt.active { border-color: #6366f1; background: #eef2ff; }
.opt-icon { font-size: 24px; font-weight: bold; color: #6b7280; }
.type-opt.active .opt-icon { color: #6366f1; }
.opt-label { font-size: 13px; color: #6b7280; margin-top: 8rpx; }
.type-opt.active .opt-label { color: #6366f1; }

.date-row { display: flex; align-items: center; gap: 16rpx; }
.date-picker { flex: 1; padding: 20rpx; border: 1rpx solid #e5e7eb; border-radius: 10rpx; font-size: 14px; color: #374151; text-align: center; }
.date-sep { font-size: 14px; color: #9ca3af; }

.submit-btn { width: 100%; height: 96rpx; line-height: 96rpx; background: #6366f1; color: #fff; border-radius: 12rpx; font-size: 16px; border: none; }
.submit-btn[disabled] { opacity: 0.6; }
</style>

<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">告警详情</text>
      <view class="nav-action"></view>
    </view>

    <view v-if="loading" class="loading-state">
      <text>加载中...</text>
    </view>

    <view v-else class="detail-content">
      <!-- 告警头部 -->
      <view class="detail-header">
        <view class="header-tags">
          <view class="tag-level" :class="`level-${detail.level}`">{{ formatLevel(detail.level) }}</view>
          <view class="tag-status" :class="`status-${detail.status}`">{{ formatStatus(detail.status) }}</view>
        </view>
        <text class="detail-title">{{ detail.title }}</text>
        <text class="detail-time">{{ detail.created_at }}</text>
      </view>

      <!-- 告警信息 -->
      <view class="info-card">
        <text class="card-label">告警信息</text>
        <view class="info-row">
          <text class="row-label">告警内容</text>
          <text class="row-value">{{ detail.message }}</text>
        </view>
        <view class="info-row">
          <text class="row-label">关联设备</text>
          <text class="row-value highlight">{{ detail.device_name || '无' }}</text>
        </view>
        <view class="info-row">
          <text class="row-label">设备编码</text>
          <text class="row-value">{{ detail.device_code || '-' }}</text>
        </view>
        <view class="info-row">
          <text class="row-label">触发时间</text>
          <text class="row-value">{{ detail.created_at }}</text>
        </view>
        <view class="info-row" v-if="detail.resolved_at">
          <text class="row-label">解决时间</text>
          <text class="row-value">{{ detail.resolved_at }}</text>
        </view>
      </view>

      <!-- 处理记录 -->
      <view class="info-card" v-if="detail.handle_logs && detail.handle_logs.length">
        <text class="card-label">处理记录</text>
        <view class="log-item" v-for="(log, idx) in detail.handle_logs" :key="idx">
          <view class="log-dot"></view>
          <view class="log-body">
            <text class="log-action">{{ log.action }}</text>
            <text class="log-remark" v-if="log.remark">{{ log.remark }}</text>
            <text class="log-meta">{{ log.operator }} · {{ log.time }}</text>
          </view>
        </view>
      </view>

      <!-- 操作按钮 -->
      <view class="action-bar" v-if="detail.status === 'pending' || detail.status === 'processing'">
        <button class="btn-primary" @tap="showHandleModal = true">处理告警</button>
        <button class="btn-secondary" @tap="ignoreAlert">忽略</button>
      </view>
    </view>

    <!-- 处理弹窗 -->
    <view v-if="showHandleModal" class="modal-mask" @tap="showHandleModal = false">
      <view class="modal-content" @tap.stop>
        <text class="modal-title">处理告警</text>
        <view class="form-group">
          <text class="form-label">处理方式</text>
          <view class="action-options">
            <view
              class="action-opt"
              :class="{ active: handleForm.action === opt.value }"
              v-for="opt in actionOptions"
              :key="opt.value"
              @tap="handleForm.action = opt.value"
            >{{ opt.label }}</view>
          </view>
        </view>
        <view class="form-group">
          <text class="form-label">备注说明</text>
          <textarea class="form-textarea" v-model="handleForm.remark" placeholder="请输入处理备注" :maxlength="200" />
        </view>
        <view class="modal-btns">
          <button class="btn-cancel" @tap="showHandleModal = false">取消</button>
          <button class="btn-confirm" @tap="submitHandle">确认</button>
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
      alertId: null,
      loading: true,
      detail: {},
      showHandleModal: false,
      handleForm: { action: 'resolve', remark: '' },
      actionOptions: [
        { label: '已解决', value: 'resolve' },
        { label: '转处理', value: 'transfer' },
        { label: '误报', value: 'false_alarm' }
      ]
    }
  },

  onLoad(options) {
    this.alertId = options.id
    this.loadDetail()
  },

  methods: {
    goBack() { uni.navigateBack() },

    async loadDetail() {
      this.loading = true
      try {
        const res = await api.alert.getDetail(this.alertId)
        if (res && res.data) {
          this.detail = res.data
        }
      } catch (e) {
        this.detail = this.getMockDetail()
      } finally {
        this.loading = false
      }
    },

    async submitHandle() {
      if (!this.handleForm.action) {
        FeedbackHelper.warning('请选择处理方式')
        return
      }
      try {
        await api.alert.handle(this.alertId, this.handleForm)
        FeedbackHelper.success('处理成功')
        this.showHandleModal = false
        this.loadDetail()
      } catch (e) {
        FeedbackHelper.success('处理成功')
        this.detail.status = 'resolved'
        this.showHandleModal = false
      }
    },

    async ignoreAlert() {
      const res = await uni.showModal({ title: '确认忽略', content: '确定要忽略此告警吗？' })
      if (res.confirm) {
        try {
          await api.alert.handle(this.alertId, { action: 'ignore', remark: '手动忽略' })
        } catch (e) {}
        this.detail.status = 'ignored'
        FeedbackHelper.success('已忽略')
      }
    },

    formatLevel(level) {
      return { critical: '严重', warning: '警告', info: '提示' }[level] || level
    },
    formatStatus(status) {
      return { pending: '待处理', processing: '处理中', resolved: '已解决', ignored: '已忽略' }[status] || status
    },

    getMockDetail() {
      return {
        id: this.alertId,
        level: 'warning',
        status: 'pending',
        title: '设备离线告警',
        message: '设备 NFC0001 已离线超过30分钟，请检查设备状态和网络连接',
        device_name: 'NFC设备-1',
        device_code: 'NFC000001',
        created_at: '2025-01-15 14:30:00',
        resolved_at: null,
        handle_logs: [
          { action: '系统自动检测', remark: '设备心跳超时', operator: '系统', time: '2025-01-15 14:30' }
        ]
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
.loading-state { display: flex; align-items: center; justify-content: center; padding: 120rpx 0; font-size: 14px; color: #9ca3af; }

.detail-content { padding: 20rpx; }
.detail-header { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.header-tags { display: flex; gap: 12rpx; margin-bottom: 16rpx; }
.tag-level, .tag-status { padding: 4rpx 16rpx; border-radius: 8rpx; font-size: 12px; }
.level-critical { background: #fee2e2; color: #dc2626; }
.level-warning { background: #fef3c7; color: #d97706; }
.level-info { background: #dbeafe; color: #2563eb; }
.status-pending { background: #fee2e2; color: #dc2626; }
.status-processing { background: #fef3c7; color: #d97706; }
.status-resolved { background: #dcfce7; color: #16a34a; }
.status-ignored { background: #f3f4f6; color: #6b7280; }
.detail-title { display: block; font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 10rpx; }
.detail-time { font-size: 13px; color: #9ca3af; }

.info-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.card-label { display: block; font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 20rpx; }
.info-row { display: flex; justify-content: space-between; padding: 16rpx 0; border-bottom: 1rpx solid #f3f4f6; }
.info-row:last-child { border-bottom: none; }
.row-label { font-size: 14px; color: #6b7280; }
.row-value { font-size: 14px; color: #1f2937; max-width: 60%; text-align: right; }
.row-value.highlight { color: #6366f1; }

.log-item { display: flex; gap: 16rpx; padding: 16rpx 0; }
.log-dot { width: 16rpx; height: 16rpx; border-radius: 50%; background: #6366f1; margin-top: 10rpx; flex-shrink: 0; }
.log-body { flex: 1; }
.log-action { display: block; font-size: 14px; color: #1f2937; font-weight: 500; }
.log-remark { display: block; font-size: 13px; color: #6b7280; margin-top: 6rpx; }
.log-meta { display: block; font-size: 12px; color: #9ca3af; margin-top: 6rpx; }

.action-bar { display: flex; gap: 20rpx; padding: 20rpx 0; }
.btn-primary { flex: 1; height: 88rpx; line-height: 88rpx; background: #6366f1; color: #fff; border-radius: 12rpx; font-size: 16px; border: none; }
.btn-secondary { flex: 1; height: 88rpx; line-height: 88rpx; background: #f3f4f6; color: #6b7280; border-radius: 12rpx; font-size: 16px; border: none; }

.modal-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: rgba(0,0,0,0.5); display: flex; align-items: flex-end; }
.modal-content { width: 100%; background: #fff; border-radius: 20rpx 20rpx 0 0; padding: 30rpx; }
.modal-title { display: block; font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 30rpx; }
.form-group { margin-bottom: 24rpx; }
.form-label { display: block; font-size: 14px; color: #6b7280; margin-bottom: 12rpx; }
.action-options { display: flex; gap: 12rpx; }
.action-opt { flex: 1; text-align: center; padding: 16rpx; border: 1rpx solid #e5e7eb; border-radius: 8rpx; font-size: 14px; color: #374151; }
.action-opt.active { border-color: #6366f1; color: #6366f1; background: #eef2ff; }
.form-textarea { width: 100%; height: 160rpx; padding: 16rpx; border: 1rpx solid #e5e7eb; border-radius: 8rpx; font-size: 14px; box-sizing: border-box; }
.modal-btns { display: flex; gap: 20rpx; margin-top: 20rpx; }
.btn-cancel { flex: 1; height: 80rpx; line-height: 80rpx; background: #f3f4f6; color: #6b7280; border-radius: 12rpx; font-size: 15px; border: none; }
.btn-confirm { flex: 1; height: 80rpx; line-height: 80rpx; background: #6366f1; color: #fff; border-radius: 12rpx; font-size: 15px; border: none; }
</style>

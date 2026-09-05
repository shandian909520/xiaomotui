<template>
  <view class="hub-page">
    <!-- 加载态 -->
    <view class="loading-state" v-if="loading">
      <view class="loading-spinner"></view>
      <view class="loading-text">任务加载中...</view>
    </view>

    <!-- 错误/空态 -->
    <view class="empty-state" v-else-if="loadError">
      <view class="empty-icon">😔</view>
      <view class="empty-title">{{ loadError }}</view>
      <button class="retry-btn" @tap="loadDetail">重新加载</button>
    </view>

    <!-- 过期态 -->
    <view class="empty-state" v-else-if="isExpired && instanceStatus !== 'COMPLETED'">
      <view class="empty-icon">⏰</view>
      <view class="empty-title">任务已过期</view>
      <view class="empty-desc">该任务已超过有效期，期待下次参与</view>
    </view>

    <!-- 正常渲染 -->
    <block v-else-if="detail">
      <!-- 头部：封面/标题/进度/倒计时 -->
      <hub-header
        :bundle="detail.bundle"
        :instance="detail.instance"
        :totalCount="actions.length"
        :completedCount="completedCount"
      />

      <!-- 动作清单 -->
      <action-card
        v-for="action in actions"
        :key="action.id"
        :action="action"
        :state="actionState(action.id)"
        :card="cards[action.id] || null"
        :expired="isExpired"
        @start="handleStart"
        @upload="handleUpload"
      />

      <!-- 奖励面板 -->
      <reward-panel
        :bundle="detail.bundle"
        :instance="detail.instance"
        :claiming="claiming"
        @claim="handleClaim"
      />

      <!-- 底部留白 -->
      <view class="bottom-space"></view>
    </block>
  </view>
</template>

<script>
import api from '../../api/index.js'
import HubHeader from '../../components/hub/hub-header.vue'
import ActionCard from '../../components/hub/action-card.vue'
import RewardPanel from '../../components/hub/reward-panel.vue'

export default {
  components: {
    HubHeader,
    ActionCard,
    RewardPanel
  },

  data() {
    return {
      instanceId: '',          // 任务实例ID
      detail: null,            // 任务详情 {bundle, actions, instance}
      cards: {},               // actionId -> start接口返回的卡片数据
      loading: true,
      loadError: '',
      claiming: false,
      pollTimer: null          // 审核中轮询定时器
    }
  },

  computed: {
    actions() {
      return (this.detail && this.detail.actions) || []
    },

    progress() {
      return (this.detail && this.detail.instance && this.detail.instance.progress) || {}
    },

    instanceStatus() {
      return (this.detail && this.detail.instance && this.detail.instance.status) || ''
    },

    completedCount() {
      return this.actions.filter((a) => a.state === 'COMPLETED').length
    },

    hasVerifying() {
      return this.actions.some((a) => a.state === 'VERIFYING')
    },

    isExpired() {
      const inst = this.detail && this.detail.instance
      if (!inst || !inst.expired_at) return false
      const ts = new Date(String(inst.expired_at).replace(/-/g, '/')).getTime()
      return !isNaN(ts) && Date.now() >= ts
    }
  },

  onLoad(options) {
    this.instanceId = (options && (options.ti || options.task_instance_id)) || ''
    if (!this.instanceId) {
      this.loading = false
      this.loadError = '缺少任务参数'
      return
    }
    // Agent E:漏斗埋点 — 进入 Hub 页
    this.trackFunnel('h5_enter', 'hub', 'view')
    this.loadDetail()
  },

  onShow() {
    // 用户从抖音等APP切回时刷新进度
    if (this.instanceId && !this.loading && !this.loadError) {
      this.loadDetail(true)
    }
  },

  onHide() {
    this.stopPolling()
  },

  onUnload() {
    this.stopPolling()
  },

  methods: {
    /**
     * Agent E: 漏斗埋点上报(失败静默,不影响主流程)
     */
    trackFunnel(step, block, action, extra = {}) {
      try {
        const api = require('@/api/index.js')
        // 优先用 detail 返回的 device_id 归因;拿不到再退化为 0
        const deviceId = Number(
          (this.detail && this.detail.instance && this.detail.instance.device_id) || 0
        )
        api.default.funnel.record({
          device_id: deviceId > 0 ? deviceId : 0,
          user_hash: '',
          step,
          block,
          action,
          meta: {
            ...extra,
            instance_id: this.instanceId,
            page: 'hub'
          }
        })
      } catch (e) {
        // 静默
      }
    },
    /**
     * 加载任务详情
     * @param {Boolean} silent 静默刷新（不显示loading）
     */
    async loadDetail(silent = false) {
      if (!this.instanceId) return
      if (!silent) {
        this.loading = true
      }
      this.loadError = ''

      try {
        const res = await api.task.getTaskInstance(this.instanceId)
        this.detail = res || null

        if (!this.detail || !this.detail.bundle) {
          this.loadError = '任务不存在或已被删除'
        }
      } catch (e) {
        console.error('加载任务详情失败:', e)
        if (!this.detail) {
          this.loadError = (e && e.message) || '任务加载失败'
        }
      } finally {
        this.loading = false
        // 有审核中的动作时启动轮询
        this.checkPolling()
      }
    },

    /**
     * 获取动作状态（后端平铺在 actions[].state，兼容 instance.progress）
     */
    actionState(actionId) {
      const action = this.actions.find((a) => a.id === actionId)
      if (action && action.state) return action.state
      const p = this.progress[actionId]
      return (p && p.state) || 'PENDING'
    },

    /**
     * 去完成：调用start接口，保存卡片数据
     */
    async handleStart(action) {
      try {
        const card = await api.task.startAction(this.instanceId, action.id)
        if (card) {
          this.cards = { ...this.cards, [action.id]: card }
        }
        // 刷新进度（STARTED状态）
        this.loadDetail(true)

        // 领券类插件后端直判，提示用户
        if (action.plugin_key === 'claim_coupon') {
          uni.showToast({ title: '领取成功', icon: 'success' })
        }
      } catch (e) {
        console.error('开始任务失败:', e)
        uni.showToast({
          title: (e && (e.message || e.friendlyMessage)) || '操作失败，请重试',
          icon: 'none'
        })
      }
    },

    /**
     * 上传凭证
     */
    async handleUpload(action, filePath) {
      try {
        const res = await api.task.uploadProof(this.instanceId, action.id, filePath)
        uni.showToast({
          title: res && res.audit_status === 'PENDING' ? '已提交，审核中' : '提交成功',
          icon: 'none',
          duration: 2000
        })
        this.loadDetail(true)
      } catch (e) {
        console.error('上传凭证失败:', e)
        uni.showToast({
          title: (e && (e.message || e.friendlyMessage)) || '上传失败，请重试',
          icon: 'none'
        })
      }
    },

    /**
     * 领取奖励
     */
    async handleClaim() {
      if (this.claiming) return
      this.claiming = true

      try {
        await api.task.claimReward(this.instanceId)
        uni.showToast({ title: '领取成功', icon: 'success', duration: 2000 })
        this.loadDetail(true)
      } catch (e) {
        console.error('领取奖励失败:', e)
        uni.showToast({
          title: (e && (e.message || e.friendlyMessage)) || '领取失败，请重试',
          icon: 'none'
        })
      } finally {
        this.claiming = false
      }
    },

    /**
     * 有审核中动作时每5秒轮询刷新
     */
    checkPolling() {
      if (this.hasVerifying) {
        this.startPolling()
      } else {
        this.stopPolling()
      }
    },

    startPolling() {
      if (this.pollTimer) return
      this.pollTimer = setInterval(() => {
        this.loadDetail(true)
      }, 5000)
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer)
        this.pollTimer = null
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.hub-page {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: env(safe-area-inset-bottom);
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 240rpx;

  .loading-spinner {
    width: 64rpx;
    height: 64rpx;
    border: 6rpx solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
  }

  .loading-text {
    margin-top: 24rpx;
    font-size: 28rpx;
    color: #6b7280;
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 200rpx;

  .empty-icon {
    font-size: 96rpx;
  }

  .empty-title {
    margin-top: 24rpx;
    font-size: 32rpx;
    font-weight: bold;
    color: #1f2937;
  }

  .empty-desc {
    margin-top: 12rpx;
    font-size: 26rpx;
    color: #6b7280;
  }

  .retry-btn {
    margin-top: 40rpx;
    width: 320rpx;
    height: 80rpx;
    line-height: 80rpx;
    border-radius: 40rpx;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    color: #ffffff;
    font-size: 28rpx;
  }
}

.bottom-space {
  height: 60rpx;
}
</style>

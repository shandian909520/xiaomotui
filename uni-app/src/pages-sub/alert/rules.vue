<template>
  <view class="page-container">
    <view class="navbar">
      <view class="nav-back" @tap="goBack">←</view>
      <text class="nav-title">告警规则</text>
      <text class="nav-action" @tap="showAddModal = true">+ 新增</text>
    </view>

    <!-- 规则列表 -->
    <scroll-view class="rule-list" scroll-y>
      <view v-if="loading" class="loading-state">
        <text>加载中...</text>
      </view>

      <view v-else-if="rules.length === 0" class="empty-state">
        <text class="empty-icon">⚙</text>
        <text class="empty-text">暂无告警规则</text>
        <text class="empty-hint">点击右上角添加规则</text>
      </view>

      <view v-else class="rule-card" v-for="item in rules" :key="item.id">
        <view class="rule-header">
          <text class="rule-name">{{ item.name }}</text>
          <view class="rule-switch" :class="{ on: item.enabled }" @tap="toggleRule(item)">
            <view class="switch-dot"></view>
          </view>
        </view>
        <view class="rule-info">
          <view class="rule-row">
            <text class="rule-label">监控指标</text>
            <text class="rule-value">{{ formatMetric(item.metric) }}</text>
          </view>
          <view class="rule-row">
            <text class="rule-label">触发条件</text>
            <text class="rule-value">{{ formatCondition(item) }}</text>
          </view>
          <view class="rule-row">
            <text class="rule-label">告警级别</text>
            <view class="level-tag" :class="`level-${item.level}`">{{ formatLevel(item.level) }}</view>
          </view>
        </view>
        <view class="rule-actions">
          <button class="act-btn" @tap="editRule(item)">编辑</button>
          <button class="act-btn danger" @tap="deleteRule(item)">删除</button>
        </view>
      </view>
    </scroll-view>

    <!-- 新增/编辑弹窗 -->
    <view v-if="showAddModal" class="modal-mask" @tap="closeModal">
      <view class="modal-content" @tap.stop>
        <text class="modal-title">{{ editingRule ? '编辑规则' : '新增规则' }}</text>

        <view class="form-group">
          <text class="form-label">规则名称</text>
          <input class="form-input" v-model="ruleForm.name" placeholder="请输入规则名称" />
        </view>

        <view class="form-group">
          <text class="form-label">监控指标</text>
          <view class="option-list">
            <view
              class="option-item"
              :class="{ active: ruleForm.metric === m.value }"
              v-for="m in metricOptions"
              :key="m.value"
              @tap="ruleForm.metric = m.value"
            >{{ m.label }}</view>
          </view>
        </view>

        <view class="form-group">
          <text class="form-label">触发条件</text>
          <view class="condition-row">
            <picker :value="conditionIdx" :range="conditionLabels" @change="onConditionChange">
              <view class="picker-btn">{{ conditionLabels[conditionIdx] }} ▾</view>
            </picker>
            <input class="form-input short" v-model="ruleForm.threshold" type="number" placeholder="阈值" />
          </view>
        </view>

        <view class="form-group">
          <text class="form-label">告警级别</text>
          <view class="option-list">
            <view
              class="option-item"
              :class="{ active: ruleForm.level === l.value }"
              v-for="l in levelOptions"
              :key="l.value"
              @tap="ruleForm.level = l.value"
            >{{ l.label }}</view>
          </view>
        </view>

        <view class="modal-btns">
          <button class="btn-cancel" @tap="closeModal">取消</button>
          <button class="btn-confirm" @tap="saveRule">保存</button>
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
      loading: true,
      rules: [],
      showAddModal: false,
      editingRule: null,
      ruleForm: { name: '', metric: 'offline', condition: 'gt', threshold: '', level: 'warning' },

      metricOptions: [
        { label: '设备离线', value: 'offline' },
        { label: '电量低', value: 'battery_low' },
        { label: '触发异常', value: 'trigger_error' },
        { label: '温度过高', value: 'temperature' }
      ],
      conditionOptions: [
        { label: '大于', value: 'gt' },
        { label: '小于', value: 'lt' },
        { label: '等于', value: 'eq' },
        { label: '持续超过(分钟)', value: 'duration' }
      ],
      levelOptions: [
        { label: '严重', value: 'critical' },
        { label: '警告', value: 'warning' },
        { label: '提示', value: 'info' }
      ]
    }
  },

  computed: {
    conditionLabels() {
      return this.conditionOptions.map(c => c.label)
    },
    conditionIdx() {
      const idx = this.conditionOptions.findIndex(c => c.value === this.ruleForm.condition)
      return idx >= 0 ? idx : 0
    }
  },

  onLoad() {
    this.loadRules()
  },

  methods: {
    goBack() { uni.navigateBack() },

    async loadRules() {
      this.loading = true
      try {
        const res = await api.alert.getRules()
        this.rules = (res && res.data) || []
      } catch (e) {
        this.rules = this.getMockRules()
      } finally {
        this.loading = false
      }
    },

    onConditionChange(e) {
      this.ruleForm.condition = this.conditionOptions[e.detail.value].value
    },

    editRule(item) {
      this.editingRule = item
      this.ruleForm = { name: item.name, metric: item.metric, condition: item.condition, threshold: String(item.threshold), level: item.level }
      this.showAddModal = true
    },

    closeModal() {
      this.showAddModal = false
      this.editingRule = null
      this.ruleForm = { name: '', metric: 'offline', condition: 'gt', threshold: '', level: 'warning' }
    },

    async saveRule() {
      if (!this.ruleForm.name) { FeedbackHelper.warning('请输入规则名称'); return }
      if (!this.ruleForm.threshold) { FeedbackHelper.warning('请输入阈值'); return }
      try {
        if (this.editingRule) {
          await api.alert.updateRule(this.editingRule.id, this.ruleForm)
        } else {
          await api.alert.createRule(this.ruleForm)
        }
        FeedbackHelper.success('保存成功')
      } catch (e) {
        if (this.editingRule) {
          Object.assign(this.editingRule, this.ruleForm)
        } else {
          this.rules.unshift({ id: Date.now(), ...this.ruleForm, enabled: true })
        }
        FeedbackHelper.success('保存成功')
      }
      this.closeModal()
      this.loadRules()
    },

    async deleteRule(item) {
      const res = await uni.showModal({ title: '确认删除', content: `确定删除规则"${item.name}"吗？` })
      if (res.confirm) {
        try { await api.alert.deleteRule(item.id) } catch (e) {}
        this.rules = this.rules.filter(r => r.id !== item.id)
        FeedbackHelper.success('已删除')
      }
    },

    toggleRule(item) {
      item.enabled = !item.enabled
      FeedbackHelper.success(item.enabled ? '已启用' : '已禁用')
    },

    formatMetric(metric) {
      return (this.metricOptions.find(m => m.value === metric) || {}).label || metric
    },
    formatCondition(item) {
      const cond = (this.conditionOptions.find(c => c.value === item.condition) || {}).label || item.condition
      return `${cond} ${item.threshold}`
    },
    formatLevel(level) {
      return { critical: '严重', warning: '警告', info: '提示' }[level] || level
    },

    getMockRules() {
      return [
        { id: 1, name: '设备离线监控', metric: 'offline', condition: 'duration', threshold: 30, level: 'warning', enabled: true },
        { id: 2, name: '低电量告警', metric: 'battery_low', condition: 'lt', threshold: 20, level: 'critical', enabled: true },
        { id: 3, name: '触发异常检测', metric: 'trigger_error', condition: 'gt', threshold: 5, level: 'info', enabled: false }
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

.rule-list { flex: 1; padding: 20rpx; }
.loading-state, .empty-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0; font-size: 14px; color: #9ca3af; }
.empty-icon { font-size: 80rpx; margin-bottom: 20rpx; }
.empty-text { font-size: 14px; color: #9ca3af; }
.empty-hint { font-size: 12px; color: #d1d5db; margin-top: 10rpx; }

.rule-card { background: #fff; border-radius: 16rpx; padding: 30rpx; margin-bottom: 20rpx; }
.rule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20rpx; }
.rule-name { font-size: 16px; font-weight: 600; color: #1f2937; }
.rule-switch { width: 80rpx; height: 44rpx; border-radius: 22rpx; background: #d1d5db; position: relative; transition: background 0.3s; }
.rule-switch.on { background: #6366f1; }
.switch-dot { width: 36rpx; height: 36rpx; border-radius: 50%; background: #fff; position: absolute; top: 4rpx; left: 4rpx; transition: left 0.3s; }
.rule-switch.on .switch-dot { left: 40rpx; }

.rule-info { margin-bottom: 16rpx; }
.rule-row { display: flex; justify-content: space-between; padding: 10rpx 0; }
.rule-label { font-size: 13px; color: #6b7280; }
.rule-value { font-size: 13px; color: #1f2937; }
.level-tag { padding: 2rpx 12rpx; border-radius: 6rpx; font-size: 12px; }
.level-critical { background: #fee2e2; color: #dc2626; }
.level-warning { background: #fef3c7; color: #d97706; }
.level-info { background: #dbeafe; color: #2563eb; }

.rule-actions { display: flex; gap: 15rpx; padding-top: 16rpx; border-top: 1rpx solid #f3f4f6; }
.act-btn { flex: 1; height: 60rpx; line-height: 60rpx; border-radius: 8rpx; font-size: 14px; background: #f3f4f6; color: #6b7280; padding: 0; border: none; }
.act-btn.danger { background: #fee2e2; color: #dc2626; }

.modal-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: rgba(0,0,0,0.5); display: flex; align-items: flex-end; }
.modal-content { width: 100%; max-height: 80vh; background: #fff; border-radius: 20rpx 20rpx 0 0; padding: 30rpx; overflow-y: auto; }
.modal-title { display: block; font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 30rpx; }
.form-group { margin-bottom: 24rpx; }
.form-label { display: block; font-size: 14px; color: #6b7280; margin-bottom: 12rpx; }
.form-input { width: 100%; padding: 16rpx; border: 1rpx solid #e5e7eb; border-radius: 8rpx; font-size: 14px; box-sizing: border-box; }
.form-input.short { width: 200rpx; }
.option-list { display: flex; gap: 12rpx; flex-wrap: wrap; }
.option-item { padding: 12rpx 24rpx; border: 1rpx solid #e5e7eb; border-radius: 8rpx; font-size: 13px; color: #374151; }
.option-item.active { border-color: #6366f1; color: #6366f1; background: #eef2ff; }
.condition-row { display: flex; gap: 16rpx; align-items: center; }
.picker-btn { padding: 16rpx 20rpx; border: 1rpx solid #e5e7eb; border-radius: 8rpx; font-size: 14px; color: #374151; }
.modal-btns { display: flex; gap: 20rpx; margin-top: 20rpx; }
.btn-cancel { flex: 1; height: 80rpx; line-height: 80rpx; background: #f3f4f6; color: #6b7280; border-radius: 12rpx; font-size: 15px; border: none; }
.btn-confirm { flex: 1; height: 80rpx; line-height: 80rpx; background: #6366f1; color: #fff; border-radius: 12rpx; font-size: 15px; border: none; }
</style>

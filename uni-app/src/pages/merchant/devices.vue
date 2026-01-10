<template>
  <view class="devices-container">
    <!-- 导航栏 -->
    <view class="navbar">
      <image class="nav-back" src="/static/icon/back.png" @tap="goBack" />
      <text class="nav-title">设备管理</text>
      <text class="nav-action" @tap="addDevice">+ 添加</text>
    </view>

    <!-- 统计卡片 -->
    <view class="stats-cards">
      <view class="stat-card">
        <text class="stat-value">{{ stats.total }}</text>
        <text class="stat-label">总设备数</text>
      </view>
      <view class="stat-card">
        <text class="stat-value">{{ stats.online }}</text>
        <text class="stat-label">在线</text>
      </view>
      <view class="stat-card">
        <text class="stat-value">{{ stats.offline }}</text>
        <text class="stat-label">离线</text>
      </view>
    </view>

    <!-- 筛选栏 -->
    <view class="filter-bar">
      <scroll-view class="filter-tabs" scroll-x>
        <view
          class="filter-tab"
          :class="{ active: currentStatus === item.value }"
          v-for="item in statusTabs"
          :key="item.value"
          @tap="switchStatus(item.value)"
        >
          {{ item.label }}
        </view>
      </scroll-view>
    </view>

    <!-- 设备列表 -->
    <scroll-view class="devices-list" scroll-y @scrolltolower="loadMore">
      <view v-if="filteredDevices.length === 0" class="empty-state">
        <text class="empty-icon">📱</text>
        <text class="empty-text">暂无设备</text>
      </view>

      <view
        v-else
        class="device-card"
        v-for="device in filteredDevices"
        :key="device.id"
        @tap="viewDevice(device)"
      >
        <view class="device-header">
          <view class="device-info">
            <text class="device-name">{{ device.name }}</text>
            <text class="device-id">ID: {{ device.deviceId }}</text>
          </view>
          <view class="device-status" :class="`status-${device.status}`">
            {{ formatStatus(device.status) }}
          </view>
        </view>

        <view class="device-details">
          <view class="detail-item">
            <text class="detail-label">📍 位置</text>
            <text class="detail-value">{{ device.location }}</text>
          </view>
          <view class="detail-item">
            <text class="detail-label">🔋 电量</text>
            <text class="detail-value">{{ device.battery }}%</text>
          </view>
          <view class="detail-item">
            <text class="detail-label">📊 扫码次数</text>
            <text class="detail-value">{{ device.scanCount }}</text>
          </view>
          <view class="detail-item">
            <text class="detail-label">🕐 最后活跃</text>
            <text class="detail-value">{{ device.lastActiveTime }}</text>
          </view>
        </view>

        <view class="device-actions">
          <button class="action-btn" @tap.stop="editDevice(device)">编辑</button>
          <button class="action-btn" @tap.stop="viewQRCode(device)">查看二维码</button>
          <button class="action-btn danger" @tap.stop="deleteDevice(device)">删除</button>
        </view>
      </view>
    </scroll-view>

    <!-- 添加设备弹窗 -->
    <view v-if="showAddModal" class="add-modal">
      <view class="modal-content">
        <view class="modal-header">
          <text class="modal-title">{{ editingDevice ? '编辑设备' : '添加设备' }}</text>
          <text class="modal-close" @tap="closeAddModal">✕</text>
        </view>

        <view class="modal-body">
          <view class="form-item">
            <text class="form-label">设备名称</text>
            <input class="form-input" v-model="deviceForm.name" placeholder="请输入设备名称" />
          </view>

          <view class="form-item">
            <text class="form-label">设备ID</text>
            <input class="form-input" v-model="deviceForm.deviceId" placeholder="请输入设备ID" />
          </view>

          <view class="form-item">
            <text class="form-label">位置</text>
            <input class="form-input" v-model="deviceForm.location" placeholder="请输入设备位置" />
          </view>
        </view>

        <view class="modal-footer">
          <button class="modal-btn cancel" @tap="closeAddModal">取消</button>
          <button class="modal-btn confirm" @tap="saveDevice">保存</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  data() {
    return {
      stats: {
        total: 0,
        online: 0,
        offline: 0
      },

      currentStatus: 'ALL',
      statusTabs: [
        { label: '全部', value: 'ALL' },
        { label: '在线', value: 'ONLINE' },
        { label: '离线', value: 'OFFLINE' },
        { label: '故障', value: 'ERROR' }
      ],

      devices: [],
      showAddModal: false,
      editingDevice: null,

      deviceForm: {
        name: '',
        deviceId: '',
        location: ''
      }
    }
  },

  computed: {
    filteredDevices() {
      if (this.currentStatus === 'ALL') {
        return this.devices
      }
      return this.devices.filter(d => d.status === this.currentStatus)
    }
  },

  onLoad(options) {
    console.log('设备管理页面加载:', options)
    this.loadDevices()
  },

  methods: {
    goBack() {
      uni.navigateBack()
    },

    async loadDevices() {
      // 模拟加载设备数据
      this.devices = this.generateMockDevices()
      this.calculateStats()
    },

    generateMockDevices() {
      const statuses = ['ONLINE', 'OFFLINE', 'ERROR']
      const locations = ['大堂前台', '1号包间', '2号包间', '3号包间', '收银台', 'VIP包间']

      return Array.from({ length: 8 }, (_, i) => ({
        id: i + 1,
        name: `NFC设备-${i + 1}`,
        deviceId: `NFC${String(i + 1).padStart(6, '0')}`,
        status: statuses[i % 3],
        location: locations[i % locations.length],
        battery: Math.floor(Math.random() * 40) + 60,
        scanCount: Math.floor(Math.random() * 500) + 100,
        lastActiveTime: `${Math.floor(Math.random() * 12) + 1}小时前`
      }))
    },

    calculateStats() {
      this.stats.total = this.devices.length
      this.stats.online = this.devices.filter(d => d.status === 'ONLINE').length
      this.stats.offline = this.devices.filter(d => d.status === 'OFFLINE').length
    },

    switchStatus(status) {
      this.currentStatus = status
    },

    formatStatus(status) {
      const map = {
        ONLINE: '在线',
        OFFLINE: '离线',
        ERROR: '故障'
      }
      return map[status] || '未知'
    },

    addDevice() {
      this.editingDevice = null
      this.deviceForm = {
        name: '',
        deviceId: '',
        location: ''
      }
      this.showAddModal = true
    },

    editDevice(device) {
      this.editingDevice = device
      this.deviceForm = {
        name: device.name,
        deviceId: device.deviceId,
        location: device.location
      }
      this.showAddModal = true
    },

    closeAddModal() {
      this.showAddModal = false
    },

    saveDevice() {
      if (!this.deviceForm.name || !this.deviceForm.deviceId) {
        FeedbackHelper.warning('请填写完整信息', { vibrate: true })
        return
      }

      if (this.editingDevice) {
        Object.assign(this.editingDevice, this.deviceForm)
        FeedbackHelper.saveSuccess()
      } else {
        this.devices.unshift({
          id: Date.now(),
          ...this.deviceForm,
          status: 'OFFLINE',
          battery: 100,
          scanCount: 0,
          lastActiveTime: '刚刚'
        })
        FeedbackHelper.success('添加成功', { vibrate: true })
      }

      this.calculateStats()
      this.closeAddModal()
    },

    viewDevice(device) {
      FeedbackHelper.success(`查看${device.name}详情`, {
        vibrate: false,
        icon: 'none'
      })
    },

    viewQRCode(device) {
      FeedbackHelper.success('查看设备二维码', {
        vibrate: false,
        icon: 'none'
      })
    },

    async deleteDevice(device) {
      const res = await uni.showModal({
        title: '确认删除',
        content: `确定要删除设备"${device.name}"吗？`
      })

      if (res.confirm) {
        const index = this.devices.findIndex(d => d.id === device.id)
        if (index > -1) {
          this.devices.splice(index, 1)
          this.calculateStats()
          FeedbackHelper.deleteSuccess()
        }
      }
    },

    loadMore() {
      // 加载更多
    }
  }
}
</script>

<style scoped>
.devices-container {
  min-height: 100vh;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
}

.navbar {
  position: sticky;
  top: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 30rpx;
  background: #fff;
  border-bottom: 1rpx solid #e5e7eb;
}

.nav-back {
  width: 40rpx;
  height: 40rpx;
}

.nav-title {
  flex: 1;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  text-align: center;
}

.nav-action {
  font-size: 16px;
  color: #6366f1;
}

/* 统计卡片 */
.stats-cards {
  display: flex;
  gap: 20rpx;
  padding: 20rpx;
}

.stat-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 30rpx;
  background: #fff;
  border-radius: 12rpx;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #6366f1;
  margin-bottom: 10rpx;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
}

/* 筛选栏 */
.filter-bar {
  padding: 0 20rpx 20rpx;
}

.filter-tabs {
  white-space: nowrap;
}

.filter-tab {
  display: inline-block;
  padding: 12rpx 30rpx;
  margin-right: 15rpx;
  border-radius: 20rpx;
  font-size: 14px;
  background: #fff;
  color: #6b7280;
}

.filter-tab.active {
  background: #6366f1;
  color: #fff;
}

/* 设备列表 */
.devices-list {
  flex: 1;
  padding: 0 20rpx 20rpx;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 0;
}

.empty-icon {
  font-size: 80rpx;
  margin-bottom: 20rpx;
}

.empty-text {
  font-size: 14px;
  color: #9ca3af;
}

.device-card {
  background: #fff;
  border-radius: 16rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
}

.device-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20rpx;
}

.device-info {
  flex: 1;
}

.device-name {
  display: block;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 8rpx;
}

.device-id {
  font-size: 12px;
  color: #9ca3af;
}

.device-status {
  padding: 6rpx 20rpx;
  border-radius: 20rpx;
  font-size: 12px;
}

.status-ONLINE {
  background: #dcfce7;
  color: #16a34a;
}

.status-OFFLINE {
  background: #f3f4f6;
  color: #6b7280;
}

.status-ERROR {
  background: #fee2e2;
  color: #dc2626;
}

.device-details {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
  margin-bottom: 20rpx;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}

.detail-label {
  font-size: 12px;
  color: #9ca3af;
}

.detail-value {
  font-size: 14px;
  color: #1f2937;
}

.device-actions {
  display: flex;
  gap: 15rpx;
  padding-top: 20rpx;
  border-top: 1rpx solid #f3f4f6;
}

.action-btn {
  flex: 1;
  height: 60rpx;
  line-height: 60rpx;
  border-radius: 8rpx;
  font-size: 14px;
  background: #f3f4f6;
  color: #6b7280;
  padding: 0;
  border: none;
}

.action-btn.danger {
  background: #fee2e2;
  color: #dc2626;
}

/* 添加弹窗 */
.add-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.5);
}

.modal-content {
  width: 100%;
  max-height: 70vh;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
}

.modal-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.modal-close {
  font-size: 24px;
  color: #9ca3af;
}

.modal-body {
  padding: 30rpx;
}

.form-item {
  margin-bottom: 30rpx;
}

.form-label {
  display: block;
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 10rpx;
}

.form-input {
  width: 100%;
  padding: 20rpx;
  border: 1rpx solid #e5e7eb;
  border-radius: 8rpx;
  font-size: 14px;
  box-sizing: border-box;
}

.modal-footer {
  display: flex;
  gap: 20rpx;
  padding: 30rpx;
  border-top: 1rpx solid #e5e7eb;
}

.modal-btn {
  flex: 1;
  height: 90rpx;
  line-height: 90rpx;
  border-radius: 12rpx;
  font-size: 16px;
}

.modal-btn.cancel {
  background: #f3f4f6;
  color: #6b7280;
}

.modal-btn.confirm {
  background: #6366f1;
  color: #fff;
}
</style>

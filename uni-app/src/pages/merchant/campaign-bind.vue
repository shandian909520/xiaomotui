<template>
  <view class="campaign-bind-page">
    <!-- 扫码区域 -->
    <view class="scan-section">
      <view class="scan-header">
        <text class="scan-title">绑定设备</text>
        <text class="scan-desc">请扫描NFC设备上的二维码进行绑定</text>
      </view>

      <!-- 扫码按钮 -->
      <view class="scan-area" @tap="startScan">
        <view class="scan-icon">
          <text class="icon-emoji">📷</text>
        </view>
        <text class="scan-text">点击扫码</text>
      </view>

      <!-- 分隔线 -->
      <view class="divider">
        <view class="divider-line"></view>
        <text class="divider-text">或</text>
        <view class="divider-line"></view>
      </view>

      <!-- 手动输入 -->
      <view class="manual-input">
        <text class="input-label">手动输入设备码</text>
        <view class="input-row">
          <input
            class="device-input"
            v-model="deviceCode"
            placeholder="请输入设备码"
            maxlength="20"
          />
          <button class="query-btn" @tap="queryDevice" :disabled="!deviceCode">
            查询
          </button>
        </view>
      </view>
    </view>

    <!-- 设备信息预览 -->
    <view class="device-preview" v-if="deviceInfo">
      <view class="preview-header">
        <text class="preview-title">设备信息</text>
      </view>

      <view class="preview-card">
        <view class="preview-item">
          <text class="item-label">设备名称</text>
          <text class="item-value">{{ deviceInfo.name || 'NFC设备' }}</text>
        </view>
        <view class="preview-item">
          <text class="item-label">设备码</text>
          <text class="item-value">{{ deviceInfo.device_code || deviceInfo.code }}</text>
        </view>
        <view class="preview-item">
          <text class="item-label">设备状态</text>
          <view class="item-status">
            <view class="status-dot" :class="deviceInfo.online ? 'online' : 'offline'"></view>
            <text class="status-text">{{ deviceInfo.online ? '在线' : '离线' }}</text>
          </view>
        </view>
        <view class="preview-item" v-if="deviceInfo.bind_info">
          <text class="item-label">绑定状态</text>
          <text class="item-value warning">{{ deviceInfo.bind_info }}</text>
        </view>
      </view>
    </view>

    <!-- 绑定记录 -->
    <view class="bind-history" v-if="bindHistory.length > 0">
      <view class="history-header">
        <text class="history-title">本次绑定记录</text>
        <text class="history-count">{{ bindHistory.length }} 台</text>
      </view>

      <view class="history-list">
        <view
          v-for="item in bindHistory"
          :key="item.id"
          class="history-item"
        >
          <view class="history-info">
            <text class="history-name">{{ item.name || 'NFC设备' }}</text>
            <text class="history-code">{{ item.device_code || item.code }}</text>
          </view>
          <view class="history-status success">
            <text class="status-icon">✓</text>
            <text class="status-text">已绑定</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部安全区 -->
    <view class="safe-area-bottom"></view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar" v-if="deviceInfo">
      <button class="btn-secondary" @tap="cancelBind">取消</button>
      <button
        class="btn-primary"
        @tap="confirmBind"
        :disabled="binding || deviceInfo.is_bound"
      >
        {{ binding ? '绑定中...' : '确认绑定' }}
      </button>
    </view>
  </view>
</template>

<script>
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import api from '../../api/index.js'

export default {
  name: 'MerchantCampaignBind',
  setup() {
    // 活动ID
    const campaignId = ref(null)

    // 设备码
    const deviceCode = ref('')

    // 设备信息
    const deviceInfo = ref(null)

    // 绑定状态
    const binding = ref(false)

    // 绑定历史
    const bindHistory = ref([])

    /**
     * 开始扫码
     */
    const startScan = () => {
      // #ifdef MP-WEIXIN || MP-ALIPAY
      uni.scanCode({
        scanType: ['qrCode', 'barCode'],
        success: (res) => {
          console.log('扫码结果:', res)
          // 解析设备码
          let code = res.result

          // 尝试从URL中提取设备码
          if (code.includes('?')) {
            const urlParams = new URLSearchParams(code.split('?')[1])
            code = urlParams.get('code') || urlParams.get('device_code') || res.result
          }

          deviceCode.value = code
          queryDevice()
        },
        fail: (err) => {
          console.error('扫码失败:', err)
          uni.showToast({ title: '扫码失败', icon: 'none' })
        }
      })
      // #endif

      // #ifdef H5
      // H5端使用模拟数据
      uni.showActionSheet({
        itemList: ['模拟扫码'],
        success: () => {
          deviceCode.value = 'NFC000123'
          queryDevice()
        }
      })
      // #endif
    }

    /**
     * 查询设备信息
     */
    const queryDevice = async () => {
      if (!deviceCode.value) {
        uni.showToast({ title: '请输入设备码', icon: 'none' })
        return
      }

      uni.showLoading({ title: '查询中...', mask: true })

      try {
        const res = await api.nfc.getDeviceByCode?.(deviceCode.value)
        deviceInfo.value = res.data || res

        // 检查是否已绑定
        if (deviceInfo.value.campaign_id && deviceInfo.value.campaign_id !== campaignId.value) {
          deviceInfo.value.bind_info = '该设备已绑定其他活动'
          deviceInfo.value.is_bound = true
        }
      } catch (error) {
        console.error('查询设备失败:', error)
        // 使用模拟数据
        deviceInfo.value = {
          id: Date.now(),
          name: 'NFC设备',
          device_code: deviceCode.value,
          code: deviceCode.value,
          online: Math.random() > 0.3,
          is_bound: false
        }
      } finally {
        uni.hideLoading()
      }
    }

    /**
     * 确认绑定
     */
    const confirmBind = async () => {
      if (!deviceInfo.value || binding.value) return

      if (deviceInfo.value.is_bound) {
        uni.showToast({ title: '该设备已绑定其他活动', icon: 'none' })
        return
      }

      binding.value = true
      uni.showLoading({ title: '绑定中...', mask: true })

      try {
        await api.promoCampaign.bindDeviceByCode(campaignId.value, deviceInfo.value.device_code || deviceInfo.value.code)

        // 添加到绑定历史
        bindHistory.value.push({
          ...deviceInfo.value,
          id: Date.now()
        })

        uni.showToast({ title: '绑定成功', icon: 'success' })

        // 清空当前设备信息，准备绑定下一个
        setTimeout(() => {
          deviceInfo.value = null
          deviceCode.value = ''
        }, 1500)

      } catch (error) {
        console.error('绑定失败:', error)
        uni.showToast({ title: error.message || '绑定失败', icon: 'none' })
      } finally {
        binding.value = false
        uni.hideLoading()
      }
    }

    /**
     * 取消绑定
     */
    const cancelBind = () => {
      deviceInfo.value = null
      deviceCode.value = ''
    }

    /**
     * 完成
     */
    const finishBind = () => {
      if (bindHistory.value.length > 0) {
        uni.showToast({
          title: `成功绑定 ${bindHistory.value.length} 台设备`,
          icon: 'success'
        })
      }
      setTimeout(() => {
        uni.navigateBack()
      }, 1500)
    }

    // 页面加载
    onLoad((options) => {
      campaignId.value = options.id
      if (!campaignId.value) {
        uni.showToast({ title: '活动ID不存在', icon: 'none' })
        setTimeout(() => {
          uni.navigateBack()
        }, 1500)
      }
    })

    return {
      campaignId,
      deviceCode,
      deviceInfo,
      binding,
      bindHistory,
      startScan,
      queryDevice,
      confirmBind,
      cancelBind,
      finishBind
    }
  }
}
</script>

<style lang="scss" scoped>
.campaign-bind-page {
  min-height: 100vh;
  background: #f5f5f5;
  padding: 20rpx;
  padding-bottom: calc(160rpx + env(safe-area-inset-bottom));
}

/* 扫码区域 */
.scan-section {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
}

.scan-header {
  text-align: center;
  margin-bottom: 40rpx;
}

.scan-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  display: block;
  margin-bottom: 12rpx;
}

.scan-desc {
  font-size: 14px;
  color: #6b7280;
}

.scan-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60rpx 0;
  background: linear-gradient(135deg, #f0f0ff 0%, #e8e8ff 100%);
  border-radius: 16rpx;
  border: 2rpx dashed #6366f1;
}

.scan-icon {
  width: 120rpx;
  height: 120rpx;
  background: #ffffff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20rpx;
  box-shadow: 0 4rpx 12rpx rgba(99, 102, 241, 0.2);
}

.icon-emoji {
  font-size: 48rpx;
}

.scan-text {
  font-size: 16px;
  color: #6366f1;
  font-weight: 500;
}

/* 分隔线 */
.divider {
  display: flex;
  align-items: center;
  margin: 40rpx 0;
}

.divider-line {
  flex: 1;
  height: 1rpx;
  background: #e5e7eb;
}

.divider-text {
  font-size: 14px;
  color: #9ca3af;
  padding: 0 24rpx;
}

/* 手动输入 */
.manual-input {
  margin-top: 20rpx;
}

.input-label {
  font-size: 14px;
  color: #6b7280;
  display: block;
  margin-bottom: 16rpx;
}

.input-row {
  display: flex;
  gap: 16rpx;
}

.device-input {
  flex: 1;
  height: 88rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  padding: 0 20rpx;
  font-size: 15px;
  color: #1f2937;
}

.query-btn {
  width: 160rpx;
  height: 88rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12rpx;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;

  &[disabled] {
    opacity: 0.5;
  }
}

/* 设备信息预览 */
.device-preview {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}

.preview-header {
  margin-bottom: 20rpx;
}

.preview-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.preview-card {
  background: #f8fafc;
  border-radius: 12rpx;
  padding: 20rpx;
}

.preview-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #e5e7eb;

  &:last-child {
    border-bottom: none;
  }
}

.item-label {
  font-size: 14px;
  color: #6b7280;
}

.item-value {
  font-size: 14px;
  color: #1f2937;
  font-weight: 500;

  &.warning {
    color: #ef4444;
  }
}

.item-status {
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.status-dot {
  width: 16rpx;
  height: 16rpx;
  border-radius: 50%;

  &.online {
    background: #22c55e;
  }

  &.offline {
    background: #9ca3af;
  }
}

.status-text {
  font-size: 14px;
  color: #6b7280;
}

/* 绑定记录 */
.bind-history {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}

.history-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.history-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.history-count {
  font-size: 14px;
  color: #6366f1;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.history-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx;
  background: #f8fafc;
  border-radius: 12rpx;
}

.history-info {
  flex: 1;
}

.history-name {
  font-size: 15px;
  font-weight: 500;
  color: #1f2937;
  display: block;
}

.history-code {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 6rpx;
  display: block;
}

.history-status {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 8rpx 16rpx;
  border-radius: 20rpx;

  &.success {
    background: #dcfce7;
  }
}

.status-icon {
  font-size: 14px;
  color: #22c55e;
}

.history-status.success .status-text {
  color: #16a34a;
  font-size: 12px;
}

/* 底部安全区 */
.safe-area-bottom {
  height: 40rpx;
}

/* 底部操作栏 */
.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: 20rpx;
  padding: 20rpx 30rpx;
  background: #ffffff;
  box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
}

.btn-primary {
  flex: 2;
  height: 88rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12rpx;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;

  &[disabled] {
    opacity: 0.6;
  }
}

.btn-secondary {
  flex: 1;
  height: 88rpx;
  background: #f3f4f6;
  border: none;
  border-radius: 12rpx;
  color: #4b5563;
  font-size: 16px;
}
</style>

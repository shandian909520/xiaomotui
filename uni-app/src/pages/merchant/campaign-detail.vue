<template>
  <view class="campaign-detail-page">
    <!-- 加载状态 -->
    <view class="loading-container" v-if="loading">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>

    <template v-else>
      <!-- 活动基本信息 -->
      <view class="info-section">
        <view class="info-header">
          <view class="info-title-row">
            <text class="info-title">{{ campaign.name }}</text>
            <view class="info-status" :class="`status-${campaign.status}`">
              {{ getStatusText(campaign.status) }}
            </view>
          </view>
          <text class="info-desc" v-if="campaign.description">{{ campaign.description }}</text>
        </view>

        <view class="info-grid">
          <view class="info-item">
            <text class="info-label">活动时间</text>
            <text class="info-value">{{ formatTimeRange(campaign.start_time, campaign.end_time) }}</text>
          </view>
          <view class="info-item">
            <text class="info-label">目标平台</text>
            <view class="platform-icons">
              <text
                v-for="platform in campaign.platforms"
                :key="platform"
                class="platform-icon"
              >
                {{ getPlatformIcon(platform) }}
              </text>
            </view>
          </view>
        </view>
      </view>

      <!-- 统计数据卡片 -->
      <view class="stats-section">
        <text class="section-title">活动数据</text>
        <view class="stats-cards">
          <view class="stats-card">
            <text class="stats-value">{{ stats.triggerCount }}</text>
            <text class="stats-label">触发次数</text>
          </view>
          <view class="stats-card">
            <text class="stats-value">{{ stats.downloadCount }}</text>
            <text class="stats-label">下载次数</text>
          </view>
          <view class="stats-card">
            <text class="stats-value">{{ stats.publishCount }}</text>
            <text class="stats-label">发布次数</text>
          </view>
          <view class="stats-card">
            <text class="stats-value">{{ stats.rewardCount }}</text>
            <text class="stats-label">奖励发放</text>
          </view>
        </view>
      </view>

      <!-- 推广内容预览 -->
      <view class="content-section">
        <text class="section-title">推广内容</text>

        <!-- 推广文案 -->
        <view class="promo-text-card" v-if="campaign.promo_text">
          <text class="promo-text">{{ campaign.promo_text }}</text>
        </view>

        <!-- 话题标签 -->
        <view class="tags-container" v-if="campaign.tags && campaign.tags.length > 0">
          <view
            v-for="tag in campaign.tags"
            :key="tag"
            class="tag-item"
          >
            #{{ tag }}
          </view>
        </view>

        <!-- 视频变体预览 -->
        <view class="variants-section">
          <view class="variants-header">
            <text class="variants-title">视频变体 ({{ variants.length }})</text>
            <text class="variants-more" @tap="viewAllVariants">查看全部 ></text>
          </view>
          <scroll-view class="variants-scroll" scroll-x>
            <view class="variants-list">
              <view
                v-for="(item, index) in variants"
                :key="item.id"
                class="variant-item"
                @tap="previewVariant(item)"
              >
                <view class="variant-cover">
                  <text class="variant-icon">▶</text>
                </view>
                <text class="variant-index">变体 {{ index + 1 }}</text>
              </view>
            </view>
          </scroll-view>
        </view>
      </view>

      <!-- 绑定设备区 -->
      <view class="devices-section">
        <view class="section-header">
          <text class="section-title">绑定设备</text>
          <text class="section-count">{{ devices.length }} 台</text>
        </view>

        <!-- 设备列表 -->
        <view class="devices-list" v-if="devices.length > 0">
          <view
            v-for="device in devices"
            :key="device.id"
            class="device-item"
          >
            <view class="device-info">
              <text class="device-name">{{ device.name || 'NFC设备' }}</text>
              <text class="device-code">设备码: {{ device.device_code || device.code }}</text>
            </view>
            <view class="device-status">
              <view class="status-dot" :class="device.online ? 'online' : 'offline'"></view>
              <text class="status-text">{{ device.online ? '在线' : '离线' }}</text>
            </view>
            <view class="device-unbind" @tap="unbindDevice(device)">
              <text class="unbind-icon">×</text>
            </view>
          </view>
        </view>

        <!-- 空状态 -->
        <view class="devices-empty" v-else>
          <text class="empty-icon">📱</text>
          <text class="empty-text">暂无绑定设备</text>
        </view>

        <!-- 添加设备按钮 -->
        <button class="add-device-btn" @tap="goToBindDevice">
          <text class="btn-icon">📷</text>
          扫码添加设备
        </button>
      </view>

      <!-- 底部安全区 -->
      <view class="safe-area-bottom"></view>
    </template>

    <!-- 底部操作栏 -->
    <view class="bottom-bar" v-if="!loading">
      <button class="action-btn secondary" @tap="editCampaign">
        <text class="btn-icon">✏️</text>
        编辑活动
      </button>
      <button
        class="action-btn danger"
        v-if="campaign.status === 'active'"
        @tap="endCampaign"
      >
        <text class="btn-icon">⏹️</text>
        结束活动
      </button>
      <button
        class="action-btn danger"
        v-else
        @tap="deleteCampaign"
      >
        <text class="btn-icon">🗑️</text>
        删除活动
      </button>
    </view>

    <!-- 确认弹窗 -->
    <view class="confirm-modal" v-if="showConfirmModal" @tap="showConfirmModal = false">
      <view class="modal-content" @tap.stop>
        <text class="modal-title">{{ confirmTitle }}</text>
        <text class="modal-desc">{{ confirmDesc }}</text>
        <view class="modal-actions">
          <button class="modal-btn cancel" @tap="showConfirmModal = false">取消</button>
          <button class="modal-btn confirm" @tap="confirmAction">确定</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import api from '../../api/index.js'

export default {
  name: 'MerchantCampaignDetail',
  setup() {
    // 活动ID
    const campaignId = ref(null)

    // 加载状态
    const loading = ref(true)

    // 活动信息
    const campaign = ref({
      id: null,
      name: '',
      description: '',
      status: 'active',
      promo_text: '',
      tags: [],
      platforms: ['douyin'],
      start_time: null,
      end_time: null
    })

    // 统计数据
    const stats = reactive({
      triggerCount: 0,
      downloadCount: 0,
      publishCount: 0,
      rewardCount: 0
    })

    // 视频变体
    const variants = ref([])

    // 绑定设备
    const devices = ref([])

    // 确认弹窗
    const showConfirmModal = ref(false)
    const confirmTitle = ref('')
    const confirmDesc = ref('')
    const confirmCallback = ref(null)

    /**
     * 加载活动详情
     */
    const loadCampaignDetail = async () => {
      loading.value = true
      try {
        const res = await api.promoCampaign.getDetail(campaignId.value)
        campaign.value = res.data || res

        // 加载统计数据
        loadStats()

        // 加载变体
        if (campaign.value.variants) {
          variants.value = campaign.value.variants
        } else {
          variants.value = generateMockVariants()
        }

        // 加载设备
        loadDevices()

      } catch (error) {
        console.error('加载活动详情失败:', error)
        // 使用模拟数据
        campaign.value = {
          id: campaignId.value,
          name: '推广活动示例',
          description: '这是一个推广活动的描述信息',
          status: 'active',
          promo_text: '体验我们的优质产品，享受专属优惠！快来参与吧~',
          tags: ['好物推荐', '限时优惠', '小魔推'],
          platforms: ['douyin', 'kuaishou'],
          start_time: new Date(Date.now() - 3 * 24 * 3600000).toISOString(),
          end_time: new Date(Date.now() + 7 * 24 * 3600000).toISOString()
        }

        stats.triggerCount = 156
        stats.downloadCount = 89
        stats.publishCount = 67
        stats.rewardCount = 45

        variants.value = generateMockVariants()
        devices.value = generateMockDevices()
      } finally {
        loading.value = false
      }
    }

    /**
     * 加载统计数据
     */
    const loadStats = async () => {
      try {
        const res = await api.promoCampaign.getStats(campaignId.value)
        const data = res.data || res
        stats.triggerCount = data.trigger_count || data.triggerCount || 0
        stats.downloadCount = data.download_count || data.downloadCount || 0
        stats.publishCount = data.publish_count || data.publishCount || 0
        stats.rewardCount = data.reward_count || data.rewardCount || 0
      } catch (error) {
        console.error('加载统计数据失败:', error)
      }
    }

    /**
     * 加载设备列表
     */
    const loadDevices = async () => {
      try {
        const res = await api.promoCampaign.getDevices(campaignId.value)
        devices.value = res.data || res.list || []
      } catch (error) {
        console.error('加载设备列表失败:', error)
        devices.value = generateMockDevices()
      }
    }

    /**
     * 生成模拟变体
     */
    const generateMockVariants = () => {
      return Array.from({ length: 5 }, (_, i) => ({
        id: Date.now() + i,
        name: `变体 ${i + 1}`,
        duration: Math.floor(Math.random() * 30) + 15,
        url: ''
      }))
    }

    /**
     * 生成模拟设备
     */
    const generateMockDevices = () => {
      return Array.from({ length: 3 }, (_, i) => ({
        id: Date.now() + i,
        name: `NFC设备 ${i + 1}`,
        device_code: `NFC${String(1000 + i).padStart(6, '0')}`,
        code: `NFC${String(1000 + i).padStart(6, '0')}`,
        online: Math.random() > 0.3
      }))
    }

    /**
     * 获取状态文本
     */
    const getStatusText = (status) => {
      const statusMap = {
        'active': '进行中',
        'pending': '待开始',
        'ended': '已结束',
        'draft': '草稿'
      }
      return statusMap[status] || status
    }

    /**
     * 格式化时间范围
     */
    const formatTimeRange = (startTime, endTime) => {
      if (!startTime && !endTime) return '未设置'

      const formatDateTime = (dateStr) => {
        if (!dateStr) return ''
        const date = new Date(dateStr)
        return `${date.getMonth() + 1}月${date.getDate()}日 ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
      }

      const start = formatDateTime(startTime)
      const end = formatDateTime(endTime)

      if (start && end) {
        return `${start} 至 ${end}`
      }
      return start || end
    }

    /**
     * 获取平台图标
     */
    const getPlatformIcon = (platform) => {
      const iconMap = {
        'douyin': '🎵',
        'kuaishou': '⚡',
        'shipinhao': '📺'
      }
      return iconMap[platform] || '📱'
    }

    /**
     * 查看所有变体
     */
    const viewAllVariants = () => {
      uni.showToast({ title: '查看所有变体功能开发中', icon: 'none' })
    }

    /**
     * 预览变体
     */
    const previewVariant = (item) => {
      uni.showToast({ title: `预览变体 ${item.id}`, icon: 'none' })
    }

    /**
     * 跳转绑定设备页面
     */
    const goToBindDevice = () => {
      uni.navigateTo({
        url: `/pages/merchant/campaign-bind?id=${campaignId.value}`
      })
    }

    /**
     * 解绑设备
     */
    const unbindDevice = (device) => {
      confirmTitle.value = '解绑设备'
      confirmDesc.value = `确定要解绑设备 "${device.name || device.device_code}" 吗？`
      confirmCallback.value = async () => {
        try {
          await api.promoCampaign.unbindDevice(campaignId.value, device.id)
          devices.value = devices.value.filter(d => d.id !== device.id)
          uni.showToast({ title: '解绑成功', icon: 'success' })
        } catch (error) {
          console.error('解绑设备失败:', error)
          uni.showToast({ title: '解绑失败', icon: 'none' })
        }
      }
      showConfirmModal.value = true
    }

    /**
     * 编辑活动
     */
    const editCampaign = () => {
      uni.navigateTo({
        url: `/pages/merchant/campaign-edit?id=${campaignId.value}`
      })
    }

    /**
     * 结束活动
     */
    const endCampaign = () => {
      confirmTitle.value = '结束活动'
      confirmDesc.value = '确定要结束此活动吗？结束后将无法恢复。'
      confirmCallback.value = async () => {
        try {
          await api.promoCampaign.end(campaignId.value)
          campaign.value.status = 'ended'
          uni.showToast({ title: '活动已结束', icon: 'success' })
        } catch (error) {
          console.error('结束活动失败:', error)
          uni.showToast({ title: '操作失败', icon: 'none' })
        }
      }
      showConfirmModal.value = true
    }

    /**
     * 删除活动
     */
    const deleteCampaign = () => {
      confirmTitle.value = '删除活动'
      confirmDesc.value = '确定要删除此活动吗？删除后无法恢复。'
      confirmCallback.value = async () => {
        try {
          await api.promoCampaign.delete(campaignId.value)
          uni.showToast({ title: '删除成功', icon: 'success' })
          setTimeout(() => {
            uni.navigateBack()
          }, 1500)
        } catch (error) {
          console.error('删除活动失败:', error)
          uni.showToast({ title: '删除失败', icon: 'none' })
        }
      }
      showConfirmModal.value = true
    }

    /**
     * 确认操作
     */
    const confirmAction = () => {
      showConfirmModal.value = false
      if (confirmCallback.value) {
        confirmCallback.value()
      }
    }

    // 页面加载
    onLoad((options) => {
      campaignId.value = options.id
      if (campaignId.value) {
        loadCampaignDetail()
      } else {
        uni.showToast({ title: '活动ID不存在', icon: 'none' })
        setTimeout(() => {
          uni.navigateBack()
        }, 1500)
      }
    })

    return {
      loading,
      campaign,
      stats,
      variants,
      devices,
      showConfirmModal,
      confirmTitle,
      confirmDesc,
      getStatusText,
      formatTimeRange,
      getPlatformIcon,
      viewAllVariants,
      previewVariant,
      goToBindDevice,
      unbindDevice,
      editCampaign,
      endCampaign,
      deleteCampaign,
      confirmAction
    }
  }
}
</script>

<style lang="scss" scoped>
.campaign-detail-page {
  min-height: 100vh;
  background: #f5f5f5;
  padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}

/* 加载状态 */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
}

.loading-spinner {
  width: 60rpx;
  height: 60rpx;
  border: 6rpx solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-text {
  font-size: 14px;
  color: #9ca3af;
  margin-top: 20rpx;
}

/* 活动基本信息 */
.info-section {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 30rpx;
  padding-top: calc(env(safe-area-inset-top) + 30rpx);
}

.info-header {
  margin-bottom: 30rpx;
}

.info-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16rpx;
}

.info-title {
  font-size: 22px;
  font-weight: bold;
  color: #ffffff;
  flex: 1;
}

.info-status {
  padding: 8rpx 20rpx;
  border-radius: 20rpx;
  font-size: 12px;
  font-weight: 500;
  flex-shrink: 0;

  &.status-active {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
  }

  &.status-ended {
    background: rgba(0, 0, 0, 0.2);
    color: rgba(255, 255, 255, 0.8);
  }

  &.status-draft {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
  }
}

.info-desc {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
  line-height: 1.5;
}

.info-grid {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 12rpx;
  padding: 20rpx;
  display: flex;
  gap: 30rpx;
}

.info-item {
  flex: 1;
}

.info-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  display: block;
  margin-bottom: 8rpx;
}

.info-value {
  font-size: 14px;
  color: #ffffff;
}

.platform-icons {
  display: flex;
  gap: 12rpx;
}

.platform-icon {
  font-size: 20px;
}

/* 统计数据卡片 */
.stats-section {
  margin: -20rpx 20rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.08);
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  display: block;
  margin-bottom: 20rpx;
}

.stats-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16rpx;
}

.stats-card {
  text-align: center;
  padding: 16rpx 0;
}

.stats-value {
  font-size: 24px;
  font-weight: bold;
  color: #6366f1;
  display: block;
}

.stats-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 8rpx;
  display: block;
}

/* 推广内容预览 */
.content-section {
  margin: 0 20rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
}

.promo-text-card {
  background: #f8fafc;
  border-radius: 12rpx;
  padding: 20rpx;
  margin-bottom: 20rpx;
}

.promo-text {
  font-size: 15px;
  color: #374151;
  line-height: 1.6;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
  margin-bottom: 20rpx;
}

.tag-item {
  padding: 10rpx 20rpx;
  background: #ede9fe;
  color: #6366f1;
  border-radius: 20rpx;
  font-size: 13px;
}

.variants-section {
  margin-top: 20rpx;
}

.variants-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16rpx;
}

.variants-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.variants-more {
  font-size: 13px;
  color: #6366f1;
}

.variants-scroll {
  white-space: nowrap;
  margin: 0 -10rpx;
}

.variants-list {
  display: inline-flex;
  gap: 16rpx;
  padding: 10rpx;
}

.variant-item {
  width: 160rpx;
  flex-shrink: 0;
}

.variant-cover {
  width: 160rpx;
  height: 200rpx;
  background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
  border-radius: 12rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12rpx;
}

.variant-icon {
  font-size: 40rpx;
  color: #ffffff;
}

.variant-index {
  font-size: 12px;
  color: #6b7280;
  display: block;
  text-align: center;
}

/* 绑定设备区 */
.devices-section {
  margin: 0 20rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.section-count {
  font-size: 14px;
  color: #6366f1;
}

.devices-list {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
  margin-bottom: 20rpx;
}

.device-item {
  display: flex;
  align-items: center;
  padding: 20rpx;
  background: #f8fafc;
  border-radius: 12rpx;
}

.device-info {
  flex: 1;
}

.device-name {
  font-size: 15px;
  font-weight: 500;
  color: #1f2937;
  display: block;
}

.device-code {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 6rpx;
  display: block;
}

.device-status {
  display: flex;
  align-items: center;
  gap: 8rpx;
  margin-right: 16rpx;
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
  font-size: 12px;
  color: #6b7280;
}

.device-unbind {
  width: 48rpx;
  height: 48rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fee2e2;
  border-radius: 50%;
}

.unbind-icon {
  font-size: 24rpx;
  color: #ef4444;
  line-height: 1;
}

.devices-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40rpx 0;
  margin-bottom: 20rpx;
}

.empty-icon {
  font-size: 48rpx;
  margin-bottom: 16rpx;
}

.empty-text {
  font-size: 14px;
  color: #9ca3af;
}

.add-device-btn {
  width: 100%;
  height: 88rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12rpx;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;

  .btn-icon {
    margin-right: 12rpx;
  }
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

.action-btn {
  flex: 1;
  height: 88rpx;
  border-radius: 12rpx;
  font-size: 15px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;

  .btn-icon {
    margin-right: 8rpx;
  }

  &.secondary {
    background: #f3f4f6;
    color: #4b5563;
  }

  &.danger {
    background: #fee2e2;
    color: #ef4444;
  }
}

/* 确认弹窗 */
.confirm-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-content {
  width: 560rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 40rpx;
}

.modal-title {
  display: block;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  text-align: center;
  margin-bottom: 20rpx;
}

.modal-desc {
  display: block;
  font-size: 14px;
  color: #6b7280;
  text-align: center;
  line-height: 1.6;
  margin-bottom: 40rpx;
}

.modal-actions {
  display: flex;
  gap: 20rpx;
}

.modal-btn {
  flex: 1;
  height: 88rpx;
  border-radius: 12rpx;
  font-size: 16px;
  font-weight: 500;
  border: none;

  &.cancel {
    background: #f3f4f6;
    color: #6b7280;
  }

  &.confirm {
    background: #ef4444;
    color: #ffffff;
  }
}
</style>

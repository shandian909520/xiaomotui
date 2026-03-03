<template>
  <view class="compose-result-page">
    <!-- 结果概览 -->
    <view class="result-header">
      <view class="header-content">
        <text class="header-title">视频变体</text>
        <text class="header-count">共 {{ variants.length }} 个</text>
      </view>
      <view class="header-info">
        <view class="info-tag">
          <text class="tag-text">生成时间: {{ formatTime(createTime) }}</text>
        </view>
      </view>
    </view>

    <!-- 变体列表 -->
    <scroll-view class="variant-list" scroll-y>
      <view
        class="variant-item"
        v-for="item in variants"
        :key="item.id"
      >
        <!-- 视频预览 -->
        <view class="video-preview">
          <video
            v-if="item.url"
            :src="item.url"
            class="video-player"
            :poster="item.poster"
            :show-center-play-btn="true"
            :show-play-btn="true"
            :enable-progress-gesture="true"
            object-fit="cover"
            @tap="previewVariant(item)"
          />
          <view v-else class="video-placeholder">
            <text class="placeholder-icon">▶</text>
            <text class="placeholder-text">视频加载中</text>
          </view>
          <view class="video-badge" v-if="item.is_used">
            <text class="badge-text">已使用</text>
          </view>
        </view>

        <!-- 变体信息 -->
        <view class="variant-info">
          <view class="info-row">
            <text class="info-label">时长:</text>
            <text class="info-value">{{ item.duration || estimatedDuration }}s</text>
          </view>
          <view class="info-row">
            <text class="info-label">大小:</text>
            <text class="info-value">{{ formatSize(item.file_size) }}</text>
          </view>
          <view class="info-row">
            <text class="info-label">序号:</text>
            <text class="info-value">#{{ item.variant_index || item.id }}</text>
          </view>
        </view>

        <!-- 操作按钮 -->
        <view class="variant-actions">
          <button
            class="action-btn btn-preview"
            size="mini"
            @tap="previewVariant(item)"
          >
            <text class="btn-icon">▶</text>
            预览
          </button>
          <button
            class="action-btn btn-download"
            size="mini"
            @tap="downloadVariant(item)"
          >
            <text class="btn-icon">↓</text>
            下载
          </button>
          <button
            class="action-btn btn-publish"
            size="mini"
            type="primary"
            @tap="publishToDevice(item)"
          >
            <text class="btn-icon">📱</text>
            发布
          </button>
        </view>
      </view>

      <!-- 加载更多 -->
      <view class="load-more" v-if="hasMore" @tap="loadMore">
        <text class="load-more-text">加载更多</text>
      </view>

      <!-- 空状态 -->
      <view class="empty-state" v-if="!loading && variants.length === 0">
        <text class="empty-icon">📭</text>
        <text class="empty-text">暂无视频变体</text>
      </view>
    </scroll-view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar">
      <button class="bottom-btn btn-secondary" @tap="composeAgain">
        <text class="btn-icon">🎬</text>
        再次合成
      </button>
      <button class="bottom-btn btn-primary" @tap="goToDeviceBind">
        <text class="btn-icon">📱</text>
        绑定设备
      </button>
    </view>

    <!-- 发布到设备弹窗 -->
    <uni-popup ref="publishPopup" type="bottom" background-color="#fff">
      <view class="publish-popup">
        <view class="popup-header">
          <text class="popup-title">发布到设备</text>
          <text class="popup-close" @tap="closePublishPopup">×</text>
        </view>

        <view class="popup-content">
          <!-- 设备选择 -->
          <view class="device-section">
            <text class="section-title">选择设备</text>
            <scroll-view class="device-list" scroll-y>
              <label
                class="device-item"
                v-for="device in deviceList"
                :key="device.id"
              >
                <radio
                  :value="device.id"
                  :checked="selectedDeviceId === device.id"
                  color="#6366f1"
                  @tap="selectDevice(device.id)"
                />
                <view class="device-info">
                  <text class="device-name">{{ device.name || device.device_code }}</text>
                  <text class="device-status">{{ device.status_text || '在线' }}</text>
                </view>
              </label>
            </scroll-view>
          </view>

          <!-- 播放设置 -->
          <view class="settings-section">
            <text class="section-title">播放设置</text>
            <view class="setting-item">
              <text class="setting-label">开始时间</text>
              <picker
                mode="time"
                :value="publishSettings.startTime"
                @change="onStartTimeChange"
              >
                <view class="setting-picker">
                  <text class="picker-value">{{ publishSettings.startTime || '立即开始' }}</text>
                  <text class="picker-arrow">></text>
                </view>
              </picker>
            </view>
            <view class="setting-item">
              <text class="setting-label">结束时间</text>
              <picker
                mode="time"
                :value="publishSettings.endTime"
                @change="onEndTimeChange"
              >
                <view class="setting-picker">
                  <text class="picker-value">{{ publishSettings.endTime || '不限制' }}</text>
                  <text class="picker-arrow">></text>
                </view>
              </picker>
            </view>
          </view>
        </view>

        <view class="popup-footer">
          <button class="btn-cancel" @tap="closePublishPopup">取消</button>
          <button class="btn-confirm" @tap="confirmPublish">确认发布</button>
        </view>
      </view>
    </uni-popup>
  </view>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../api/index.js'

export default {
  name: 'ComposeResult',
  setup() {
    // 模板ID
    const templateId = ref(null)

    // 变体列表
    const variants = ref([])

    // 创建时间
    const createTime = ref(new Date().toISOString())

    // 加载状态
    const loading = ref(false)
    const hasMore = ref(false)
    const page = ref(1)
    const pageSize = ref(10)

    // 预计时长
    const estimatedDuration = ref(0)

    // 设备列表
    const deviceList = ref([])
    const selectedDeviceId = ref(null)
    const currentVariant = ref(null)

    // 发布设置
    const publishSettings = reactive({
      startTime: '',
      endTime: ''
    })

    // 弹窗引用
    const publishPopup = ref(null)

    /**
     * 加载变体列表
     */
    const loadVariants = async () => {
      loading.value = true
      try {
        const res = await api.promoTemplate.getVariantList({
          template_id: templateId.value,
          page: page.value,
          pageSize: pageSize.value
        })

        const list = res.data || res.list || []
        if (page.value === 1) {
          variants.value = list
        } else {
          variants.value.push(...list)
        }

        hasMore.value = list.length >= pageSize.value

        // 使用模拟数据
        if (variants.value.length === 0) {
          variants.value = generateMockVariants()
        }
      } catch (error) {
        console.error('加载变体列表失败:', error)
        // 使用模拟数据
        variants.value = generateMockVariants()
      } finally {
        loading.value = false
      }
    }

    /**
     * 生成模拟变体数据
     */
    const generateMockVariants = () => {
      const count = 5
      return Array.from({ length: count }, (_, i) => ({
        id: i + 1,
        url: '', // 实际应该是视频URL
        poster: `https://via.placeholder.com/400x300?text=Variant${i + 1}`,
        duration: 10 + Math.floor(Math.random() * 5),
        file_size: 1024 * 1024 * (2 + Math.random() * 3),
        variant_index: i + 1,
        is_used: i === 0,
        created_at: new Date().toISOString()
      }))
    }

    /**
     * 加载设备列表
     */
    const loadDeviceList = async () => {
      try {
        const res = await api.merchant.getDeviceList?.({ page: 1, pageSize: 50 })
        deviceList.value = res.data || res.list || []
      } catch (error) {
        console.error('加载设备列表失败:', error)
        // 使用模拟数据
        deviceList.value = [
          { id: 1, name: '前台设备', device_code: 'DEV001', status_text: '在线' },
          { id: 2, name: '收银台设备', device_code: 'DEV002', status_text: '在线' },
          { id: 3, name: '门口设备', device_code: 'DEV003', status_text: '离线' }
        ]
      }
    }

    /**
     * 加载更多
     */
    const loadMore = () => {
      if (!hasMore.value || loading.value) return
      page.value++
      loadVariants()
    }

    /**
     * 预览变体
     */
    const previewVariant = (item) => {
      if (item.url) {
        uni.navigateTo({
          url: `/pages/content/preview?type=video&url=${encodeURIComponent(item.url)}`
        })
      } else {
        uni.showToast({ title: '视频地址不可用', icon: 'none' })
      }
    }

    /**
     * 下载变体
     */
    const downloadVariant = async (item) => {
      if (!item.url) {
        uni.showToast({ title: '视频地址不可用', icon: 'none' })
        return
      }

      try {
        uni.showLoading({ title: '下载中...' })
        const tempFilePath = await api.request.download(item.url)
        uni.hideLoading()

        // 保存到相册
        // #ifdef MP-WEIXIN || MP-ALIPAY
        uni.saveVideoToPhotosAlbum({
          filePath: tempFilePath,
          success: () => {
            uni.showToast({ title: '已保存到相册', icon: 'success' })
          },
          fail: () => {
            uni.showToast({ title: '保存失败', icon: 'none' })
          }
        })
        // #endif

        // #ifdef H5
        uni.showToast({ title: '下载完成', icon: 'success' })
        // #endif
      } catch (error) {
        uni.hideLoading()
        uni.showToast({ title: '下载失败', icon: 'none' })
      }
    }

    /**
     * 发布到设备
     */
    const publishToDevice = (item) => {
      currentVariant.value = item
      loadDeviceList()
      publishPopup.value?.open()
    }

    /**
     * 关闭发布弹窗
     */
    const closePublishPopup = () => {
      publishPopup.value?.close()
    }

    /**
     * 选择设备
     */
    const selectDevice = (deviceId) => {
      selectedDeviceId.value = deviceId
    }

    /**
     * 开始时间变化
     */
    const onStartTimeChange = (e) => {
      publishSettings.startTime = e.detail.value
    }

    /**
     * 结束时间变化
     */
    const onEndTimeChange = (e) => {
      publishSettings.endTime = e.detail.value
    }

    /**
     * 确认发布
     */
    const confirmPublish = async () => {
      if (!selectedDeviceId.value) {
        uni.showToast({ title: '请选择设备', icon: 'none' })
        return
      }

      if (!currentVariant.value) {
        uni.showToast({ title: '请选择要发布的视频', icon: 'none' })
        return
      }

      try {
        uni.showLoading({ title: '发布中...' })

        await api.promoTemplate.publishVariantToDevice(currentVariant.value.id, {
          deviceIds: [selectedDeviceId.value],
          startTime: publishSettings.startTime,
          endTime: publishSettings.endTime
        })

        uni.hideLoading()
        uni.showToast({ title: '发布成功', icon: 'success' })
        closePublishPopup()

        // 更新变体状态
        const variant = variants.value.find(v => v.id === currentVariant.value.id)
        if (variant) {
          variant.is_used = true
        }
      } catch (error) {
        uni.hideLoading()
        console.error('发布失败:', error)
        uni.showToast({ title: error.message || '发布失败', icon: 'none' })
      }
    }

    /**
     * 再次合成
     */
    const composeAgain = () => {
      uni.navigateBack()
      uni.navigateTo({ url: '/pages/merchant/compose' })
    }

    /**
     * 绑定设备
     */
    const goToDeviceBind = () => {
      uni.navigateTo({ url: '/pages/merchant/devices' })
    }

    /**
     * 格式化文件大小
     */
    const formatSize = (bytes) => {
      if (!bytes || bytes === 0) return '未知'
      const units = ['B', 'KB', 'MB', 'GB']
      let unitIndex = 0
      let size = bytes

      while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024
        unitIndex++
      }

      return `${size.toFixed(1)}${units[unitIndex]}`
    }

    /**
     * 格式化时间
     */
    const formatTime = (isoString) => {
      if (!isoString) return '未知'
      const date = new Date(isoString)
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      const hour = String(date.getHours()).padStart(2, '0')
      const minute = String(date.getMinutes()).padStart(2, '0')
      return `${year}-${month}-${day} ${hour}:${minute}`
    }

    onMounted(() => {
      // 获取页面参数
      const pages = getCurrentPages()
      const currentPage = pages[pages.length - 1]
      const options = currentPage.options || currentPage.$page?.options || {}

      templateId.value = options.templateId
      estimatedDuration.value = parseFloat(options.duration) || 10

      loadVariants()
    })

    return {
      templateId,
      variants,
      createTime,
      loading,
      hasMore,
      estimatedDuration,
      deviceList,
      selectedDeviceId,
      publishSettings,
      publishPopup,
      loadMore,
      previewVariant,
      downloadVariant,
      publishToDevice,
      closePublishPopup,
      selectDevice,
      onStartTimeChange,
      onEndTimeChange,
      confirmPublish,
      composeAgain,
      goToDeviceBind,
      formatSize,
      formatTime
    }
  }
}
</script>

<style lang="scss" scoped>
.compose-result-page {
  min-height: 100vh;
  background: #f5f5f5;
}

/* 结果概览 */
.result-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 40rpx 30rpx;
  padding-top: calc(env(safe-area-inset-top) + 40rpx);
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16rpx;
}

.header-title {
  font-size: 24px;
  font-weight: bold;
  color: #ffffff;
}

.header-count {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
}

.header-info {
  display: flex;
  gap: 16rpx;
}

.info-tag {
  padding: 8rpx 16rpx;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 6rpx;
}

.tag-text {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.9);
}

/* 变体列表 */
.variant-list {
  height: calc(100vh - 280rpx - env(safe-area-inset-top));
  padding: 20rpx;
  padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}

.variant-item {
  background: #ffffff;
  border-radius: 16rpx;
  overflow: hidden;
  margin-bottom: 20rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
}

/* 视频预览 */
.video-preview {
  position: relative;
  width: 100%;
  height: 400rpx;
  background: #1f2937;
}

.video-player {
  width: 100%;
  height: 100%;
}

.video-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.placeholder-icon {
  font-size: 60rpx;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 16rpx;
}

.placeholder-text {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.5);
}

.video-badge {
  position: absolute;
  top: 16rpx;
  right: 16rpx;
  padding: 8rpx 16rpx;
  background: rgba(34, 197, 94, 0.9);
  border-radius: 6rpx;
}

.badge-text {
  font-size: 12px;
  color: #ffffff;
}

/* 变体信息 */
.variant-info {
  padding: 24rpx;
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
  border-bottom: 1rpx solid #f3f4f6;
}

.info-row {
  flex: 1;
  min-width: 120rpx;
}

.info-label {
  font-size: 12px;
  color: #6b7280;
}

.info-value {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin-left: 8rpx;
}

/* 操作按钮 */
.variant-actions {
  display: flex;
  gap: 16rpx;
  padding: 20rpx 24rpx;
}

.action-btn {
  flex: 1;
  height: 64rpx;
  border-radius: 8rpx;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.btn-icon {
  margin-right: 8rpx;
}

.btn-preview {
  background: #f3f4f6;
  color: #4b5563;
  border: none;
}

.btn-download {
  background: #f0f9ff;
  color: #0284c7;
  border: 1rpx solid #bae6fd;
}

.btn-publish {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: #ffffff;
  border: none;
}

/* 加载更多 */
.load-more {
  padding: 30rpx;
  text-align: center;
}

.load-more-text {
  font-size: 14px;
  color: #6366f1;
}

/* 空状态 */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 100rpx 0;
}

.empty-icon {
  font-size: 80rpx;
  margin-bottom: 24rpx;
}

.empty-text {
  font-size: 14px;
  color: #6b7280;
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
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
  background: #ffffff;
  box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.bottom-btn {
  flex: 1;
  height: 88rpx;
  border-radius: 12rpx;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-secondary {
  background: #ffffff;
  border: 2rpx solid #e5e7eb;
  color: #4b5563;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  color: #ffffff;
  font-weight: 600;
}

.btn-icon {
  margin-right: 12rpx;
}

/* 发布弹窗 */
.publish-popup {
  max-height: 70vh;
  display: flex;
  flex-direction: column;
}

.popup-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
}

.popup-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.popup-close {
  font-size: 32px;
  color: #9ca3af;
  line-height: 1;
}

.popup-content {
  flex: 1;
  padding: 20rpx 30rpx;
  overflow-y: auto;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 16rpx;
  display: block;
}

.device-section {
  margin-bottom: 30rpx;
}

.device-list {
  max-height: 300rpx;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  overflow: hidden;
}

.device-item {
  display: flex;
  align-items: center;
  padding: 24rpx;
  border-bottom: 1rpx solid #f3f4f6;
}

.device-item:last-child {
  border-bottom: none;
}

.device-info {
  flex: 1;
  margin-left: 16rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.device-name {
  font-size: 14px;
  color: #1f2937;
}

.device-status {
  font-size: 12px;
  color: #22c55e;
  padding: 4rpx 12rpx;
  background: #f0fdf4;
  border-radius: 4rpx;
}

.settings-section {
  margin-top: 20rpx;
}

.setting-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx;
  background: #f9fafb;
  border-radius: 12rpx;
  margin-bottom: 16rpx;
}

.setting-label {
  font-size: 14px;
  color: #4b5563;
}

.setting-picker {
  display: flex;
  align-items: center;
}

.picker-value {
  font-size: 14px;
  color: #1f2937;
}

.picker-arrow {
  font-size: 14px;
  color: #9ca3af;
  margin-left: 12rpx;
}

.popup-footer {
  display: flex;
  gap: 20rpx;
  padding: 20rpx 30rpx;
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
  border-top: 1rpx solid #e5e7eb;
}

.btn-cancel {
  flex: 1;
  height: 88rpx;
  background: #f3f4f6;
  border: none;
  border-radius: 12rpx;
  font-size: 16px;
  color: #4b5563;
}

.btn-confirm {
  flex: 1;
  height: 88rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12rpx;
  font-size: 16px;
  color: #ffffff;
  font-weight: 600;
}
</style>

<template>
  <view class="merchant-home">
    <!-- 顶部导航栏 -->
    <view class="header">
      <view class="header-content">
        <view class="header-left">
          <text class="header-title">商家助手</text>
          <text class="header-subtitle">{{ merchantInfo.name || '小魔推' }}</text>
        </view>
        <view class="header-right" @tap="goToProfile">
          <image
            class="avatar"
            :src="userInfo.avatar || '/static/default-avatar.png'"
            mode="aspectFill"
          />
        </view>
      </view>
    </view>

    <!-- 今日数据 -->
    <view class="data-section">
      <view class="section-header">
        <text class="section-title">今日数据</text>
        <text class="section-more" @tap="goToStatistics">详情 ></text>
      </view>
      <view class="data-cards">
        <view class="data-card">
          <text class="data-value">{{ todayData.triggerCount }}</text>
          <text class="data-label">触发数</text>
        </view>
        <view class="data-card">
          <text class="data-value">{{ todayData.publishCount }}</text>
          <text class="data-label">发布确认</text>
        </view>
        <view class="data-card">
          <text class="data-value">{{ todayData.rewardCount }}</text>
          <text class="data-label">奖励发放</text>
        </view>
      </view>
    </view>

    <!-- 快捷功能 -->
    <view class="quick-section">
      <view class="section-header">
        <text class="section-title">快捷功能</text>
      </view>
      <view class="quick-grid">
        <view class="quick-item" @tap="goToCapture">
          <view class="quick-icon">
            <text class="icon-emoji">📷</text>
          </view>
          <text class="quick-label">拍素材</text>
        </view>
        <view class="quick-item" @tap="goToCompose">
          <view class="quick-icon">
            <text class="icon-emoji">🎬</text>
          </view>
          <text class="quick-label">合成视频</text>
        </view>
        <view class="quick-item" @tap="goToCreateCampaign">
          <view class="quick-icon">
            <text class="icon-emoji">📢</text>
          </view>
          <text class="quick-label">创建活动</text>
        </view>
        <view class="quick-item" @tap="goToStatistics">
          <view class="quick-icon">
            <text class="icon-emoji">📊</text>
          </view>
          <text class="quick-label">看数据</text>
        </view>
      </view>
    </view>

    <!-- 我的素材 -->
    <view class="material-section">
      <view class="section-header">
        <text class="section-title">我的素材</text>
        <text class="section-more" @tap="goToMaterial">全部 ></text>
      </view>
      <scroll-view class="material-scroll" scroll-x>
        <view class="material-list">
          <view
            v-for="item in recentMaterials"
            :key="item.id"
            class="material-item"
            @tap="previewMaterial(item)"
          >
            <image
              v-if="item.type === 'image'"
              class="material-cover"
              :src="item.url"
              mode="aspectFill"
            />
            <view v-else class="material-cover video-cover">
              <text class="video-icon">▶</text>
            </view>
          </view>
          <view class="material-item add-item" @tap="goToCapture">
            <text class="add-icon">+</text>
            <text class="add-text">上传</text>
          </view>
        </view>
      </scroll-view>
    </view>

    <!-- 常用功能 -->
    <view class="function-section">
      <view class="section-header">
        <text class="section-title">常用功能</text>
      </view>
      <view class="function-list">
        <view class="function-item" @tap="goToCampaign">
          <text class="function-icon">📢</text>
          <text class="function-label">活动管理</text>
          <text class="function-arrow">></text>
        </view>
        <view class="function-item" @tap="goToDevices">
          <text class="function-icon">📱</text>
          <text class="function-label">设备管理</text>
          <text class="function-arrow">></text>
        </view>
        <view class="function-item" @tap="goToContent">
          <text class="function-icon">📝</text>
          <text class="function-label">内容管理</text>
          <text class="function-arrow">></text>
        </view>
        <view class="function-item" @tap="goToTemplate">
          <text class="function-icon">📄</text>
          <text class="function-label">模板管理</text>
          <text class="function-arrow">></text>
        </view>
        <view class="function-item" @tap="goToSettings">
          <text class="function-icon">⚙️</text>
          <text class="function-label">系统设置</text>
          <text class="function-arrow">></text>
        </view>
      </view>
    </view>

    <!-- 底部安全区 -->
    <view class="safe-area-bottom"></view>
  </view>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import { useUserStore } from '../../stores/user.js'
import api from '../../api/index.js'

export default {
  name: 'MerchantHome',
  setup() {
    const userStore = useUserStore()

    // 用户信息
    const userInfo = ref(userStore.userInfo || {})

    // 商户信息
    const merchantInfo = ref({})

    // 今日数据
    const todayData = reactive({
      triggerCount: 0,
      publishCount: 0,
      rewardCount: 0
    })

    // 最近素材
    const recentMaterials = ref([])

    // 加载状态
    const loading = ref(false)

    /**
     * 加载首页数据
     */
    const loadHomeData = async () => {
      loading.value = true
      try {
        // 并行请求多个接口
        const [statsRes, materialsRes] = await Promise.allSettled([
          api.statistics.getOverview?.({ type: 'today' }) || Promise.resolve(generateMockStats()),
          api.promoMaterial.getList?.({ page: 1, pageSize: 6 }) || Promise.resolve({ data: generateMockMaterials() })
        ])

        // 处理统计数据
        if (statsRes.status === 'fulfilled' && statsRes.value) {
          const stats = statsRes.value
          todayData.triggerCount = stats.trigger_count || stats.triggerCount || 0
          todayData.publishCount = stats.publish_count || stats.publishCount || 0
          todayData.rewardCount = stats.reward_count || stats.rewardCount || 0
        } else {
          Object.assign(todayData, generateMockStats())
        }

        // 处理素材数据
        if (materialsRes.status === 'fulfilled') {
          recentMaterials.value = materialsRes.value.data || materialsRes.value.list || []
        } else {
          recentMaterials.value = generateMockMaterials()
        }

        // 获取商户信息
        if (userStore.userInfo.merchant_id) {
          merchantInfo.value = userStore.userInfo
        } else {
          merchantInfo.value = { name: '小魔推示范店' }
        }

      } catch (error) {
        console.error('加载首页数据失败:', error)
        // 使用模拟数据
        Object.assign(todayData, generateMockStats())
        recentMaterials.value = generateMockMaterials()
        merchantInfo.value = { name: '小魔推示范店' }
      } finally {
        loading.value = false
      }
    }

    /**
     * 生成模拟统计数据
     */
    const generateMockStats = () => ({
      triggerCount: Math.floor(Math.random() * 100),
      publishCount: Math.floor(Math.random() * 50),
      rewardCount: Math.floor(Math.random() * 30)
    })

    /**
     * 生成模拟素材数据
     */
    const generateMockMaterials = () => {
      return Array.from({ length: 5 }, (_, i) => ({
        id: i + 1,
        type: i % 2 === 0 ? 'image' : 'video',
        url: `https://via.placeholder.com/200x200?text=Material${i + 1}`,
        created_at: new Date().toISOString()
      }))
    }

    // 页面跳转方法
    const goToCapture = () => {
      uni.navigateTo({ url: '/pages/merchant/capture' })
    }

    const goToMaterial = () => {
      uni.navigateTo({ url: '/pages/merchant/material' })
    }

    const goToCompose = () => {
      uni.navigateTo({ url: '/pages/merchant/compose' })
    }

    const goToScanDevice = () => {
      // #ifdef MP-WEIXIN || MP-ALIPAY
      uni.scanCode({
        success: (res) => {
          console.log('扫码结果:', res)
          // 处理设备码
          uni.navigateTo({ url: `/pages/nfc/trigger?code=${res.result}` })
        }
      })
      // #endif
      // #ifdef H5
      uni.showToast({ title: 'H5端请使用小程序扫码', icon: 'none' })
      // #endif
    }

    const goToCampaign = () => {
      uni.navigateTo({ url: '/pages/merchant/campaign' })
    }

    const goToCreateCampaign = () => {
      uni.navigateTo({ url: '/pages/merchant/campaign-edit' })
    }

    const goToStatistics = () => {
      uni.navigateTo({ url: '/pages/merchant/stats' })
    }

    const goToDevices = () => {
      uni.navigateTo({ url: '/pages/merchant/devices' })
    }

    const goToContent = () => {
      uni.navigateTo({ url: '/pages/content/generate' })
    }

    const goToTemplate = () => {
      uni.navigateTo({ url: '/pages/template/list' })
    }

    const goToSettings = () => {
      uni.navigateTo({ url: '/pages/user/settings' })
    }

    const goToProfile = () => {
      uni.navigateTo({ url: '/pages/user/profile' })
    }

    const previewMaterial = (item) => {
      if (item.type === 'image') {
        uni.previewImage({
          urls: [item.url],
          current: item.url
        })
      } else {
        uni.showToast({ title: '视频预览功能开发中', icon: 'none' })
      }
    }

    onMounted(() => {
      loadHomeData()
    })

    return {
      userInfo,
      merchantInfo,
      todayData,
      recentMaterials,
      loading,
      goToCapture,
      goToMaterial,
      goToCompose,
      goToScanDevice,
      goToCampaign,
      goToCreateCampaign,
      goToStatistics,
      goToDevices,
      goToContent,
      goToTemplate,
      goToSettings,
      goToProfile,
      previewMaterial
    }
  }
}
</script>

<style lang="scss" scoped>
.merchant-home {
  min-height: 100vh;
  background: #f5f5f5;
}

/* 顶部导航栏 */
.header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 0 30rpx;
  padding-top: calc(env(safe-area-inset-top) + 20rpx);
  padding-bottom: 40rpx;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-left {
  display: flex;
  flex-direction: column;
}

.header-title {
  font-size: 24px;
  font-weight: bold;
  color: #ffffff;
}

.header-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
  margin-top: 8rpx;
}

.header-right {
  display: flex;
  align-items: center;
}

.avatar {
  width: 80rpx;
  height: 80rpx;
  border-radius: 50%;
  border: 4rpx solid rgba(255, 255, 255, 0.3);
}

/* 今日数据 */
.data-section {
  margin: -30rpx 30rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.section-more {
  font-size: 14px;
  color: #6366f1;
}

.data-cards {
  display: flex;
  justify-content: space-between;
}

.data-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 0;
}

.data-value {
  font-size: 28px;
  font-weight: bold;
  color: #6366f1;
}

.data-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 8rpx;
}

/* 快捷功能 */
.quick-section {
  margin: 0 30rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
}

.quick-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20rpx;
}

.quick-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 0;
}

.quick-icon {
  width: 100rpx;
  height: 100rpx;
  background: linear-gradient(135deg, #f0f0ff 0%, #e8e8ff 100%);
  border-radius: 24rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12rpx;
}

.icon-emoji {
  font-size: 40rpx;
}

.quick-label {
  font-size: 12px;
  color: #4b5563;
}

/* 我的素材 */
.material-section {
  margin: 0 30rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
}

.material-scroll {
  white-space: nowrap;
  margin: 0 -10rpx;
}

.material-list {
  display: inline-flex;
  gap: 20rpx;
  padding: 10rpx;
}

.material-item {
  width: 160rpx;
  height: 160rpx;
  border-radius: 12rpx;
  overflow: hidden;
  flex-shrink: 0;
}

.material-cover {
  width: 100%;
  height: 100%;
}

.video-cover {
  background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.video-icon {
  font-size: 40rpx;
  color: #ffffff;
}

.add-item {
  background: #f3f4f6;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2rpx dashed #d1d5db;
}

.add-icon {
  font-size: 48rpx;
  color: #9ca3af;
  line-height: 1;
}

.add-text {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 8rpx;
}

/* 常用功能 */
.function-section {
  margin: 0 30rpx 20rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 10rpx 0;
}

.function-list {
  display: flex;
  flex-direction: column;
}

.function-item {
  display: flex;
  align-items: center;
  padding: 30rpx;
  border-bottom: 1rpx solid #f3f4f6;
}

.function-item:last-child {
  border-bottom: none;
}

.function-icon {
  font-size: 20px;
  margin-right: 20rpx;
}

.function-label {
  flex: 1;
  font-size: 16px;
  color: #1f2937;
}

.function-arrow {
  font-size: 16px;
  color: #9ca3af;
}

/* 底部安全区 */
.safe-area-bottom {
  height: calc(120rpx + env(safe-area-inset-bottom));
}
</style>

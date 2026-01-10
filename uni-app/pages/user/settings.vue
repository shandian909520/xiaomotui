<template>
  <view class="settings-container">
    <!-- 导航栏 -->
    <view class="navbar">
      <image
        class="nav-back"
        src="/static/icon/back.png"
        @tap="goBack"
      />
      <text class="nav-title">系统设置</text>
    </view>

    <!-- 设置内容 -->
    <scroll-view class="settings-content" scroll-y>

      <!-- 通用设置 -->
      <view class="settings-section">
        <view class="section-title">通用设置</view>

        <view class="settings-item" @tap="handleLanguage">
          <view class="item-left">
            <text class="item-icon">🌐</text>
            <text class="item-label">语言</text>
          </view>
          <view class="item-right">
            <text class="item-value">简体中文</text>
            <text class="item-arrow">›</text>
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">🌙</text>
            <text class="item-label">深色模式</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.darkMode"
              @change="toggleDarkMode"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📱</text>
            <text class="item-label">自动更新</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.autoUpdate"
              @change="toggleAutoUpdate"
              color="#6366f1"
            />
          </view>
        </view>
      </view>

      <!-- 通知设置 -->
      <view class="settings-section">
        <view class="section-title">通知设置</view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">🔔</text>
            <text class="item-label">推送通知</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.pushNotification"
              @change="togglePushNotification"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📧</text>
            <text class="item-label">邮件通知</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.emailNotification"
              @change="toggleEmailNotification"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">💬</text>
            <text class="item-label">消息提醒</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.messageAlert"
              @change="toggleMessageAlert"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">🔊</text>
            <text class="item-label">提示音</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.sound"
              @change="toggleSound"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📳</text>
            <text class="item-label">振动</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.vibration"
              @change="toggleVibration"
              color="#6366f1"
            />
          </view>
        </view>
      </view>

      <!-- 隐私设置 -->
      <view class="settings-section">
        <view class="section-title">隐私设置</view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">🔒</text>
            <text class="item-label">隐私保护</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.privacyProtection"
              @change="togglePrivacyProtection"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📍</text>
            <text class="item-label">位置服务</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.locationService"
              @change="toggleLocationService"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item" @tap="handlePrivacyPolicy">
          <view class="item-left">
            <text class="item-icon">📄</text>
            <text class="item-label">隐私政策</text>
          </view>
          <view class="item-right">
            <text class="item-arrow">›</text>
          </view>
        </view>

        <view class="settings-item" @tap="handleUserAgreement">
          <view class="item-left">
            <text class="item-icon">📋</text>
            <text class="item-label">用户协议</text>
          </view>
          <view class="item-right">
            <text class="item-arrow">›</text>
          </view>
        </view>
      </view>

      <!-- 存储管理 -->
      <view class="settings-section">
        <view class="section-title">存储管理</view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">💾</text>
            <text class="item-label">缓存大小</text>
          </view>
          <view class="item-right">
            <text class="item-value">{{ cacheSize }}</text>
          </view>
        </view>

        <view class="settings-item" @tap="clearCache">
          <view class="item-left">
            <text class="item-icon">🗑️</text>
            <text class="item-label">清除缓存</text>
          </view>
          <view class="item-right">
            <text class="item-arrow">›</text>
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📦</text>
            <text class="item-label">自动清理</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.autoClean"
              @change="toggleAutoClean"
              color="#6366f1"
            />
          </view>
        </view>
      </view>

      <!-- 内容设置 -->
      <view class="settings-section">
        <view class="section-title">内容设置</view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">🎨</text>
            <text class="item-label">图片质量</text>
          </view>
          <view class="item-right">
            <text class="item-value">{{ imageQualityText }}</text>
            <text class="item-arrow">›</text>
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📹</text>
            <text class="item-label">视频自动播放</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.autoPlayVideo"
              @change="toggleAutoPlayVideo"
              color="#6366f1"
            />
          </view>
        </view>

        <view class="settings-item">
          <view class="item-left">
            <text class="item-icon">📶</text>
            <text class="item-label">仅WiFi下载</text>
          </view>
          <view class="item-right">
            <switch
              :checked="settings.wifiOnly"
              @change="toggleWifiOnly"
              color="#6366f1"
            />
          </view>
        </view>
      </view>

      <!-- 关于 -->
      <view class="settings-section">
        <view class="section-title">关于</view>

        <view class="settings-item" @tap="checkUpdate">
          <view class="item-left">
            <text class="item-icon">🔄</text>
            <text class="item-label">检查更新</text>
          </view>
          <view class="item-right">
            <text class="item-value">v{{ appVersion }}</text>
            <text class="item-arrow">›</text>
          </view>
        </view>

        <view class="settings-item" @tap="handleFeedback">
          <view class="item-left">
            <text class="item-icon">💬</text>
            <text class="item-label">意见反馈</text>
          </view>
          <view class="item-right">
            <text class="item-arrow">›</text>
          </view>
        </view>

        <view class="settings-item" @tap="handleAbout">
          <view class="item-left">
            <text class="item-icon">ℹ️</text>
            <text class="item-label">关于我们</text>
          </view>
          <view class="item-right">
            <text class="item-arrow">›</text>
          </view>
        </view>
      </view>

      <!-- 底部安全区 -->
      <view class="safe-area-bottom"></view>
    </scroll-view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      // 设置项
      settings: {
        darkMode: false,
        autoUpdate: true,
        pushNotification: true,
        emailNotification: false,
        messageAlert: true,
        sound: true,
        vibration: true,
        privacyProtection: true,
        locationService: false,
        autoClean: false,
        autoPlayVideo: false,
        wifiOnly: true,
        imageQuality: 'high' // low, medium, high
      },

      // 缓存大小
      cacheSize: '0 MB',

      // 应用版本
      appVersion: '1.0.0'
    }
  },

  computed: {
    imageQualityText() {
      const map = {
        low: '低',
        medium: '中',
        high: '高'
      }
      return map[this.settings.imageQuality] || '高'
    }
  },

  onLoad(options) {
    console.log('系统设置页面加载:', options)
    this.loadSettings()
    this.calculateCacheSize()
  },

  methods: {
    /**
     * 返回
     */
    goBack() {
      uni.navigateBack()
    },

    /**
     * 加载设置
     */
    loadSettings() {
      try {
        const savedSettings = uni.getStorageSync('appSettings')
        if (savedSettings) {
          this.settings = { ...this.settings, ...savedSettings }
        }
      } catch (error) {
        console.error('加载设置失败:', error)
      }
    },

    /**
     * 保存设置
     */
    saveSettings() {
      try {
        uni.setStorageSync('appSettings', this.settings)
        console.log('设置已保存')
      } catch (error) {
        console.error('保存设置失败:', error)
      }
    },

    /**
     * 切换深色模式
     */
    toggleDarkMode(e) {
      this.settings.darkMode = e.detail.value
      this.saveSettings()

      uni.showToast({
        title: this.settings.darkMode ? '已开启深色模式' : '已关闭深色模式',
        icon: 'none'
      })
    },

    /**
     * 切换自动更新
     */
    toggleAutoUpdate(e) {
      this.settings.autoUpdate = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换推送通知
     */
    togglePushNotification(e) {
      this.settings.pushNotification = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换邮件通知
     */
    toggleEmailNotification(e) {
      this.settings.emailNotification = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换消息提醒
     */
    toggleMessageAlert(e) {
      this.settings.messageAlert = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换提示音
     */
    toggleSound(e) {
      this.settings.sound = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换振动
     */
    toggleVibration(e) {
      this.settings.vibration = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换隐私保护
     */
    togglePrivacyProtection(e) {
      this.settings.privacyProtection = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换位置服务
     */
    toggleLocationService(e) {
      this.settings.locationService = e.detail.value
      this.saveSettings()

      uni.showToast({
        title: this.settings.locationService ? '位置服务已开启' : '位置服务已关闭',
        icon: 'none'
      })
    },

    /**
     * 切换自动清理
     */
    toggleAutoClean(e) {
      this.settings.autoClean = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换视频自动播放
     */
    toggleAutoPlayVideo(e) {
      this.settings.autoPlayVideo = e.detail.value
      this.saveSettings()
    },

    /**
     * 切换仅WiFi下载
     */
    toggleWifiOnly(e) {
      this.settings.wifiOnly = e.detail.value
      this.saveSettings()
    },

    /**
     * 计算缓存大小
     */
    calculateCacheSize() {
      // 模拟计算缓存大小
      const randomSize = (Math.random() * 100).toFixed(2)
      this.cacheSize = `${randomSize} MB`
    },

    /**
     * 清除缓存
     */
    async clearCache() {
      const res = await uni.showModal({
        title: '清除缓存',
        content: '确定要清除所有缓存吗?'
      })

      if (!res.confirm) return

      uni.showLoading({ title: '清除中...', mask: true })

      setTimeout(() => {
        uni.hideLoading()

        this.cacheSize = '0 MB'

        uni.showToast({
          title: '缓存已清除',
          icon: 'success'
        })
      }, 1000)
    },

    /**
     * 语言设置
     */
    handleLanguage() {
      uni.showToast({
        title: '暂时只支持简体中文',
        icon: 'none'
      })
    },

    /**
     * 隐私政策
     */
    handlePrivacyPolicy() {
      uni.showToast({
        title: '跳转到隐私政策页面',
        icon: 'none'
      })
    },

    /**
     * 用户协议
     */
    handleUserAgreement() {
      uni.showToast({
        title: '跳转到用户协议页面',
        icon: 'none'
      })
    },

    /**
     * 检查更新
     */
    checkUpdate() {
      uni.showLoading({ title: '检查中...', mask: true })

      setTimeout(() => {
        uni.hideLoading()

        uni.showModal({
          title: '检查更新',
          content: '当前已是最新版本 v' + this.appVersion,
          showCancel: false
        })
      }, 1500)
    },

    /**
     * 意见反馈
     */
    handleFeedback() {
      uni.showToast({
        title: '跳转到意见反馈页面',
        icon: 'none'
      })
    },

    /**
     * 关于我们
     */
    handleAbout() {
      uni.showModal({
        title: '关于小魔推',
        content: `小魔推碰一碰\n版本: ${this.appVersion}\n\n智能营销内容生成与管理平台`,
        showCancel: false
      })
    }
  }
}
</script>

<style scoped>
.settings-container {
  min-height: 100vh;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
}

/* 导航栏 */
.navbar {
  position: sticky;
  top: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  padding: 20rpx 30rpx;
  background: #fff;
  border-bottom: 1rpx solid #e5e7eb;
}

.nav-back {
  width: 40rpx;
  height: 40rpx;
  margin-right: 20rpx;
}

.nav-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

/* 设置内容 */
.settings-content {
  flex: 1;
  padding: 20rpx 0;
}

/* 设置区域 */
.settings-section {
  margin-bottom: 20rpx;
  background: #fff;
}

.section-title {
  padding: 30rpx 30rpx 20rpx;
  font-size: 14px;
  font-weight: 600;
  color: #6b7280;
}

/* 设置项 */
.settings-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx;
  border-bottom: 1rpx solid #f3f4f6;
  background: #fff;
  transition: background 0.2s;
}

.settings-item:active {
  background: #f9fafb;
}

.settings-item:last-child {
  border-bottom: none;
}

.item-left {
  display: flex;
  align-items: center;
  flex: 1;
}

.item-icon {
  font-size: 20px;
  margin-right: 20rpx;
}

.item-label {
  font-size: 16px;
  color: #1f2937;
}

.item-right {
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.item-value {
  font-size: 14px;
  color: #6b7280;
}

.item-arrow {
  font-size: 20px;
  color: #9ca3af;
}

/* 底部安全区 */
.safe-area-bottom {
  height: 40rpx;
}
</style>

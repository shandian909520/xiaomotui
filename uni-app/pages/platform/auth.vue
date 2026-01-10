<template>
  <view class="platform-auth-page">
    <!-- 导航栏 -->
    <view class="nav-bar">
      <view class="nav-back" @tap="goBack">
        <text class="back-icon">←</text>
      </view>
      <view class="nav-title">平台授权</view>
      <view class="nav-action"></view>
    </view>

    <!-- 顶部说明 -->
    <view class="auth-tips">
      <view class="tip-icon">🔐</view>
      <view class="tip-title">授权说明</view>
      <view class="tip-content">
        授权后，小魔推将获得在选定平台发布内容的权限。您的账号信息将被安全保存，可随时取消授权。
      </view>
    </view>

    <!-- 平台列表 -->
    <view class="platform-list">
      <view
        v-for="platform in platforms"
        :key="platform.value"
        class="platform-card"
        :class="{ disabled: platform.disabled }"
        @tap="selectPlatform(platform)"
      >
        <view class="platform-header">
          <view class="platform-icon-wrapper">
            <text class="platform-icon">{{ platform.icon }}</text>
            <view v-if="platform.disabled" class="coming-soon-badge">敬请期待</view>
          </view>
          <view class="platform-info">
            <view class="platform-name">{{ platform.name }}</view>
            <view class="platform-desc">{{ platform.desc }}</view>
          </view>
        </view>

        <view class="platform-footer">
          <view class="platform-stats">
            <view class="stat-item">
              <text class="stat-value">{{ platform.users || '500万+' }}</text>
              <text class="stat-label">用户</text>
            </view>
            <view class="stat-item">
              <text class="stat-value">{{ platform.dailyActive || '100万+' }}</text>
              <text class="stat-label">日活</text>
            </view>
          </view>
          <button class="auth-btn" :class="{ disabled: platform.disabled }">
            {{ platform.disabled ? '即将上线' : '立即授权' }}
          </button>
        </view>
      </view>
    </view>

    <!-- 授权引导弹窗 -->
    <view class="auth-guide-modal" v-if="showGuide" @tap="closeGuide">
      <view class="modal-content" @tap.stop="">
        <view class="modal-header">
          <text class="modal-title">{{ selectedPlatform?.name }}授权指南</text>
          <view class="modal-close" @tap="closeGuide">×</view>
        </view>

        <view class="modal-body">
          <view class="guide-step" v-for="(step, index) in guideSteps" :key="index">
            <view class="step-number">{{ index + 1 }}</view>
            <view class="step-content">
              <view class="step-title">{{ step.title }}</view>
              <view class="step-desc">{{ step.desc }}</view>
              <image
                v-if="step.image"
                class="step-image"
                :src="step.image"
                mode="aspectFit"
              />
            </view>
          </view>

          <!-- 注意事项 -->
          <view class="guide-notes">
            <view class="notes-title">📌 注意事项</view>
            <view class="note-item" v-for="(note, index) in authNotes" :key="index">
              • {{ note }}
            </view>
          </view>
        </view>

        <view class="modal-footer">
          <button class="modal-btn btn-cancel" @tap="closeGuide">稍后授权</button>
          <button class="modal-btn btn-confirm" @tap="startAuth">开始授权</button>
        </view>
      </view>
    </view>

    <!-- 已授权账号列表 -->
    <view class="authorized-section" v-if="authorizedAccounts.length > 0">
      <view class="section-title">
        <text class="title-icon">✓</text>
        <text class="title-text">已授权账号</text>
      </view>

      <view class="account-list">
        <view
          v-for="account in authorizedAccounts"
          :key="account.id"
          class="account-item"
        >
          <view class="account-header">
            <text class="account-platform-icon">{{ getPlatformIcon(account.platform) }}</text>
            <view class="account-info">
              <view class="account-name">{{ account.nickname || account.account_name }}</view>
              <view class="account-meta">
                <text class="meta-item">{{ getPlatformName(account.platform) }}</text>
                <view class="status-badge" :class="`status-${account.status.toLowerCase()}`">
                  {{ getStatusText(account.status) }}
                </view>
              </view>
            </view>
          </view>

          <view class="account-actions">
            <button
              v-if="account.status !== 'ACTIVE'"
              class="action-btn btn-refresh"
              @tap="refreshAuth(account)"
            >
              刷新
            </button>
            <button class="action-btn btn-remove" @tap="removeAuth(account)">
              解绑
            </button>
          </view>
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
      // 平台列表
      platforms: [
        {
          value: 'douyin',
          name: '抖音',
          icon: '🎵',
          desc: '短视频社交平台，年轻人的聚集地',
          users: '8亿+',
          dailyActive: '6亿+',
          disabled: false
        },
        {
          value: 'xiaohongshu',
          name: '小红书',
          icon: '📕',
          desc: '生活方式分享平台，种草神器',
          users: '3亿+',
          dailyActive: '1亿+',
          disabled: false
        },
        {
          value: 'kuaishou',
          name: '快手',
          icon: '⚡',
          desc: '记录生活，分享快乐',
          users: '6亿+',
          dailyActive: '3亿+',
          disabled: true
        },
        {
          value: 'weibo',
          name: '微博',
          icon: '📰',
          desc: '热点资讯，实时互动',
          users: '5亿+',
          dailyActive: '2亿+',
          disabled: true
        },
        {
          value: 'bilibili',
          name: 'B站',
          icon: '📺',
          desc: '年轻人的文化社区',
          users: '3亿+',
          dailyActive: '8000万+',
          disabled: true
        }
      ],

      // 选中的平台
      selectedPlatform: null,

      // 显示授权指南
      showGuide: false,

      // 已授权账号
      authorizedAccounts: [],

      // 授权步骤（动态生成）
      guideSteps: [],

      // 注意事项（动态生成）
      authNotes: []
    }
  },

  onLoad() {
    this.loadAuthorizedAccounts()
  },

  methods: {
    /**
     * 返回
     */
    goBack() {
      uni.navigateBack()
    },

    /**
     * 加载已授权账号
     */
    async loadAuthorizedAccounts() {
      try {
        const res = await api.publish.getAccounts()
        this.authorizedAccounts = res.data || res.list || []
      } catch (error) {
        console.error('加载已授权账号失败:', error)
      }
    },

    /**
     * 选择平台
     */
    selectPlatform(platform) {
      if (platform.disabled) {
        FeedbackHelper.warning('该平台即将上线，敬请期待', { vibrate: false })
        return
      }

      this.selectedPlatform = platform
      this.generateGuide(platform)
      this.showGuide = true
    },

    /**
     * 生成授权指南
     */
    generateGuide(platform) {
      // 根据平台生成不同的授权步骤
      const guides = {
        douyin: [
          {
            title: '打开抖音开放平台',
            desc: '访问 open.douyin.com，使用抖音APP扫码登录',
            image: ''
          },
          {
            title: '创建应用',
            desc: '在"应用管理"中创建新应用，选择"移动应用"类型',
            image: ''
          },
          {
            title: '获取授权',
            desc: '配置回调地址后，点击下方"开始授权"按钮，跳转到抖音完成授权',
            image: ''
          },
          {
            title: '完成绑定',
            desc: '授权成功后，系统将自动保存您的账号信息',
            image: ''
          }
        ],
        xiaohongshu: [
          {
            title: '访问小红书开放平台',
            desc: '打开 open.xiaohongshu.com，使用小红书账号登录',
            image: ''
          },
          {
            title: '申请权限',
            desc: '在"权限管理"中申请"内容发布"权限',
            image: ''
          },
          {
            title: '授权登录',
            desc: '点击下方"开始授权"，使用小红书APP扫码完成授权',
            image: ''
          },
          {
            title: '绑定完成',
            desc: '授权成功后即可在小魔推发布内容到小红书',
            image: ''
          }
        ],
        kuaishou: [
          {
            title: '打开快手开放平台',
            desc: '访问 open.kuaishou.com',
            image: ''
          },
          {
            title: '完成授权',
            desc: '使用快手APP扫码授权',
            image: ''
          }
        ]
      }

      this.guideSteps = guides[platform.value] || []

      // 生成注意事项
      this.authNotes = [
        '授权过程中请确保网络畅通',
        '授权有效期通常为30-90天，过期后需重新授权',
        '首次授权可能需要等待平台审核',
        '可随时在此页面查看和管理已授权账号'
      ]
    },

    /**
     * 关闭引导
     */
    closeGuide() {
      this.showGuide = false
      this.selectedPlatform = null
    },

    /**
     * 开始授权
     */
    async startAuth() {
      if (!this.selectedPlatform) return

      try {
        FeedbackHelper.loading('获取授权链接...')

        // 获取授权URL
        const res = await api.publish.getPlatformAuthUrl(this.selectedPlatform.value)

        FeedbackHelper.hideLoading()

        if (res.auth_url) {
          // 在H5环境下，可以直接跳转
          // #ifdef H5
          window.location.href = res.auth_url
          // #endif

          // 在小程序环境下，需要使用web-view或其他方式
          // #ifdef MP
          FeedbackHelper.warning('请在浏览器中完成授权', {
            duration: 3000
          })
          // 可以显示二维码或复制链接
          uni.setClipboardData({
            data: res.auth_url,
            success: () => {
              FeedbackHelper.success('授权链接已复制，请在浏览器中打开')
            }
          })
          // #endif

          this.closeGuide()
        } else {
          FeedbackHelper.error('获取授权链接失败')
        }
      } catch (error) {
        FeedbackHelper.hideLoading()
        FeedbackHelper.error('获取授权链接失败：' + (error.message || '未知错误'))
      }
    },

    /**
     * 刷新授权
     */
    async refreshAuth(account) {
      const confirmed = await FeedbackHelper.confirm(
        '刷新授权',
        `确定要刷新"${account.nickname || account.account_name}"的授权吗？`
      )

      if (!confirmed) return

      try {
        await api.publish.refreshAccountToken(account.id)
        FeedbackHelper.success('授权已刷新', { vibrate: true })
        this.loadAuthorizedAccounts()
      } catch (error) {
        FeedbackHelper.error('刷新失败：' + (error.message || '未知错误'))
      }
    },

    /**
     * 解绑授权
     */
    async removeAuth(account) {
      const confirmed = await FeedbackHelper.confirm(
        '解绑账号',
        `确定要解绑"${account.nickname || account.account_name}"吗？`,
        {
          confirmText: '解绑',
          confirmColor: '#FF3B30'
        }
      )

      if (!confirmed) return

      try {
        await api.publish.deleteAccount(account.id)
        FeedbackHelper.success('已解绑', { vibrate: true })
        this.loadAuthorizedAccounts()
      } catch (error) {
        FeedbackHelper.error('解绑失败：' + (error.message || '未知错误'))
      }
    },

    /**
     * 获取平台图标
     */
    getPlatformIcon(platform) {
      const icons = {
        douyin: '🎵',
        xiaohongshu: '📕',
        kuaishou: '⚡',
        weibo: '📰',
        bilibili: '📺'
      }
      return icons[platform] || '📱'
    },

    /**
     * 获取平台名称
     */
    getPlatformName(platform) {
      const names = {
        douyin: '抖音',
        xiaohongshu: '小红书',
        kuaishou: '快手',
        weibo: '微博',
        bilibili: 'B站'
      }
      return names[platform] || platform
    },

    /**
     * 获取状态文本
     */
    getStatusText(status) {
      const texts = {
        'ACTIVE': '已授权',
        'EXPIRED': '已过期',
        'INVALID': '已失效'
      }
      return texts[status] || status
    }
  }
}
</script>

<style scoped>
.platform-auth-page {
  min-height: 100vh;
  background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%);
}

/* 导航栏 */
.nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 30rpx;
  background-color: #fff;
  border-bottom: 1px solid #eee;
}

.nav-back {
  width: 80rpx;
}

.back-icon {
  font-size: 40rpx;
  color: #333;
}

.nav-title {
  flex: 1;
  text-align: center;
  font-size: 36rpx;
  font-weight: bold;
  color: #333;
}

.nav-action {
  width: 80rpx;
}

/* 顶部说明 */
.auth-tips {
  margin: 30rpx;
  padding: 40rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20rpx;
  color: #fff;
}

.tip-icon {
  font-size: 64rpx;
  text-align: center;
  margin-bottom: 20rpx;
}

.tip-title {
  font-size: 32rpx;
  font-weight: bold;
  text-align: center;
  margin-bottom: 15rpx;
}

.tip-content {
  font-size: 26rpx;
  line-height: 1.6;
  opacity: 0.9;
}

/* 平台列表 */
.platform-list {
  padding: 30rpx;
}

.platform-card {
  background-color: #fff;
  border-radius: 20rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 2rpx 10rpx rgba(0,0,0,0.05);
}

.platform-card.disabled {
  opacity: 0.6;
}

.platform-header {
  display: flex;
  align-items: center;
  gap: 20rpx;
  margin-bottom: 30rpx;
}

.platform-icon-wrapper {
  position: relative;
}

.platform-icon {
  font-size: 80rpx;
}

.coming-soon-badge {
  position: absolute;
  top: -10rpx;
  right: -10rpx;
  padding: 4rpx 12rpx;
  background-color: #ff6b6b;
  color: #fff;
  font-size: 20rpx;
  border-radius: 10rpx;
}

.platform-info {
  flex: 1;
}

.platform-name {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 10rpx;
}

.platform-desc {
  font-size: 26rpx;
  color: #666;
}

.platform-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.platform-stats {
  display: flex;
  gap: 40rpx;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: 28rpx;
  font-weight: bold;
  color: #667eea;
}

.stat-label {
  font-size: 22rpx;
  color: #999;
  margin-top: 5rpx;
}

.auth-btn {
  padding: 20rpx 40rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-radius: 40rpx;
  border: none;
  font-size: 28rpx;
}

.auth-btn.disabled {
  background: #ddd;
  color: #999;
}

/* 授权引导弹窗 */
.auth-guide-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  width: 90%;
  max-height: 80%;
  background-color: #fff;
  border-radius: 20rpx;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx;
  border-bottom: 1px solid #eee;
}

.modal-title {
  font-size: 32rpx;
  font-weight: bold;
}

.modal-close {
  font-size: 48rpx;
  color: #999;
  line-height: 1;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 30rpx;
}

.guide-step {
  display: flex;
  gap: 20rpx;
  margin-bottom: 40rpx;
}

.step-number {
  width: 60rpx;
  height: 60rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  font-weight: bold;
  flex-shrink: 0;
}

.step-content {
  flex: 1;
}

.step-title {
  font-size: 30rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 10rpx;
}

.step-desc {
  font-size: 26rpx;
  color: #666;
  line-height: 1.6;
}

.step-image {
  width: 100%;
  height: 300rpx;
  margin-top: 20rpx;
  border-radius: 10rpx;
}

.guide-notes {
  margin-top: 40rpx;
  padding: 30rpx;
  background-color: #fff8dc;
  border-radius: 10rpx;
  border-left: 4rpx solid #ffa500;
}

.notes-title {
  font-size: 28rpx;
  font-weight: bold;
  color: #ff8800;
  margin-bottom: 20rpx;
}

.note-item {
  font-size: 26rpx;
  color: #666;
  line-height: 2;
}

.modal-footer {
  display: flex;
  gap: 20rpx;
  padding: 30rpx;
  border-top: 1px solid #eee;
}

.modal-btn {
  flex: 1;
  padding: 25rpx 0;
  font-size: 30rpx;
  border-radius: 10rpx;
  border: none;
}

.btn-cancel {
  background-color: #f5f5f5;
  color: #666;
}

.btn-confirm {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

/* 已授权账号 */
.authorized-section {
  margin: 30rpx;
  padding: 30rpx;
  background-color: #fff;
  border-radius: 20rpx;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-bottom: 30rpx;
}

.title-icon {
  font-size: 32rpx;
  color: #52c41a;
}

.title-text {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
}

.account-list {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.account-item {
  padding: 30rpx;
  background-color: #f8f9fa;
  border-radius: 15rpx;
}

.account-header {
  display: flex;
  align-items: center;
  gap: 20rpx;
  margin-bottom: 20rpx;
}

.account-platform-icon {
  font-size: 48rpx;
}

.account-info {
  flex: 1;
}

.account-name {
  font-size: 30rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 8rpx;
}

.account-meta {
  display: flex;
  align-items: center;
  gap: 15rpx;
}

.meta-item {
  font-size: 24rpx;
  color: #999;
}

.status-badge {
  padding: 4rpx 12rpx;
  font-size: 22rpx;
  border-radius: 10rpx;
}

.status-active {
  background-color: #f0f9ff;
  color: #1890ff;
}

.status-expired, .status-invalid {
  background-color: #fff1f0;
  color: #ff4d4f;
}

.account-actions {
  display: flex;
  gap: 15rpx;
  justify-content: flex-end;
}

.action-btn {
  padding: 15rpx 30rpx;
  font-size: 26rpx;
  border-radius: 10rpx;
  border: none;
}

.btn-refresh {
  background-color: #1890ff;
  color: #fff;
}

.btn-remove {
  background-color: #ff4d4f;
  color: #fff;
}
</style>

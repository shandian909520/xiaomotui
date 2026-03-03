<template>
  <view class="profile-page">
    <!-- 加载中 -->
    <skeleton type="profile" :loading="isLoading && !userInfo.id" />

    <!-- 内容区域 -->
    <view class="content-wrapper" v-if="!isLoading || userInfo.id">
      <!-- 用户信息卡片 -->
      <view class="user-card">
        <view class="user-header">
          <image class="user-avatar" :src="userInfo.avatar || defaultAvatar" mode="aspectFill" />
          <view class="user-info">
            <view class="user-name">{{ userInfo.nickname || '未设置昵称' }}</view>
            <view class="user-meta">
              <view class="member-badge" :class="`badge-${userInfo.member_level}`">
                {{ formatMemberLevel(userInfo.member_level) }}
              </view>
              <view class="user-phone" v-if="userInfo.phone">
                {{ formatPhone(userInfo.phone) }}
              </view>
            </view>
          </view>
          <view class="edit-btn" @tap="editProfile">
            <text class="edit-icon">✏️</text>
          </view>
        </view>

        <view class="user-stats">
          <view class="stat-item" @tap="navigateTo('/pages/user/points')">
            <view class="stat-value">{{ userInfo.points || 0 }}</view>
            <view class="stat-label">积分</view>
          </view>
          <view class="stat-divider"></view>
          <view class="stat-item" @tap="navigateTo('/pages/content/list')">
            <view class="stat-value">{{ userStats.content_count || 0 }}</view>
            <view class="stat-label">内容</view>
          </view>
          <view class="stat-divider"></view>
          <view class="stat-item" @tap="navigateTo('/pages/material/favorite')">
            <view class="stat-value">{{ userStats.favorite_count || 0 }}</view>
            <view class="stat-label">收藏</view>
          </view>
        </view>
      </view>

      <!-- 快捷入口 -->
      <view class="quick-actions">
        <view class="action-item" @tap="navigateTo('/pages/merchant/info')">
          <view class="action-icon">🏪</view>
          <text class="action-text">商家信息</text>
        </view>
        <view class="action-item" @tap="navigateTo('/pages/merchant/devices')">
          <view class="action-icon">📱</view>
          <text class="action-text">设备管理</text>
        </view>
        <view class="action-item" @tap="navigateTo('/pages/statistics/overview')">
          <view class="action-icon">📊</view>
          <text class="action-text">数据统计</text>
        </view>
        <view class="action-item" @tap="navigateTo('/pages/coupon/my')">
          <view class="action-icon">🎟️</view>
          <text class="action-text">我的优惠券</text>
        </view>
      </view>

      <!-- 功能列表 -->
      <view class="menu-section">
        <view class="menu-group">
          <view class="menu-item" @tap="navigateTo('/pages/user/account')">
            <view class="menu-left">
              <text class="menu-icon">👤</text>
              <text class="menu-title">账号管理</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>

          <view class="menu-item" @tap="navigateTo('/pages/user/security')">
            <view class="menu-left">
              <text class="menu-icon">🔒</text>
              <text class="menu-title">账号安全</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>

          <view class="menu-item" @tap="navigateTo('/pages/user/notifications')">
            <view class="menu-left">
              <text class="menu-icon">🔔</text>
              <text class="menu-title">消息通知</text>
            </view>
            <view class="menu-right">
              <view class="badge" v-if="unreadCount > 0">{{ unreadCount }}</view>
              <text class="menu-arrow">›</text>
            </view>
          </view>
        </view>

        <view class="menu-group">
          <view class="menu-item" @tap="navigateTo('/pages/publish/history')">
            <view class="menu-left">
              <text class="menu-icon">📤</text>
              <text class="menu-title">发布记录</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>

          <view class="menu-item" @tap="navigateTo('/pages/user/orders')">
            <view class="menu-left">
              <text class="menu-icon">📦</text>
              <text class="menu-title">我的订单</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>

          <view class="menu-item" @tap="navigateTo('/pages/user/billing')">
            <view class="menu-left">
              <text class="menu-icon">💳</text>
              <text class="menu-title">账单明细</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>
        </view>

        <view class="menu-group">
          <view class="menu-item" @tap="navigateTo('/pages/user/settings')">
            <view class="menu-left">
              <text class="menu-icon">⚙️</text>
              <text class="menu-title">设置</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>

          <view class="menu-item" @tap="navigateTo('/pages/user/help')">
            <view class="menu-left">
              <text class="menu-icon">❓</text>
              <text class="menu-title">帮助与反馈</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>

          <view class="menu-item" @tap="navigateTo('/pages/user/about')">
            <view class="menu-left">
              <text class="menu-icon">ℹ️</text>
              <text class="menu-title">关于我们</text>
            </view>
            <text class="menu-arrow">›</text>
          </view>
        </view>
      </view>

      <!-- 退出登录按钮 -->
      <view class="logout-section">
        <button class="logout-btn" @tap="handleLogout">退出登录</button>
      </view>
    </view>

    <!-- 编辑弹窗 -->
    <view class="edit-modal" v-if="showEditModal" @tap="closeEditModal">
      <view class="modal-content" @tap.stop>
        <view class="modal-header">
          <text class="modal-title">编辑资料</text>
          <text class="modal-close" @tap="closeEditModal">✕</text>
        </view>

        <view class="modal-body">
          <view class="form-item">
            <text class="form-label">头像</text>
            <view class="avatar-upload" @tap="chooseAvatar">
              <image class="upload-avatar" :src="editForm.avatar || defaultAvatar" mode="aspectFill" />
              <text class="upload-tip">点击更换</text>
            </view>
          </view>

          <view class="form-item">
            <text class="form-label">昵称</text>
            <input
              class="form-input"
              v-model="editForm.nickname"
              placeholder="请输入昵称"
              maxlength="20"
            />
          </view>

          <view class="form-item">
            <text class="form-label">性别</text>
            <view class="gender-group">
              <view
                class="gender-item"
                :class="{ active: editForm.gender === 1 }"
                @tap="editForm.gender = 1"
              >
                <text class="gender-icon">👨</text>
                <text class="gender-text">男</text>
              </view>
              <view
                class="gender-item"
                :class="{ active: editForm.gender === 2 }"
                @tap="editForm.gender = 2"
              >
                <text class="gender-icon">👩</text>
                <text class="gender-text">女</text>
              </view>
            </view>
          </view>
        </view>

        <view class="modal-footer">
          <button class="modal-btn cancel" @tap="closeEditModal">取消</button>
          <button class="modal-btn confirm" @tap="saveProfile">保存</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'

export default {
  data() {
    return {
      // 用户信息
      userInfo: {},
      userStats: {
        content_count: 0,
        favorite_count: 0
      },

      // Token from localStorage
      token: '',

      // 未读消息数
      unreadCount: 0,

      // 编辑表单
      showEditModal: false,
      editForm: {
        avatar: '',
        nickname: '',
        gender: 0
      },

      // 默认头像
      defaultAvatar: 'https://via.placeholder.com/200/6366f1/ffffff?text=User',

      // 状态
      isLoading: false,
    }
  },

  onShow() {
    // 每次显示页面时刷新数据
    this.loadUserProfile()
  },

  methods: {
    /**
     * 加载用户资料
     */
    async loadUserProfile() {
      this.isLoading = true

      try {
        // 尝试从localStorage获取用户信息
        const storedUser = uni.getStorageSync('userInfo')
        const storedToken = uni.getStorageSync('token')

        if (storedUser) {
          this.userInfo = storedUser
          this.token = storedToken
        }

        // 调用API获取最新用户信息和统计
        if (typeof api.user?.getProfile === 'function') {
          const res = await api.user.getProfile()
          this.userInfo = res.user || res

          // 更新localStorage
          uni.setStorageSync('userInfo', this.userInfo)
        } else {
          // 使用mock数据
          if (!this.userInfo.id) {
            this.userInfo = this.generateMockUserInfo()
          }
        }

        // 加载用户统计
        await this.loadUserStats()

        // 加载未读消息数
        await this.loadUnreadCount()
      } catch (error) {
        console.error('加载用户资料失败:', error)

        // 失败时使用mock数据
        if (!this.userInfo.id) {
          this.userInfo = this.generateMockUserInfo()
          this.userStats = {
            content_count: 23,
            favorite_count: 15
          }
          this.unreadCount = 3
        }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 加载用户统计
     */
    async loadUserStats() {
      try {
        if (typeof api.user?.getStats === 'function') {
          const res = await api.user.getStats()
          this.userStats = res
        } else {
          this.userStats = {
            content_count: 23,
            favorite_count: 15
          }
        }
      } catch (error) {
        console.error('加载用户统计失败:', error)
      }
    },

    /**
     * 加载未读消息数
     */
    async loadUnreadCount() {
      try {
        if (typeof api.user?.getUnreadCount === 'function') {
          const res = await api.user.getUnreadCount()
          this.unreadCount = res.count || 0
        } else {
          this.unreadCount = 3
        }
      } catch (error) {
        console.error('加载未读消息数失败:', error)
      }
    },

    /**
     * 编辑资料
     */
    editProfile() {
      this.editForm = {
        avatar: this.userInfo.avatar || '',
        nickname: this.userInfo.nickname || '',
        gender: this.userInfo.gender || 0
      }
      this.showEditModal = true
    },

    /**
     * 关闭编辑弹窗
     */
    closeEditModal() {
      this.showEditModal = false
    },

    /**
     * 选择头像
     */
    async chooseAvatar() {
      try {
        const res = await uni.chooseImage({
          count: 1,
          sizeType: ['compressed'],
          sourceType: ['album', 'camera']
        })

        if (res.tempFilePaths && res.tempFilePaths.length > 0) {
          // 这里应该上传到服务器，暂时直接使用本地路径
          this.editForm.avatar = res.tempFilePaths[0]
        }
      } catch (error) {
        console.error('选择头像失败:', error)
      }
    },

    /**
     * 保存资料
     */
    async saveProfile() {
      if (!this.editForm.nickname) {
        uni.showToast({
          title: '请输入昵称',
          icon: 'none'
        })
        return
      }

      try {
        uni.showLoading({ title: '保存中...', mask: true })

        if (typeof api.user?.updateProfile === 'function') {
          await api.user.updateProfile(this.editForm)
        }

        // 更新本地数据
        this.userInfo.avatar = this.editForm.avatar
        this.userInfo.nickname = this.editForm.nickname
        this.userInfo.gender = this.editForm.gender

        // 更新localStorage
        uni.setStorageSync('userInfo', this.userInfo)

        uni.showToast({
          title: '保存成功',
          icon: 'success'
        })

        this.closeEditModal()
      } catch (error) {
        console.error('保存资料失败:', error)
        uni.showToast({
          title: '保存失败',
          icon: 'none'
        })
      } finally {
        uni.hideLoading()
      }
    },

    /**
     * 页面跳转
     */
    navigateTo(url) {
      uni.navigateTo({ url })
    },

    /**
     * 退出登录
     */
    async handleLogout() {
      const res = await uni.showModal({
        title: '确认退出',
        content: '确定要退出登录吗？'
      })

      if (!res.confirm) return

      try {
        uni.showLoading({ title: '退出中...', mask: true })

        // 调用退出API
        if (typeof api.auth?.logout === 'function') {
          await api.auth.logout()
        }

        // 清除localStorage
        uni.removeStorageSync('token')
        uni.removeStorageSync('userInfo')
        this.token = ''
        this.userInfo = {}

        uni.showToast({
          title: '已退出登录',
          icon: 'success'
        })

        // 跳转到登录页
        setTimeout(() => {
          uni.reLaunch({
            url: '/pages/auth/index'
          })
        }, 1500)
      } catch (error) {
        console.error('退出登录失败:', error)
        uni.showToast({
          title: '退出失败',
          icon: 'none'
        })
      } finally {
        uni.hideLoading()
      }
    },

    /**
     * 生成mock用户信息
     */
    generateMockUserInfo() {
      return {
        id: 1,
        nickname: '小魔推用户',
        avatar: this.defaultAvatar,
        phone: '138****0000',
        gender: 1,
        member_level: 'VIP',
        points: 1280,
        status: 1
      }
    },

    /**
     * 格式化会员等级
     */
    formatMemberLevel(level) {
      const levels = {
        'BASIC': '基础会员',
        'VIP': 'VIP会员',
        'PREMIUM': '高级会员'
      }
      return levels[level] || '普通用户'
    },

    /**
     * 格式化手机号
     */
    formatPhone(phone) {
      if (!phone) return ''
      return phone.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
    }
  }
}
</script>

<style lang="scss" scoped>
.profile-page {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: 40rpx;
}

// 用户卡片
.user-card {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  margin: 20rpx 30rpx;
  border-radius: 20rpx;
  padding: 40rpx 30rpx 30rpx;
  box-shadow: 0 8rpx 24rpx rgba(99, 102, 241, 0.3);

  .user-header {
    display: flex;
    align-items: center;
    margin-bottom: 30rpx;

    .user-avatar {
      width: 120rpx;
      height: 120rpx;
      border-radius: 50%;
      border: 4rpx solid rgba(255, 255, 255, 0.3);
      margin-right: 24rpx;
    }

    .user-info {
      flex: 1;

      .user-name {
        font-size: 22px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 12rpx;
      }

      .user-meta {
        display: flex;
        align-items: center;
        gap: 16rpx;

        .member-badge {
          padding: 6rpx 16rpx;
          border-radius: 16rpx;
          font-size: 12px;
          font-weight: 600;
          background: rgba(255, 255, 255, 0.25);
          color: #ffffff;

          &.badge-VIP {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
          }

          &.badge-PREMIUM {
            background: linear-gradient(135deg, #f59e0b 0%, #dc2626 100%);
          }
        }

        .user-phone {
          font-size: 14px;
          color: rgba(255, 255, 255, 0.9);
        }
      }
    }

    .edit-btn {
      width: 64rpx;
      height: 64rpx;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(10rpx);

      .edit-icon {
        font-size: 24px;
      }
    }
  }

  .user-stats {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 16rpx;
    padding: 24rpx 0;
    backdrop-filter: blur(20rpx);

    .stat-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8rpx;

      .stat-value {
        font-size: 24px;
        font-weight: 600;
        color: #ffffff;
      }

      .stat-label {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.8);
      }
    }

    .stat-divider {
      width: 1rpx;
      height: 60rpx;
      background: rgba(255, 255, 255, 0.2);
    }
  }
}

// 快捷入口
.quick-actions {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20rpx;
  padding: 30rpx;

  .action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12rpx;

    .action-icon {
      width: 96rpx;
      height: 96rpx;
      background: #ffffff;
      border-radius: 20rpx;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);
    }

    .action-text {
      font-size: 13px;
      color: #6b7280;
    }
  }
}

// 菜单区域
.menu-section {
  padding: 0 30rpx;

  .menu-group {
    background: #ffffff;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    overflow: hidden;
  }

  .menu-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28rpx 24rpx;
    border-bottom: 1rpx solid #f3f4f6;

    &:last-child {
      border-bottom: none;
    }

    .menu-left {
      display: flex;
      align-items: center;
      gap: 20rpx;

      .menu-icon {
        font-size: 22px;
        width: 40rpx;
      }

      .menu-title {
        font-size: 15px;
        color: #1f2937;
      }
    }

    .menu-right {
      display: flex;
      align-items: center;
      gap: 12rpx;

      .badge {
        min-width: 36rpx;
        height: 36rpx;
        padding: 0 8rpx;
        background: #ef4444;
        color: #ffffff;
        border-radius: 18rpx;
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
      }
    }

    .menu-arrow {
      font-size: 24px;
      color: #d1d5db;
      font-weight: 300;
    }
  }
}

// 退出登录
.logout-section {
  padding: 0 30rpx;
  margin-top: 40rpx;

  .logout-btn {
    width: 100%;
    padding: 28rpx;
    background: #ffffff;
    color: #ef4444;
    border: none;
    border-radius: 16rpx;
    font-size: 16px;
    font-weight: 600;
  }
}

// 编辑弹窗
.edit-modal {
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

  .modal-content {
    width: 600rpx;
    background: #ffffff;
    border-radius: 20rpx;
    overflow: hidden;
  }

  .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 30rpx;
    border-bottom: 1rpx solid #f3f4f6;

    .modal-title {
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
    }

    .modal-close {
      font-size: 28px;
      color: #9ca3af;
      line-height: 1;
    }
  }

  .modal-body {
    padding: 30rpx;

    .form-item {
      margin-bottom: 30rpx;

      &:last-child {
        margin-bottom: 0;
      }

      .form-label {
        display: block;
        font-size: 14px;
        color: #374151;
        margin-bottom: 16rpx;
        font-weight: 500;
      }

      .avatar-upload {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16rpx;

        .upload-avatar {
          width: 160rpx;
          height: 160rpx;
          border-radius: 50%;
          border: 2rpx dashed #d1d5db;
        }

        .upload-tip {
          font-size: 13px;
          color: #6b7280;
        }
      }

      .form-input {
        width: 100%;
        padding: 24rpx;
        background: #f9fafb;
        border: 1rpx solid #e5e7eb;
        border-radius: 12rpx;
        font-size: 15px;
      }

      .gender-group {
        display: flex;
        gap: 20rpx;

        .gender-item {
          flex: 1;
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 12rpx;
          padding: 24rpx;
          background: #f9fafb;
          border: 2rpx solid #e5e7eb;
          border-radius: 12rpx;
          transition: all 0.3s;

          &.active {
            background: #ede9fe;
            border-color: #6366f1;

            .gender-icon {
              transform: scale(1.2);
            }

            .gender-text {
              color: #6366f1;
              font-weight: 600;
            }
          }

          .gender-icon {
            font-size: 40px;
            transition: all 0.3s;
          }

          .gender-text {
            font-size: 14px;
            color: #6b7280;
          }
        }
      }
    }
  }

  .modal-footer {
    display: flex;
    gap: 20rpx;
    padding: 30rpx;
    border-top: 1rpx solid #f3f4f6;

    .modal-btn {
      flex: 1;
      padding: 24rpx;
      border: none;
      border-radius: 12rpx;
      font-size: 15px;
      font-weight: 600;

      &.cancel {
        background: #f3f4f6;
        color: #6b7280;
      }

      &.confirm {
        background: #6366f1;
        color: #ffffff;
      }
    }
  }
}

// 动画
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>

<template>
  <view class="merchant-info-container">
    <!-- 导航栏 -->
    <view class="navbar">
      <image class="nav-back" src="/static/icon/back.png" @tap="goBack" />
      <text class="nav-title">商户信息</text>
      <text class="nav-action" @tap="editMerchant">编辑</text>
    </view>

    <!-- 加载状态 -->
    <view v-if="isLoading" class="loading-container">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载中...</text>
    </view>

    <!-- 商户信息 -->
    <scroll-view v-else class="merchant-content" scroll-y>

      <!-- 商户头部 -->
      <view class="merchant-header">
        <image class="merchant-logo" :src="merchantInfo.logo" mode="aspectFill" />
        <view class="merchant-basic">
          <view class="merchant-name">{{ merchantInfo.name }}</view>
          <view class="merchant-status" :class="`status-${merchantInfo.status}`">
            {{ formatStatus(merchantInfo.status) }}
          </view>
        </view>
      </view>

      <!-- 基本信息 -->
      <view class="info-section">
        <view class="section-title">基本信息</view>

        <view class="info-item">
          <text class="info-label">商户名称</text>
          <text class="info-value">{{ merchantInfo.name }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">商户类型</text>
          <text class="info-value">{{ formatType(merchantInfo.type) }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">联系人</text>
          <text class="info-value">{{ merchantInfo.contact }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">联系电话</text>
          <text class="info-value">{{ merchantInfo.phone }}</text>
          <text class="info-action" @tap="callPhone">📞</text>
        </view>

        <view class="info-item">
          <text class="info-label">电子邮箱</text>
          <text class="info-value">{{ merchantInfo.email }}</text>
        </view>
      </view>

      <!-- 地址信息 -->
      <view class="info-section">
        <view class="section-title">地址信息</view>

        <view class="info-item">
          <text class="info-label">所在地区</text>
          <text class="info-value">{{ merchantInfo.region }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">详细地址</text>
          <text class="info-value">{{ merchantInfo.address }}</text>
          <text class="info-action" @tap="viewLocation">📍</text>
        </view>

        <view class="info-item">
          <text class="info-label">经纬度</text>
          <text class="info-value">{{ merchantInfo.latitude }}, {{ merchantInfo.longitude }}</text>
        </view>
      </view>

      <!-- 营业信息 -->
      <view class="info-section">
        <view class="section-title">营业信息</view>

        <view class="info-item">
          <text class="info-label">营业时间</text>
          <text class="info-value">{{ merchantInfo.businessHours }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">营业执照</text>
          <text class="info-value">{{ merchantInfo.businessLicense }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">注册时间</text>
          <text class="info-value">{{ merchantInfo.registerDate }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">认证状态</text>
          <view class="verify-badge" :class="merchantInfo.verified ? 'verified' : ''">
            <text>{{ merchantInfo.verified ? '✓ 已认证' : '未认证' }}</text>
          </view>
        </view>
      </view>

      <!-- 业务统计 -->
      <view class="info-section">
        <view class="section-title">业务统计</view>

        <view class="stats-grid">
          <view class="stat-item">
            <text class="stat-value">{{ merchantStats.deviceCount }}</text>
            <text class="stat-label">设备数量</text>
          </view>

          <view class="stat-item">
            <text class="stat-value">{{ merchantStats.contentCount }}</text>
            <text class="stat-label">内容数量</text>
          </view>

          <view class="stat-item">
            <text class="stat-value">{{ merchantStats.scanCount }}</text>
            <text class="stat-label">扫码次数</text>
          </view>

          <view class="stat-item">
            <text class="stat-value">{{ merchantStats.customerCount }}</text>
            <text class="stat-label">客户数量</text>
          </view>
        </view>
      </view>

      <!-- 账户信息 -->
      <view class="info-section">
        <view class="section-title">账户信息</view>

        <view class="info-item">
          <text class="info-label">会员等级</text>
          <text class="info-value">{{ formatMemberLevel(merchantInfo.memberLevel) }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">到期时间</text>
          <text class="info-value">{{ merchantInfo.expireDate }}</text>
        </view>

        <view class="info-item">
          <text class="info-label">可用积分</text>
          <text class="info-value">{{ merchantInfo.points }}</text>
        </view>
      </view>

      <!-- 描述信息 -->
      <view class="info-section" v-if="merchantInfo.description">
        <view class="section-title">商户简介</view>
        <view class="merchant-description">
          {{ merchantInfo.description }}
        </view>
      </view>

      <!-- 操作按钮 -->
      <view class="action-buttons">
        <button class="action-btn primary" @tap="manageMerchant">管理商户</button>
        <button class="action-btn" @tap="viewDevices">设备管理</button>
      </view>

      <!-- 底部安全区 -->
      <view class="safe-area-bottom"></view>
    </scroll-view>

    <!-- 编辑弹窗 -->
    <view v-if="showEditModal" class="edit-modal">
      <view class="modal-content">
        <view class="modal-header">
          <text class="modal-title">编辑商户信息</text>
          <text class="modal-close" @tap="closeEditModal">✕</text>
        </view>

        <scroll-view class="modal-body" scroll-y>
          <view class="form-item">
            <text class="form-label">商户名称</text>
            <input
              class="form-input"
              v-model="editForm.name"
              placeholder="请输入商户名称"
            />
          </view>

          <view class="form-item">
            <text class="form-label">联系人</text>
            <input
              class="form-input"
              v-model="editForm.contact"
              placeholder="请输入联系人"
            />
          </view>

          <view class="form-item">
            <text class="form-label">联系电话</text>
            <input
              class="form-input"
              v-model="editForm.phone"
              type="number"
              placeholder="请输入联系电话"
            />
          </view>

          <view class="form-item">
            <text class="form-label">商户简介</text>
            <textarea
              class="form-textarea"
              v-model="editForm.description"
              placeholder="请输入商户简介"
              maxlength="200"
            />
          </view>
        </scroll-view>

        <view class="modal-footer">
          <button class="modal-btn cancel" @tap="closeEditModal">取消</button>
          <button class="modal-btn confirm" @tap="saveMerchant">保存</button>
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
      isLoading: false,
      showEditModal: false,

      // 商户信息
      merchantInfo: {},

      // 商户统计
      merchantStats: {
        deviceCount: 0,
        contentCount: 0,
        scanCount: 0,
        customerCount: 0
      },

      // 编辑表单
      editForm: {
        name: '',
        contact: '',
        phone: '',
        description: ''
      }
    }
  },

  onLoad(options) {
    console.log('商户信息页面加载:', options)
    this.loadMerchantInfo()
  },

  methods: {
    /**
     * 返回
     */
    goBack() {
      uni.navigateBack()
    },

    /**
     * 加载商户信息
     */
    async loadMerchantInfo() {
      this.isLoading = true

      try {
        // 调用API获取商户信息
        if (typeof api.merchant?.getInfo === 'function') {
          const res = await api.merchant.getInfo()
          this.merchantInfo = res.merchant || res
          this.merchantStats = res.stats || this.merchantStats
        } else {
          // 使用mock数据
          this.merchantInfo = this.generateMockMerchantInfo()
          this.merchantStats = this.generateMockStats()
        }
      } catch (error) {
        console.error('加载商户信息失败:', error)

        // 失败时使用mock数据
        this.merchantInfo = this.generateMockMerchantInfo()
        this.merchantStats = this.generateMockStats()
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 生成mock商户信息
     */
    generateMockMerchantInfo() {
      return {
        id: 1,
        name: '小魔推示范餐厅',
        logo: 'https://via.placeholder.com/120/6366f1/ffffff?text=LOGO',
        type: 'RESTAURANT',
        status: 'ACTIVE',
        contact: '张经理',
        phone: '13800138000',
        email: 'merchant@xiaomotui.com',
        region: '北京市朝阳区',
        address: '三里屯SOHO 5号楼1202',
        latitude: '39.9042',
        longitude: '116.4074',
        businessHours: '10:00 - 22:00',
        businessLicense: '京ICP备12345678号',
        registerDate: '2024-01-15',
        verified: true,
        memberLevel: 'VIP',
        expireDate: '2025-12-31',
        points: 1280,
        description: '小魔推示范餐厅是一家专注于提供优质餐饮服务的现代化餐厅，拥有多年的行业经验和专业的服务团队。'
      }
    },

    /**
     * 生成mock统计数据
     */
    generateMockStats() {
      return {
        deviceCount: 12,
        contentCount: 156,
        scanCount: 3456,
        customerCount: 892
      }
    },

    /**
     * 格式化状态
     */
    formatStatus(status) {
      const map = {
        ACTIVE: '营业中',
        INACTIVE: '已关闭',
        PENDING: '审核中',
        SUSPENDED: '已暂停'
      }
      return map[status] || '未知'
    },

    /**
     * 格式化类型
     */
    formatType(type) {
      const map = {
        RESTAURANT: '餐饮',
        RETAIL: '零售',
        SERVICE: '服务',
        ENTERTAINMENT: '娱乐',
        OTHER: '其他'
      }
      return map[type] || '其他'
    },

    /**
     * 格式化会员等级
     */
    formatMemberLevel(level) {
      const map = {
        BASIC: '基础版',
        VIP: '专业版',
        PREMIUM: '企业版'
      }
      return map[level] || '基础版'
    },

    /**
     * 拨打电话
     */
    callPhone() {
      uni.makePhoneCall({
        phoneNumber: this.merchantInfo.phone
      })
    },

    /**
     * 查看位置
     */
    viewLocation() {
      uni.openLocation({
        latitude: parseFloat(this.merchantInfo.latitude),
        longitude: parseFloat(this.merchantInfo.longitude),
        name: this.merchantInfo.name,
        address: this.merchantInfo.address
      })
    },

    /**
     * 编辑商户
     */
    editMerchant() {
      this.editForm = {
        name: this.merchantInfo.name,
        contact: this.merchantInfo.contact,
        phone: this.merchantInfo.phone,
        description: this.merchantInfo.description
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
     * 保存商户信息
     */
    async saveMerchant() {
      if (!this.editForm.name) {
        uni.showToast({
          title: '请输入商户名称',
          icon: 'none'
        })
        return
      }

      try {
        uni.showLoading({ title: '保存中...', mask: true })

        // 调用API保存
        if (typeof api.merchant?.update === 'function') {
          await api.merchant.update(this.editForm)
        }

        // 更新本地数据
        Object.assign(this.merchantInfo, this.editForm)

        uni.showToast({
          title: '保存成功',
          icon: 'success'
        })

        this.closeEditModal()
      } catch (error) {
        console.error('保存商户信息失败:', error)
        uni.showToast({
          title: '保存失败',
          icon: 'none'
        })
      } finally {
        uni.hideLoading()
      }
    },

    /**
     * 管理商户
     */
    manageMerchant() {
      uni.showToast({
        title: '跳转到商户管理页面',
        icon: 'none'
      })
    },

    /**
     * 查看设备
     */
    viewDevices() {
      uni.navigateTo({
        url: '/pages/merchant/devices'
      })
    }
  }
}
</script>

<style scoped>
.merchant-info-container {
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

/* 加载状态 */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 200rpx 0;
}

.loading-spinner {
  width: 40rpx;
  height: 40rpx;
  border: 3rpx solid #f3f4f6;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 30rpx;
}

.loading-text {
  font-size: 14px;
  color: #6b7280;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 商户内容 */
.merchant-content {
  flex: 1;
  padding: 20rpx;
}

/* 商户头部 */
.merchant-header {
  display: flex;
  align-items: center;
  padding: 40rpx 30rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 20rpx;
  margin-bottom: 20rpx;
}

.merchant-logo {
  width: 120rpx;
  height: 120rpx;
  border-radius: 60rpx;
  border: 4rpx solid rgba(255, 255, 255, 0.3);
  margin-right: 30rpx;
}

.merchant-basic {
  flex: 1;
}

.merchant-name {
  font-size: 20px;
  font-weight: bold;
  color: #fff;
  margin-bottom: 10rpx;
}

.merchant-status {
  display: inline-block;
  padding: 6rpx 20rpx;
  border-radius: 20rpx;
  font-size: 12px;
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
}

.status-ACTIVE {
  background: rgba(34, 197, 94, 0.2);
}

/* 信息区域 */
.info-section {
  background: #fff;
  border-radius: 16rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 30rpx;
}

.info-item {
  display: flex;
  align-items: center;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #f3f4f6;
}

.info-item:last-child {
  border-bottom: none;
}

.info-label {
  width: 180rpx;
  font-size: 14px;
  color: #6b7280;
}

.info-value {
  flex: 1;
  font-size: 14px;
  color: #1f2937;
}

.info-action {
  font-size: 20px;
  margin-left: 20rpx;
}

.verify-badge {
  padding: 6rpx 20rpx;
  border-radius: 20rpx;
  font-size: 12px;
  background: #f3f4f6;
  color: #6b7280;
}

.verify-badge.verified {
  background: #dcfce7;
  color: #16a34a;
}

/* 统计网格 */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 30rpx;
  background: #f9fafb;
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

/* 商户简介 */
.merchant-description {
  font-size: 14px;
  line-height: 1.8;
  color: #4b5563;
}

/* 操作按钮 */
.action-buttons {
  display: flex;
  gap: 20rpx;
  padding: 0 10rpx;
  margin-top: 20rpx;
}

.action-btn {
  flex: 1;
  height: 90rpx;
  line-height: 90rpx;
  border-radius: 12rpx;
  font-size: 16px;
  background: #fff;
  color: #1f2937;
  border: 1rpx solid #e5e7eb;
}

.action-btn.primary {
  background: #6366f1;
  color: #fff;
  border: none;
}

/* 编辑弹窗 */
.edit-modal {
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
  max-height: 80vh;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
  display: flex;
  flex-direction: column;
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
  flex: 1;
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

.form-input,
.form-textarea {
  width: 100%;
  padding: 20rpx;
  border: 1rpx solid #e5e7eb;
  border-radius: 8rpx;
  font-size: 14px;
  box-sizing: border-box;
}

.form-textarea {
  height: 200rpx;
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

/* 底部安全区 */
.safe-area-bottom {
  height: 40rpx;
}
</style>

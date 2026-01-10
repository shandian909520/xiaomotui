<script>
import { useUserStore } from './stores/user.js'

export default {
  onLaunch: function() {
    console.log('App Launch')
    // 初始化应用配置
    this.initApp()
  },
  onShow: function() {
    console.log('App Show')
  },
  onHide: function() {
    console.log('App Hide')
  },
  methods: {
    initApp() {
      // 初始化用户状态
      try {
        const userStore = useUserStore()
        userStore.initUserState()
        console.log('用户状态初始化完成')
      } catch (error) {
        console.error('用户状态初始化失败', error)
      }

      // 获取系统信息
      uni.getSystemInfo({
        success: (res) => {
          console.log('系统信息:', res)
          // 存储系统信息到全局
          getApp().globalData.systemInfo = res
        }
      })
    }
  },
  globalData: {
    systemInfo: null
  }
}
</script>

<style lang="scss">
/* 全局样式 */
/* @import '@/static/styles/common.scss'; */

page {
  background-color: #f8f9fa;
  font-size: 14px;
  line-height: 1.6;
  color: #333333;
}

/* 通用容器样式 */
.container {
  padding: 20rpx;
}

.page-container {
  min-height: 100vh;
  background-color: #f8f9fa;
}

/* 通用按钮样式 */
.btn-primary {
  background-color: #6366f1;
  color: #ffffff;
  border-radius: 8rpx;
  padding: 24rpx 32rpx;
  font-size: 16px;
  font-weight: 500;
  border: none;
}

.btn-primary:active {
  background-color: #4f46e5;
}

.btn-secondary {
  background-color: #e2e8f0;
  color: #475569;
  border-radius: 8rpx;
  padding: 24rpx 32rpx;
  font-size: 16px;
  font-weight: 500;
  border: none;
}

.btn-secondary:active {
  background-color: #cbd5e1;
}

/* 通用卡片样式 */
.card {
  background-color: #ffffff;
  border-radius: 12rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
}

/* 通用列表样式 */
.list-item {
  background-color: #ffffff;
  padding: 24rpx;
  border-bottom: 1rpx solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.list-item:active {
  background-color: #f8f9fa;
}

/* 通用标题样式 */
.title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 16rpx;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin-bottom: 8rpx;
}

/* 通用间距 */
.mt-10 { margin-top: 10rpx; }
.mt-20 { margin-top: 20rpx; }
.mt-30 { margin-top: 30rpx; }
.mb-10 { margin-bottom: 10rpx; }
.mb-20 { margin-bottom: 20rpx; }
.mb-30 { margin-bottom: 30rpx; }
.ml-10 { margin-left: 10rpx; }
.ml-20 { margin-left: 20rpx; }
.mr-10 { margin-right: 10rpx; }
.mr-20 { margin-right: 20rpx; }

/* 通用Flex布局 */
.flex {
  display: flex;
}

.flex-column {
  display: flex;
  flex-direction: column;
}

.flex-center {
  display: flex;
  align-items: center;
  justify-content: center;
}

.flex-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.flex-wrap {
  flex-wrap: wrap;
}

.flex-1 {
  flex: 1;
}

/* 通用文本样式 */
.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}

.text-primary {
  color: #6366f1;
}

.text-secondary {
  color: #64748b;
}

.text-success {
  color: #10b981;
}

.text-warning {
  color: #f59e0b;
}

.text-danger {
  color: #ef4444;
}

.text-muted {
  color: #94a3b8;
}

/* 通用字体大小 */
.text-xs {
  font-size: 12px;
}

.text-sm {
  font-size: 14px;
}

.text-base {
  font-size: 16px;
}

.text-lg {
  font-size: 18px;
}

.text-xl {
  font-size: 20px;
}

/* 通用字重 */
.font-normal {
  font-weight: 400;
}

.font-medium {
  font-weight: 500;
}

.font-semibold {
  font-weight: 600;
}

.font-bold {
  font-weight: 700;
}
</style>

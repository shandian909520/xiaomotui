<template>
  <view class="publish-setting-container">
    <!-- 页面标题 -->
    <view class="page-header">
      <view class="header-title">发布设置</view>
      <view class="header-subtitle">选择平台并配置发布参数</view>
    </view>

    <!-- 加载状态 -->
    <skeleton type="list" :rows="3" :loading="isLoading && !platformAccounts.length" />

    <!-- 主内容区域 -->
    <view class="content-wrapper" v-if="!isLoading || platformAccounts.length">
      <!-- 平台选择区域 -->
      <view class="section platform-section">
        <platform-selector
          :accounts="platformAccounts"
          :selectedIds="selectedPlatforms.map(p => p.id)"
          @toggle="togglePlatform"
          @add-platform="goToAuthPage"
        />
      </view>

      <!-- 发布配置区域 -->
      <view class="section config-section" v-if="selectedPlatforms.length">
        <publish-config-form
          :config="publishConfig"
          :selectedPlatforms="getSelectedPlatformObjects()"
          :platformConfigs="platformConfigs"
          :expandedConfigs="expandedConfigs"
          @update:config="publishConfig = $event"
          @update:platformConfig="handlePlatformConfigUpdate"
          @toggle-expand="togglePlatformConfig"
        />
      </view>

      <!-- 定时发布区域 -->
      <view class="section schedule-section" v-if="selectedPlatforms.length">
        <schedule-picker
          :isScheduled="isScheduled"
          :scheduleDate="scheduleDate"
          :scheduleTime="scheduleTime"
          @toggle="handleScheduleToggle"
          @change="handleScheduleChange"
        />
      </view>

      <!-- 发布预览 -->
      <view class="section preview-section" v-if="selectedPlatforms.length && contentData">
        <view class="section-header">
          <text class="section-icon">👁️</text>
          <text class="section-title">内容预览</text>
        </view>

        <view class="content-preview">
          <view class="preview-media" v-if="contentData.type === 'VIDEO' && contentData.video_url">
            <video
              class="preview-video"
              :src="contentData.video_url"
              :poster="contentData.poster_url || contentData.cover_url"
              :controls="true"
              :show-center-play-btn="true"
            />
          </view>
          <view class="preview-media" v-else-if="contentData.type === 'IMAGE' && contentData.image_url">
            <image
              class="preview-image"
              :src="contentData.image_url"
              mode="aspectFit"
            />
          </view>

          <view class="preview-info">
            <view class="preview-title">{{ publishConfig.title || contentData.title || '标题' }}</view>
            <view class="preview-description">
              {{ publishConfig.description || contentData.description || contentData.text_content || '内容描述' }}
            </view>
            <view class="preview-tags" v-if="publishConfig.tags && publishConfig.tags.length">
              <view class="preview-tag" v-for="(tag, index) in publishConfig.tags" :key="index">
                #{{ tag }}
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 底部按钮 -->
    <view class="footer-actions" v-if="platformAccounts.length">
      <button class="action-btn cancel-btn" @tap="handleCancel">取消</button>
      <button
        class="action-btn submit-btn"
        :class="{ 'btn-disabled': !canSubmit }"
        :disabled="!canSubmit || isSubmitting"
        @tap="handleSubmit"
      >
        <text class="btn-icon" v-if="!isSubmitting">{{ isScheduled ? '⏰' : '🚀' }}</text>
        <text class="btn-text">{{ getBtnText() }}</text>
      </button>
    </view>

    <!-- 加载遮罩 -->
    <view class="loading-overlay" v-if="isSubmitting">
      <view class="loading-box">
        <view class="loading-spinner"></view>
        <text class="loading-text">{{ loadingText }}</text>
      </view>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'
import PlatformSelector from '../../components/business/publish/platform-selector.vue'
import PublishConfigForm from '../../components/business/publish/publish-config-form.vue'
import SchedulePicker from '../../components/business/publish/schedule-picker.vue'

export default {
  components: { PlatformSelector, PublishConfigForm, SchedulePicker },

  data() {
    return {
      contentTaskId: '',
      contentData: null,

      // 平台账号
      platformAccounts: [],
      selectedPlatforms: [],

      // 发布配置
      publishConfig: {
        title: '',
        description: '',
        tags: []
      },

      // 平台专属配置
      platformConfigs: {},
      expandedConfigs: {},

      // 定时发布
      isScheduled: false,
      scheduleDate: '',
      scheduleTime: '',

      // 状态
      isLoading: false,
      isSubmitting: false,
      loadingText: '提交中...'
    }
  },

  computed: {
    canSubmit() {
      return this.selectedPlatforms.length > 0 &&
        (!this.isScheduled || (this.scheduleDate && this.scheduleTime))
    }
  },

  onLoad(options) {
    console.log('发布设置页面参数:', options)

    if (options.task_id) {
      this.contentTaskId = options.task_id
      this.initPage()
    } else {
      uni.showModal({
        title: '提示',
        content: '缺少内容任务ID参数',
        showCancel: false,
        success: () => {
          uni.navigateBack()
        }
      })
    }
  },

  methods: {
    /**
     * 初始化页面
     */
    async initPage() {
      // 并发加载数据
      await Promise.all([
        this.loadPlatformAccounts(),
        this.loadContentData()
      ])
    },

    /**
     * 加载平台账号列表
     */
    async loadPlatformAccounts() {
      this.isLoading = true

      try {
        const res = await api.publish.getPlatformAccounts()
        console.log('平台账号列表:', res)

        // 只显示已激活的账号
        this.platformAccounts = (res.data || res).filter(account => account.status === 'ACTIVE')

        // 初始化平台配置
        this.platformAccounts.forEach(account => {
          this.$set(this.platformConfigs, account.id, {
            title: '',
            description: ''
          })
          this.$set(this.expandedConfigs, account.id, false)
        })
      } catch (error) {
        console.error('加载平台账号失败:', error)
        uni.showToast({
          title: error.message || '加载平台账号失败',
          icon: 'none',
          duration: 2000
        })
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 加载内容数据
     */
    async loadContentData() {
      try {
        const res = await api.content.getTaskDetail(this.contentTaskId)
        console.log('内容详情:', res)

        // 解析output_data
        if (res.output_data) {
          if (typeof res.output_data === 'string') {
            try {
              res.output_data = JSON.parse(res.output_data)
            } catch (e) {
              console.error('解析output_data失败:', e)
            }
          }

          this.contentData = {
            ...res,
            ...res.output_data
          }
        } else {
          this.contentData = res
        }

        // 预填充配置
        if (this.contentData.title) {
          this.publishConfig.title = this.contentData.title
        }
        if (this.contentData.description || this.contentData.text_content) {
          this.publishConfig.description = this.contentData.description || this.contentData.text_content
        }
        if (this.contentData.tags) {
          if (typeof this.contentData.tags === 'string') {
            try {
              this.publishConfig.tags = JSON.parse(this.contentData.tags)
            } catch (e) {
              this.publishConfig.tags = this.contentData.tags.split(',')
            }
          } else if (Array.isArray(this.contentData.tags)) {
            this.publishConfig.tags = this.contentData.tags
          }
        }
      } catch (error) {
        console.error('加载内容数据失败:', error)
        uni.showToast({
          title: '加载内容失败',
          icon: 'none'
        })
      }
    },

    /**
     * 切换平台选择
     */
    togglePlatform(account) {
      if (account.status !== 'ACTIVE') {
        uni.showModal({
          title: '提示',
          content: '该账号已失效，请重新授权',
          confirmText: '去授权',
          success: (res) => {
            if (res.confirm) {
              this.goToAuthPage()
            }
          }
        })
        return
      }

      const index = this.selectedPlatforms.findIndex(p => p.id === account.id)
      if (index > -1) {
        this.selectedPlatforms.splice(index, 1)
      } else {
        this.selectedPlatforms.push(account)
      }
    },

    /**
     * 切换平台配置展开状态
     */
    togglePlatformConfig(accountId) {
      this.$set(this.expandedConfigs, accountId, !this.expandedConfigs[accountId])
    },

    /**
     * 获取已选平台的完整对象列表
     */
    getSelectedPlatformObjects() {
      return this.platformAccounts.filter(a => this.selectedPlatforms.some(p => p.id === a.id))
    },

    /**
     * 处理平台专属配置更新
     */
    handlePlatformConfigUpdate({ platformId, config }) {
      this.$set(this.platformConfigs, platformId, config)
    },

    /**
     * 处理定时发布开关
     */
    handleScheduleToggle(value) {
      this.isScheduled = value

      // 如果开启定时发布，设置默认时间为1小时后
      if (this.isScheduled && !this.scheduleDate) {
        const now = new Date()
        now.setHours(now.getHours() + 1)

        this.scheduleDate = this.formatDate(now)
        this.scheduleTime = this.formatTime(now)
      }
    },

    /**
     * 处理定时日期/时间变化
     */
    handleScheduleChange({ date, time }) {
      this.scheduleDate = date
      this.scheduleTime = time
    },

    /**
     * 格式化日期
     */
    formatDate(date) {
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    },

    /**
     * 格式化时间
     */
    formatTime(date) {
      const hours = String(date.getHours()).padStart(2, '0')
      const minutes = String(date.getMinutes()).padStart(2, '0')
      return `${hours}:${minutes}`
    },

    /**
     * 获取按钮文字
     */
    getBtnText() {
      if (this.isSubmitting) {
        return '提交中...'
      }
      if (this.isScheduled) {
        return '创建定时发布'
      }
      return '立即发布'
    },

    /**
     * 去授权页面
     */
    goToAuthPage() {
      uni.navigateTo({
        url: '/pages/platform/auth'
      })
    },

    /**
     * 取消操作
     */
    handleCancel() {
      uni.navigateBack()
    },

    /**
     * 提交发布
     */
    async handleSubmit() {
      if (!this.canSubmit || this.isSubmitting) return

      // 验证定时发布时间
      if (this.isScheduled) {
        const scheduledTime = new Date(`${this.scheduleDate} ${this.scheduleTime}`)
        const now = new Date()

        if (scheduledTime <= now) {
          uni.showModal({
            title: '提示',
            content: '定时发布时间必须晚于当前时间',
            showCancel: false
          })
          return
        }
      }

      this.isSubmitting = true
      this.loadingText = this.isScheduled ? '创建定时任务中...' : '发布中...'

      try {
        // 构建发布数据
        const publishData = {
          contentTaskId: this.contentTaskId,
          platforms: this.selectedPlatforms.map(p => ({
            platform: p.platform,
            account_id: p.id,
            config: this.platformConfigs[p.id]
          })),
          title: this.publishConfig.title,
          description: this.publishConfig.description,
          tags: this.publishConfig.tags
        }

        // 添加定时发布时间
        if (this.isScheduled) {
          publishData.scheduledTime = `${this.scheduleDate} ${this.scheduleTime}:00`
        }

        console.log('发布数据:', publishData)

        // 调用发布API
        const result = await api.publish.createPublishTask(publishData)
        console.log('发布结果:', result)

        // 显示成功提示
        uni.showToast({
          title: this.isScheduled ? '定时任务创建成功' : '发布成功',
          icon: 'success',
          duration: 2000
        })

        // 延迟跳转到发布任务列表
        setTimeout(() => {
          uni.redirectTo({
            url: '/pages/publish/list'
          })
        }, 2000)
      } catch (error) {
        console.error('发布失败:', error)

        uni.showModal({
          title: '发布失败',
          content: error.message || '发布内容失败，请稍后重试',
          showCancel: false
        })
      } finally {
        this.isSubmitting = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.publish-setting-container {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: 120rpx;
}

// 页面头部
.page-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 40rpx 30rpx;
  color: #ffffff;

  .header-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 12rpx;
  }

  .header-subtitle {
    font-size: 14px;
    opacity: 0.9;
  }
}

// 内容包装器
.content-wrapper {
  padding: 20rpx 30rpx;
}

// 通用区块
.section {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  .section-header {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 30rpx;

    .section-icon {
      font-size: 20px;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
    }

    .section-tip {
      font-size: 12px;
      color: #9ca3af;
      margin-left: 8rpx;
    }
  }
}

// 内容预览
.content-preview {
  .preview-media {
    width: 100%;
    margin-bottom: 24rpx;
    border-radius: 12rpx;
    overflow: hidden;
    background: #f9fafb;

    .preview-video {
      width: 100%;
      height: 400rpx;
    }

    .preview-image {
      width: 100%;
      min-height: 300rpx;
    }
  }

  .preview-info {
    .preview-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 16rpx;
      line-height: 1.4;
    }

    .preview-description {
      font-size: 14px;
      color: #6b7280;
      line-height: 1.6;
      margin-bottom: 20rpx;
    }

    .preview-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 12rpx;

      .preview-tag {
        padding: 6rpx 16rpx;
        background: #f3f4f6;
        color: #6366f1;
        border-radius: 16rpx;
        font-size: 12px;
      }
    }
  }
}

// 底部按钮
.footer-actions {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: 20rpx;
  padding: 20rpx 30rpx;
  background: #ffffff;
  box-shadow: 0 -2rpx 12rpx rgba(0, 0, 0, 0.08);
  z-index: 999;

  .action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12rpx;
    padding: 28rpx;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
    border: none;

    .btn-icon {
      font-size: 20px;
    }
  }

  .cancel-btn {
    background: #f3f4f6;
    color: #6b7280;
  }

  .submit-btn {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    box-shadow: 0 4rpx 16rpx rgba(99, 102, 241, 0.3);

    &.btn-disabled {
      background: #e5e7eb;
      color: #9ca3af;
      box-shadow: none;
    }
  }
}

// 加载遮罩
.loading-overlay {
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

  .loading-box {
    background: #ffffff;
    border-radius: 16rpx;
    padding: 60rpx 80rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30rpx;

    .loading-spinner {
      width: 60rpx;
      height: 60rpx;
      border: 4rpx solid #e5e7eb;
      border-top-color: #6366f1;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .loading-text {
      font-size: 14px;
      color: #6b7280;
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

<template>
  <view class="publish-setting-container">
    <!-- 页面标题 -->
    <view class="page-header">
      <view class="header-title">发布设置</view>
      <view class="header-subtitle">选择平台并配置发布参数</view>
    </view>

    <!-- 加载状态 -->
    <view class="loading-state" v-if="isLoading && !platformAccounts.length">
      <view class="loading-spinner"></view>
      <text class="loading-text">加载平台账号中...</text>
    </view>

    <!-- 主内容区域 -->
    <view class="content-wrapper" v-else>
      <!-- 平台选择区域 -->
      <view class="section platform-section">
        <view class="section-header">
          <text class="section-icon">📱</text>
          <text class="section-title">选择发布平台</text>
          <text class="section-tip">（至少选择一个）</text>
        </view>

        <view class="platform-list" v-if="platformAccounts.length">
          <view
            class="platform-card"
            :class="{ 'platform-selected': isPlatformSelected(account.id) }"
            v-for="account in platformAccounts"
            :key="account.id"
            @tap="togglePlatform(account)"
          >
            <view class="platform-checkbox">
              <view class="checkbox-icon" v-if="isPlatformSelected(account.id)">✓</view>
            </view>

            <view class="platform-info">
              <view class="platform-header">
                <text class="platform-icon">{{ getPlatformIcon(account.platform) }}</text>
                <text class="platform-name">{{ getPlatformName(account.platform) }}</text>
              </view>
              <view class="platform-account">
                <text class="account-nickname">{{ account.nickname || account.account_name }}</text>
                <view class="account-badge" :class="`badge-${account.status}`">
                  {{ account.status === 'ACTIVE' ? '已授权' : '已失效' }}
                </view>
              </view>
            </view>
          </view>
        </view>

        <!-- 无账号提示 -->
        <view class="empty-platforms" v-else>
          <view class="empty-icon">📱</view>
          <view class="empty-title">暂无授权平台</view>
          <view class="empty-tip">请先授权至少一个发布平台</view>
          <button class="empty-btn" @tap="goToAuthPage">去授权</button>
        </view>

        <!-- 添加平台按钮 -->
        <view class="add-platform-btn" v-if="platformAccounts.length" @tap="goToAuthPage">
          <text class="btn-icon">➕</text>
          <text>添加更多平台</text>
        </view>
      </view>

      <!-- 发布配置区域 -->
      <view class="section config-section" v-if="selectedPlatforms.length">
        <view class="section-header">
          <text class="section-icon">⚙️</text>
          <text class="section-title">发布配置</text>
        </view>

        <!-- 通用配置 -->
        <view class="config-form">
          <view class="form-item">
            <view class="form-label">
              <text class="label-text">发布标题</text>
              <text class="label-tip">（可选）</text>
            </view>
            <input
              class="form-input"
              v-model="publishConfig.title"
              placeholder="默认使用内容标题"
              maxlength="100"
            />
          </view>

          <view class="form-item">
            <view class="form-label">
              <text class="label-text">内容描述</text>
              <text class="label-tip">（可选）</text>
            </view>
            <textarea
              class="form-textarea"
              v-model="publishConfig.description"
              placeholder="为内容添加描述信息"
              maxlength="500"
              :auto-height="true"
            />
          </view>

          <view class="form-item">
            <view class="form-label">
              <text class="label-text">标签</text>
              <text class="label-tip">（用空格分隔）</text>
            </view>
            <input
              class="form-input"
              v-model="tagsInput"
              placeholder="例如：美食 探店 推荐"
              @blur="handleTagsChange"
            />
            <view class="tags-preview" v-if="publishConfig.tags && publishConfig.tags.length">
              <view class="tag-item" v-for="(tag, index) in publishConfig.tags" :key="index">
                #{{ tag }}
              </view>
            </view>
          </view>
        </view>

        <!-- 平台特定配置 -->
        <view class="platform-configs">
          <view
            class="platform-config-item"
            v-for="platform in selectedPlatforms"
            :key="platform.id"
          >
            <view class="config-item-header" @tap="togglePlatformConfig(platform.id)">
              <view class="config-header-left">
                <text class="config-icon">{{ getPlatformIcon(platform.platform) }}</text>
                <text class="config-title">{{ getPlatformName(platform.platform) }} 专属设置</text>
              </view>
              <text class="toggle-icon">{{ expandedConfigs[platform.id] ? '▼' : '▶' }}</text>
            </view>

            <view class="config-item-body" v-if="expandedConfigs[platform.id]">
              <view class="form-item">
                <view class="form-label">
                  <text class="label-text">平台标题</text>
                </view>
                <input
                  class="form-input"
                  v-model="platformConfigs[platform.id].title"
                  :placeholder="`${getPlatformName(platform.platform)}专用标题`"
                />
              </view>

              <view class="form-item">
                <view class="form-label">
                  <text class="label-text">平台描述</text>
                </view>
                <textarea
                  class="form-textarea"
                  v-model="platformConfigs[platform.id].description"
                  :placeholder="`${getPlatformName(platform.platform)}专用描述`"
                  :auto-height="true"
                />
              </view>
            </view>
          </view>
        </view>
      </view>

      <!-- 定时发布区域 -->
      <view class="section schedule-section" v-if="selectedPlatforms.length">
        <view class="section-header">
          <text class="section-icon">⏰</text>
          <text class="section-title">定时发布</text>
        </view>

        <view class="schedule-toggle">
          <view class="toggle-info">
            <text class="toggle-title">启用定时发布</text>
            <text class="toggle-tip">在指定时间自动发布内容</text>
          </view>
          <switch
            :checked="isScheduled"
            @change="handleScheduleToggle"
            color="#6366f1"
          />
        </view>

        <view class="schedule-picker" v-if="isScheduled">
          <view class="picker-item">
            <view class="picker-label">
              <text class="label-icon">📅</text>
              <text class="label-text">发布日期</text>
            </view>
            <picker
              mode="date"
              :value="scheduleDate"
              :start="minDate"
              @change="handleDateChange"
            >
              <view class="picker-value">
                {{ scheduleDate || '选择日期' }}
              </view>
            </picker>
          </view>

          <view class="picker-item">
            <view class="picker-label">
              <text class="label-icon">🕐</text>
              <text class="label-text">发布时间</text>
            </view>
            <picker
              mode="time"
              :value="scheduleTime"
              @change="handleTimeChange"
            >
              <view class="picker-value">
                {{ scheduleTime || '选择时间' }}
              </view>
            </picker>
          </view>

          <view class="schedule-preview" v-if="scheduleDate && scheduleTime">
            <text class="preview-icon">⏰</text>
            <text class="preview-text">将在 {{ formatScheduleTime() }} 自动发布</text>
          </view>
        </view>
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

export default {
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

      // 标签输入
      tagsInput: '',

      // 定时发布
      isScheduled: false,
      scheduleDate: '',
      scheduleTime: '',
      minDate: '',

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
      // 设置最小日期为今天
      const today = new Date()
      this.minDate = this.formatDate(today)

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
          this.tagsInput = this.publishConfig.tags.join(' ')
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
     * 判断平台是否已选择
     */
    isPlatformSelected(accountId) {
      return this.selectedPlatforms.some(p => p.id === accountId)
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
     * 处理标签变化
     */
    handleTagsChange() {
      if (!this.tagsInput.trim()) {
        this.publishConfig.tags = []
        return
      }

      // 按空格分割，去除空白和重复
      const tags = this.tagsInput
        .split(/\s+/)
        .map(tag => tag.trim())
        .filter(tag => tag)
      this.publishConfig.tags = [...new Set(tags)]
    },

    /**
     * 处理定时发布开关
     */
    handleScheduleToggle(e) {
      this.isScheduled = e.detail.value

      // 如果开启定时发布，设置默认时间为1小时后
      if (this.isScheduled && !this.scheduleDate) {
        const now = new Date()
        now.setHours(now.getHours() + 1)

        this.scheduleDate = this.formatDate(now)
        this.scheduleTime = this.formatTime(now)
      }
    },

    /**
     * 处理日期变化
     */
    handleDateChange(e) {
      this.scheduleDate = e.detail.value
    },

    /**
     * 处理时间变化
     */
    handleTimeChange(e) {
      this.scheduleTime = e.detail.value
    },

    /**
     * 格式化定时时间显示
     */
    formatScheduleTime() {
      if (!this.scheduleDate || !this.scheduleTime) return ''

      const dateObj = new Date(`${this.scheduleDate} ${this.scheduleTime}`)
      const now = new Date()

      // 判断是今天、明天还是具体日期
      const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      const targetDate = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate())
      const diffDays = Math.floor((targetDate - today) / (1000 * 60 * 60 * 24))

      let dateStr = ''
      if (diffDays === 0) {
        dateStr = '今天'
      } else if (diffDays === 1) {
        dateStr = '明天'
      } else {
        dateStr = this.scheduleDate
      }

      return `${dateStr} ${this.scheduleTime}`
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
     * 获取平台图标
     */
    getPlatformIcon(platform) {
      const icons = {
        douyin: '🎵',
        xiaohongshu: '📕',
        wechat: '💬',
        channels: '📹',
        weibo: '📱',
        kuaishou: '🎬'
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
        wechat: '微信',
        channels: '视频号',
        weibo: '微博',
        kuaishou: '快手'
      }
      return names[platform] || platform
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

// 加载状态
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx 0;

  .loading-spinner {
    width: 60rpx;
    height: 60rpx;
    border: 4rpx solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 30rpx;
  }

  .loading-text {
    font-size: 14px;
    color: #6b7280;
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

// 平台列表
.platform-list {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.platform-card {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 24rpx;
  background: #f9fafb;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;
  transition: all 0.3s;

  &.platform-selected {
    background: #ede9fe;
    border-color: #6366f1;
    box-shadow: 0 4rpx 12rpx rgba(99, 102, 241, 0.1);
  }

  .platform-checkbox {
    width: 48rpx;
    height: 48rpx;
    border: 2rpx solid #d1d5db;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    flex-shrink: 0;

    .checkbox-icon {
      color: #6366f1;
      font-size: 20px;
      font-weight: bold;
    }
  }

  &.platform-selected .platform-checkbox {
    border-color: #6366f1;
    background: #6366f1;

    .checkbox-icon {
      color: #ffffff;
    }
  }

  .platform-info {
    flex: 1;

    .platform-header {
      display: flex;
      align-items: center;
      gap: 12rpx;
      margin-bottom: 12rpx;

      .platform-icon {
        font-size: 22px;
      }

      .platform-name {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
      }
    }

    .platform-account {
      display: flex;
      align-items: center;
      gap: 16rpx;

      .account-nickname {
        font-size: 14px;
        color: #6b7280;
      }

      .account-badge {
        padding: 4rpx 12rpx;
        border-radius: 12rpx;
        font-size: 12px;
        font-weight: 500;

        &.badge-ACTIVE {
          background: #d1fae5;
          color: #065f46;
        }

        &.badge-EXPIRED {
          background: #fee2e2;
          color: #991b1b;
        }
      }
    }
  }
}

// 空状态
.empty-platforms {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 0;

  .empty-icon {
    font-size: 80px;
    margin-bottom: 30rpx;
    opacity: 0.5;
  }

  .empty-title {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12rpx;
  }

  .empty-tip {
    font-size: 14px;
    color: #9ca3af;
    margin-bottom: 40rpx;
  }

  .empty-btn {
    padding: 20rpx 60rpx;
    background: #6366f1;
    color: #ffffff;
    border: none;
    border-radius: 24rpx;
    font-size: 14px;
  }
}

// 添加平台按钮
.add-platform-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  padding: 24rpx;
  margin-top: 20rpx;
  background: #ffffff;
  border: 2rpx dashed #d1d5db;
  border-radius: 12rpx;
  color: #6b7280;
  font-size: 14px;

  .btn-icon {
    font-size: 18px;
  }
}

// 表单配置
.config-form {
  display: flex;
  flex-direction: column;
  gap: 30rpx;
}

.form-item {
  .form-label {
    display: flex;
    align-items: baseline;
    margin-bottom: 16rpx;

    .label-text {
      font-size: 14px;
      font-weight: 500;
      color: #374151;
    }

    .label-tip {
      font-size: 12px;
      color: #9ca3af;
      margin-left: 8rpx;
    }
  }

  .form-input {
    width: 100%;
    padding: 24rpx;
    background: #f9fafb;
    border: 1rpx solid #e5e7eb;
    border-radius: 8rpx;
    font-size: 14px;
    color: #1f2937;
  }

  .form-textarea {
    width: 100%;
    min-height: 120rpx;
    padding: 24rpx;
    background: #f9fafb;
    border: 1rpx solid #e5e7eb;
    border-radius: 8rpx;
    font-size: 14px;
    color: #1f2937;
    line-height: 1.6;
  }

  .tags-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    margin-top: 16rpx;

    .tag-item {
      padding: 8rpx 20rpx;
      background: #ede9fe;
      color: #6366f1;
      border-radius: 20rpx;
      font-size: 12px;
      font-weight: 500;
    }
  }
}

// 平台配置项
.platform-configs {
  margin-top: 30rpx;
}

.platform-config-item {
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  overflow: hidden;
  margin-bottom: 20rpx;

  &:last-child {
    margin-bottom: 0;
  }

  .config-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24rpx;
    background: #f9fafb;
    cursor: pointer;

    .config-header-left {
      display: flex;
      align-items: center;
      gap: 12rpx;

      .config-icon {
        font-size: 18px;
      }

      .config-title {
        font-size: 14px;
        font-weight: 500;
        color: #374151;
      }
    }

    .toggle-icon {
      font-size: 12px;
      color: #9ca3af;
    }
  }

  .config-item-body {
    padding: 24rpx;
    display: flex;
    flex-direction: column;
    gap: 24rpx;
  }
}

// 定时发布
.schedule-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx;
  background: #f9fafb;
  border-radius: 12rpx;

  .toggle-info {
    flex: 1;

    .toggle-title {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #374151;
      margin-bottom: 8rpx;
    }

    .toggle-tip {
      font-size: 12px;
      color: #9ca3af;
    }
  }
}

.schedule-picker {
  margin-top: 30rpx;
  display: flex;
  flex-direction: column;
  gap: 20rpx;

  .picker-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24rpx;
    background: #f9fafb;
    border-radius: 12rpx;

    .picker-label {
      display: flex;
      align-items: center;
      gap: 12rpx;

      .label-icon {
        font-size: 18px;
      }

      .label-text {
        font-size: 14px;
        font-weight: 500;
        color: #374151;
      }
    }

    .picker-value {
      font-size: 14px;
      color: #6366f1;
      font-weight: 500;
    }
  }

  .schedule-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12rpx;
    padding: 24rpx;
    background: #fef3c7;
    border-radius: 12rpx;
    margin-top: 10rpx;

    .preview-icon {
      font-size: 18px;
    }

    .preview-text {
      font-size: 14px;
      color: #92400e;
      font-weight: 500;
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

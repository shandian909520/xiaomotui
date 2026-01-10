<template>
  <view class="generate-page">
    <!-- 顶部导航 -->
    <view class="nav-bar">
      <view class="nav-title">AI内容生成</view>
    </view>

    <!-- 主内容 -->
    <scroll-view class="content-scroll" scroll-y>
      <!-- 模板选择 -->
      <view class="section">
        <view class="section-header">
          <view class="section-title">选择模板</view>
          <view class="section-action" @tap="goToTemplateManage">
            <text class="action-icon">⚙️</text>
            <text class="action-text">管理</text>
          </view>
        </view>
        <scroll-view class="template-scroll" scroll-x>
          <view class="template-list">
            <view
              v-for="template in templates"
              :key="template.id"
              class="template-item"
              :class="{ active: selectedTemplate?.id === template.id }"
              @tap="selectTemplate(template)"
            >
              <view class="template-icon">{{ template.icon }}</view>
              <view class="template-name">{{ template.name }}</view>
            </view>
          </view>
        </scroll-view>
      </view>

      <!-- 内容类型 -->
      <view class="section">
        <view class="section-title">内容类型</view>
        <view class="type-list">
          <view
            v-for="type in contentTypes"
            :key="type.value"
            class="type-item"
            :class="{ active: form.type === type.value }"
            @tap="form.type = type.value"
          >
            <text class="type-icon">{{ type.icon }}</text>
            <text class="type-name">{{ type.label }}</text>
          </view>
        </view>
      </view>

      <!-- 生成配置 -->
      <view class="section">
        <view class="section-title">生成配置</view>

        <!-- 关键词 -->
        <view class="form-item">
          <view class="form-label">关键词</view>
          <input
            class="form-input"
            v-model="form.keywords"
            placeholder="输入关键词，多个用逗号分隔"
            placeholder-style="color: #9ca3af"
          />
        </view>

        <!-- 风格 -->
        <view class="form-item">
          <view class="form-label">内容风格</view>
          <picker
            mode="selector"
            :range="styleOptions"
            range-key="label"
            @change="onStyleChange"
          >
            <view class="form-picker">
              {{ selectedStyle?.label || '请选择风格' }}
            </view>
          </picker>
        </view>

        <!-- 目标平台 -->
        <view class="form-item">
          <view class="form-label">目标平台</view>
          <picker
            mode="selector"
            :range="platformOptions"
            range-key="label"
            @change="onPlatformChange"
          >
            <view class="form-picker">
              {{ selectedPlatform?.label || '请选择平台' }}
            </view>
          </picker>
        </view>

        <!-- 场景描述 -->
        <view class="form-item">
          <view class="form-label">场景描述（可选）</view>
          <textarea
            class="form-textarea"
            v-model="form.scene"
            placeholder="详细描述内容场景，帮助AI更好地生成内容"
            placeholder-style="color: #9ca3af"
            maxlength="500"
          />
          <view class="textarea-count">{{ form.scene?.length || 0 }}/500</view>
        </view>
      </view>

      <!-- AI推荐 -->
      <view class="section" v-if="aiRecommendation">
        <view class="section-title">
          <text>AI推荐</text>
          <text class="ai-badge">智能</text>
        </view>
        <view class="ai-recommendation">
          <view class="ai-tip">{{ aiRecommendation.tip }}</view>
          <view class="ai-tags">
            <text
              v-for="tag in aiRecommendation.tags"
              :key="tag"
              class="ai-tag"
              @tap="addKeyword(tag)"
            >
              {{ tag }}
            </text>
          </view>
        </view>
      </view>
    </scroll-view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar">
      <button class="secondary-btn" @tap="handleReset">重置</button>
      <button class="primary-btn" @tap="handleGenerate" :disabled="!canGenerate">
        开始生成
      </button>
    </view>

    <!-- 加载提示 -->
    <view class="loading-overlay" v-if="isLoading">
      <view class="loading-box">
        <view class="loading-spinner"></view>
        <view class="loading-text">{{ loadingText }}</view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '../../api/index.js'
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  data() {
    return {
      isLoading: false,
      loadingText: '加载中...',

      // 模板列表
      templates: [],
      selectedTemplate: null,

      // 内容类型
      contentTypes: [
        { value: 'TEXT', label: '文本', icon: '📝' },
        { value: 'IMAGE', label: '图片', icon: '🖼️' },
        { value: 'VIDEO', label: '视频', icon: '🎬' },
        { value: 'MIXED', label: '混合', icon: '🎨' }
      ],

      // 风格选项
      styleOptions: [
        { value: 'professional', label: '专业正式' },
        { value: 'casual', label: '轻松随意' },
        { value: 'humorous', label: '幽默风趣' },
        { value: 'warm', label: '温馨亲切' },
        { value: 'trendy', label: '时尚潮流' }
      ],
      selectedStyle: null,

      // 平台选项
      platformOptions: [
        { value: 'wechat', label: '微信' },
        { value: 'douyin', label: '抖音' },
        { value: 'xiaohongshu', label: '小红书' },
        { value: 'weibo', label: '微博' },
        { value: 'general', label: '通用' }
      ],
      selectedPlatform: null,

      // 表单数据
      form: {
        type: 'TEXT',
        keywords: '',
        style: '',
        platform: '',
        scene: ''
      },

      // AI推荐
      aiRecommendation: null
    }
  },

  computed: {
    canGenerate() {
      return this.form.type && this.form.keywords && this.form.platform
    }
  },

  onLoad(options) {
    console.log('AI内容生成页面加载:', options)
    this.loadTemplates()

    // 如果有设备码参数，获取AI推荐
    if (options.device_code) {
      this.getAIRecommendation(options.device_code)
    }
  },

  methods: {
    /**
     * 加载模板列表
     */
    async loadTemplates() {
      this.isLoading = true
      this.loadingText = '加载模板...'

      try {
        const res = await api.template.getList({
          page: 1,
          pageSize: 20,
          status: 1  // 只加载启用的模板
        })

        const list = res.data || res.list || []

        // 添加图标
        this.templates = list.map(item => ({
          ...item,
          icon: this.getTemplateIcon(item.type)
        }))

        // 默认选择第一个模板
        if (this.templates.length > 0) {
          this.selectedTemplate = this.templates[0]
        }
      } catch (error) {
        console.error('加载模板失败:', error)

        // 使用默认模板
        this.templates = [
          { id: 'default', name: '通用模板', icon: '📄', type: 'TEXT' },
          { id: 'promotion', name: '营销推广', icon: '📢', type: 'TEXT' },
          { id: 'story', name: '故事叙述', icon: '📖', type: 'TEXT' },
          { id: 'tutorial', name: '教程说明', icon: '📚', type: 'TEXT' }
        ]
        this.selectedTemplate = this.templates[0]
      } finally {
        this.isLoading = false
      }
    },

    /**
     * 获取模板图标
     */
    getTemplateIcon(type) {
      const icons = {
        'TEXT': '📝',
        'IMAGE': '🖼️',
        'VIDEO': '🎬',
        'MIXED': '🎨'
      }
      return icons[type] || '📄'
    },

    /**
     * 选择模板
     */
    selectTemplate(template) {
      this.selectedTemplate = template
    },

    /**
     * 跳转到模板管理页面
     */
    goToTemplateManage() {
      uni.navigateTo({
        url: '/pages/template/list'
      })
    },

    /**
     * 风格选择变化
     */
    onStyleChange(e) {
      const index = e.detail.value
      this.selectedStyle = this.styleOptions[index]
      this.form.style = this.selectedStyle.value
    },

    /**
     * 平台选择变化
     */
    onPlatformChange(e) {
      const index = e.detail.value
      this.selectedPlatform = this.platformOptions[index]
      this.form.platform = this.selectedPlatform.value
    },

    /**
     * 获取AI推荐
     */
    async getAIRecommendation(deviceCode) {
      try {
        const res = await api.content.getAIRecommendation({
          device_code: deviceCode,
          type: this.form.type
        })

        this.aiRecommendation = res
      } catch (error) {
        console.error('获取AI推荐失败:', error)
      }
    },

    /**
     * 添加关键词
     */
    addKeyword(keyword) {
      if (!this.form.keywords) {
        this.form.keywords = keyword
      } else {
        const keywords = this.form.keywords.split(',').map(k => k.trim())
        if (!keywords.includes(keyword)) {
          keywords.push(keyword)
          this.form.keywords = keywords.join(',')
        }
      }

      FeedbackHelper.success('已添加关键词', {
        vibrate: true,
        duration: 1000
      })
    },

    /**
     * 重置表单
     */
    handleReset() {
      uni.showModal({
        title: '提示',
        content: '确定要重置所有配置吗？',
        success: (res) => {
          if (res.confirm) {
            this.form = {
              type: 'TEXT',
              keywords: '',
              style: '',
              platform: '',
              scene: ''
            }
            this.selectedStyle = null
            this.selectedPlatform = null
            this.selectedTemplate = this.templates[0]

            FeedbackHelper.success('已重置', { vibrate: false })
          }
        }
      })
    },

    /**
     * 开始生成
     */
    async handleGenerate() {
      if (!this.canGenerate) {
        FeedbackHelper.warning('请完善生成配置', { vibrate: true })
        return
      }

      this.isLoading = true
      this.loadingText = '正在创建任务...'

      try {
        // 创建生成任务
        const res = await api.content.createTask({
          type: this.form.type,
          templateId: this.selectedTemplate?.id,
          keywords: this.form.keywords,
          style: this.form.style,
          platform: this.form.platform,
          scene: this.form.scene
        })

        console.log('任务创建成功:', res)

        const taskId = res.content_task_id || res.task_id || res.id

        if (!taskId) {
          throw new Error('未返回任务ID')
        }

        // 显示成功反馈并跳转
        FeedbackHelper.successAndNavigate(
          '任务已创建',
          '/pages/nfc/trigger',
          1500
        )

        // 原跳转逻辑已由FeedbackHelper处理
        /*
        setTimeout(() => {
          uni.redirectTo({
            url: `/pages/nfc/trigger?task_id=${taskId}`
          })
        }, 500)
        */

      } catch (error) {
        console.error('创建任务失败:', error)
        FeedbackHelper.error(error.message || '创建任务失败，请重试', {
          vibrate: true,
          duration: 3000
        })
      } finally {
        this.isLoading = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.generate-page {
  min-height: 100vh;
  background: #f8f9fa;
  display: flex;
  flex-direction: column;
}

// 导航栏
.nav-bar {
  background: #ffffff;
  padding: 20rpx 30rpx;
  border-bottom: 1rpx solid #e5e7eb;

  .nav-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
  }
}

// 滚动内容
.content-scroll {
  flex: 1;
  padding: 30rpx;
}

// 区块
.section {
  margin-bottom: 40rpx;

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24rpx;
  }

  .section-title {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 12rpx;

    .ai-badge {
      font-size: 12px;
      padding: 4rpx 12rpx;
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: #ffffff;
      border-radius: 8rpx;
    }
  }

  .section-action {
    display: flex;
    align-items: center;
    gap: 8rpx;
    color: #667eea;
    font-size: 28rpx;

    .action-icon {
      font-size: 32rpx;
    }

    .action-text {
      font-size: 28rpx;
    }
  }
}

// 模板滚动
.template-scroll {
  white-space: nowrap;
}

.template-list {
  display: inline-flex;
  gap: 20rpx;
}

.template-item {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 12rpx;
  padding: 24rpx 32rpx;
  background: #ffffff;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;
  min-width: 140rpx;

  &.active {
    border-color: #6366f1;
    background: #f0f0ff;
  }

  .template-icon {
    font-size: 32px;
  }

  .template-name {
    font-size: 14px;
    color: #4b5563;
  }
}

// 内容类型
.type-list {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16rpx;
}

.type-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12rpx;
  padding: 24rpx 16rpx;
  background: #ffffff;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;

  &.active {
    border-color: #6366f1;
    background: #f0f0ff;
  }

  .type-icon {
    font-size: 28px;
  }

  .type-name {
    font-size: 13px;
    color: #4b5563;
  }
}

// 表单项
.form-item {
  margin-bottom: 30rpx;

  .form-label {
    font-size: 14px;
    color: #4b5563;
    margin-bottom: 16rpx;
    font-weight: 500;
  }

  .form-input,
  .form-picker {
    background: #ffffff;
    border: 1rpx solid #d1d5db;
    border-radius: 8rpx;
    padding: 24rpx;
    font-size: 14px;
    color: #1f2937;
  }

  .form-textarea {
    background: #ffffff;
    border: 1rpx solid #d1d5db;
    border-radius: 8rpx;
    padding: 24rpx;
    font-size: 14px;
    color: #1f2937;
    min-height: 180rpx;
  }

  .textarea-count {
    text-align: right;
    font-size: 12px;
    color: #9ca3af;
    margin-top: 8rpx;
  }
}

// AI推荐
.ai-recommendation {
  background: linear-gradient(135deg, #f0f0ff 0%, #faf5ff 100%);
  border: 1rpx solid #c7d2fe;
  border-radius: 12rpx;
  padding: 24rpx;

  .ai-tip {
    font-size: 14px;
    color: #4338ca;
    margin-bottom: 16rpx;
    line-height: 1.6;
  }

  .ai-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
  }

  .ai-tag {
    padding: 8rpx 16rpx;
    background: #ffffff;
    border: 1rpx solid #6366f1;
    border-radius: 16rpx;
    font-size: 13px;
    color: #6366f1;
  }
}

// 底部操作栏
.bottom-bar {
  background: #ffffff;
  padding: 24rpx 30rpx;
  border-top: 1rpx solid #e5e7eb;
  display: flex;
  gap: 20rpx;

  button {
    flex: 1;
    height: 88rpx;
    line-height: 88rpx;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
  }

  .secondary-btn {
    background: #ffffff;
    color: #6b7280;
    border: 1rpx solid #d1d5db;

    &:active {
      background: #f9fafb;
    }
  }

  .primary-btn {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    border: none;

    &:active {
      opacity: 0.8;
    }

    &:disabled {
      opacity: 0.5;
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

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>

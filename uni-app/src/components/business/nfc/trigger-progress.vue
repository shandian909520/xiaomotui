<template>
  <view class="triggering-state">
    <!-- 设备信息 -->
    <view class="device-info" v-if="deviceInfo">
      <view class="device-icon">🏪</view>
      <view class="device-name">{{ deviceInfo.name }}</view>
      <view class="device-code">设备码: {{ deviceCode }}</view>
    </view>

    <!-- 任务状态 -->
    <view class="task-status">
      <view class="status-icon" :class="statusClass">
        <text v-if="taskStatus === 'pending'">⏳</text>
        <text v-if="taskStatus === 'processing'">⚙️</text>
        <text v-if="taskStatus === 'completed'">✅</text>
        <text v-if="taskStatus === 'failed'">❌</text>
      </view>
      <view class="status-text">{{ statusText }}</view>
    </view>

    <!-- AI进度可视化组件 -->
    <view class="ai-progress-section" v-if="taskStatus === 'processing' || taskStatus === 'pending'">
      <ai-progress
        :progress="progress"
        :steps="progressSteps"
        :elapsedTime="elapsedTime"
        :remainingTime="remainingTime"
        :currentStepName="currentStepName"
        :taskStatus="taskStatus"
      ></ai-progress>
    </view>

    <!-- 生成信息 -->
    <view class="generation-info" v-if="generationInfo">
      <view class="info-item" v-if="generationInfo.content_type">
        <text class="info-label">内容类型:</text>
        <text class="info-value">{{ formatContentType(generationInfo.content_type) }}</text>
      </view>
      <view class="info-item" v-if="generationInfo.platform">
        <text class="info-label">目标平台:</text>
        <text class="info-value">{{ generationInfo.platform }}</text>
      </view>
      <view class="info-item" v-if="generationInfo.generation_time">
        <text class="info-label">生成时间:</text>
        <text class="info-value">{{ generationInfo.generation_time }}秒</text>
      </view>
    </view>

    <!-- 错误信息 -->
    <view class="error-message" v-if="errorMessage">
      <view class="error-icon">{{ errorInfo.icon || '⚠️' }}</view>
      <view class="error-content">
        <text class="error-title">{{ errorInfo.message || errorMessage }}</text>
        <text class="error-solution" v-if="errorInfo.solution">
          💡 {{ errorInfo.solution }}
        </text>
        <text class="error-device-code" v-if="errorInfo.contact_merchant && deviceCode">
          设备编号：{{ deviceCode }}
        </text>
      </view>
    </view>

    <!-- 操作按钮 -->
    <view class="action-buttons">
      <!-- 完成状态 -->
      <button
        class="primary-btn"
        v-if="taskStatus === 'completed'"
        @tap="handleViewContent"
      >
        查看内容
      </button>

      <!-- 失败状态 -->
      <button
        class="primary-btn"
        v-if="taskStatus === 'failed' && errorInfo.retry"
        @tap="handleRetry"
      >
        重新触发
      </button>

      <!-- 联系商家按钮 -->
      <button
        class="secondary-btn"
        v-if="taskStatus === 'failed' && errorInfo.contact_merchant"
        @tap="handleContactMerchant"
      >
        联系商家
      </button>

      <!-- 处理中状态 -->
      <button
        class="secondary-btn"
        v-if="taskStatus === 'processing' || taskStatus === 'pending'"
        @tap="handleCancel"
      >
        取消任务
      </button>
    </view>
  </view>
</template>

<script>
import AiProgress from '../../ai-progress/ai-progress.vue'

export default {
  name: 'TriggerProgress',

  components: {
    AiProgress
  },

  props: {
    taskStatus: {
      type: String,
      default: ''
    },
    progress: {
      type: Number,
      default: 0
    },
    progressSteps: {
      type: Array,
      default: () => []
    },
    elapsedTime: {
      type: Number,
      default: 0
    },
    remainingTime: {
      type: Number,
      default: 0
    },
    currentStepName: {
      type: String,
      default: '等待处理'
    },
    errorMessage: {
      type: String,
      default: ''
    },
    errorInfo: {
      type: Object,
      default: () => ({})
    },
    deviceInfo: {
      type: Object,
      default: null
    },
    deviceCode: {
      type: String,
      default: ''
    },
    generationInfo: {
      type: Object,
      default: null
    }
  },

  computed: {
    statusText() {
      const statusMap = {
        pending: '任务等待中...',
        processing: '正在生成内容...',
        completed: '生成完成!',
        failed: '生成失败'
      }
      return statusMap[this.taskStatus] || '未知状态'
    },

    statusClass() {
      return `status-${this.taskStatus}`
    }
  },

  methods: {
    formatContentType(type) {
      const typeMap = {
        'TEXT': '文本内容',
        'IMAGE': '图片内容',
        'VIDEO': '视频内容',
        'MIXED': '混合内容'
      }
      return typeMap[type] || type
    },

    handleViewContent() {
      this.$emit('view-content')
    },

    handleRetry() {
      this.$emit('retry')
    },

    handleCancel() {
      this.$emit('cancel')
    },

    handleContactMerchant() {
      this.$emit('contact-merchant')
    }
  }
}
</script>

<style lang="scss" scoped>
.triggering-state {
  display: flex;
  flex-direction: column;
  align-items: center;

  .device-info {
    background: #ffffff;
    border-radius: 16rpx;
    padding: 40rpx;
    width: 100%;
    text-align: center;
    margin-bottom: 40rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

    .device-icon {
      font-size: 48px;
      margin-bottom: 20rpx;
    }

    .device-name {
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 12rpx;
    }

    .device-code {
      font-size: 12px;
      color: #9ca3af;
      font-family: monospace;
    }
  }

  .task-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 40rpx;

    .status-icon {
      font-size: 60px;
      margin-bottom: 20rpx;
      animation: pulse 2s ease-in-out infinite;

      &.status-processing {
        animation: rotate 2s linear infinite;
      }
    }

    .status-text {
      font-size: 16px;
      color: #4b5563;
      font-weight: 500;
    }
  }

  .ai-progress-section {
    width: 100%;
    margin-bottom: 40rpx;
  }

  .generation-info {
    width: 100%;
    background: #f9fafb;
    border-radius: 12rpx;
    padding: 30rpx;
    margin-bottom: 40rpx;

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16rpx 0;
      border-bottom: 1rpx solid #e5e7eb;

      &:last-child {
        border-bottom: none;
      }

      .info-label {
        font-size: 14px;
        color: #6b7280;
      }

      .info-value {
        font-size: 14px;
        color: #1f2937;
        font-weight: 500;
      }
    }
  }

  .error-message {
    width: 100%;
    background: #fef2f2;
    border: 1rpx solid #fecaca;
    border-radius: 12rpx;
    padding: 30rpx;
    margin-bottom: 40rpx;
    display: flex;
    align-items: flex-start;
    gap: 16rpx;

    .error-icon {
      font-size: 24px;
      flex-shrink: 0;
    }

    .error-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 12rpx;

      .error-title {
        font-size: 14px;
        color: #dc2626;
        font-weight: 600;
      }

      .error-solution {
        font-size: 13px;
        color: #ef4444;
        line-height: 1.6;
      }

      .error-device-code {
        font-size: 12px;
        color: #9ca3af;
        font-family: monospace;
        margin-top: 8rpx;
      }
    }
  }

  .action-buttons {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 20rpx;

    button {
      width: 100%;
      border: none;
      border-radius: 12rpx;
      padding: 24rpx 48rpx;
      font-size: 16px;

      &:active {
        opacity: 0.8;
      }
    }

    .primary-btn {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: #ffffff;
      font-weight: 600;
    }

    .secondary-btn {
      background: #ffffff;
      color: #6b7280;
      border: 1rpx solid #d1d5db;
    }
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>

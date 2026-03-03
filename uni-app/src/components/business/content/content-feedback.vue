<template>
  <view class="feedback-section">
    <view class="section-header">
      <text class="section-icon">💬</text>
      <text class="section-title">内容反馈</text>
    </view>

    <!-- 点赞/点踩按钮 -->
    <view class="feedback-buttons" v-if="!feedbackSubmitted">
      <button
        class="feedback-btn like-btn"
        :class="{ active: feedbackType === 'like' }"
        @tap="handleFeedback('like')"
      >
        <text class="btn-icon">{{ feedbackType === 'like' ? '👍' : '👍🏻' }}</text>
        <text class="btn-text">满意</text>
      </button>
      <button
        class="feedback-btn dislike-btn"
        :class="{ active: feedbackType === 'dislike' }"
        @tap="handleFeedback('dislike')"
      >
        <text class="btn-icon">{{ feedbackType === 'dislike' ? '👎' : '👎🏻' }}</text>
        <text class="btn-text">不满意</text>
      </button>
    </view>

    <!-- 反馈原因选择 -->
    <view class="feedback-reasons" v-if="feedbackType === 'dislike' && !feedbackSubmitted">
      <view class="reasons-title">请选择不满意的原因（可多选）:</view>
      <view class="reasons-list">
        <view
          v-for="(reason, index) in dislikeReasons"
          :key="index"
          class="reason-item"
          :class="{ selected: selectedReasons.includes(index) }"
          @tap="toggleReason(index)"
        >
          <text class="reason-checkbox">
            {{ selectedReasons.includes(index) ? '☑️' : '☐' }}
          </text>
          <text class="reason-text">{{ reason }}</text>
        </view>
      </view>

      <view class="other-reason-input">
        <textarea
          v-model="otherReason"
          placeholder="其他原因（选填）"
          maxlength="200"
          class="reason-textarea"
        />
        <text class="char-count">{{ otherReason.length }}/200</text>
      </view>

      <button class="submit-feedback-btn" @tap="submitFeedback">
        提交反馈
      </button>
    </view>

    <!-- 反馈成功提示 -->
    <view class="feedback-success" v-if="feedbackSubmitted">
      <text class="success-icon">✅</text>
      <text class="success-text">感谢您的反馈！</text>
      <text class="success-desc" v-if="feedbackType === 'like'">
        您的满意是我们最大的动力
      </text>
      <text class="success-desc" v-else>
        我们会根据您的建议持续优化
      </text>
    </view>

    <!-- 重新生成提示 -->
    <view class="regenerate-hint" v-if="feedbackType === 'dislike' && feedbackSubmitted">
      <button class="regenerate-btn-inline" @tap="$emit('regenerate', getFeedbackData())">
        <text class="btn-icon">🔄</text>
        <text class="btn-text">根据反馈重新生成</text>
      </button>
    </view>
  </view>
</template>

<script>
import api from '../../../api/index.js'

export default {
  props: {
    contentId: { type: String, default: '' },
    submitted: { type: Boolean, default: false }
  },

  data() {
    return {
      feedbackType: '',
      feedbackSubmitted: false,
      selectedReasons: [],
      otherReason: '',
      dislikeReasons: [
        '内容与需求不符',
        '质量不够好',
        '创意不够新颖',
        '文案表达不准确',
        '画面/视频效果差',
        '时长/篇幅不合适',
        '缺少关键信息',
        '风格不符合预期'
      ]
    }
  },

  watch: {
    submitted(val) {
      if (val) this.feedbackSubmitted = true
    }
  },

  methods: {
    handleFeedback(type) {
      if (this.feedbackSubmitted) return
      this.feedbackType = type
      if (type === 'like') {
        this.submitFeedback()
      }
    },

    toggleReason(index) {
      const pos = this.selectedReasons.indexOf(index)
      if (pos > -1) {
        this.selectedReasons.splice(pos, 1)
      } else {
        this.selectedReasons.push(index)
      }
    },

    getFeedbackData() {
      return {
        feedback_type: this.feedbackType,
        reasons: this.selectedReasons.map(i => this.dislikeReasons[i]),
        other_reason: this.otherReason
      }
    },

    async submitFeedback() {
      if (this.feedbackSubmitted) return

      if (this.feedbackType === 'dislike' && this.selectedReasons.length === 0 && !this.otherReason) {
        uni.showToast({ title: '请至少选择一个原因', icon: 'none' })
        return
      }

      try {
        uni.showLoading({ title: '提交中...' })

        const feedbackData = {
          task_id: this.contentId,
          feedback_type: this.feedbackType,
          reasons: this.feedbackType === 'dislike'
            ? this.selectedReasons.map(i => this.dislikeReasons[i])
            : [],
          other_reason: this.otherReason,
          submit_time: new Date().toISOString()
        }

        await api.content.submitFeedback(feedbackData)
        this.feedbackSubmitted = true
        uni.vibrateShort()
        uni.showToast({ title: '反馈提交成功', icon: 'success' })
        this.$emit('feedback', feedbackData)
      } catch (error) {
        console.error('提交反馈失败:', error)
        uni.showToast({ title: '提交失败，请重试', icon: 'none' })
      } finally {
        uni.hideLoading()
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.feedback-section {
  background: #ffffff;
  padding: 30rpx;
  margin-bottom: 20rpx;

  .section-header {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 30rpx;

    .section-icon { font-size: 20px; }
    .section-title { font-size: 16px; font-weight: 600; color: #1f2937; }
  }

  .feedback-buttons {
    display: flex;
    gap: 30rpx;
    margin-bottom: 30rpx;

    .feedback-btn {
      flex: 1;
      height: 100rpx;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border-radius: 12rpx;
      border: 2rpx solid #e5e7eb;
      background: #fff;
      font-size: 14px;
      color: #6b7280;
      transition: all 0.3s;

      .btn-icon { font-size: 24px; margin-bottom: 8rpx; }

      &.like-btn.active {
        border-color: #10b981;
        background: #d1fae5;
        color: #059669;
      }

      &.dislike-btn.active {
        border-color: #ef4444;
        background: #fee2e2;
        color: #dc2626;
      }
    }
  }

  .feedback-reasons {
    .reasons-title { font-size: 14px; color: #1f2937; margin-bottom: 20rpx; }

    .reasons-list {
      margin-bottom: 30rpx;

      .reason-item {
        padding: 20rpx;
        background: #f9fafb;
        border-radius: 12rpx;
        margin-bottom: 16rpx;
        display: flex;
        align-items: center;
        border: 2rpx solid transparent;
        transition: all 0.3s;

        &.selected { background: #fef3c7; border-color: #f59e0b; }
        .reason-checkbox { font-size: 16px; margin-right: 16rpx; }
        .reason-text { flex: 1; font-size: 14px; color: #1f2937; }
      }
    }

    .other-reason-input {
      margin-bottom: 30rpx;
      position: relative;

      .reason-textarea {
        width: 100%;
        min-height: 150rpx;
        padding: 20rpx;
        background: #f9fafb;
        border-radius: 12rpx;
        font-size: 14px;
        color: #1f2937;
        border: 2rpx solid #e5e7eb;

        &:focus { border-color: #6366f1; }
      }

      .char-count {
        position: absolute;
        right: 20rpx;
        bottom: 10rpx;
        font-size: 12px;
        color: #9ca3af;
      }
    }

    .submit-feedback-btn {
      width: 100%;
      height: 88rpx;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border-radius: 12rpx;
      font-size: 16px;
      font-weight: 600;
      border: none;
    }
  }

  .feedback-success {
    padding: 60rpx 30rpx;
    display: flex;
    flex-direction: column;
    align-items: center;

    .success-icon { font-size: 50px; margin-bottom: 30rpx; }
    .success-text { font-size: 16px; font-weight: 600; color: #059669; margin-bottom: 16rpx; }
    .success-desc { font-size: 13px; color: #6b7280; }
  }

  .regenerate-hint {
    margin-top: 30rpx;

    .regenerate-btn-inline {
      width: 100%;
      height: 88rpx;
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: #fff;
      border-radius: 12rpx;
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12rpx;
      border: none;

      .btn-icon { font-size: 18px; }
    }
  }
}
</style>

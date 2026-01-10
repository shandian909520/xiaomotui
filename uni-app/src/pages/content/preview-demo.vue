<template>
  <view class="demo-page">
    <view class="demo-header">
      <text class="demo-title">内容预览页面演示</text>
      <text class="demo-subtitle">展示不同类型内容的预览效果</text>
    </view>

    <view class="demo-section">
      <view class="section-title">视频内容预览</view>
      <view class="demo-card" @tap="previewVideo">
        <view class="card-icon">🎬</view>
        <view class="card-info">
          <view class="card-title">视频探店内容</view>
          <view class="card-desc">点击预览视频内容，包含播放控制、下载、分享等功能</view>
        </view>
        <view class="card-arrow">→</view>
      </view>
    </view>

    <view class="demo-section">
      <view class="section-title">图片内容预览</view>
      <view class="demo-card" @tap="previewImage">
        <view class="card-icon">🖼️</view>
        <view class="card-info">
          <view class="card-title">图片营销内容</view>
          <view class="card-desc">点击预览图片内容，支持保存和分享</view>
        </view>
        <view class="card-arrow">→</view>
      </view>
    </view>

    <view class="demo-section">
      <view class="section-title">文案内容预览</view>
      <view class="demo-card" @tap="previewText">
        <view class="card-icon">📝</view>
        <view class="card-info">
          <view class="card-title">纯文案内容</view>
          <view class="card-desc">点击预览文案内容，支持复制和编辑</view>
        </view>
        <view class="card-arrow">→</view>
      </view>
    </view>

    <view class="demo-section">
      <view class="section-title">功能特性</view>
      <view class="features-list">
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">多平台兼容（H5/小程序/APP）</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">视频播放控制和进度展示</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">下载和保存到相册</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">分享到社交平台</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">一键重新生成</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">立即发布到各平台</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">内容信息详细展示</text>
        </view>
        <view class="feature-item">
          <text class="feature-icon">✅</text>
          <text class="feature-text">标签和文案管理</text>
        </view>
      </view>
    </view>

    <view class="demo-section">
      <view class="section-title">使用说明</view>
      <view class="usage-box">
        <view class="usage-step">
          <view class="step-number">1</view>
          <view class="step-content">
            <view class="step-title">从NFC触发页面跳转</view>
            <view class="step-desc">在内容生成完成后，点击"查看内容"按钮跳转到预览页面</view>
          </view>
        </view>

        <view class="usage-step">
          <view class="step-number">2</view>
          <view class="step-content">
            <view class="step-title">URL参数传递</view>
            <view class="step-desc">通过task_id参数传递任务ID，例如：/pages/content/preview?task_id=123</view>
          </view>
        </view>

        <view class="usage-step">
          <view class="step-number">3</view>
          <view class="step-content">
            <view class="step-title">操作内容</view>
            <view class="step-desc">在预览页面可以下载、保存、分享内容，或继续编辑和发布</view>
          </view>
        </view>

        <view class="usage-step">
          <view class="step-number">4</view>
          <view class="step-content">
            <view class="step-title">发布内容</view>
            <view class="step-desc">点击"立即发布"按钮跳转到发布设置页面，选择平台进行发布</view>
          </view>
        </view>
      </view>
    </view>

    <view class="demo-section">
      <view class="section-title">API调用示例</view>
      <view class="code-block">
        <text class="code-line">// 跳转到内容预览页面</text>
        <text class="code-line">uni.navigateTo(&#123;</text>
        <text class="code-line">  url: `/pages/content/preview?task_id=$&#123;taskId&#125;`</text>
        <text class="code-line">&#125;)</text>
        <text class="code-line"></text>
        <text class="code-line">// 获取内容详情</text>
        <text class="code-line">const content = await api.content.getTaskDetail(taskId)</text>
        <text class="code-line"></text>
        <text class="code-line">// 下载内容</text>
        <text class="code-line">await api.content.downloadContent(taskId)</text>
        <text class="code-line"></text>
        <text class="code-line">// 保存到相册</text>
        <text class="code-line">await api.content.saveToAlbum(fileUrl, 'video')</text>
        <text class="code-line"></text>
        <text class="code-line">// 重新生成</text>
        <text class="code-line">await api.content.regenerate(taskId)</text>
      </view>
    </view>

    <view class="demo-footer">
      <text class="footer-text">小魔推碰一碰 - 内容预览演示</text>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      // 模拟数据
      mockTaskIds: {
        video: 'demo_video_001',
        image: 'demo_image_001',
        text: 'demo_text_001'
      }
    }
  },

  methods: {
    /**
     * 预览视频内容
     */
    previewVideo() {
      uni.showModal({
        title: '提示',
        content: '这是演示页面。实际使用中，点击会跳转到真实的内容预览页面。',
        confirmText: '前往预览',
        success: (res) => {
          if (res.confirm) {
            // 实际项目中使用真实的task_id
            uni.navigateTo({
              url: `/pages/content/preview?task_id=${this.mockTaskIds.video}`
            })
          }
        }
      })
    },

    /**
     * 预览图片内容
     */
    previewImage() {
      uni.showModal({
        title: '提示',
        content: '这是演示页面。实际使用中，点击会跳转到真实的内容预览页面。',
        confirmText: '前往预览',
        success: (res) => {
          if (res.confirm) {
            uni.navigateTo({
              url: `/pages/content/preview?task_id=${this.mockTaskIds.image}`
            })
          }
        }
      })
    },

    /**
     * 预览文案内容
     */
    previewText() {
      uni.showModal({
        title: '提示',
        content: '这是演示页面。实际使用中，点击会跳转到真实的内容预览页面。',
        confirmText: '前往预览',
        success: (res) => {
          if (res.confirm) {
            uni.navigateTo({
              url: `/pages/content/preview?task_id=${this.mockTaskIds.text}`
            })
          }
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-page {
  min-height: 100vh;
  background: #f8f9fa;
  padding-bottom: 40rpx;
}

.demo-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 60rpx 30rpx 80rpx;
  text-align: center;
  color: #ffffff;

  .demo-title {
    display: block;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 16rpx;
  }

  .demo-subtitle {
    display: block;
    font-size: 14px;
    opacity: 0.9;
  }
}

.demo-section {
  margin: 30rpx;
  margin-top: -40rpx;

  &:not(:first-child) {
    margin-top: 30rpx;
  }

  .section-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 20rpx;
    padding-left: 20rpx;
    border-left: 4rpx solid #6366f1;
  }
}

.demo-card {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  display: flex;
  align-items: center;
  gap: 24rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.06);
  margin-bottom: 20rpx;
  transition: transform 0.2s;

  &:active {
    transform: scale(0.98);
  }

  .card-icon {
    font-size: 48px;
    flex-shrink: 0;
  }

  .card-info {
    flex: 1;

    .card-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 8rpx;
    }

    .card-desc {
      font-size: 14px;
      color: #6b7280;
      line-height: 1.5;
    }
  }

  .card-arrow {
    font-size: 24px;
    color: #9ca3af;
    flex-shrink: 0;
  }
}

.features-list {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.06);

  .feature-item {
    display: flex;
    align-items: center;
    gap: 16rpx;
    padding: 16rpx 0;
    border-bottom: 1rpx solid #f3f4f6;

    &:last-child {
      border-bottom: none;
    }

    .feature-icon {
      font-size: 18px;
      flex-shrink: 0;
    }

    .feature-text {
      flex: 1;
      font-size: 14px;
      color: #4b5563;
    }
  }
}

.usage-box {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.06);

  .usage-step {
    display: flex;
    gap: 24rpx;
    margin-bottom: 30rpx;

    &:last-child {
      margin-bottom: 0;
    }

    .step-number {
      width: 48rpx;
      height: 48rpx;
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-size: 18px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .step-content {
      flex: 1;

      .step-title {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 8rpx;
      }

      .step-desc {
        font-size: 14px;
        color: #6b7280;
        line-height: 1.6;
      }
    }
  }
}

.code-block {
  background: #1f2937;
  border-radius: 12rpx;
  padding: 30rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.06);

  .code-line {
    display: block;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #d1d5db;
    line-height: 1.8;

    &:first-child {
      color: #9ca3af;
    }
  }
}

.demo-footer {
  margin-top: 60rpx;
  padding: 40rpx 30rpx;
  text-align: center;

  .footer-text {
    font-size: 14px;
    color: #9ca3af;
  }
}
</style>

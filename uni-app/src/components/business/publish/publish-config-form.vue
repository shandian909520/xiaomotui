<template>
  <view class="publish-config-form">
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
          :value="config.title"
          @input="handleTitleInput"
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
          :value="config.description"
          @input="handleDescriptionInput"
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
        <view class="tags-preview" v-if="config.tags && config.tags.length">
          <view class="tag-item" v-for="(tag, index) in config.tags" :key="index">
            #{{ tag }}
          </view>
        </view>
      </view>
    </view>

    <!-- 平台特定配置 -->
    <view class="platform-configs" v-if="selectedPlatforms.length">
      <view
        class="platform-config-item"
        v-for="platform in selectedPlatforms"
        :key="platform.id"
      >
        <view class="config-item-header" @tap="handleToggleExpand(platform.id)">
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
              :value="platformConfigs[platform.id] && platformConfigs[platform.id].title"
              @input="handlePlatformTitleInput($event, platform.id)"
              :placeholder="`${getPlatformName(platform.platform)}专用标题`"
            />
          </view>

          <view class="form-item">
            <view class="form-label">
              <text class="label-text">平台描述</text>
            </view>
            <textarea
              class="form-textarea"
              :value="platformConfigs[platform.id] && platformConfigs[platform.id].description"
              @input="handlePlatformDescriptionInput($event, platform.id)"
              :placeholder="`${getPlatformName(platform.platform)}专用描述`"
              :auto-height="true"
            />
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'PublishConfigForm',

  props: {
    config: {
      type: Object,
      default: () => ({
        title: '',
        description: '',
        tags: []
      })
    },
    selectedPlatforms: {
      type: Array,
      default: () => []
    },
    platformConfigs: {
      type: Object,
      default: () => ({})
    },
    expandedConfigs: {
      type: Object,
      default: () => ({})
    }
  },

  data() {
    return {
      tagsInput: ''
    }
  },

  watch: {
    'config.tags': {
      handler(newTags) {
        if (Array.isArray(newTags)) {
          this.tagsInput = newTags.join(' ')
        }
      },
      immediate: true
    }
  },

  methods: {
    handleTitleInput(e) {
      this.$emit('update:config', {
        ...this.config,
        title: e.detail.value
      })
    },

    handleDescriptionInput(e) {
      this.$emit('update:config', {
        ...this.config,
        description: e.detail.value
      })
    },

    handleTagsChange() {
      if (!this.tagsInput.trim()) {
        this.$emit('update:config', {
          ...this.config,
          tags: []
        })
        return
      }

      const tags = this.tagsInput
        .split(/\s+/)
        .map(tag => tag.trim())
        .filter(tag => tag)

      this.$emit('update:config', {
        ...this.config,
        tags: [...new Set(tags)]
      })
    },

    handlePlatformTitleInput(e, platformId) {
      const currentConfig = this.platformConfigs[platformId] || {}
      this.$emit('update:platformConfig', {
        platformId,
        config: {
          ...currentConfig,
          title: e.detail.value
        }
      })
    },

    handlePlatformDescriptionInput(e, platformId) {
      const currentConfig = this.platformConfigs[platformId] || {}
      this.$emit('update:platformConfig', {
        platformId,
        config: {
          ...currentConfig,
          description: e.detail.value
        }
      })
    },

    handleToggleExpand(platformId) {
      this.$emit('toggle-expand', platformId)
    },

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
    }
  }
}
</script>

<style lang="scss" scoped>
.publish-config-form {
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
  }
}

.config-form {
  display: flex;
  flex-direction: column;
  gap: 30rpx;
}

.form-item {
  .form-label {
    display: flex;
    align-items: center;
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
    border-radius: 12rpx;
    font-size: 14px;
    color: #1f2937;

    &::placeholder {
      color: #9ca3af;
    }
  }

  .form-textarea {
    width: 100%;
    padding: 24rpx;
    background: #f9fafb;
    border: 1rpx solid #e5e7eb;
    border-radius: 12rpx;
    font-size: 14px;
    color: #1f2937;
    min-height: 120rpx;
    line-height: 1.6;

    &::placeholder {
      color: #9ca3af;
    }
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
</style>

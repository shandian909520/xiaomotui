<template>
  <view class="related-section" v-if="materials.length">
    <view class="section-header">
      <text class="section-icon">🔗</text>
      <text class="section-title">相关素材</text>
    </view>
    <scroll-view class="related-scroll" scroll-x>
      <view class="related-list">
        <view
          class="related-item"
          v-for="item in materials"
          :key="item.id"
          @tap="handleView(item.id)"
        >
          <image
            class="related-image"
            :src="item.cover_url || item.file_url"
            mode="aspectFill"
            lazy-load
          />
          <text class="related-title">{{ item.title }}</text>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script>
export default {
  name: 'RelatedMaterials',

  props: {
    materials: {
      type: Array,
      default: () => []
    }
  },

  methods: {
    /**
     * 处理查看相关素材
     */
    handleView(materialId) {
      this.$emit('view', materialId)
    }
  }
}
</script>

<style lang="scss" scoped>
.related-section {
  background: #ffffff;
  padding: 30rpx;
  margin-bottom: 20rpx;

  .section-header {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 20rpx;

    .section-icon {
      font-size: 20px;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
    }
  }

  .related-scroll {
    width: 100%;
    white-space: nowrap;
  }

  .related-list {
    display: inline-flex;
    gap: 20rpx;

    .related-item {
      display: inline-flex;
      flex-direction: column;
      width: 200rpx;
      gap: 12rpx;

      .related-image {
        width: 200rpx;
        height: 200rpx;
        border-radius: 12rpx;
        background: #f3f4f6;
      }

      .related-title {
        font-size: 14px;
        color: #4b5563;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }
  }
}
</style>

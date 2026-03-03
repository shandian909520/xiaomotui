<template>
  <view class="material-page">
    <!-- 顶部搜索栏 -->
    <view class="search-bar">
      <view class="search-input-wrapper">
        <text class="search-icon">🔍</text>
        <input
          class="search-input"
          v-model="searchKeyword"
          placeholder="搜索素材"
          @confirm="handleSearch"
        />
      </view>
      <view class="filter-btn" @tap="showFilterPopup = true">
        <text class="filter-icon">🎛️</text>
      </view>
    </view>

    <!-- 分类标签 -->
    <scroll-view class="category-scroll" scroll-x>
      <view class="category-list">
        <view
          v-for="cat in categories"
          :key="cat.value"
          class="category-item"
          :class="{ active: currentCategory === cat.value }"
          @tap="selectCategory(cat.value)"
        >
          {{ cat.label }}
        </view>
      </view>
    </scroll-view>

    <!-- 素材列表 -->
    <scroll-view
      class="material-list"
      scroll-y
      @scrolltolower="loadMore"
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      @refresherrefresh="onRefresh"
    >
      <view class="material-grid">
        <view
          v-for="item in materialList"
          :key="item.id"
          class="material-card"
          @tap="viewDetail(item.id)"
        >
          <!-- 封面 -->
          <view class="material-cover">
            <image
              v-if="item.cover_url"
              class="cover-image"
              :src="item.cover_url"
              mode="aspectFill"
              lazy-load
            />
            <view v-else class="cover-placeholder">
              <text class="placeholder-icon">{{ getTypeIcon(item.type) }}</text>
            </view>

            <!-- 类型标签 -->
            <view class="type-badge" :class="`type-${item.type}`">
              {{ formatType(item.type) }}
            </view>

            <!-- 评分 -->
            <view class="rating-badge" v-if="item.rating">
              <text class="star-icon">⭐</text>
              <text>{{ item.rating }}</text>
            </view>
          </view>

          <!-- 信息 -->
          <view class="material-info">
            <view class="material-title">{{ item.title || '未命名素材' }}</view>
            <view class="material-meta">
              <text class="meta-item">👁️ {{ item.view_count || 0 }}</text>
              <text class="meta-item">❤️ {{ item.like_count || 0 }}</text>
              <text class="meta-item">📥 {{ item.use_count || 0 }}</text>
            </view>
            <view class="material-tags" v-if="item.tags && item.tags.length">
              <text
                v-for="(tag, index) in item.tags.slice(0, 3)"
                :key="index"
                class="tag"
              >
                #{{ tag }}
              </text>
            </view>
          </view>
        </view>
      </view>

      <!-- 加载状态 -->
      <view class="load-more" v-if="hasMore">
        <view class="loading-spinner" v-if="loading"></view>
        <text class="load-more-text">{{ loading ? '加载中...' : '上拉加载更多' }}</text>
      </view>
      <view class="no-more" v-else-if="materialList.length > 0">
        <text>没有更多了</text>
      </view>

      <!-- 空状态 -->
      <empty-state
        v-if="!loading && materialList.length === 0"
        icon="📦"
        title="暂无素材"
        btnText="上传素材"
        @action="handleUpload"
      />
    </scroll-view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar">
      <button class="action-btn secondary" @tap="handleUpload">
        <text class="btn-icon">📤</text>
        <text>上传</text>
      </button>
      <button class="action-btn primary" @tap="handleBatchManage">
        <text class="btn-icon">✏️</text>
        <text>管理</text>
      </button>
    </view>

    <!-- 筛选弹窗 -->
    <view class="filter-popup" v-if="showFilterPopup" @tap="showFilterPopup = false">
      <view class="filter-content" @tap.stop>
        <view class="filter-header">
          <text class="filter-title">筛选条件</text>
          <text class="filter-reset" @tap="resetFilter">重置</text>
        </view>

        <view class="filter-section">
          <view class="filter-label">排序方式</view>
          <view class="filter-options">
            <view
              v-for="sort in sortOptions"
              :key="sort.value"
              class="filter-option"
              :class="{ active: filterData.sort === sort.value }"
              @tap="filterData.sort = sort.value"
            >
              {{ sort.label }}
            </view>
          </view>
        </view>

        <view class="filter-section">
          <view class="filter-label">素材类型</view>
          <view class="filter-options">
            <view
              v-for="type in typeOptions"
              :key="type.value"
              class="filter-option"
              :class="{ active: filterData.type === type.value }"
              @tap="filterData.type = type.value"
            >
              {{ type.label }}
            </view>
          </view>
        </view>

        <view class="filter-actions">
          <button class="filter-btn-secondary" @tap="showFilterPopup = false">取消</button>
          <button class="filter-btn-primary" @tap="applyFilter">确定</button>
        </view>
      </view>
    </view>

    <!-- 加载遮罩 -->
    <view class="loading-overlay" v-if="isLoading">
      <view class="loading-box">
        <view class="loading-spinner-large"></view>
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
      searchKeyword: '',
      currentCategory: 'all',
      categories: [
        { value: 'all', label: '全部' },
        { value: 'image', label: '图片' },
        { value: 'video', label: '视频' },
        { value: 'text', label: '文案' },
        { value: 'template', label: '模板' }
      ],

      materialList: [],
      page: 1,
      pageSize: 20,
      hasMore: true,
      loading: false,
      refreshing: false,

      showFilterPopup: false,
      filterData: {
        sort: 'latest',
        type: 'all'
      },

      sortOptions: [
        { value: 'latest', label: '最新' },
        { value: 'popular', label: '最热' },
        { value: 'rating', label: '评分' }
      ],

      typeOptions: [
        { value: 'all', label: '全部类型' },
        { value: 'image', label: '图片' },
        { value: 'video', label: '视频' },
        { value: 'text', label: '文案' }
      ],

      isLoading: false,
      loadingText: '加载中...'
    }
  },

  onLoad(options) {
    console.log('素材库页面加载:', options)
    this.loadMaterialList()
  },

  methods: {
    /**
     * 加载素材列表
     */
    async loadMaterialList(refresh = false) {
      if (this.loading) return

      if (refresh) {
        this.page = 1
        this.materialList = []
        this.hasMore = true
      }

      this.loading = true

      try {
        const params = {
          page: this.page,
          pageSize: this.pageSize,
          category: this.currentCategory === 'all' ? '' : this.currentCategory,
          keyword: this.searchKeyword,
          sort: this.filterData.sort,
          type: this.filterData.type === 'all' ? '' : this.filterData.type
        }

        const res = await api.material.getList(params)

        const newList = res.data || []

        if (refresh) {
          this.materialList = newList
        } else {
          this.materialList = [...this.materialList, ...newList]
        }

        this.hasMore = newList.length >= this.pageSize
        this.page++

      } catch (error) {
        console.error('加载素材列表失败:', error)

        // 使用模拟数据
        const mockData = this.generateMockData()
        if (refresh) {
          this.materialList = mockData
        } else {
          this.materialList = [...this.materialList, ...mockData]
        }

        this.hasMore = false
      } finally {
        this.loading = false
        this.refreshing = false
      }
    },

    /**
     * 生成模拟数据
     */
    generateMockData() {
      const types = ['image', 'video', 'text']
      const titles = [
        '美食推荐文案',
        '店铺宣传视频',
        '优惠活动海报',
        '菜品展示图',
        '节日营销素材'
      ]

      return Array.from({ length: 10 }, (_, i) => ({
        id: Date.now() + i,
        type: types[i % 3],
        title: titles[i % titles.length],
        cover_url: i % 2 === 0 ? `https://via.placeholder.com/300x200?text=Material${i + 1}` : '',
        view_count: Math.floor(Math.random() * 1000),
        like_count: Math.floor(Math.random() * 100),
        use_count: Math.floor(Math.random() * 50),
        rating: (Math.random() * 2 + 3).toFixed(1),
        tags: ['美食', '推广', '优惠'].slice(0, Math.floor(Math.random() * 3) + 1)
      }))
    },

    /**
     * 选择分类
     */
    selectCategory(category) {
      if (this.currentCategory === category) return
      this.currentCategory = category
      this.loadMaterialList(true)
    },

    /**
     * 搜索
     */
    handleSearch() {
      this.loadMaterialList(true)
    },

    /**
     * 下拉刷新
     */
    onRefresh() {
      this.refreshing = true
      this.loadMaterialList(true)
    },

    /**
     * 加载更多
     */
    loadMore() {
      if (!this.hasMore || this.loading) return
      this.loadMaterialList()
    },

    /**
     * 重置筛选
     */
    resetFilter() {
      this.filterData = {
        sort: 'latest',
        type: 'all'
      }
    },

    /**
     * 应用筛选
     */
    applyFilter() {
      this.showFilterPopup = false
      this.loadMaterialList(true)
    },

    /**
     * 查看详情
     */
    viewDetail(id) {
      uni.navigateTo({
        url: `/pages/material/detail?id=${id}`
      })
    },

    /**
     * 上传素材
     */
    handleUpload() {
      uni.showModal({
        title: '提示',
        content: '上传功能开发中',
        showCancel: false
      })
    },

    /**
     * 批量管理
     */
    handleBatchManage() {
      uni.showModal({
        title: '提示',
        content: '批量管理功能开发中',
        showCancel: false
      })
    },

    /**
     * 格式化类型
     */
    formatType(type) {
      const typeMap = {
        image: '图片',
        video: '视频',
        text: '文案',
        template: '模板'
      }
      return typeMap[type] || type
    },

    /**
     * 获取类型图标
     */
    getTypeIcon(type) {
      const iconMap = {
        image: '🖼️',
        video: '🎬',
        text: '📝',
        template: '📄'
      }
      return iconMap[type] || '📦'
    }
  }
}
</script>

<style lang="scss" scoped>
.material-page {
  min-height: 100vh;
  background: #f8f9fa;
  display: flex;
  flex-direction: column;
  padding-bottom: 140rpx;
}

// 搜索栏
.search-bar {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 20rpx 30rpx;
  background: #ffffff;
  border-bottom: 1rpx solid #e5e7eb;

  .search-input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    background: #f3f4f6;
    border-radius: 20rpx;
    padding: 16rpx 24rpx;

    .search-icon {
      font-size: 18px;
      margin-right: 12rpx;
    }

    .search-input {
      flex: 1;
      font-size: 14px;
      color: #1f2937;
    }
  }

  .filter-btn {
    padding: 16rpx;
    background: #f3f4f6;
    border-radius: 12rpx;

    .filter-icon {
      font-size: 18px;
    }
  }
}

// 分类滚动
.category-scroll {
  white-space: nowrap;
  background: #ffffff;
  border-bottom: 1rpx solid #e5e7eb;
}

.category-list {
  display: inline-flex;
  gap: 12rpx;
  padding: 20rpx 30rpx;
}

.category-item {
  padding: 12rpx 24rpx;
  background: #f3f4f6;
  border-radius: 20rpx;
  font-size: 14px;
  color: #6b7280;
  white-space: nowrap;

  &.active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
  }
}

// 素材列表
.material-list {
  flex: 1;
  padding: 30rpx;
}

.material-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20rpx;
}

.material-card {
  background: #ffffff;
  border-radius: 12rpx;
  overflow: hidden;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
}

.material-cover {
  position: relative;
  width: 100%;
  padding-top: 75%;
  background: #f3f4f6;

  .cover-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }

  .cover-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;

    .placeholder-icon {
      font-size: 48px;
    }
  }

  .type-badge {
    position: absolute;
    top: 12rpx;
    left: 12rpx;
    padding: 6rpx 12rpx;
    background: rgba(0, 0, 0, 0.6);
    color: #ffffff;
    font-size: 12px;
    border-radius: 8rpx;
  }

  .rating-badge {
    position: absolute;
    top: 12rpx;
    right: 12rpx;
    display: flex;
    align-items: center;
    gap: 4rpx;
    padding: 6rpx 12rpx;
    background: rgba(0, 0, 0, 0.6);
    color: #ffffff;
    font-size: 12px;
    border-radius: 8rpx;

    .star-icon {
      font-size: 12px;
    }
  }
}

.material-info {
  padding: 20rpx;
}

.material-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 12rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.material-meta {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin-bottom: 12rpx;

  .meta-item {
    font-size: 12px;
    color: #9ca3af;
  }
}

.material-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8rpx;

  .tag {
    font-size: 12px;
    color: #6366f1;
    background: #f0f0ff;
    padding: 4rpx 8rpx;
    border-radius: 4rpx;
  }
}

// 加载状态
.load-more,
.no-more {
  text-align: center;
  padding: 40rpx 0;
  font-size: 14px;
  color: #9ca3af;
}

.load-more {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
}

.loading-spinner {
  width: 40rpx;
  height: 40rpx;
  border: 4rpx solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

// 底部操作栏
.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: 20rpx;
  padding: 20rpx 30rpx;
  background: #ffffff;
  border-top: 1rpx solid #e5e7eb;
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));

  .action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    height: 88rpx;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
    border: none;

    .btn-icon {
      font-size: 18px;
    }

    &.secondary {
      background: #ffffff;
      color: #6b7280;
      border: 1rpx solid #d1d5db;
    }

    &.primary {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: #ffffff;
    }
  }
}

// 筛选弹窗
.filter-popup {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  display: flex;
  align-items: flex-end;
}

.filter-content {
  width: 100%;
  max-height: 80vh;
  background: #ffffff;
  border-radius: 24rpx 24rpx 0 0;
  padding: 40rpx 30rpx;
}

.filter-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 40rpx;

  .filter-title {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
  }

  .filter-reset {
    font-size: 14px;
    color: #6366f1;
  }
}

.filter-section {
  margin-bottom: 40rpx;

  .filter-label {
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 20rpx;
  }

  .filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
  }

  .filter-option {
    padding: 16rpx 32rpx;
    background: #f3f4f6;
    border-radius: 12rpx;
    font-size: 14px;
    color: #6b7280;

    &.active {
      background: #f0f0ff;
      color: #6366f1;
      border: 1rpx solid #6366f1;
    }
  }
}

.filter-actions {
  display: flex;
  gap: 20rpx;
  padding-top: 20rpx;

  button {
    flex: 1;
    height: 88rpx;
    border-radius: 12rpx;
    font-size: 16px;
    font-weight: 600;
    border: none;
  }

  .filter-btn-secondary {
    background: #f3f4f6;
    color: #6b7280;
  }

  .filter-btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
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

    .loading-spinner-large {
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

<template>
  <view class="material-page">
    <!-- 顶部标签栏 -->
    <view class="tab-bar">
      <view
        v-for="tab in tabs"
        :key="tab.value"
        class="tab-item"
        :class="{ active: currentTab === tab.value }"
        @tap="switchTab(tab.value)"
      >
        {{ tab.label }}
        <text v-if="tab.count" class="tab-count">({{ tab.count }})</text>
      </view>
    </view>

    <!-- 素材列表 -->
    <scroll-view
      class="material-list"
      scroll-y
      :refresher-enabled="true"
      :refresher-triggered="refreshing"
      @refresherrefresh="onRefresh"
      @scrolltolower="loadMore"
    >
      <!-- 素材网格 -->
      <view class="material-grid" v-if="materialList.length > 0">
        <view
          v-for="item in materialList"
          :key="item.id"
          class="material-card"
          :class="{ 'select-mode': selectMode }"
          @tap="handleItemClick(item)"
          @longpress="handleLongPress(item)"
        >
          <!-- 封面 -->
          <view class="material-cover">
            <image
              v-if="item.type === 'image'"
              class="cover-image"
              :src="item.url"
              mode="aspectFill"
              lazy-load
            />
            <view v-else class="cover-video">
              <text class="video-icon">▶</text>
              <text class="video-duration">{{ item.duration || '00:00' }}</text>
            </view>

            <!-- 选中标记 -->
            <view v-if="selectMode" class="select-mark">
              <view class="checkbox" :class="{ checked: selectedIds.includes(item.id) }">
                <text v-if="selectedIds.includes(item.id)" class="check-icon">✓</text>
              </view>
            </view>

            <!-- 类型标签 -->
            <view class="type-tag" :class="`type-${item.type}`">
              {{ item.type === 'image' ? '图片' : '视频' }}
            </view>
          </view>

          <!-- 信息 -->
          <view class="material-info">
            <text class="material-time">{{ formatTime(item.created_at) }}</text>
          </view>
        </view>
      </view>

      <!-- 空状态 -->
      <empty-state
        v-else-if="!loading"
        icon="📷"
        title="暂无素材"
        description="点击下方按钮开始拍摄素材"
        btnText="去拍摄"
        @action="goToCapture"
      />

      <!-- 加载更多 -->
      <view class="load-more" v-if="hasMore && materialList.length > 0">
        <view class="loading-spinner" v-if="loading"></view>
        <text class="load-more-text">{{ loading ? '加载中...' : '上拉加载更多' }}</text>
      </view>
      <view class="no-more" v-else-if="materialList.length > 0">
        <text>没有更多了</text>
      </view>
    </scroll-view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar">
      <template v-if="!selectMode">
        <button class="action-btn secondary" @tap="goToCapture">
          <text class="btn-icon">📷</text>
          <text>拍摄</text>
        </button>
        <button class="action-btn secondary" @tap="chooseFromAlbum">
          <text class="btn-icon">🖼️</text>
          <text>相册</text>
        </button>
        <button class="action-btn primary" @tap="enterSelectMode">
          <text class="btn-icon">✏️</text>
          <text>管理</text>
        </button>
      </template>
      <template v-else>
        <button class="action-btn secondary" @tap="cancelSelectMode">
          <text>取消</text>
        </button>
        <button class="action-btn secondary" @tap="selectAll">
          <text>{{ isAllSelected ? '取消全选' : '全选' }}</text>
        </button>
        <button class="action-btn danger" @tap="batchDelete" :disabled="selectedIds.length === 0">
          <text>删除({{ selectedIds.length }})</text>
        </button>
      </template>
    </view>

    <!-- 删除确认弹窗 -->
    <view class="confirm-modal" v-if="showDeleteModal" @tap="showDeleteModal = false">
      <view class="modal-content" @tap.stop>
        <text class="modal-title">确认删除</text>
        <text class="modal-desc">确定要删除选中的 {{ selectedIds.length }} 个素材吗？删除后无法恢复。</text>
        <view class="modal-actions">
          <button class="modal-btn cancel" @tap="showDeleteModal = false">取消</button>
          <button class="modal-btn confirm" @tap="confirmDelete">删除</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../api/index.js'

export default {
  name: 'MerchantMaterial',
  setup() {
    // 标签数据
    const tabs = ref([
      { value: 'all', label: '全部', count: 0 },
      { value: 'image', label: '图片', count: 0 },
      { value: 'video', label: '视频', count: 0 }
    ])
    const currentTab = ref('all')

    // 素材列表
    const materialList = ref([])
    const page = ref(1)
    const pageSize = 20
    const hasMore = ref(true)
    const loading = ref(false)
    const refreshing = ref(false)

    // 选择模式
    const selectMode = ref(false)
    const selectedIds = ref([])
    const showDeleteModal = ref(false)

    // 是否全选
    const isAllSelected = computed(() => {
      return materialList.value.length > 0 &&
        selectedIds.value.length === materialList.value.length
    })

    /**
     * 加载素材列表
     */
    const loadMaterialList = async (refresh = false) => {
      if (loading.value) return

      if (refresh) {
        page.value = 1
        materialList.value = []
        hasMore.value = true
      }

      loading.value = true

      try {
        const params = {
          page: page.value,
          pageSize,
          type: currentTab.value === 'all' ? '' : currentTab.value
        }

        const res = await api.promoMaterial.getList(params)
        const newList = res.data || res.list || []

        if (refresh) {
          materialList.value = newList
        } else {
          materialList.value = [...materialList.value, ...newList]
        }

        hasMore.value = newList.length >= pageSize
        page.value++

        // 更新标签计数
        if (res.counts) {
          tabs.value[0].count = res.counts.total || 0
          tabs.value[1].count = res.counts.image || 0
          tabs.value[2].count = res.counts.video || 0
        }
      } catch (error) {
        console.error('加载素材列表失败:', error)
        // 使用模拟数据
        const mockData = generateMockData()
        if (refresh) {
          materialList.value = mockData
        } else {
          materialList.value = [...materialList.value, ...mockData]
        }
        hasMore.value = false
      } finally {
        loading.value = false
        refreshing.value = false
      }
    }

    /**
     * 生成模拟数据
     */
    const generateMockData = () => {
      return Array.from({ length: 10 }, (_, i) => ({
        id: Date.now() + i,
        type: i % 3 === 0 ? 'video' : 'image',
        url: `https://via.placeholder.com/400x400?text=Material${page.value}-${i + 1}`,
        duration: '00:' + String(Math.floor(Math.random() * 60)).padStart(2, '0'),
        created_at: new Date(Date.now() - i * 3600000).toISOString()
      }))
    }

    /**
     * 切换标签
     */
    const switchTab = (tab) => {
      if (currentTab.value === tab) return
      currentTab.value = tab
      loadMaterialList(true)
    }

    /**
     * 下拉刷新
     */
    const onRefresh = () => {
      refreshing.value = true
      loadMaterialList(true)
    }

    /**
     * 加载更多
     */
    const loadMore = () => {
      if (!hasMore.value || loading.value) return
      loadMaterialList()
    }

    /**
     * 格式化时间
     */
    const formatTime = (time) => {
      if (!time) return ''
      const date = new Date(time)
      const now = new Date()
      const diff = now - date

      if (diff < 60000) return '刚刚'
      if (diff < 3600000) return `${Math.floor(diff / 60000)}分钟前`
      if (diff < 86400000) return `${Math.floor(diff / 3600000)}小时前`
      if (diff < 604800000) return `${Math.floor(diff / 86400000)}天前`

      return `${date.getMonth() + 1}月${date.getDate()}日`
    }

    /**
     * 跳转拍摄页面
     */
    const goToCapture = () => {
      uni.navigateTo({ url: '/pages/merchant/capture' })
    }

    /**
     * 从相册选择
     */
    const chooseFromAlbum = () => {
      uni.chooseImage({
        count: 9,
        sizeType: ['compressed'],
        sourceType: ['album'],
        success: async (res) => {
          const files = res.tempFilePaths
          uni.showLoading({ title: '上传中...', mask: true })

          try {
            for (const filePath of files) {
              await api.promoMaterial.upload(filePath, 'image', { showLoading: false })
            }
            uni.showToast({ title: '上传成功', icon: 'success' })
            loadMaterialList(true)
          } catch (error) {
            console.error('上传失败:', error)
            uni.showToast({ title: '上传失败', icon: 'none' })
          } finally {
            uni.hideLoading()
          }
        }
      })
    }

    /**
     * 进入选择模式
     */
    const enterSelectMode = () => {
      selectMode.value = true
      selectedIds.value = []
    }

    /**
     * 取消选择模式
     */
    const cancelSelectMode = () => {
      selectMode.value = false
      selectedIds.value = []
    }

    /**
     * 全选/取消全选
     */
    const selectAll = () => {
      if (isAllSelected.value) {
        selectedIds.value = []
      } else {
        selectedIds.value = materialList.value.map(item => item.id)
      }
    }

    /**
     * 点击素材项
     */
    const handleItemClick = (item) => {
      if (selectMode.value) {
        const index = selectedIds.value.indexOf(item.id)
        if (index > -1) {
          selectedIds.value.splice(index, 1)
        } else {
          selectedIds.value.push(item.id)
        }
      } else {
        // 预览
        if (item.type === 'image') {
          const urls = materialList.value
            .filter(m => m.type === 'image')
            .map(m => m.url)
          uni.previewImage({
            urls,
            current: item.url
          })
        } else {
          uni.showToast({ title: '视频预览开发中', icon: 'none' })
        }
      }
    }

    /**
     * 长按素材项
     */
    const handleLongPress = (item) => {
      if (!selectMode.value) {
        selectMode.value = true
        selectedIds.value = [item.id]
      }
    }

    /**
     * 批量删除
     */
    const batchDelete = () => {
      if (selectedIds.value.length === 0) return
      showDeleteModal.value = true
    }

    /**
     * 确认删除
     */
    const confirmDelete = async () => {
      showDeleteModal.value = false
      uni.showLoading({ title: '删除中...', mask: true })

      try {
        if (selectedIds.value.length === 1) {
          await api.promoMaterial.delete(selectedIds.value[0])
        } else {
          await api.promoMaterial.batchDelete(selectedIds.value)
        }

        uni.showToast({ title: '删除成功', icon: 'success' })

        // 从列表中移除已删除项
        materialList.value = materialList.value.filter(
          item => !selectedIds.value.includes(item.id)
        )

        cancelSelectMode()
      } catch (error) {
        console.error('删除失败:', error)
        uni.showToast({ title: '删除失败', icon: 'none' })
      } finally {
        uni.hideLoading()
      }
    }

    onMounted(() => {
      loadMaterialList(true)
    })

    return {
      tabs,
      currentTab,
      materialList,
      hasMore,
      loading,
      refreshing,
      selectMode,
      selectedIds,
      showDeleteModal,
      isAllSelected,
      switchTab,
      onRefresh,
      loadMore,
      formatTime,
      goToCapture,
      chooseFromAlbum,
      enterSelectMode,
      cancelSelectMode,
      selectAll,
      handleItemClick,
      handleLongPress,
      batchDelete,
      confirmDelete
    }
  }
}
</script>

<style lang="scss" scoped>
.material-page {
  min-height: 100vh;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
}

/* 标签栏 */
.tab-bar {
  display: flex;
  background: #ffffff;
  padding: 0 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
}

.tab-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 30rpx 0;
  font-size: 15px;
  color: #6b7280;
  position: relative;
}

.tab-item.active {
  color: #6366f1;
  font-weight: 600;
}

.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 48rpx;
  height: 6rpx;
  background: #6366f1;
  border-radius: 3rpx;
}

.tab-count {
  font-size: 12px;
  margin-left: 8rpx;
}

/* 素材列表 */
.material-list {
  flex: 1;
  padding: 20rpx;
}

.material-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12rpx;
}

.material-card {
  background: #ffffff;
  border-radius: 8rpx;
  overflow: hidden;
  position: relative;
}

.material-card.select-mode {
  opacity: 0.9;
}

.material-cover {
  position: relative;
  width: 100%;
  padding-top: 100%;
  background: #f3f4f6;
}

.cover-image {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.cover-video {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.video-icon {
  font-size: 32rpx;
  color: #ffffff;
}

.video-duration {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.8);
  margin-top: 8rpx;
}

.select-mark {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
}

.checkbox {
  width: 40rpx;
  height: 40rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.8);
  border: 2rpx solid #d1d5db;
  display: flex;
  align-items: center;
  justify-content: center;
}

.checkbox.checked {
  background: #6366f1;
  border-color: #6366f1;
}

.check-icon {
  font-size: 12px;
  color: #ffffff;
  font-weight: bold;
}

.type-tag {
  position: absolute;
  bottom: 8rpx;
  left: 8rpx;
  padding: 4rpx 12rpx;
  border-radius: 4rpx;
  font-size: 10px;
  color: #ffffff;
  background: rgba(0, 0, 0, 0.5);
}

.type-tag.type-video {
  background: rgba(239, 68, 68, 0.8);
}

.material-info {
  padding: 12rpx;
}

.material-time {
  font-size: 11px;
  color: #9ca3af;
}

/* 加载状态 */
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

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 底部操作栏 */
.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: 16rpx;
  padding: 20rpx 30rpx;
  background: #ffffff;
  border-top: 1rpx solid #e5e7eb;
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
}

.action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  height: 88rpx;
  border-radius: 12rpx;
  font-size: 15px;
  font-weight: 500;
  border: none;

  .btn-icon {
    font-size: 18px;
  }

  &.secondary {
    background: #f3f4f6;
    color: #4b5563;
  }

  &.primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
  }

  &.danger {
    background: #ef4444;
    color: #ffffff;
  }

  &[disabled] {
    opacity: 0.5;
  }
}

/* 确认弹窗 */
.confirm-modal {
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
}

.modal-content {
  width: 560rpx;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 40rpx;
}

.modal-title {
  display: block;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  text-align: center;
  margin-bottom: 20rpx;
}

.modal-desc {
  display: block;
  font-size: 14px;
  color: #6b7280;
  text-align: center;
  line-height: 1.6;
  margin-bottom: 40rpx;
}

.modal-actions {
  display: flex;
  gap: 20rpx;
}

.modal-btn {
  flex: 1;
  height: 88rpx;
  border-radius: 12rpx;
  font-size: 16px;
  font-weight: 500;
  border: none;

  &.cancel {
    background: #f3f4f6;
    color: #6b7280;
  }

  &.confirm {
    background: #ef4444;
    color: #ffffff;
  }
}
</style>

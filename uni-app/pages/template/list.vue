<template>
  <view class="template-list-page">
    <!-- 导航栏 -->
    <view class="nav-bar">
      <view class="nav-title">内容模板</view>
      <view class="nav-actions">
        <button class="btn-add" @tap="showAddModal">+</button>
      </view>
    </view>

    <!-- 筛选栏 -->
    <view class="filter-bar">
      <scroll-view class="filter-tabs" scroll-x>
        <view
          v-for="type in typeFilters"
          :key="type.value"
          class="filter-tab"
          :class="{ active: filterType === type.value }"
          @tap="selectType(type.value)"
        >
          <text class="tab-icon">{{ type.icon }}</text>
          <text class="tab-text">{{ type.label }}</text>
        </view>
      </scroll-view>

      <view class="filter-actions">
        <view class="filter-item" @tap="toggleOnlyMine">
          <text class="filter-icon">{{ onlyMine ? '✓' : '○' }}</text>
          <text class="filter-text">只看我的</text>
        </view>
      </view>
    </view>

    <!-- 搜索框 -->
    <view class="search-bar">
      <input
        class="search-input"
        v-model="keyword"
        placeholder="搜索模板名称、分类、风格"
        placeholder-style="color: #9ca3af"
        @confirm="searchTemplates"
      />
      <view class="search-icon" @tap="searchTemplates">🔍</view>
    </view>

    <!-- 模板列表 -->
    <scroll-view
      class="template-scroll"
      scroll-y
      @scrolltolower="loadMore"
      :lower-threshold="100"
    >
      <view v-if="loading && templates.length === 0" class="loading-state">
        <view class="loading-spinner"></view>
        <text class="loading-text">加载中...</text>
      </view>

      <view v-else-if="templates.length === 0" class="empty-state">
        <text class="empty-icon">📝</text>
        <text class="empty-text">暂无模板</text>
        <button class="btn-create" @tap="showAddModal">创建模板</button>
      </view>

      <view v-else class="template-list">
        <view
          v-for="template in templates"
          :key="template.id"
          class="template-item"
          @tap="selectTemplate(template)"
        >
          <!-- 模板信息 -->
          <view class="template-header">
            <view class="template-type-icon">{{ getTypeIcon(template.type) }}</view>
            <view class="template-info">
              <view class="template-name">{{ template.name }}</view>
              <view class="template-meta">
                <text class="meta-tag">{{ template.category }}</text>
                <text class="meta-tag">{{ template.style }}</text>
                <text class="meta-source">{{ template.template_source }}</text>
              </view>
            </view>
            <view class="template-usage">
              <text class="usage-count">{{ template.usage_count }}</text>
              <text class="usage-label">使用</text>
            </view>
          </view>

          <!-- 操作按钮 -->
          <view class="template-actions" @tap.stop="">
            <button class="action-btn btn-use" @tap="useTemplate(template)">
              使用
            </button>
            <button class="action-btn btn-copy" @tap="copyTemplate(template)">
              复制
            </button>
            <button
              v-if="template.merchant_id"
              class="action-btn btn-edit"
              @tap="editTemplate(template)"
            >
              编辑
            </button>
            <button
              v-if="template.merchant_id"
              class="action-btn btn-delete"
              @tap="deleteTemplate(template)"
            >
              删除
            </button>
          </view>
        </view>
      </view>

      <view v-if="hasMore && !loading" class="load-more" @tap="loadMore">
        <text>加载更多</text>
      </view>

      <view v-if="!hasMore && templates.length > 0" class="no-more">
        <text>已加载全部</text>
      </view>
    </scroll-view>

    <!-- 创建/编辑模板弹窗 -->
    <view class="modal" v-if="showModal" @tap="closeModal">
      <view class="modal-content" @tap.stop="">
        <view class="modal-header">
          <text class="modal-title">{{ editingTemplate ? '编辑模板' : '创建模板' }}</text>
          <view class="modal-close" @tap="closeModal">×</view>
        </view>

        <view class="modal-body">
          <!-- 模板名称 -->
          <view class="form-item">
            <text class="form-label">模板名称</text>
            <input
              class="form-input"
              v-model="formData.name"
              placeholder="请输入模板名称"
              maxlength="100"
            />
          </view>

          <!-- 模板类型 -->
          <view class="form-item">
            <text class="form-label">内容类型</text>
            <picker
              mode="selector"
              :range="contentTypes"
              range-key="label"
              :value="getTypeIndex(formData.type)"
              @change="onTypeChange"
            >
              <view class="form-picker">
                {{ getTypeLabelByValue(formData.type) || '请选择类型' }}
              </view>
            </picker>
          </view>

          <!-- 模板分类 -->
          <view class="form-item">
            <text class="form-label">模板分类</text>
            <picker
              mode="selector"
              :range="categories"
              range-key="label"
              :value="getCategoryIndex(formData.category)"
              @change="onCategoryChange"
            >
              <view class="form-picker">
                {{ formData.category || '请选择分类' }}
              </view>
            </picker>
          </view>

          <!-- 风格标签 -->
          <view class="form-item">
            <text class="form-label">风格标签</text>
            <picker
              mode="selector"
              :range="styles"
              range-key="label"
              :value="getStyleIndex(formData.style)"
              @change="onStyleChange"
            >
              <view class="form-picker">
                {{ formData.style || '请选择风格' }}
              </view>
            </picker>
          </view>

          <!-- 模板内容 -->
          <view class="form-item">
            <text class="form-label">模板内容</text>
            <textarea
              class="form-textarea"
              v-model="formData.contentText"
              placeholder="请输入模板内容（JSON格式）"
              maxlength="5000"
            />
          </view>

          <!-- 是否公开 -->
          <view class="form-item form-item-inline">
            <text class="form-label">是否公开</text>
            <switch
              :checked="formData.is_public === 1"
              @change="onPublicChange"
              color="#007AFF"
            />
          </view>
        </view>

        <view class="modal-footer">
          <button class="modal-btn btn-cancel" @tap="closeModal">取消</button>
          <button class="modal-btn btn-confirm" @tap="saveTemplate">保存</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import api from '@/api/index.js'
import FeedbackHelper from '@/utils/feedbackHelper.js'

export default {
  data() {
    return {
      // 筛选条件
      filterType: '',
      onlyMine: false,
      keyword: '',

      // 分页
      page: 1,
      pageSize: 20,
      total: 0,
      hasMore: true,

      // 数据
      templates: [],
      loading: false,

      // 模态框
      showModal: false,
      editingTemplate: null,
      formData: {
        name: '',
        type: '',
        category: '',
        style: '',
        contentText: '',
        is_public: 0
      },

      // 选项
      typeFilters: [
        { value: '', label: '全部', icon: '📋' },
        { value: 'TEXT', label: '文本', icon: '📝' },
        { value: 'IMAGE', label: '图片', icon: '🖼️' },
        { value: 'VIDEO', label: '视频', icon: '🎬' }
      ],
      contentTypes: [
        { value: 'TEXT', label: '文本', icon: '📝' },
        { value: 'IMAGE', label: '图片', icon: '🖼️' },
        { value: 'VIDEO', label: '视频', icon: '🎬' }
      ],
      categories: [],
      styles: []
    }
  },

  onLoad() {
    this.loadCategories()
    this.loadStyles()
    this.loadTemplates()
  },

  methods: {
    /**
     * 加载模板列表
     */
    async loadTemplates(refresh = false) {
      if (this.loading) return

      if (refresh) {
        this.page = 1
        this.templates = []
        this.hasMore = true
      }

      this.loading = true

      try {
        const res = await api.template.getList({
          page: this.page,
          pageSize: this.pageSize,
          type: this.filterType,
          keyword: this.keyword,
          only_mine: this.onlyMine ? 1 : 0
        })

        const list = res.data || res.list || []
        this.total = res.total || 0

        if (refresh) {
          this.templates = list
        } else {
          this.templates = [...this.templates, ...list]
        }

        this.hasMore = this.templates.length < this.total

      } catch (error) {
        console.error('加载模板列表失败:', error)
        FeedbackHelper.error('加载模板列表失败')
      } finally {
        this.loading = false
      }
    },

    /**
     * 加载更多
     */
    loadMore() {
      if (!this.hasMore || this.loading) return

      this.page++
      this.loadTemplates()
    },

    /**
     * 加载分类选项
     */
    async loadCategories() {
      try {
        const res = await api.template.getCategories()
        this.categories = res.list || []
      } catch (error) {
        console.error('加载分类失败:', error)
      }
    },

    /**
     * 加载风格选项
     */
    async loadStyles() {
      try {
        const res = await api.template.getStyles()
        this.styles = res.list || []
      } catch (error) {
        console.error('加载风格失败:', error)
      }
    },

    /**
     * 选择类型
     */
    selectType(type) {
      this.filterType = type
      this.loadTemplates(true)
    },

    /**
     * 切换只看我的
     */
    toggleOnlyMine() {
      this.onlyMine = !this.onlyMine
      this.loadTemplates(true)
    },

    /**
     * 搜索模板
     */
    searchTemplates() {
      this.loadTemplates(true)
    },

    /**
     * 选择模板（查看详情）
     */
    selectTemplate(template) {
      uni.navigateTo({
        url: `/pages/template/detail?id=${template.id}`
      })
    },

    /**
     * 使用模板
     */
    useTemplate(template) {
      // 跳转到内容生成页面，带上模板ID
      uni.navigateTo({
        url: `/pages/content/generate?template_id=${template.id}`
      })
    },

    /**
     * 复制模板
     */
    async copyTemplate(template) {
      const confirmed = await FeedbackHelper.confirm(
        '复制模板',
        `确定要复制模板"${template.name}"吗？`
      )

      if (!confirmed) return

      try {
        FeedbackHelper.loading('复制中...')

        await api.template.copy(template.id, {
          name: template.name + '_副本'
        })

        FeedbackHelper.hideLoading()
        FeedbackHelper.success('模板复制成功', { vibrate: true })

        this.loadTemplates(true)
      } catch (error) {
        FeedbackHelper.hideLoading()
        FeedbackHelper.error('复制失败：' + (error.message || '未知错误'))
      }
    },

    /**
     * 编辑模板
     */
    editTemplate(template) {
      this.editingTemplate = template
      this.formData = {
        name: template.name,
        type: template.type,
        category: template.category,
        style: template.style,
        contentText: JSON.stringify(template.content, null, 2),
        is_public: template.is_public
      }
      this.showModal = true
    },

    /**
     * 删除模板
     */
    async deleteTemplate(template) {
      const confirmed = await FeedbackHelper.confirm(
        '删除模板',
        `确定要删除模板"${template.name}"吗？`,
        {
          confirmText: '删除',
          confirmColor: '#FF3B30'
        }
      )

      if (!confirmed) return

      try {
        await api.template.delete(template.id)
        FeedbackHelper.deleteSuccess()
        this.loadTemplates(true)
      } catch (error) {
        FeedbackHelper.error('删除失败：' + (error.message || '未知错误'))
      }
    },

    /**
     * 显示创建模板弹窗
     */
    showAddModal() {
      this.editingTemplate = null
      this.formData = {
        name: '',
        type: '',
        category: '',
        style: '',
        contentText: '',
        is_public: 0
      }
      this.showModal = true
    },

    /**
     * 关闭弹窗
     */
    closeModal() {
      this.showModal = false
      this.editingTemplate = null
    },

    /**
     * 保存模板
     */
    async saveTemplate() {
      // 验证
      if (!this.formData.name) {
        FeedbackHelper.warning('请输入模板名称')
        return
      }

      if (!this.formData.type) {
        FeedbackHelper.warning('请选择内容类型')
        return
      }

      if (!this.formData.category) {
        FeedbackHelper.warning('请选择模板分类')
        return
      }

      if (!this.formData.contentText) {
        FeedbackHelper.warning('请输入模板内容')
        return
      }

      // 验证JSON格式
      let content
      try {
        content = JSON.parse(this.formData.contentText)
      } catch (e) {
        FeedbackHelper.warning('模板内容必须是有效的JSON格式')
        return
      }

      try {
        FeedbackHelper.loading('保存中...')

        const data = {
          name: this.formData.name,
          type: this.formData.type,
          category: this.formData.category,
          style: this.formData.style,
          content: content,
          is_public: this.formData.is_public
        }

        if (this.editingTemplate) {
          await api.template.update(this.editingTemplate.id, data)
        } else {
          await api.template.create(data)
        }

        FeedbackHelper.hideLoading()
        FeedbackHelper.saveSuccess()

        this.closeModal()
        this.loadTemplates(true)
      } catch (error) {
        FeedbackHelper.hideLoading()
        FeedbackHelper.error('保存失败：' + (error.message || '未知错误'))
      }
    },

    /**
     * 类型选择变化
     */
    onTypeChange(e) {
      this.formData.type = this.contentTypes[e.detail.value].value
    },

    /**
     * 分类选择变化
     */
    onCategoryChange(e) {
      this.formData.category = this.categories[e.detail.value].value
    },

    /**
     * 风格选择变化
     */
    onStyleChange(e) {
      this.formData.style = this.styles[e.detail.value].value
    },

    /**
     * 公开状态变化
     */
    onPublicChange(e) {
      this.formData.is_public = e.detail.value ? 1 : 0
    },

    /**
     * 获取类型索引
     */
    getTypeIndex(value) {
      return this.contentTypes.findIndex(t => t.value === value)
    },

    /**
     * 获取分类索引
     */
    getCategoryIndex(value) {
      return this.categories.findIndex(c => c.value === value)
    },

    /**
     * 获取风格索引
     */
    getStyleIndex(value) {
      return this.styles.findIndex(s => s.value === value)
    },

    /**
     * 根据值获取类型标签
     */
    getTypeLabelByValue(value) {
      const type = this.contentTypes.find(t => t.value === value)
      return type ? type.label : ''
    },

    /**
     * 获取类型图标
     */
    getTypeIcon(type) {
      const icons = {
        'TEXT': '📝',
        'IMAGE': '🖼️',
        'VIDEO': '🎬',
        'MIXED': '🎨'
      }
      return icons[type] || '📄'
    }
  }
}
</script>

<style scoped>
.template-list-page {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;
}

/* 导航栏 */
.nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 30rpx;
  background-color: #fff;
  border-bottom: 1px solid #eee;
}

.nav-title {
  font-size: 36rpx;
  font-weight: bold;
  color: #333;
}

.nav-actions {
  display: flex;
  gap: 20rpx;
}

.btn-add {
  width: 60rpx;
  height: 60rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  font-size: 40rpx;
  line-height: 60rpx;
  text-align: center;
  border: none;
  padding: 0;
}

/* 筛选栏 */
.filter-bar {
  background-color: #fff;
  border-bottom: 1px solid #eee;
}

.filter-tabs {
  white-space: nowrap;
  padding: 20rpx 0;
}

.filter-tab {
  display: inline-flex;
  align-items: center;
  gap: 10rpx;
  padding: 10rpx 30rpx;
  margin-left: 20rpx;
  border-radius: 40rpx;
  background-color: #f5f5f5;
  transition: all 0.3s;
}

.filter-tab.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}

.tab-icon {
  font-size: 32rpx;
}

.tab-text {
  font-size: 28rpx;
}

.filter-actions {
  padding: 20rpx 30rpx;
  border-top: 1px solid #f5f5f5;
}

.filter-item {
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.filter-icon {
  font-size: 32rpx;
  color: #667eea;
}

.filter-text {
  font-size: 28rpx;
  color: #666;
}

/* 搜索框 */
.search-bar {
  display: flex;
  align-items: center;
  padding: 20rpx 30rpx;
  background-color: #fff;
  border-bottom: 1px solid #eee;
}

.search-input {
  flex: 1;
  padding: 15rpx 20rpx;
  background-color: #f5f5f5;
  border-radius: 40rpx;
  font-size: 28rpx;
}

.search-icon {
  margin-left: 20rpx;
  font-size: 40rpx;
}

/* 模板列表 */
.template-scroll {
  flex: 1;
  padding: 20rpx 30rpx;
}

.template-list {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.template-item {
  background-color: #fff;
  border-radius: 20rpx;
  padding: 30rpx;
  box-shadow: 0 2rpx 10rpx rgba(0,0,0,0.05);
}

.template-header {
  display: flex;
  align-items: center;
  gap: 20rpx;
  margin-bottom: 20rpx;
}

.template-type-icon {
  font-size: 48rpx;
}

.template-info {
  flex: 1;
}

.template-name {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 10rpx;
}

.template-meta {
  display: flex;
  gap: 15rpx;
  flex-wrap: wrap;
}

.meta-tag {
  font-size: 24rpx;
  color: #667eea;
  padding: 4rpx 12rpx;
  background-color: rgba(102, 126, 234, 0.1);
  border-radius: 10rpx;
}

.meta-source {
  font-size: 24rpx;
  color: #999;
}

.template-usage {
  text-align: center;
}

.usage-count {
  display: block;
  font-size: 32rpx;
  font-weight: bold;
  color: #667eea;
}

.usage-label {
  display: block;
  font-size: 24rpx;
  color: #999;
}

/* 操作按钮 */
.template-actions {
  display: flex;
  gap: 15rpx;
  padding-top: 20rpx;
  border-top: 1px solid #f5f5f5;
}

.action-btn {
  flex: 1;
  padding: 15rpx 0;
  font-size: 26rpx;
  border-radius: 10rpx;
  border: 1px solid #ddd;
  background-color: #fff;
  color: #333;
}

.btn-use {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border: none;
}

.btn-copy {
  color: #667eea;
  border-color: #667eea;
}

.btn-edit {
  color: #ffa500;
  border-color: #ffa500;
}

.btn-delete {
  color: #ff3b30;
  border-color: #ff3b30;
}

/* 加载状态 */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx;
  color: #999;
}

.loading-spinner {
  width: 60rpx;
  height: 60rpx;
  border: 4rpx solid #f3f3f3;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-text {
  margin-top: 20rpx;
  font-size: 28rpx;
}

.empty-icon {
  font-size: 100rpx;
  margin-bottom: 20rpx;
}

.empty-text {
  font-size: 28rpx;
  margin-bottom: 40rpx;
}

.btn-create {
  padding: 20rpx 60rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-radius: 40rpx;
  border: none;
}

.load-more,
.no-more {
  text-align: center;
  padding: 40rpx;
  font-size: 26rpx;
  color: #999;
}

/* 模态框 */
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  width: 90%;
  max-height: 80%;
  background-color: #fff;
  border-radius: 20rpx;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx;
  border-bottom: 1px solid #eee;
}

.modal-title {
  font-size: 32rpx;
  font-weight: bold;
}

.modal-close {
  font-size: 48rpx;
  color: #999;
  line-height: 1;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 30rpx;
}

.form-item {
  margin-bottom: 30rpx;
}

.form-item-inline {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.form-label {
  display: block;
  margin-bottom: 15rpx;
  font-size: 28rpx;
  color: #333;
  font-weight: 500;
}

.form-input,
.form-picker {
  width: 100%;
  padding: 20rpx;
  background-color: #f5f5f5;
  border-radius: 10rpx;
  font-size: 28rpx;
  border: 1px solid transparent;
}

.form-input:focus {
  border-color: #667eea;
}

.form-textarea {
  width: 100%;
  min-height: 200rpx;
  padding: 20rpx;
  background-color: #f5f5f5;
  border-radius: 10rpx;
  font-size: 26rpx;
  line-height: 1.6;
  font-family: monospace;
  border: 1px solid transparent;
}

.form-textarea:focus {
  border-color: #667eea;
}

.modal-footer {
  display: flex;
  gap: 20rpx;
  padding: 30rpx;
  border-top: 1px solid #eee;
}

.modal-btn {
  flex: 1;
  padding: 25rpx 0;
  font-size: 30rpx;
  border-radius: 10rpx;
  border: none;
}

.btn-cancel {
  background-color: #f5f5f5;
  color: #666;
}

.btn-confirm {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}
</style>

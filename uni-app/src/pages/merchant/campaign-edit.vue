<template>
  <view class="campaign-edit-page">
    <!-- 表单区域 -->
    <scroll-view class="form-container" scroll-y>
      <!-- 活动名称 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label required">活动名称</text>
          <input
            class="form-input"
            v-model="form.name"
            placeholder="请输入活动名称"
            maxlength="50"
          />
          <text class="form-count">{{ form.name.length }}/50</text>
        </view>
      </view>

      <!-- 活动描述 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label">活动描述</text>
          <textarea
            class="form-textarea"
            v-model="form.description"
            placeholder="请输入活动描述（选填）"
            maxlength="200"
          />
          <text class="form-count">{{ form.description.length }}/200</text>
        </view>
      </view>

      <!-- 选择变体 -->
      <view class="form-section">
        <view class="form-group">
          <view class="form-header">
            <text class="form-label required">选择变体</text>
            <text class="form-value">{{ selectedVariants.length }} 个</text>
          </view>
          <view class="variant-selector" @tap="openVariantPicker">
            <view class="variant-preview" v-if="selectedVariants.length > 0">
              <scroll-view class="variant-scroll" scroll-x>
                <view class="variant-list">
                  <view
                    v-for="item in selectedVariants"
                    :key="item.id"
                    class="variant-thumb"
                  >
                    <text class="thumb-icon">▶</text>
                    <view class="thumb-remove" @tap.stop="removeVariant(item)">
                      <text class="remove-icon">×</text>
                    </view>
                  </view>
                </view>
              </scroll-view>
            </view>
            <view class="variant-placeholder" v-else>
              <text class="placeholder-icon">+</text>
              <text class="placeholder-text">点击选择视频变体</text>
            </view>
            <text class="selector-arrow">></text>
          </view>
        </view>
      </view>

      <!-- 推广文案 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label">推广文案</text>
          <textarea
            class="form-textarea"
            v-model="form.promoText"
            placeholder="请输入推广文案，用于用户发布时的默认文案（选填）"
            maxlength="500"
          />
          <text class="form-count">{{ form.promoText.length }}/500</text>
        </view>
      </view>

      <!-- 话题标签 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label">话题标签</text>
          <view class="tags-container">
            <view
              v-for="(tag, index) in form.tags"
              :key="index"
              class="tag-item"
            >
              #{{ tag }}
              <text class="tag-remove" @tap="removeTag(index)">×</text>
            </view>
            <input
              class="tag-input"
              v-model="newTag"
              placeholder="添加标签"
              maxlength="20"
              @confirm="addTag"
              v-if="form.tags.length < 10"
            />
          </view>
          <text class="form-tip">按回车添加标签，最多10个</text>
        </view>
      </view>

      <!-- 奖励优惠券 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label">奖励优惠券</text>
          <picker
            mode="selector"
            :range="couponList"
            range-key="name"
            @change="onCouponChange"
          >
            <view class="picker">
              <text class="picker-text">{{ selectedCouponName || '选择优惠券（选填）' }}</text>
              <text class="picker-arrow">></text>
            </view>
          </picker>
        </view>
      </view>

      <!-- 目标平台 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label required">目标平台</text>
          <view class="platform-options">
            <view
              v-for="platform in platformOptions"
              :key="platform.value"
              class="platform-item"
              :class="{ active: form.platforms.includes(platform.value) }"
              @tap="togglePlatform(platform.value)"
            >
              <text class="platform-icon">{{ platform.icon }}</text>
              <text class="platform-name">{{ platform.label }}</text>
              <view class="platform-check" v-if="form.platforms.includes(platform.value)">
                <text class="check-icon">✓</text>
              </view>
            </view>
          </view>
        </view>
      </view>

      <!-- 活动时间 -->
      <view class="form-section">
        <view class="form-group">
          <text class="form-label">活动时间</text>
          <view class="time-picker-row">
            <picker
              mode="date"
              :value="startDate"
              @change="onStartDateChange"
            >
              <view class="time-picker">
                <text class="time-text">{{ startDate || '开始日期' }}</text>
              </view>
            </picker>
            <picker
              mode="time"
              :value="startTime"
              @change="onStartTimeChange"
            >
              <view class="time-picker">
                <text class="time-text">{{ startTime || '开始时间' }}</text>
              </view>
            </picker>
          </view>
          <view class="time-separator">至</view>
          <view class="time-picker-row">
            <picker
              mode="date"
              :value="endDate"
              @change="onEndDateChange"
            >
              <view class="time-picker">
                <text class="time-text">{{ endDate || '结束日期' }}</text>
              </view>
            </picker>
            <picker
              mode="time"
              :value="endTime"
              @change="onEndTimeChange"
            >
              <view class="time-picker">
                <text class="time-text">{{ endTime || '结束时间' }}</text>
              </view>
            </picker>
          </view>
        </view>
      </view>

      <!-- 底部安全区 -->
      <view class="safe-area-bottom"></view>
    </scroll-view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar">
      <button class="btn-secondary" @tap="goBack">取消</button>
      <button class="btn-primary" @tap="submitForm" :disabled="submitting">
        {{ isEdit ? '保存修改' : '创建活动' }}
      </button>
    </view>

    <!-- 变体选择弹窗 -->
    <uni-popup ref="variantPicker" type="bottom" background-color="#fff">
      <view class="variant-picker">
        <view class="picker-header">
          <text class="picker-title">选择视频变体</text>
          <text class="picker-close" @tap="closeVariantPicker">×</text>
        </view>
        <scroll-view class="picker-content" scroll-y>
          <view class="picker-grid">
            <view
              class="picker-item"
              v-for="item in availableVariants"
              :key="item.id"
              :class="{ selected: isVariantSelected(item.id) }"
              @tap="toggleVariant(item)"
            >
              <view class="item-cover">
                <text class="item-icon">▶</text>
              </view>
              <text class="item-name">{{ item.name || `变体 ${item.id}` }}</text>
              <view class="item-check" v-if="isVariantSelected(item.id)">
                <text class="check-icon">✓</text>
              </view>
            </view>
          </view>
        </scroll-view>
        <view class="picker-footer">
          <button class="btn-primary" @tap="confirmVariantSelection">
            确定 ({{ selectedVariants.length }})
          </button>
        </view>
      </view>
    </uni-popup>
  </view>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import api from '../../api/index.js'

export default {
  name: 'MerchantCampaignEdit',
  setup() {
    // 活动ID（编辑模式）
    const campaignId = ref(null)
    const isEdit = computed(() => !!campaignId.value)

    // 提交状态
    const submitting = ref(false)

    // 表单数据
    const form = reactive({
      name: '',
      description: '',
      promoText: '',
      tags: [],
      couponId: null,
      platforms: ['douyin'],
      startTime: null,
      endTime: null
    })

    // 时间选择
    const startDate = ref('')
    const startTime = ref('')
    const endDate = ref('')
    const endTime = ref('')

    // 新标签输入
    const newTag = ref('')

    // 平台选项
    const platformOptions = [
      { value: 'douyin', label: '抖音', icon: '🎵' },
      { value: 'kuaishou', label: '快手', icon: '⚡' },
      { value: 'shipinhao', label: '视频号', icon: '📺' }
    ]

    // 优惠券列表
    const couponList = ref([])
    const selectedCouponName = ref('')

    // 变体相关
    const variantPicker = ref(null)
    const availableVariants = ref([])
    const selectedVariants = ref([])
    const tempSelectedVariants = ref([])

    /**
     * 加载活动详情（编辑模式）
     */
    const loadCampaignDetail = async () => {
      try {
        const res = await api.promoCampaign.getDetail(campaignId.value)
        const data = res.data || res

        form.name = data.name || ''
        form.description = data.description || ''
        form.promoText = data.promo_text || data.promoText || ''
        form.tags = data.tags || []
        form.couponId = data.coupon_id || data.couponId || null
        form.platforms = data.platforms || ['douyin']

        if (data.start_time || data.startTime) {
          const start = new Date(data.start_time || data.startTime)
          startDate.value = formatDate(start)
          startTime.value = formatTime(start)
        }

        if (data.end_time || data.endTime) {
          const end = new Date(data.end_time || data.endTime)
          endDate.value = formatDate(end)
          endTime.value = formatTime(end)
        }

        if (data.variants) {
          selectedVariants.value = data.variants
        }

        if (data.coupon_name || data.couponName) {
          selectedCouponName.value = data.coupon_name || data.couponName
        }

      } catch (error) {
        console.error('加载活动详情失败:', error)
        uni.showToast({ title: '加载失败', icon: 'none' })
      }
    }

    /**
     * 加载优惠券列表
     */
    const loadCouponList = async () => {
      try {
        const res = await api.coupon.getList?.({ page: 1, pageSize: 50 })
        couponList.value = res.data || res.list || []
      } catch (error) {
        console.error('加载优惠券列表失败:', error)
        // 使用模拟数据
        couponList.value = [
          { id: 1, name: '满100减20优惠券' },
          { id: 2, name: '8折优惠券' },
          { id: 3, name: '新人专享50元券' }
        ]
      }
    }

    /**
     * 加载可用变体
     */
    const loadVariants = async () => {
      try {
        const res = await api.promoTemplate.getList?.({ page: 1, pageSize: 50 })
        availableVariants.value = res.data || res.list || []
      } catch (error) {
        console.error('加载变体列表失败:', error)
        // 使用模拟数据
        availableVariants.value = generateMockVariants()
      }
    }

    /**
     * 生成模拟变体
     */
    const generateMockVariants = () => {
      return Array.from({ length: 10 }, (_, i) => ({
        id: i + 1,
        name: `视频变体 ${i + 1}`,
        duration: Math.floor(Math.random() * 30) + 15
      }))
    }

    /**
     * 格式化日期
     */
    const formatDate = (date) => {
      return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    }

    /**
     * 格式化时间
     */
    const formatTime = (date) => {
      return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
    }

    /**
     * 打开变体选择器
     */
    const openVariantPicker = () => {
      tempSelectedVariants.value = [...selectedVariants.value]
      variantPicker.value?.open()
    }

    /**
     * 关闭变体选择器
     */
    const closeVariantPicker = () => {
      variantPicker.value?.close()
    }

    /**
     * 判断变体是否已选中
     */
    const isVariantSelected = (variantId) => {
      return tempSelectedVariants.value.some(v => v.id === variantId)
    }

    /**
     * 切换变体选择
     */
    const toggleVariant = (item) => {
      const index = tempSelectedVariants.value.findIndex(v => v.id === item.id)
      if (index > -1) {
        tempSelectedVariants.value.splice(index, 1)
      } else {
        tempSelectedVariants.value.push(item)
      }
    }

    /**
     * 确认变体选择
     */
    const confirmVariantSelection = () => {
      selectedVariants.value = [...tempSelectedVariants.value]
      closeVariantPicker()
    }

    /**
     * 移除变体
     */
    const removeVariant = (item) => {
      const index = selectedVariants.value.findIndex(v => v.id === item.id)
      if (index > -1) {
        selectedVariants.value.splice(index, 1)
      }
    }

    /**
     * 添加标签
     */
    const addTag = () => {
      const tag = newTag.value.trim()
      if (tag && !form.tags.includes(tag) && form.tags.length < 10) {
        form.tags.push(tag)
        newTag.value = ''
      }
    }

    /**
     * 移除标签
     */
    const removeTag = (index) => {
      form.tags.splice(index, 1)
    }

    /**
     * 优惠券选择
     */
    const onCouponChange = (e) => {
      const index = e.detail.value
      const coupon = couponList.value[index]
      if (coupon) {
        form.couponId = coupon.id
        selectedCouponName.value = coupon.name
      }
    }

    /**
     * 切换平台
     */
    const togglePlatform = (platform) => {
      const index = form.platforms.indexOf(platform)
      if (index > -1) {
        if (form.platforms.length > 1) {
          form.platforms.splice(index, 1)
        }
      } else {
        form.platforms.push(platform)
      }
    }

    /**
     * 时间选择
     */
    const onStartDateChange = (e) => {
      startDate.value = e.detail.value
      updateFormTime()
    }

    const onStartTimeChange = (e) => {
      startTime.value = e.detail.value
      updateFormTime()
    }

    const onEndDateChange = (e) => {
      endDate.value = e.detail.value
      updateFormTime()
    }

    const onEndTimeChange = (e) => {
      endTime.value = e.detail.value
      updateFormTime()
    }

    /**
     * 更新表单时间
     */
    const updateFormTime = () => {
      if (startDate.value && startTime.value) {
        form.startTime = `${startDate.value} ${startTime.value}:00`
      }
      if (endDate.value && endTime.value) {
        form.endTime = `${endDate.value} ${endTime.value}:00`
      }
    }

    /**
     * 返回
     */
    const goBack = () => {
      uni.navigateBack()
    }

    /**
     * 提交表单
     */
    const submitForm = async () => {
      // 验证
      if (!form.name.trim()) {
        uni.showToast({ title: '请输入活动名称', icon: 'none' })
        return
      }

      if (selectedVariants.value.length === 0) {
        uni.showToast({ title: '请选择至少一个变体', icon: 'none' })
        return
      }

      if (form.platforms.length === 0) {
        uni.showToast({ title: '请选择目标平台', icon: 'none' })
        return
      }

      submitting.value = true

      try {
        const data = {
          name: form.name,
          description: form.description,
          variantIds: selectedVariants.value.map(v => v.id),
          promoText: form.promoText,
          tags: form.tags,
          couponId: form.couponId,
          platforms: form.platforms,
          startTime: form.startTime,
          endTime: form.endTime
        }

        if (isEdit.value) {
          await api.promoCampaign.update(campaignId.value, data)
          uni.showToast({ title: '修改成功', icon: 'success' })
        } else {
          await api.promoCampaign.create(data)
          uni.showToast({ title: '创建成功', icon: 'success' })
        }

        setTimeout(() => {
          uni.navigateBack()
        }, 1500)

      } catch (error) {
        console.error('提交失败:', error)
        uni.showToast({ title: error.message || '操作失败', icon: 'none' })
      } finally {
        submitting.value = false
      }
    }

    // 页面加载
    onLoad((options) => {
      campaignId.value = options.id || null
      loadCouponList()
      loadVariants()

      if (campaignId.value) {
        uni.setNavigationBarTitle({ title: '编辑活动' })
        loadCampaignDetail()
      }
    })

    return {
      campaignId,
      isEdit,
      submitting,
      form,
      startDate,
      startTime,
      endDate,
      endTime,
      newTag,
      platformOptions,
      couponList,
      selectedCouponName,
      variantPicker,
      availableVariants,
      selectedVariants,
      openVariantPicker,
      closeVariantPicker,
      isVariantSelected,
      toggleVariant,
      confirmVariantSelection,
      removeVariant,
      addTag,
      removeTag,
      onCouponChange,
      togglePlatform,
      onStartDateChange,
      onStartTimeChange,
      onEndDateChange,
      onEndTimeChange,
      goBack,
      submitForm
    }
  }
}
</script>

<style lang="scss" scoped>
.campaign-edit-page {
  min-height: 100vh;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
}

/* 表单区域 */
.form-container {
  flex: 1;
  padding: 20rpx;
  padding-bottom: calc(160rpx + env(safe-area-inset-bottom));
}

.form-section {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}

.form-group {
  position: relative;
}

.form-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16rpx;
}

.form-label {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  display: block;
  margin-bottom: 16rpx;

  &.required::after {
    content: '*';
    color: #ef4444;
    margin-left: 8rpx;
  }
}

.form-header .form-label {
  margin-bottom: 0;
}

.form-value {
  font-size: 14px;
  color: #6366f1;
}

.form-input {
  width: 100%;
  height: 88rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  padding: 0 20rpx;
  font-size: 15px;
  color: #1f2937;
}

.form-textarea {
  width: 100%;
  min-height: 160rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  padding: 20rpx;
  font-size: 15px;
  color: #1f2937;
}

.form-count {
  position: absolute;
  right: 20rpx;
  bottom: 16rpx;
  font-size: 12px;
  color: #9ca3af;
}

.form-tip {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 12rpx;
  display: block;
}

/* 变体选择器 */
.variant-selector {
  display: flex;
  align-items: center;
  padding: 20rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  min-height: 100rpx;
}

.variant-preview {
  flex: 1;
}

.variant-scroll {
  white-space: nowrap;
}

.variant-list {
  display: inline-flex;
  gap: 16rpx;
}

.variant-thumb {
  width: 100rpx;
  height: 120rpx;
  background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
  border-radius: 8rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  flex-shrink: 0;
}

.thumb-icon {
  font-size: 24rpx;
  color: #ffffff;
}

.thumb-remove {
  position: absolute;
  top: -8rpx;
  right: -8rpx;
  width: 32rpx;
  height: 32rpx;
  background: #ef4444;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-icon {
  font-size: 20rpx;
  color: #ffffff;
  line-height: 1;
}

.variant-placeholder {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.placeholder-icon {
  font-size: 28rpx;
  color: #9ca3af;
}

.placeholder-text {
  font-size: 14px;
  color: #9ca3af;
}

.selector-arrow {
  font-size: 16px;
  color: #9ca3af;
  margin-left: 16rpx;
}

/* 话题标签 */
.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
  align-items: center;
}

.tag-item {
  display: flex;
  align-items: center;
  padding: 12rpx 20rpx;
  background: #ede9fe;
  color: #6366f1;
  border-radius: 20rpx;
  font-size: 14px;
}

.tag-remove {
  margin-left: 12rpx;
  font-size: 18rpx;
  color: #6366f1;
}

.tag-input {
  width: 140rpx;
  height: 60rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 20rpx;
  padding: 0 20rpx;
  font-size: 14px;
}

/* 选择器 */
.picker {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
}

.picker-text {
  font-size: 15px;
  color: #1f2937;
}

.picker-arrow {
  font-size: 14px;
  color: #9ca3af;
}

/* 平台选择 */
.platform-options {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16rpx;
}

.platform-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24rpx 16rpx;
  background: #f8fafc;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;
  position: relative;
  transition: all 0.2s;

  &.active {
    background: #ede9fe;
    border-color: #6366f1;
  }
}

.platform-icon {
  font-size: 32rpx;
  margin-bottom: 12rpx;
}

.platform-name {
  font-size: 14px;
  color: #4b5563;
}

.platform-item.active .platform-name {
  color: #6366f1;
  font-weight: 500;
}

.platform-check {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  width: 32rpx;
  height: 32rpx;
  background: #6366f1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.check-icon {
  font-size: 16rpx;
  color: #ffffff;
}

/* 时间选择 */
.time-picker-row {
  display: flex;
  gap: 16rpx;
}

.time-picker {
  flex: 1;
  padding: 20rpx;
  background: #f8fafc;
  border: 1rpx solid #e5e7eb;
  border-radius: 12rpx;
  text-align: center;
}

.time-text {
  font-size: 14px;
  color: #1f2937;
}

.time-separator {
  text-align: center;
  font-size: 14px;
  color: #9ca3af;
  padding: 16rpx 0;
}

/* 底部安全区 */
.safe-area-bottom {
  height: 40rpx;
}

/* 底部操作栏 */
.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: 20rpx;
  padding: 20rpx 30rpx;
  background: #ffffff;
  box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
}

.btn-primary {
  flex: 2;
  height: 88rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12rpx;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;

  &[disabled] {
    opacity: 0.6;
  }
}

.btn-secondary {
  flex: 1;
  height: 88rpx;
  background: #f3f4f6;
  border: none;
  border-radius: 12rpx;
  color: #4b5563;
  font-size: 16px;
}

/* 变体选择弹窗 */
.variant-picker {
  max-height: 70vh;
  display: flex;
  flex-direction: column;
}

.picker-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx;
  border-bottom: 1rpx solid #e5e7eb;
}

.picker-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.picker-close {
  font-size: 32px;
  color: #9ca3af;
  line-height: 1;
}

.picker-content {
  flex: 1;
  padding: 20rpx;
}

.picker-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16rpx;
}

.picker-item {
  position: relative;
  padding: 16rpx;
  background: #f8fafc;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;

  &.selected {
    background: #ede9fe;
    border-color: #6366f1;
  }
}

.item-cover {
  width: 100%;
  aspect-ratio: 9/16;
  background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
  border-radius: 8rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12rpx;
}

.item-icon {
  font-size: 32rpx;
  color: #ffffff;
}

.item-name {
  font-size: 12px;
  color: #4b5563;
  display: block;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.picker-item.selected .item-name {
  color: #6366f1;
}

.item-check {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  width: 36rpx;
  height: 36rpx;
  background: #6366f1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.picker-footer {
  padding: 20rpx 30rpx;
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
  border-top: 1rpx solid #e5e7eb;

  .btn-primary {
    width: 100%;
  }
}
</style>

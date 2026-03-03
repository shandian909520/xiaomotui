<template>
  <view class="compose-page">
    <!-- 步骤指示器 -->
    <view class="steps-container">
      <view class="steps">
        <view
          class="step"
          :class="{ active: currentStep >= 0, completed: currentStep > 0 }"
        >
          <view class="step-number">1</view>
          <text class="step-text">选素材</text>
        </view>
        <view class="step-line" :class="{ active: currentStep > 0 }"></view>
        <view
          class="step"
          :class="{ active: currentStep >= 1, completed: currentStep > 1 }"
        >
          <view class="step-number">2</view>
          <text class="step-text">配参数</text>
        </view>
        <view class="step-line" :class="{ active: currentStep > 1 }"></view>
        <view
          class="step"
          :class="{ active: currentStep >= 2 }"
        >
          <view class="step-number">3</view>
          <text class="step-text">生成</text>
        </view>
      </view>
    </view>

    <!-- Step 1: 选择素材 -->
    <view class="step-content" v-if="currentStep === 0">
      <view class="section">
        <view class="section-header">
          <text class="section-title">选择图片</text>
          <text class="section-count">已选 {{ selectedMaterials.length }}/9</text>
        </view>

        <!-- 素材网格 -->
        <view class="material-grid">
          <!-- 已选素材 -->
          <view
            class="material-item"
            v-for="(item, index) in selectedMaterials"
            :key="item.id"
          >
            <image
              class="material-image"
              :src="item.url"
              mode="aspectFill"
              @tap="previewMaterial(item)"
            />
            <view class="material-index">{{ index + 1 }}</view>
            <view class="remove-btn" @tap.stop="removeMaterial(item)">
              <text class="remove-icon">×</text>
            </view>
            <view class="drag-handle" @touchstart="onDragStart($event, index)">
              <text class="drag-icon">⠿</text>
            </view>
          </view>

          <!-- 添加按钮 -->
          <view
            class="add-item"
            v-if="selectedMaterials.length < 9"
            @tap="openMaterialPicker"
          >
            <text class="add-icon">+</text>
            <text class="add-text">添加</text>
          </view>
        </view>

        <!-- 操作提示 -->
        <view class="tips">
          <text class="tip-text">提示: 长按素材可拖动排序</text>
        </view>
      </view>

      <!-- 底部操作按钮 -->
      <view class="bottom-actions">
        <button class="btn-secondary" @tap="goToCapture">
          <text class="btn-icon">📷</text>
          拍照添加
        </button>
        <button
          class="btn-primary"
          :disabled="selectedMaterials.length < 2"
          @tap="nextStep"
        >
          下一步
        </button>
      </view>
    </view>

    <!-- Step 2: 配置参数 -->
    <view class="step-content" v-if="currentStep === 1">
      <view class="config-form">
        <!-- 每张图片时长 -->
        <view class="form-group">
          <view class="form-header">
            <text class="form-label">每张图片时长</text>
            <text class="form-value">{{ duration }}秒</text>
          </view>
          <slider
            :value="duration"
            min="1"
            max="5"
            step="0.5"
            activeColor="#6366f1"
            backgroundColor="#e5e7eb"
            block-size="20"
            @change="onDurationChange"
          />
        </view>

        <!-- 转场效果 -->
        <view class="form-group">
          <text class="form-label">转场效果</text>
          <radio-group class="radio-group" @change="onTransitionChange">
            <label class="radio-item" v-for="item in transitionOptions" :key="item.value">
              <radio
                :value="item.value"
                :checked="transition === item.value"
                color="#6366f1"
              />
              <text class="radio-text">{{ item.label }}</text>
            </label>
          </radio-group>
        </view>

        <!-- 背景音乐 -->
        <view class="form-group">
          <text class="form-label">背景音乐</text>
          <picker
            mode="selector"
            :range="musicList"
            range-key="name"
            @change="onMusicChange"
          >
            <view class="picker">
              <text class="picker-text">{{ selectedMusicName || '选择音乐' }}</text>
              <text class="picker-arrow">></text>
            </view>
          </picker>
        </view>

        <!-- 变体数量 -->
        <view class="form-group">
          <view class="form-header">
            <text class="form-label">变体数量</text>
            <text class="form-value">{{ variantCount }} 个</text>
          </view>
          <view class="stepper">
            <button
              class="stepper-btn"
              :disabled="variantCount <= 1"
              @tap="decreaseVariantCount"
            >
              -
            </button>
            <text class="stepper-value">{{ variantCount }}</text>
            <button
              class="stepper-btn"
              :disabled="variantCount >= 20"
              @tap="increaseVariantCount"
            >
              +
            </button>
          </view>
        </view>

        <!-- 预计信息 -->
        <view class="preview-info">
          <view class="info-item">
            <text class="info-label">预计视频时长</text>
            <text class="info-value">{{ estimatedDuration }}秒</text>
          </view>
          <view class="info-item">
            <text class="info-label">素材数量</text>
            <text class="info-value">{{ selectedMaterials.length }}张</text>
          </view>
        </view>
      </view>

      <!-- 底部操作按钮 -->
      <view class="bottom-actions">
        <button class="btn-secondary" @tap="prevStep">上一步</button>
        <button class="btn-primary" @tap="submitCompose">开始合成</button>
      </view>
    </view>

    <!-- Step 3: 生成中/完成 -->
    <view class="step-content" v-if="currentStep === 2">
      <!-- 生成中 -->
      <view v-if="generating" class="generating-container">
        <view class="generating-icon">
          <view class="spinner"></view>
        </view>
        <text class="generating-title">正在生成视频变体</text>
        <text class="generating-subtitle">请稍候...</text>

        <view class="progress-container">
          <progress
            class="progress-bar"
            :percent="progress"
            stroke-width="8"
            activeColor="#6366f1"
            backgroundColor="#e5e7eb"
          />
          <text class="progress-text">{{ progress }}%</text>
        </view>

        <view class="generating-info">
          <text class="info-text">正在处理第 {{ currentProcessing }}/{{ variantCount }} 个变体</text>
        </view>
      </view>

      <!-- 生成完成 -->
      <view v-else class="completed-container">
        <view class="completed-icon">
          <text class="check-icon">✓</text>
        </view>
        <text class="completed-title">生成完成!</text>
        <text class="completed-subtitle">已生成 {{ generatedCount }} 个视频变体</text>

        <view class="completed-stats">
          <view class="stat-item">
            <text class="stat-value">{{ selectedMaterials.length }}</text>
            <text class="stat-label">素材数量</text>
          </view>
          <view class="stat-item">
            <text class="stat-value">{{ estimatedDuration }}s</text>
            <text class="stat-label">视频时长</text>
          </view>
          <view class="stat-item">
            <text class="stat-value">{{ generatedCount }}</text>
            <text class="stat-label">变体数量</text>
          </view>
        </view>

        <button class="btn-primary btn-large" @tap="viewResult">
          查看结果
        </button>
      </view>
    </view>

    <!-- 素材选择弹窗 -->
    <uni-popup ref="materialPicker" type="bottom" background-color="#fff">
      <view class="material-picker">
        <view class="picker-header">
          <text class="picker-title">选择素材</text>
          <text class="picker-close" @tap="closeMaterialPicker">×</text>
        </view>
        <scroll-view class="picker-content" scroll-y>
          <view class="picker-grid">
            <view
              class="picker-item"
              v-for="item in availableMaterials"
              :key="item.id"
              @tap="selectMaterial(item)"
            >
              <image
                class="picker-image"
                :src="item.url"
                mode="aspectFill"
              />
              <view class="picker-checkbox" v-if="isMaterialSelected(item.id)">
                <text class="checkbox-icon">✓</text>
              </view>
            </view>
          </view>
        </scroll-view>
        <view class="picker-footer">
          <button class="btn-primary" @tap="confirmMaterialSelection">
            确定 ({{ selectedMaterials.length }}/9)
          </button>
        </view>
      </view>
    </uni-popup>
  </view>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../api/index.js'

export default {
  name: 'MerchantCompose',
  setup() {
    // 当前步骤
    const currentStep = ref(0)

    // 选中的素材
    const selectedMaterials = ref([])

    // 可用素材列表
    const availableMaterials = ref([])

    // 配置参数
    const duration = ref(2)
    const transition = ref('fade')
    const selectedMusicId = ref(null)
    const selectedMusicName = ref('')
    const variantCount = ref(3)

    // 音乐列表
    const musicList = ref([])

    // 转场效果选项
    const transitionOptions = [
      { value: 'fade', label: '淡入淡出' },
      { value: 'slide', label: '滑动' },
      { value: 'zoom', label: '缩放' },
      { value: 'none', label: '无转场' }
    ]

    // 生成状态
    const generating = ref(false)
    const progress = ref(0)
    const currentProcessing = ref(0)
    const generatedCount = ref(0)
    const templateId = ref(null)

    // 弹窗引用
    const materialPicker = ref(null)

    // 临时选中的素材
    const tempSelectedMaterials = ref([])

    // 预计视频时长
    const estimatedDuration = computed(() => {
      return (selectedMaterials.value.length * duration.value).toFixed(1)
    })

    /**
     * 加载素材列表
     */
    const loadMaterials = async () => {
      try {
        const res = await api.promoMaterial.getList({ page: 1, pageSize: 50 })
        availableMaterials.value = res.data || res.list || []
      } catch (error) {
        console.error('加载素材失败:', error)
        // 使用模拟数据
        availableMaterials.value = generateMockMaterials()
      }
    }

    /**
     * 加载音乐列表
     */
    const loadMusicList = async () => {
      try {
        const res = await api.promoTemplate.getMusicList()
        musicList.value = res.data || res || []
      } catch (error) {
        console.error('加载音乐列表失败:', error)
        // 使用模拟数据
        musicList.value = [
          { id: 1, name: '轻快节奏' },
          { id: 2, name: '温馨浪漫' },
          { id: 3, name: '动感活力' },
          { id: 4, name: '优雅古典' }
        ]
      }
    }

    /**
     * 生成模拟素材数据
     */
    const generateMockMaterials = () => {
      return Array.from({ length: 12 }, (_, i) => ({
        id: i + 1,
        type: 'image',
        url: `https://via.placeholder.com/200x200?text=Material${i + 1}`,
        created_at: new Date().toISOString()
      }))
    }

    /**
     * 打开素材选择器
     */
    const openMaterialPicker = () => {
      tempSelectedMaterials.value = [...selectedMaterials.value]
      materialPicker.value?.open()
    }

    /**
     * 关闭素材选择器
     */
    const closeMaterialPicker = () => {
      materialPicker.value?.close()
    }

    /**
     * 选择素材
     */
    const selectMaterial = (item) => {
      const index = tempSelectedMaterials.value.findIndex(m => m.id === item.id)
      if (index > -1) {
        tempSelectedMaterials.value.splice(index, 1)
      } else if (tempSelectedMaterials.value.length < 9) {
        tempSelectedMaterials.value.push(item)
      }
    }

    /**
     * 判断素材是否已选中
     */
    const isMaterialSelected = (materialId) => {
      return tempSelectedMaterials.value.some(m => m.id === materialId)
    }

    /**
     * 确认素材选择
     */
    const confirmMaterialSelection = () => {
      selectedMaterials.value = [...tempSelectedMaterials.value]
      closeMaterialPicker()
    }

    /**
     * 移除素材
     */
    const removeMaterial = (item) => {
      const index = selectedMaterials.value.findIndex(m => m.id === item.id)
      if (index > -1) {
        selectedMaterials.value.splice(index, 1)
      }
    }

    /**
     * 预览素材
     */
    const previewMaterial = (item) => {
      uni.previewImage({
        urls: [item.url],
        current: item.url
      })
    }

    /**
     * 拖动开始
     */
    const onDragStart = (event, index) => {
      // TODO: 实现拖动排序
      console.log('开始拖动:', index)
    }

    /**
     * 时长变化
     */
    const onDurationChange = (e) => {
      duration.value = e.detail.value
    }

    /**
     * 转场效果变化
     */
    const onTransitionChange = (e) => {
      transition.value = e.detail.value
    }

    /**
     * 音乐变化
     */
    const onMusicChange = (e) => {
      const index = e.detail.value
      const music = musicList.value[index]
      if (music) {
        selectedMusicId.value = music.id
        selectedMusicName.value = music.name
      }
    }

    /**
     * 增加变体数量
     */
    const increaseVariantCount = () => {
      if (variantCount.value < 20) {
        variantCount.value++
      }
    }

    /**
     * 减少变体数量
     */
    const decreaseVariantCount = () => {
      if (variantCount.value > 1) {
        variantCount.value--
      }
    }

    /**
     * 下一步
     */
    const nextStep = () => {
      if (currentStep.value === 0 && selectedMaterials.value.length < 2) {
        uni.showToast({ title: '请至少选择2张素材', icon: 'none' })
        return
      }
      currentStep.value++
    }

    /**
     * 上一步
     */
    const prevStep = () => {
      currentStep.value--
    }

    /**
     * 跳转到拍摄页面
     */
    const goToCapture = () => {
      uni.navigateTo({ url: '/pages/merchant/capture' })
    }

    /**
     * 提交合成任务
     */
    const submitCompose = async () => {
      currentStep.value = 2
      generating.value = true
      progress.value = 0
      currentProcessing.value = 0

      try {
        // 创建模板
        const templateData = {
          name: `视频模板_${Date.now()}`,
          materialIds: selectedMaterials.value.map(m => m.id),
          duration: duration.value,
          transition: transition.value,
          musicId: selectedMusicId.value,
          variantCount: variantCount.value
        }

        const createRes = await api.promoTemplate.create(templateData)
        templateId.value = createRes.id || createRes.template_id

        // 模拟生成进度
        for (let i = 0; i < variantCount.value; i++) {
          currentProcessing.value = i + 1
          await simulateProgress(i)
        }

        generating.value = false
        generatedCount.value = variantCount.value

        uni.showToast({ title: '生成完成', icon: 'success' })
      } catch (error) {
        console.error('提交合成失败:', error)
        generating.value = false
        uni.showToast({ title: error.message || '生成失败', icon: 'none' })
      }
    }

    /**
     * 模拟进度
     */
    const simulateProgress = (index) => {
      return new Promise((resolve) => {
        const startProgress = (index / variantCount.value) * 100
        const endProgress = ((index + 1) / variantCount.value) * 100
        const duration = 1500 // 每个变体1.5秒
        const steps = 30
        const stepDuration = duration / steps
        const progressStep = (endProgress - startProgress) / steps

        let currentProgress = startProgress
        const timer = setInterval(() => {
          currentProgress += progressStep
          progress.value = Math.min(Math.round(currentProgress), endProgress)

          if (currentProgress >= endProgress) {
            clearInterval(timer)
            resolve()
          }
        }, stepDuration)
      })
    }

    /**
     * 查看结果
     */
    const viewResult = () => {
      uni.navigateTo({
        url: `/pages/merchant/compose-result?templateId=${templateId.value}`
      })
    }

    onMounted(() => {
      loadMaterials()
      loadMusicList()
    })

    return {
      currentStep,
      selectedMaterials,
      availableMaterials,
      duration,
      transition,
      transitionOptions,
      selectedMusicId,
      selectedMusicName,
      variantCount,
      musicList,
      generating,
      progress,
      currentProcessing,
      generatedCount,
      estimatedDuration,
      materialPicker,
      openMaterialPicker,
      closeMaterialPicker,
      selectMaterial,
      isMaterialSelected,
      confirmMaterialSelection,
      removeMaterial,
      previewMaterial,
      onDragStart,
      onDurationChange,
      onTransitionChange,
      onMusicChange,
      increaseVariantCount,
      decreaseVariantCount,
      nextStep,
      prevStep,
      goToCapture,
      submitCompose,
      viewResult
    }
  }
}
</script>

<style lang="scss" scoped>
.compose-page {
  min-height: 100vh;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
}

/* 步骤指示器 */
.steps-container {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  padding: 40rpx 30rpx;
  padding-top: calc(env(safe-area-inset-top) + 40rpx);
}

.steps {
  display: flex;
  align-items: center;
  justify-content: center;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.step-number {
  width: 60rpx;
  height: 60rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  color: rgba(255, 255, 255, 0.7);
  font-size: 14px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.step.active .step-number {
  background: #ffffff;
  color: #6366f1;
}

.step.completed .step-number {
  background: #22c55e;
  color: #ffffff;
}

.step-text {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 12rpx;
}

.step.active .step-text {
  color: #ffffff;
  font-weight: 600;
}

.step-line {
  width: 80rpx;
  height: 4rpx;
  background: rgba(255, 255, 255, 0.3);
  margin: 0 20rpx;
  margin-bottom: 40rpx;
  transition: all 0.3s;
}

.step-line.active {
  background: #ffffff;
}

/* 内容区域 */
.step-content {
  padding: 30rpx;
  padding-bottom: calc(180rpx + env(safe-area-inset-bottom));
}

/* 素材选择 */
.section {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.section-count {
  font-size: 14px;
  color: #6366f1;
}

.material-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20rpx;
}

.material-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12rpx;
  overflow: hidden;
}

.material-image {
  width: 100%;
  height: 100%;
}

.material-index {
  position: absolute;
  top: 8rpx;
  left: 8rpx;
  width: 40rpx;
  height: 40rpx;
  background: rgba(99, 102, 241, 0.9);
  color: #ffffff;
  font-size: 12px;
  font-weight: bold;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-btn {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  width: 40rpx;
  height: 40rpx;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-icon {
  color: #ffffff;
  font-size: 24rpx;
  line-height: 1;
}

.drag-handle {
  position: absolute;
  bottom: 8rpx;
  right: 8rpx;
  width: 48rpx;
  height: 48rpx;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 8rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.drag-icon {
  color: #ffffff;
  font-size: 20rpx;
}

.add-item {
  aspect-ratio: 1;
  border: 2rpx dashed #d1d5db;
  border-radius: 12rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f9fafb;
}

.add-icon {
  font-size: 48rpx;
  color: #9ca3af;
  line-height: 1;
}

.add-text {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 8rpx;
}

.tips {
  margin-top: 20rpx;
  padding: 16rpx;
  background: #f0f9ff;
  border-radius: 8rpx;
}

.tip-text {
  font-size: 12px;
  color: #0284c7;
}

/* 配置表单 */
.config-form {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 30rpx;
}

.form-group {
  margin-bottom: 40rpx;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.form-label {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.form-value {
  font-size: 14px;
  color: #6366f1;
  font-weight: 600;
}

.radio-group {
  display: flex;
  flex-wrap: wrap;
  gap: 20rpx;
  margin-top: 16rpx;
}

.radio-item {
  display: flex;
  align-items: center;
  padding: 16rpx 24rpx;
  background: #f9fafb;
  border-radius: 8rpx;
  border: 1rpx solid #e5e7eb;
}

.radio-text {
  font-size: 14px;
  color: #4b5563;
  margin-left: 12rpx;
}

.picker {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx;
  background: #f9fafb;
  border-radius: 8rpx;
  border: 1rpx solid #e5e7eb;
  margin-top: 16rpx;
}

.picker-text {
  font-size: 14px;
  color: #1f2937;
}

.picker-arrow {
  font-size: 14px;
  color: #9ca3af;
}

.stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 40rpx;
  margin-top: 16rpx;
}

.stepper-btn {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  background: #f3f4f6;
  border: none;
  font-size: 24px;
  color: #6366f1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  line-height: 1;
}

.stepper-btn[disabled] {
  color: #d1d5db;
}

.stepper-value {
  font-size: 24px;
  font-weight: bold;
  color: #1f2937;
  min-width: 80rpx;
  text-align: center;
}

.preview-info {
  display: flex;
  gap: 20rpx;
  margin-top: 30rpx;
  padding-top: 30rpx;
  border-top: 1rpx solid #e5e7eb;
}

.info-item {
  flex: 1;
  text-align: center;
  padding: 20rpx;
  background: #f9fafb;
  border-radius: 8rpx;
}

.info-label {
  font-size: 12px;
  color: #6b7280;
  display: block;
}

.info-value {
  font-size: 16px;
  font-weight: bold;
  color: #1f2937;
  margin-top: 8rpx;
  display: block;
}

/* 生成状态 */
.generating-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 40rpx;
}

.generating-icon {
  width: 120rpx;
  height: 120rpx;
  margin-bottom: 40rpx;
}

.spinner {
  width: 100%;
  height: 100%;
  border: 6rpx solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.generating-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 12rpx;
}

.generating-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 60rpx;
}

.progress-container {
  width: 100%;
  margin-bottom: 30rpx;
}

.progress-bar {
  width: 100%;
}

.progress-text {
  display: block;
  text-align: center;
  font-size: 14px;
  color: #6b7280;
  margin-top: 16rpx;
}

.generating-info {
  padding: 20rpx 40rpx;
  background: #f0f9ff;
  border-radius: 8rpx;
}

.info-text {
  font-size: 14px;
  color: #0284c7;
}

/* 生成完成 */
.completed-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 40rpx;
}

.completed-icon {
  width: 120rpx;
  height: 120rpx;
  background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 40rpx;
}

.check-icon {
  font-size: 60rpx;
  color: #ffffff;
  line-height: 1;
}

.completed-title {
  font-size: 24px;
  font-weight: bold;
  color: #1f2937;
  margin-bottom: 12rpx;
}

.completed-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 60rpx;
}

.completed-stats {
  display: flex;
  gap: 30rpx;
  width: 100%;
  margin-bottom: 60rpx;
}

.stat-item {
  flex: 1;
  text-align: center;
  padding: 30rpx 20rpx;
  background: #ffffff;
  border-radius: 12rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #6366f1;
  display: block;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 8rpx;
  display: block;
}

/* 底部操作按钮 */
.bottom-actions {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  gap: 20rpx;
  padding: 30rpx;
  padding-bottom: calc(30rpx + env(safe-area-inset-bottom));
  background: #ffffff;
  box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.btn-primary {
  flex: 1;
  height: 88rpx;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12rpx;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-primary[disabled] {
  opacity: 0.5;
}

.btn-secondary {
  flex: 1;
  height: 88rpx;
  background: #ffffff;
  border: 2rpx solid #e5e7eb;
  border-radius: 12rpx;
  color: #4b5563;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon {
  margin-right: 12rpx;
}

.btn-large {
  width: 100%;
  height: 96rpx;
  font-size: 18px;
}

/* 素材选择弹窗 */
.material-picker {
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
  aspect-ratio: 1;
  border-radius: 8rpx;
  overflow: hidden;
}

.picker-image {
  width: 100%;
  height: 100%;
}

.picker-checkbox {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  width: 40rpx;
  height: 40rpx;
  background: #6366f1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.checkbox-icon {
  color: #ffffff;
  font-size: 20rpx;
}

.picker-footer {
  padding: 20rpx 30rpx;
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
  border-top: 1rpx solid #e5e7eb;
}
</style>

<template>
  <div class="campaign-editor">
    <el-form
      ref="formRef"
      :model="formData"
      :rules="formRules"
      label-width="120px"
      class="campaign-form"
    >
      <el-form-item label="活动名称" prop="name">
        <el-input
          v-model="formData.name"
          placeholder="请输入活动名称"
          maxlength="50"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="活动描述" prop="description">
        <el-input
          v-model="formData.description"
          type="textarea"
          placeholder="请输入活动描述"
          :rows="3"
          maxlength="200"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="选择变体" prop="variant_ids">
        <el-select
          v-model="formData.variant_ids"
          multiple
          filterable
          placeholder="请选择视频变体"
          :loading="variantLoading"
          class="full-width"
        >
          <el-option
            v-for="item in variantOptions"
            :key="item.id"
            :label="`${item.template_name || '变体'} - ${formatDuration(item.duration)}`"
            :value="item.id"
          >
            <div class="variant-option">
              <span>{{ item.template_name || '变体' }}</span>
              <el-tag size="small" type="info">{{ formatDuration(item.duration) }}</el-tag>
            </div>
          </el-option>
        </el-select>
        <div class="form-tip">已选择 {{ formData.variant_ids.length }} 个变体</div>
      </el-form-item>

      <el-form-item label="推广文案" prop="promo_text">
        <el-input
          v-model="formData.promo_text"
          type="textarea"
          placeholder="请输入推广文案，可使用话题标签如 #美食推荐"
          :rows="4"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="话题标签" prop="tags">
        <el-select
          v-model="formData.tags"
          multiple
          filterable
          allow-create
          default-first-option
          placeholder="输入话题标签，回车添加"
          class="full-width"
        >
          <el-option
            v-for="tag in defaultTags"
            :key="tag"
            :label="tag"
            :value="tag"
          />
        </el-select>
        <div class="form-tip">输入后按回车添加自定义标签</div>
      </el-form-item>

      <el-form-item label="关联优惠券" prop="coupon_id">
        <el-select
          v-model="formData.coupon_id"
          placeholder="请选择优惠券（可选）"
          clearable
          :loading="couponLoading"
          class="full-width"
        >
          <el-option
            v-for="item in couponOptions"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          >
            <div class="coupon-option">
              <span>{{ item.name }}</span>
              <el-tag size="small" :type="item.status === 'active' ? 'success' : 'info'">
                {{ item.status === 'active' ? '有效' : '已过期' }}
              </el-tag>
            </div>
          </el-option>
        </el-select>
      </el-form-item>

      <el-form-item label="目标平台" prop="platforms">
        <el-checkbox-group v-model="formData.platforms">
          <el-checkbox label="douyin">抖音</el-checkbox>
          <el-checkbox label="kuaishou">快手</el-checkbox>
        </el-checkbox-group>
      </el-form-item>

      <el-form-item label="活动时间" prop="date_range">
        <el-date-picker
          v-model="formData.date_range"
          type="datetimerange"
          range-separator="至"
          start-placeholder="开始时间"
          end-placeholder="结束时间"
          value-format="YYYY-MM-DD HH:mm:ss"
          :shortcuts="dateShortcuts"
          class="full-width"
        />
      </el-form-item>
    </el-form>

    <div class="form-actions">
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
        {{ isEdit ? '保存' : '创建活动' }}
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getVariantList } from '@/api/promo-template'
import { couponApi } from '@/api'

// Props
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  campaignData: {
    type: Object,
    default: null
  },
  isEdit: {
    type: Boolean,
    default: false
  }
})

// Emits
const emit = defineEmits(['update:modelValue', 'success', 'cancel'])

// Refs
const formRef = ref(null)
const submitLoading = ref(false)
const variantLoading = ref(false)
const couponLoading = ref(false)

// 表单数据
const formData = reactive({
  name: '',
  description: '',
  variant_ids: [],
  promo_text: '',
  tags: [],
  coupon_id: null,
  platforms: ['douyin'],
  date_range: []
})

// 表单验证规则
const formRules = {
  name: [
    { required: true, message: '请输入活动名称', trigger: 'blur' },
    { min: 2, max: 50, message: '长度在 2 到 50 个字符', trigger: 'blur' }
  ],
  variant_ids: [
    { required: true, type: 'array', min: 1, message: '请至少选择一个变体', trigger: 'change' }
  ],
  platforms: [
    { required: true, type: 'array', min: 1, message: '请至少选择一个目标平台', trigger: 'change' }
  ],
  date_range: [
    { required: true, message: '请选择活动时间', trigger: 'change' }
  ]
}

// 默认标签
const defaultTags = [
  '#推荐',
  '#好物分享',
  '#美食探店',
  '#打卡',
  '#优惠活动'
]

// 日期快捷选项
const dateShortcuts = [
  {
    text: '一周',
    value: () => {
      const start = new Date()
      const end = new Date()
      end.setTime(end.getTime() + 3600 * 1000 * 24 * 7)
      return [start, end]
    }
  },
  {
    text: '一个月',
    value: () => {
      const start = new Date()
      const end = new Date()
      end.setTime(end.getTime() + 3600 * 1000 * 24 * 30)
      return [start, end]
    }
  },
  {
    text: '三个月',
    value: () => {
      const start = new Date()
      const end = new Date()
      end.setTime(end.getTime() + 3600 * 1000 * 24 * 90)
      return [start, end]
    }
  }
]

// 变体选项
const variantOptions = ref([])

// 优惠券选项
const couponOptions = ref([])

// 获取变体列表
const getVariantOptions = async () => {
  variantLoading.value = true
  try {
    const response = await getVariantList({ limit: 200, status: 'completed' })
    if (response) {
      if (response.list) {
        variantOptions.value = response.list
      } else if (response.data) {
        variantOptions.value = Array.isArray(response.data) ? response.data : []
      } else if (Array.isArray(response)) {
        variantOptions.value = response
      }
    }
  } catch (error) {
    console.error('获取变体列表失败:', error)
  } finally {
    variantLoading.value = false
  }
}

// 获取优惠券列表
const getCouponOptions = async () => {
  couponLoading.value = true
  try {
    const response = await couponApi.getCoupons({ limit: 100 })
    if (response) {
      if (response.list) {
        couponOptions.value = response.list
      } else if (response.data) {
        couponOptions.value = Array.isArray(response.data) ? response.data : []
      } else if (Array.isArray(response)) {
        couponOptions.value = response
      }
    }
  } catch (error) {
    console.error('获取优惠券列表失败:', error)
  } finally {
    couponLoading.value = false
  }
}

// 格式化时长
const formatDuration = (seconds) => {
  if (!seconds) return '-'
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : `0:${secs.toString().padStart(2, '0')}`
}

// 重置表单
const resetForm = () => {
  formData.name = ''
  formData.description = ''
  formData.variant_ids = []
  formData.promo_text = ''
  formData.tags = []
  formData.coupon_id = null
  formData.platforms = ['douyin']
  formData.date_range = []
  formRef.value?.resetFields()
}

// 初始化表单数据
const initFormData = (data) => {
  if (data) {
    formData.name = data.name || ''
    formData.description = data.description || ''
    formData.variant_ids = data.variant_ids || []
    formData.promo_text = data.promo_text || ''
    formData.tags = data.tags || []
    formData.coupon_id = data.coupon_id || null
    formData.platforms = data.platforms || ['douyin']
    formData.date_range = data.date_range || []
  }
}

// 取消
const handleCancel = () => {
  emit('update:modelValue', false)
  emit('cancel')
  resetForm()
}

// 提交
const handleSubmit = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
  } catch {
    return
  }

  submitLoading.value = true
  try {
    const data = {
      name: formData.name,
      description: formData.description,
      variant_ids: formData.variant_ids,
      promo_text: formData.promo_text,
      tags: formData.tags,
      coupon_id: formData.coupon_id,
      platforms: formData.platforms,
      start_time: formData.date_range[0],
      end_time: formData.date_range[1]
    }

    emit('success', data)
    resetForm()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error('提交失败')
  } finally {
    submitLoading.value = false
  }
}

// 监听活动数据变化
watch(
  () => props.campaignData,
  (newVal) => {
    if (newVal) {
      initFormData(newVal)
    }
  },
  { immediate: true }
)

// 初始化
onMounted(() => {
  getVariantOptions()
  getCouponOptions()
})

// 暴露方法
defineExpose({
  resetForm,
  initFormData
})
</script>

<style scoped lang="scss">
.campaign-editor {
  .campaign-form {
    .full-width {
      width: 100%;
    }

    .form-tip {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }

    .variant-option {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }

    .coupon-option {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #e8e8e8;
  }
}
</style>

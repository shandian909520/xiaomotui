<template>
  <el-dialog
    v-model="visible"
    title="卡密激活"
    width="480px"
    @close="handleClose"
  >
    <div v-if="!activationResult" class="activation-form">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="卡密" prop="cardKey">
          <el-input
            v-model="form.cardKey"
            placeholder="请输入卡密"
            maxlength="32"
          />
        </el-form-item>
      </el-form>

      <div class="notice-box">
        <div class="notice-title">激活须知</div>
        <ul class="notice-list">
          <li>1. 请确认卡密未过期</li>
          <li>2. 激活后立即生效</li>
          <li>3. 首次激活和后续充值均可使用</li>
        </ul>
      </div>
    </div>

    <div v-else class="activation-result">
      <el-result icon="success" title="激活成功" :sub-title="activationResult.message">
        <template #extra>
          <div class="result-details">
            <div v-for="(item, index) in activationResult.benefits" :key="index" class="benefit-item">
              <span class="benefit-label">{{ item.label }}</span>
              <span class="benefit-value">{{ item.value }}</span>
            </div>
          </div>
        </template>
      </el-result>
    </div>

    <template #footer>
      <template v-if="!activationResult">
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" @click="handleActivate" :loading="activating">
          激活
        </el-button>
      </template>
      <template v-else>
        <el-button type="primary" @click="handleClose">确定</el-button>
      </template>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { accountApi } from '@/api/index.js'

const emit = defineEmits(['activated'])

const visible = ref(false)
const activating = ref(false)
const activationResult = ref(null)
const formRef = ref(null)

const form = reactive({
  cardKey: ''
})

const rules = {
  cardKey: [
    { required: true, message: '请输入卡密', trigger: 'blur' }
  ]
}

const handleActivate = async () => {
  if (!formRef.value) return
  await formRef.value.validate()

  activating.value = true
  try {
    const res = await accountApi.activateCard({ card_key: form.cardKey })
    activationResult.value = {
      message: res.message || '卡密激活成功',
      benefits: res.benefits || []
    }
    emit('activated', res)
  } catch (error) {
    ElMessage.error(error.message || '激活失败')
  } finally {
    activating.value = false
  }
}

const handleClose = () => {
  visible.value = false
  form.cardKey = ''
  activationResult.value = null
  formRef.value?.resetFields()
}

const open = () => {
  visible.value = true
}

defineExpose({ open })
</script>

<style lang="scss" scoped>
.notice-box {
  margin-top: 12px;
  padding: 14px 16px;
  background: #f9f5ff;
  border-radius: 8px;
  border: 1px solid #ede4ff;
}

.notice-title {
  font-size: 13px;
  font-weight: 700;
  color: #5d3f9e;
  margin-bottom: 8px;
}

.notice-list {
  margin: 0;
  padding-left: 18px;
  font-size: 12px;
  color: #8f7d9e;
  line-height: 2;
}

.activation-result {
  text-align: center;
}

.result-details {
  display: inline-flex;
  flex-direction: column;
  gap: 8px;
  text-align: left;
}

.benefit-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 16px;
  background: #f0e8ff;
  border-radius: 6px;
}

.benefit-label {
  color: #5d3f9e;
  font-size: 13px;
}

.benefit-value {
  color: #241254;
  font-weight: 700;
  font-size: 14px;
}
</style>

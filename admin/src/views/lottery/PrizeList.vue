<template>
  <div class="lottery-prize-list">
    <el-card class="header-card">
      <div class="page-bar">
        <el-button @click="goBack">
          <el-icon><ArrowLeft /></el-icon>
          返回活动
        </el-button>
        <div class="title">
          <span class="label">当前活动:</span>
          <el-tag type="primary" size="large">{{ activityName || ('#' + activityId) }}</el-tag>
        </div>
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增奖项
        </el-button>
      </div>
      <el-alert
        class="prob-alert"
        :type="probState.type"
        :title="`概率统计：当前总概率 ${totalProbability.toFixed(2)}%，剩余可分配 ${remainingProbability.toFixed(2)}%（最大 100%）`"
        :closable="false"
        show-icon
      />
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="奖项图标" width="90" align="center">
          <template #default="{ row }">
            <el-image v-if="row.icon" :src="row.icon" fit="cover" style="width: 48px; height: 48px; border-radius: 4px;" :preview-src-list="[row.icon]" />
            <span v-else class="muted">无</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="奖项名" min-width="140" show-overflow-tooltip />
        <el-table-column label="概率(%)" width="110" align="center">
          <template #default="{ row }">
            <el-tag>{{ Number(getProbability(row) * 100).toFixed(2) }}%</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="100" align="center">
          <template #default="{ row }">{{ row.stock ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="奖品类型" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="prizeTypeTag(row.prizeType)">{{ prizeTypeText(row.prizeType) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="openEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑奖项' : '新增奖项'" width="560px" @close="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="活动ID">
          <el-input :model-value="String(activityId)" disabled />
        </el-form-item>
        <el-form-item label="奖项名" prop="name">
          <el-input v-model="form.name" placeholder="例如：一等奖/谢谢参与/优惠券5元" maxlength="40" show-word-limit />
        </el-form-item>
        <el-form-item label="图标URL">
          <el-input v-model="form.icon" placeholder="图标图片URL" />
          <el-image v-if="form.icon" :src="form.icon" fit="cover" style="width: 80px; height: 80px; margin-top: 6px; border-radius: 4px;" :preview-src-list="[form.icon]" />
        </el-form-item>
        <el-form-item label="概率(0-1)" prop="probability">
          <el-input-number v-model="form.probability" :min="0" :max="1" :step="0.01" :precision="2" style="width: 100%" />
          <div class="hint">提示：所填数字为 0~1 之间（如 0.05 表示 5%）</div>
        </el-form-item>
        <el-form-item label="库存">
          <el-input-number v-model="form.stock" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="奖品类型" prop="prizeType">
          <el-select v-model="form.prizeType" placeholder="选择奖品类型" style="width: 100%">
            <el-option v-for="t in prizeTypes" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.prizeType === 'COUPON'" label="优惠券ID" prop="couponId">
          <el-input-number v-model="form.couponId" :min="0" style="width: 100%" />
          <div class="hint">后端会校验 coupon_id 是否存在</div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, ArrowLeft } from '@element-plus/icons-vue'
import { lotteryAdminApi } from '@/api/index'
import { normalizeListPayload } from '@/utils/responseHelper'

const route = useRoute()
const router = useRouter()

const activityId = computed(() => Number(route.query.id) || 0)
const activityName = computed(() => route.query.name || '')

const prizeTypes = [
  { value: 'THANKS', label: '谢谢参与' },
  { value: 'COUPON', label: '优惠券' },
  { value: 'POINTS', label: '积分' },
  { value: 'CUSTOM', label: '自定义' }
]
const prizeTypeText = (k) => prizeTypes.find(p => p.value === k)?.label || k || '-'
const prizeTypeTag = (k) => ({ THANKS: 'info', COUPON: 'warning', POINTS: 'success', CUSTOM: 'primary' }[k] || 'info')

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)

const tableData = ref([])

// 后端字段命名：probability（0~1，浮点）；这里也兼容已有 0~100 的存值
const getProbability = (row) => {
  const v = Number(row.probability)
  if (isNaN(v)) return 0
  return v > 1 ? v / 100 : v
}

const totalProbability = computed(() => tableData.value.reduce((s, r) => s + getProbability(r), 0) * 100)
const remainingProbability = computed(() => Math.max(0, 100 - totalProbability.value))
const probState = computed(() => {
  if (totalProbability.value > 100) return { type: 'error' }
  if (totalProbability.value >= 90) return { type: 'warning' }
  return { type: 'info' }
})

const blankForm = () => ({
  id: null,
  name: '',
  icon: '',
  probability: 0,
  stock: 0,
  prizeType: 'THANKS',
  couponId: null,
  sort: 0
})
const form = reactive(blankForm())

const rules = {
  name: [{ required: true, message: '请输入奖项名', trigger: 'blur' }],
  probability: [
    { required: true, message: '请输入概率', trigger: 'blur' },
    { type: 'number', min: 0, max: 1, message: '概率必须在 0~1 之间', trigger: 'blur' }
  ],
  prizeType: [{ required: true, message: '请选择奖品类型', trigger: 'change' }]
}

const loadData = async () => {
  if (!activityId.value) {
    ElMessage.error('缺少活动ID，请从活动列表进入')
    return
  }
  loading.value = true
  try {
    const res = await lotteryAdminApi.prizes(activityId.value)
    tableData.value = normalizeListPayload(res)
  } catch (err) {
    console.error('加载奖项失败:', err)
    tableData.value = []
    ElMessage.error('加载奖项失败')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  Object.assign(form, blankForm())
  formRef.value?.clearValidate()
}

const openCreate = () => { isEdit.value = false; resetForm(); dialogVisible.value = true }
const openEdit = (row) => {
  isEdit.value = true
  Object.assign(form, blankForm(), {
    id: row.id,
    name: row.name || '',
    icon: row.icon || '',
    probability: getProbability(row),
    stock: Number(row.stock) || 0,
    prizeType: row.prizeType || 'THANKS',
    couponId: row.couponId ?? row.coupon_id ?? null,
    sort: Number(row.sort) || 0
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  await formRef.value.validate().catch(() => {})
  submitting.value = true
  try {
    const payload = {
      activity_id: activityId.value,
      name: form.name,
      icon: form.icon || '',
      probability: Number(form.probability),
      stock: Number(form.stock) || 0,
      prize_type: form.prizeType,
      coupon_id: form.prizeType === 'COUPON' ? Number(form.couponId) || 0 : undefined,
      sort: Number(form.sort) || 0
    }
    if (isEdit.value) {
      await lotteryAdminApi.updatePrize(form.id, payload)
      ElMessage.success('更新成功')
    } else {
      await lotteryAdminApi.createPrize(payload)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (err) {
    console.error('保存失败:', err)
    ElMessage.error(err?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除奖项 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await lotteryAdminApi.removePrize(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除失败:', err)
      ElMessage.error('删除失败')
    }
  }
}

const goBack = () => router.push('/lottery/activity-list')

onMounted(loadData)
</script>

<style lang="scss" scoped>
.lottery-prize-list {
  padding: 20px;

  .header-card {
    margin-bottom: 16px;

    .page-bar {
      display: flex; align-items: center; gap: 16px;
      .title { flex: 1; font-size: 14px; }
      .label { color: #909399; margin-right: 8px; }
    }

    .prob-alert { margin-top: 12px; }
  }

  .table-card {
    :deep(.el-pagination) { margin-top: 16px; justify-content: flex-end; }
  }

  .muted { color: #c0c4cc; font-size: 12px; }
  .hint { font-size: 12px; color: #909399; }
}
</style>

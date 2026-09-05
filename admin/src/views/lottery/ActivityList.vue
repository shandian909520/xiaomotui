<template>
  <div class="lottery-activity-list">
    <el-card class="search-card">
      <div class="search-bar">
        <el-input v-model="query.name" placeholder="活动名关键字" clearable style="width: 200px" @keyup.enter="handleSearch" @clear="handleSearch" />
        <el-select v-model="query.deviceId" placeholder="关联设备" clearable filterable style="width: 200px" @change="handleSearch">
          <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.id}${d.name ? '（' + d.name + '）' : ''}`" :value="d.id" />
        </el-select>
        <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px" @change="handleSearch">
          <el-option label="启用" :value="1" />
          <el-option label="停用" :value="0" />
        </el-select>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
        <div class="spacer"></div>
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新建活动
        </el-button>
      </div>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="活动名" min-width="180" show-overflow-tooltip />
        <el-table-column prop="deviceId" label="关联设备" width="110" align="center">
          <template #default="{ row }">{{ row.deviceId ?? '-' }}</template>
        </el-table-column>
        <el-table-column label="活动时间" width="320" align="center">
          <template #default="{ row }">{{ formatRange(row.startAt, row.endAt) }}</template>
        </el-table-column>
        <el-table-column prop="dailyLimit" label="每日抽奖上限" width="120" align="center">
          <template #default="{ row }">{{ row.dailyLimit ?? 0 }} 次</template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="Number(row.status) === 1"
              :loading="row.__switching"
              @change="(val) => handleToggleStatus(row, val)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="240" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="goManagePrizes(row)">管理奖项</el-button>
            <el-button size="small" type="primary" link @click="openEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadData"
        @current-change="loadData"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑抽奖活动' : '新建抽奖活动'" width="640px" @close="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <el-form-item label="活动名称" prop="name">
          <el-input v-model="form.name" placeholder="例如：端午幸运大转盘" maxlength="60" show-word-limit />
        </el-form-item>
        <el-form-item label="关联设备" prop="deviceId">
          <el-select v-model="form.deviceId" placeholder="选择关联设备" filterable style="width: 100%">
            <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.id}${d.name ? '（' + d.name + '）' : ''}`" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="手动设备ID">
          <el-input-number v-model="manualDeviceId" :min="0" placeholder="下拉为空时可手动填写" style="width: 100%" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开始时间" prop="startAt">
              <el-date-picker v-model="form.startAt" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="开始时间" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束时间" prop="endAt">
              <el-date-picker v-model="form.endAt" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="结束时间" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="每日抽奖上限">
          <el-input-number v-model="form.dailyLimit" :min="0" :max="9999" style="width: 100%" />
          <div class="hint">0 表示不限制</div>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">停用</el-radio>
          </el-radio-group>
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
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { lotteryAdminApi, nfcApi } from '@/api/index'
import { normalizePagination, normalizeListPayload } from '@/utils/responseHelper'

const router = useRouter()

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)

const query = reactive({ name: '', deviceId: null, status: '' })
const pagination = reactive({ page: 1, limit: 10, total: 0 })
const tableData = ref([])
const deviceOptions = ref([])
const manualDeviceId = ref(null)

const blankForm = () => ({
  id: null,
  name: '',
  deviceId: null,
  startAt: '',
  endAt: '',
  dailyLimit: 1,
  status: 1
})
const form = reactive(blankForm())

const rules = {
  name: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
  deviceId: [{ required: true, message: '请选择关联设备', trigger: 'change' }],
  startAt: [{ required: true, message: '请选择开始时间', trigger: 'change' }],
  endAt: [{ required: true, message: '请选择结束时间', trigger: 'change' }]
}

const formatRange = (a, b) => (a || '-') + ' ~ ' + (b || '-')

const buildPayload = () => ({
  device_id: manualDeviceId.value || form.deviceId,
  name: form.name,
  start_at: form.startAt,
  end_at: form.endAt,
  daily_limit: Number(form.dailyLimit) || 0,
  status: Number(form.status)
})

const loadData = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (query.name) params.name = query.name
    if (query.deviceId) params.device_id = query.deviceId
    if (query.status !== '' && query.status !== null) params.status = query.status

    const res = await lotteryAdminApi.activityList(params)
    const { list, total } = normalizePagination(res)
    tableData.value = list.map(it => ({ ...it, __switching: false }))
    pagination.total = total
  } catch (err) {
    console.error('加载抽奖活动失败:', err)
    tableData.value = []
    pagination.total = 0
    ElMessage.error('加载抽奖活动失败')
  } finally {
    loading.value = false
  }
}

const loadDevices = async () => {
  try {
    const res = await nfcApi.getDevices({ page: 1, limit: 200 })
    deviceOptions.value = normalizeListPayload(res)
  } catch (_) {
    deviceOptions.value = []
  }
}

const handleSearch = () => { pagination.page = 1; loadData() }
const handleReset = () => {
  query.name = ''; query.deviceId = null; query.status = ''
  pagination.page = 1; loadData()
}

const resetForm = () => {
  Object.assign(form, blankForm())
  manualDeviceId.value = null
  formRef.value?.clearValidate()
}

const openCreate = () => { isEdit.value = false; resetForm(); dialogVisible.value = true }
const openEdit = (row) => {
  isEdit.value = true
  Object.assign(form, blankForm(), {
    id: row.id,
    name: row.name || '',
    deviceId: row.deviceId ?? null,
    startAt: row.startAt || row.start_at || '',
    endAt: row.endAt || row.end_at || '',
    dailyLimit: Number(row.dailyLimit ?? row.daily_limit) || 0,
    status: Number(row.status) === 1 ? 1 : 0
  })
  manualDeviceId.value = null
  dialogVisible.value = true
}

const handleSubmit = async () => {
  await formRef.value.validate().catch(() => {})
  submitting.value = true
  try {
    const payload = buildPayload()
    if (isEdit.value) {
      await lotteryAdminApi.updateActivity(form.id, payload)
      ElMessage.success('更新成功')
    } else {
      await lotteryAdminApi.createActivity(payload)
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

const handleToggleStatus = async (row, val) => {
  row.__switching = true
  try {
    // 抽奖没有专门的 toggle 接口，这里通过 update 局部传 status
    await lotteryAdminApi.updateActivity(row.id, { status: val ? 1 : 0 })
    row.status = val ? 1 : 0
    ElMessage.success(val ? '已启用' : '已停用')
  } catch (err) {
    console.error('切换状态失败:', err)
    ElMessage.error('切换状态失败')
  } finally {
    row.__switching = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除活动 "${row.name}" 吗？删除后所有奖项/记录将不再可用！`, '警告', {
      type: 'error', confirmButtonText: '删除', confirmButtonClass: 'el-button--danger'
    })
    await lotteryAdminApi.removeActivity(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除失败:', err)
      ElMessage.error('删除失败')
    }
  }
}

const goManagePrizes = (row) => {
  router.push({ path: '/lottery/prize-list', query: { id: row.id, name: row.name } })
}

onMounted(() => {
  loadDevices()
  loadData()
})
</script>

<style lang="scss" scoped>
.lottery-activity-list {
  padding: 20px;

  .search-card {
    margin-bottom: 16px;
    .search-bar {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      .spacer { flex: 1; }
    }
  }

  .table-card {
    :deep(.el-pagination) { margin-top: 16px; justify-content: flex-end; }
  }

  .hint { font-size: 12px; color: #909399; }
}
</style>

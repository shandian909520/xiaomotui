<template>
  <div class="copywriting-pool-list">
    <el-card class="search-card">
      <div class="search-bar">
        <el-input v-model="query.keyword" placeholder="搜索文案内容" clearable style="width: 200px" @keyup.enter="handleSearch" @clear="handleSearch" />
        <el-select v-model="query.deviceId" placeholder="选择设备" clearable filterable style="width: 220px" @change="handleSearch">
          <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.device_code || d.id}${d.deviceName || d.device_name ? '（' + (d.deviceName || d.device_name) + '）' : ''}`" :value="d.id" />
        </el-select>
        <el-select v-model="query.scene" placeholder="场景" clearable style="width: 120px" @change="handleSearch">
          <el-option label="发布" value="publish" />
          <el-option label="点评" value="review" />
          <el-option label="团购" value="groupbuy" />
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
          新增文案
        </el-button>
        <el-button type="success" plain @click="openBatchImport">
          <el-icon><Upload /></el-icon>
          批量导入
        </el-button>
      </div>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="device_id" label="设备ID" width="90" align="center" />
        <el-table-column prop="scene" label="场景" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="sceneTag(row.scene)">{{ sceneLabel(row.scene) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="content" label="文案内容" min-width="280" show-overflow-tooltip />
        <el-table-column prop="weight" label="权重" width="80" align="center" />
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column prop="used_count" label="使用次数" width="100" align="center" />
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="Number(row.status) === 1"
              :loading="row.__switching"
              @change="(val) => handleToggleStatus(row, val)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="170" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="openEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="empty-tip" v-if="!loading && tableData.length === 0">
        暂无文案，可点击「新增文案」或「批量导入」创建
      </div>
    </el-card>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑文案' : '新增文案'" width="640px" @close="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="所属设备" prop="device_id">
          <el-select v-model="form.device_id" placeholder="选择设备" filterable style="width: 100%">
            <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.device_code || d.id}${d.deviceName || d.device_name ? '（' + (d.deviceName || d.device_name) + '）' : ''}`" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="场景" prop="scene">
          <el-radio-group v-model="form.scene">
            <el-radio value="publish">发布</el-radio>
            <el-radio value="review">点评</el-radio>
            <el-radio value="groupbuy">团购</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="文案内容" prop="content">
          <el-input v-model="form.content" type="textarea" :rows="4" maxlength="1000" show-word-limit placeholder="例如：发现一家宝藏店,体验感拉满!" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="权重">
              <el-input-number v-model="form.weight" :min="1" :max="9999" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序">
              <el-input-number v-model="form.sort" :min="0" :max="9999" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
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

    <!-- 批量导入对话框 -->
    <el-dialog v-model="batchVisible" title="批量导入文案" width="640px" @close="resetBatch">
      <el-form label-width="100px">
        <el-form-item label="所属设备" required>
          <el-select v-model="batch.deviceId" placeholder="选择设备" filterable style="width: 100%">
            <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.device_code || d.id}${d.deviceName || d.device_name ? '（' + (d.deviceName || d.device_name) + '）' : ''}`" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="场景">
          <el-radio-group v-model="batch.scene">
            <el-radio value="publish">发布</el-radio>
            <el-radio value="review">点评</el-radio>
            <el-radio value="groupbuy">团购</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="权重">
          <el-input-number v-model="batch.weight" :min="1" :max="9999" style="width: 100%" />
        </el-form-item>
        <el-form-item label="文案内容" required>
          <el-input v-model="batch.lines" type="textarea" :rows="10" placeholder="每行一条文案,空行会被忽略" />
          <div class="hint">每行一条;总长度建议 ≤ 1000 字/条</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchVisible = false">取消</el-button>
        <el-button type="primary" :loading="batchSubmitting" @click="handleBatchSubmit">导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Upload } from '@element-plus/icons-vue'
import { copywritingAdminApi, nfcApi } from '@/api/index'
import { normalizeListPayload } from '@/utils/responseHelper'

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const batchVisible = ref(false)
const batchSubmitting = ref(false)

const query = reactive({ keyword: '', deviceId: null, scene: '', status: '' })
const tableData = ref([])
const deviceOptions = ref([])

const blankForm = () => ({
  id: null,
  device_id: null,
  scene: 'publish',
  content: '',
  weight: 10,
  sort: 0,
  status: 1
})
const form = reactive(blankForm())

const batch = reactive({
  deviceId: null,
  scene: 'publish',
  weight: 10,
  lines: ''
})

const rules = {
  device_id: [{ required: true, message: '请选择设备', trigger: 'change' }],
  content:  [{ required: true, message: '请输入文案内容', trigger: 'blur' }],
  scene:    [{ required: true, message: '请选择场景', trigger: 'change' }]
}

const sceneLabel = (s) => ({ publish: '发布', review: '点评', groupbuy: '团购' }[s] || s)
const sceneTag   = (s) => ({ publish: '',       review: 'warning', groupbuy: 'success' }[s] || '')

const loadData = async () => {
  loading.value = true
  try {
    const params = {}
    if (query.deviceId) params.device_id = query.deviceId
    if (query.scene)    params.scene    = query.scene
    if (query.status !== '' && query.status !== null) params.status = query.status

    let list = []
    if (query.deviceId) {
      const res = await copywritingAdminApi.list(params)
      list = normalizeListPayload(res)
    } else {
      // 无设备筛选时拉所有设备的文案(取每个设备的前若干条)
      list = []
      const devices = deviceOptions.value
      const targets = devices.length ? devices.slice(0, 20) : []
      for (const d of targets) {
        try {
          const res = await copywritingAdminApi.list({ ...params, device_id: d.id })
          const arr = normalizeListPayload(res)
          list = list.concat(arr)
        } catch (_) { /* ignore */ }
      }
    }

    if (query.keyword) {
      const kw = String(query.keyword).toLowerCase()
      list = list.filter(it => String(it.content || '').toLowerCase().includes(kw))
    }
    tableData.value = list.map(it => ({ ...it, __switching: false }))
  } catch (err) {
    console.error('加载文案池失败:', err)
    tableData.value = []
    ElMessage.error('加载文案池失败')
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

const handleSearch = () => loadData()
const handleReset  = () => {
  query.keyword = ''
  query.deviceId = null
  query.scene = ''
  query.status = ''
  loadData()
}

const resetForm = () => {
  Object.assign(form, blankForm())
  formRef.value?.clearValidate()
}

const openCreate = () => {
  isEdit.value = false
  resetForm()
  if (query.deviceId) form.device_id = query.deviceId
  if (query.scene)    form.scene     = query.scene
  dialogVisible.value = true
}

const openEdit = (row) => {
  isEdit.value = true
  Object.assign(form, blankForm(), {
    id: row.id,
    device_id: row.device_id ?? row.deviceId ?? null,
    scene: row.scene || 'publish',
    content: row.content || '',
    weight: Number(row.weight ?? 10),
    sort:   Number(row.sort ?? 0),
    status: Number(row.status ?? 1) === 1 ? 1 : 0
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  await formRef.value.validate().catch(() => {})
  submitting.value = true
  try {
    const payload = {
      device_id: form.device_id,
      scene:     form.scene,
      content:   form.content,
      weight:    Number(form.weight) || 10,
      sort:      Number(form.sort)   || 0,
      status:    Number(form.status)
    }
    if (isEdit.value) {
      await copywritingAdminApi.update(form.id, payload)
      ElMessage.success('更新成功')
    } else {
      await copywritingAdminApi.create(payload)
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
    await copywritingAdminApi.update(row.id, { status: val ? 1 : 0 })
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
    await ElMessageBox.confirm(`确定删除该文案吗？\n${row.content}`, '警告', {
      type: 'error', confirmButtonText: '删除', confirmButtonClass: 'el-button--danger'
    })
    await copywritingAdminApi.remove(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除失败:', err)
      ElMessage.error('删除失败')
    }
  }
}

const openBatchImport = () => {
  resetBatch()
  if (query.deviceId) batch.deviceId = query.deviceId
  if (query.scene)    batch.scene    = query.scene
  batchVisible.value = true
}

const resetBatch = () => {
  batch.deviceId = null
  batch.scene    = 'publish'
  batch.weight   = 10
  batch.lines    = ''
}

const handleBatchSubmit = async () => {
  if (!batch.deviceId) {
    ElMessage.warning('请选择设备')
    return
  }
  const lines = (batch.lines || '').split(/\r?\n/).filter(s => s.trim() !== '')
  if (lines.length === 0) {
    ElMessage.warning('请输入至少一条文案')
    return
  }
  batchSubmitting.value = true
  try {
    const res = await copywritingAdminApi.batchImport({
      device_id: batch.deviceId,
      scene:     batch.scene,
      weight:    Number(batch.weight) || 10,
      lines:     lines.join('\n')
    })
    const imported = res?.imported ?? 0
    const skipped  = res?.skipped  ?? 0
    ElMessage.success(`导入完成:成功 ${imported} 条,跳过 ${skipped} 条`)
    batchVisible.value = false
    query.deviceId = batch.deviceId
    query.scene    = batch.scene
    loadData()
  } catch (err) {
    console.error('批量导入失败:', err)
    ElMessage.error(err?.message || '批量导入失败')
  } finally {
    batchSubmitting.value = false
  }
}

onMounted(async () => {
  await loadDevices()
  await loadData()
})
</script>

<style lang="scss" scoped>
.copywriting-pool-list {
  padding: 20px;

  .search-card { margin-bottom: 16px; }
  .search-bar  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    .spacer { flex: 1; }
  }

  .table-card :deep(.el-pagination) { margin-top: 16px; justify-content: flex-end; }
  .empty-tip  { text-align: center; color: #909399; padding: 24px; font-size: 13px; }
  .hint       { font-size: 12px; color: #909399; margin-top: 4px; }
}
</style>

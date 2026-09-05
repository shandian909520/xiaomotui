<template>
  <div class="groupbuy-item-list">
    <!-- 顶部筛选 -->
    <el-card class="search-card">
      <div class="search-bar">
        <el-select v-model="query.deviceId" placeholder="关联设备ID" clearable filterable style="width: 200px" @change="handleSearch">
          <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.id} ${d.name ? '('+d.name+')' : ''}`" :value="d.id" />
        </el-select>
        <el-input v-model="query.title" placeholder="商品标题关键字" clearable style="width: 200px" @keyup.enter="handleSearch" @clear="handleSearch" />
        <el-select v-model="query.platform" placeholder="平台" clearable style="width: 150px" @change="handleSearch">
          <el-option v-for="p in platformOptions" :key="p.value" :label="p.label" :value="p.value" />
        </el-select>
        <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px" @change="handleSearch">
          <el-option label="上架" :value="1" />
          <el-option label="下架" :value="0" />
        </el-select>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
        <div class="spacer"></div>
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增商品
        </el-button>
      </div>
      <div class="hint">
        设备下拉无数据？直接输入 <el-tag size="small">device_id</el-tag> 过滤即可，或直接在编辑弹窗中填写。
      </div>
    </el-card>

    <!-- 列表 -->
    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="商品图" width="90" align="center">
          <template #default="{ row }">
            <el-image v-if="row.image" :src="row.image" fit="cover" style="width: 60px; height: 60px; border-radius: 4px;" :preview-src-list="[row.image]" />
            <span v-else class="muted">无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="商品标题" min-width="200" show-overflow-tooltip />
        <el-table-column label="平台" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="platformTag(row.platform)">{{ platformText(row.platform) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="120" align="center">
          <template #default="{ row }">
            <span class="price-now">¥{{ formatMoney(row.price) }}</span>
            <span v-if="row.originalPrice && row.originalPrice > row.price" class="price-old">¥{{ formatMoney(row.originalPrice) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="sales" label="已售数" width="100" align="center" />
        <el-table-column prop="deviceId" label="设备ID" width="100" align="center">
          <template #default="{ row }">{{ row.deviceId ?? '-' }}</template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" align="center" />
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
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
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

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑团购商品' : '新增团购商品'" width="640px" @close="resetForm">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="关联设备" prop="deviceId">
          <el-select v-model="form.deviceId" placeholder="选择设备" filterable style="width: 100%">
            <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.id}${d.name ? '（' + d.name + '）' : ''}`" :value="d.id" />
          </el-select>
          <div class="hint">若下拉为空，可手动填写 device_id（联系后端补齐设备列表接口）</div>
        </el-form-item>
        <el-form-item label="设备ID(手动)">
          <el-input-number v-model="manualDeviceId" :min="0" placeholder="如设备下拉无数据可在此直接输入" style="width: 100%" />
        </el-form-item>

        <el-form-item label="商品标题" prop="title">
          <el-input v-model="form.title" placeholder="例如：双人下午茶套餐" maxlength="80" show-word-limit />
        </el-form-item>

        <el-form-item label="商品图片" prop="image">
          <el-input v-model="form.image" placeholder="图片URL（可先填完整https链接，后续接入上传组件）" />
          <el-image v-if="form.image" :src="form.image" fit="cover" style="width: 100px; height: 100px; margin-top: 6px; border-radius: 4px;" :preview-src-list="[form.image]" />
        </el-form-item>

        <el-form-item label="平台" prop="platform">
          <el-select v-model="form.platform" placeholder="选择团购平台" style="width: 100%">
            <el-option v-for="p in platformOptions" :key="p.value" :label="p.label" :value="p.value" />
          </el-select>
        </el-form-item>

        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="团购价(元)" prop="price">
              <el-input-number v-model="form.price" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="原价(元)">
              <el-input-number v-model="form.originalPrice" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="已售数">
              <el-input-number v-model="form.sales" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序">
              <el-input-number v-model="form.sort" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="跳转链接" prop="jumpUrl">
          <el-input v-model="form.jumpUrl" placeholder="https://..." />
        </el-form-item>

        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">上架</el-radio>
            <el-radio :value="0">下架</el-radio>
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
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { groupBuyAdminApi, nfcApi } from '@/api/index'
import { normalizePagination, normalizeListPayload } from '@/utils/responseHelper'

const platformOptions = [
  { value: 'DOUYIN', label: '抖音' },
  { value: 'MEITUAN', label: '美团' },
  { value: 'ELEME', label: '饿了么' },
  { value: 'CUSTOM', label: '自定义' }
]

const platformText = (k) => platformOptions.find(p => p.value === k)?.label || k || '-'
const platformTag = (k) => ({ DOUYIN: 'danger', MEITUAN: 'warning', ELEME: 'success', CUSTOM: 'info' }[k] || 'info')

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)

const query = reactive({ deviceId: null, title: '', platform: '', status: '' })
const pagination = reactive({ page: 1, limit: 10, total: 0 })
const tableData = ref([])
const deviceOptions = ref([])
const manualDeviceId = ref(null)

const blankForm = () => ({
  id: null,
  deviceId: null,
  title: '',
  image: '',
  platform: 'DOUYIN',
  price: 0,
  originalPrice: 0,
  sales: 0,
  jumpUrl: '',
  sort: 0,
  status: 1
})
const form = reactive(blankForm())

const rules = {
  deviceId: [{ required: true, message: '请选择关联设备', trigger: 'change' }],
  title: [{ required: true, message: '请输入商品标题', trigger: 'blur' }],
  platform: [{ required: true, message: '请选择平台', trigger: 'change' }],
  jumpUrl: [
    { required: true, message: '请输入跳转链接', trigger: 'blur' },
    { pattern: /^https?:\/\//, message: '请输入以 http(s):// 开头的合法链接', trigger: 'blur' }
  ],
  price: [{ required: true, message: '请输入团购价', trigger: 'blur' }]
}

const formatMoney = (v) => {
  const n = Number(v)
  return isNaN(n) ? '0.00' : n.toFixed(2)
}

const effectiveDeviceId = computed(() => manualDeviceId.value || form.deviceId)

const buildPayload = () => {
  // 设备下拉没有时回退到手动输入
  const did = effectiveDeviceId.value
  return {
    device_id: did,
    title: form.title,
    image: form.image || '',
    platform: form.platform,
    price: form.price,
    original_price: form.originalPrice || 0,
    sales: form.sales || 0,
    jump_url: form.jumpUrl,
    sort: form.sort || 0,
    status: Number(form.status)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (query.deviceId) params.device_id = query.deviceId
    if (query.title) params.title = query.title
    if (query.platform) params.platform = query.platform
    if (query.status !== '' && query.status !== null) params.status = query.status

    const res = await groupBuyAdminApi.list(params)
    const { list, total } = normalizePagination(res)
    tableData.value = list.map(it => ({ ...it, __switching: false }))
    pagination.total = total
  } catch (err) {
    console.error('加载团购商品失败:', err)
    tableData.value = []
    pagination.total = 0
    ElMessage.error('加载团购商品失败')
  } finally {
    loading.value = false
  }
}

const loadDevices = async () => {
  // 后端若无统一设备列表接口，则忽略失败
  try {
    const res = await nfcApi.getDevices({ page: 1, limit: 200 })
    deviceOptions.value = normalizeListPayload(res)
  } catch (_) {
    deviceOptions.value = []
  }
}

const handleSearch = () => { pagination.page = 1; loadData() }
const handleReset = () => {
  query.deviceId = null; query.title = ''; query.platform = ''; query.status = ''
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
    deviceId: row.deviceId ?? null,
    title: row.title || '',
    image: row.image || '',
    platform: row.platform || 'DOUYIN',
    price: Number(row.price) || 0,
    originalPrice: Number(row.originalPrice ?? row.original_price) || 0,
    sales: Number(row.sales) || 0,
    jumpUrl: row.jumpUrl || row.jump_url || '',
    sort: Number(row.sort) || 0,
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
      await groupBuyAdminApi.update(form.id, payload)
      ElMessage.success('更新成功')
    } else {
      await groupBuyAdminApi.create(payload)
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
    // 后端 delete 走软删除(status=0)，重新上架走 update
    if (val) {
      await groupBuyAdminApi.update(row.id, { status: 1 })
    } else {
      await groupBuyAdminApi.remove(row.id)
    }
    row.status = val ? 1 : 0
    ElMessage.success(val ? '已上架' : '已下架')
  } catch (err) {
    console.error('切换状态失败:', err)
    ElMessage.error('切换状态失败')
  } finally {
    row.__switching = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定下架团购商品 "${row.title}" 吗？`, '提示', { type: 'warning' })
    await groupBuyAdminApi.remove(row.id)
    ElMessage.success('已下架')
    loadData()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('下架失败:', err)
      ElMessage.error('下架失败')
    }
  }
}

onMounted(() => {
  loadDevices()
  loadData()
})
</script>

<style lang="scss" scoped>
.groupbuy-item-list {
  padding: 20px;

  .search-card {
    margin-bottom: 16px;

    .search-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;

      .spacer { flex: 1; }
    }

    .hint {
      margin-top: 8px;
      font-size: 12px;
      color: #909399;
    }
  }

  .table-card {
    :deep(.el-pagination) {
      margin-top: 16px;
      justify-content: flex-end;
    }
  }

  .price-now { color: #f56c6c; font-weight: 600; }
  .price-old { color: #909399; text-decoration: line-through; margin-left: 6px; font-size: 12px; }
  .muted { color: #c0c4cc; font-size: 12px; }
  .hint { font-size: 12px; color: #909399; }
}
</style>

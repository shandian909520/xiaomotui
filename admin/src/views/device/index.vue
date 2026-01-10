<template>
  <div class="device-container">
    <!-- 搜索和操作栏 -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="设备名称">
          <el-input v-model="searchForm.keyword" placeholder="设备名称或编码" clearable />
        </el-form-item>
        <el-form-item label="设备状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="在线" :value="1" />
            <el-option label="离线" :value="0" />
            <el-option label="维护" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="设备类型">
          <el-select v-model="searchForm.type" placeholder="全部类型" clearable>
            <el-option label="全部" value="" />
            <el-option label="桌台" value="TABLE" />
            <el-option label="墙面" value="WALL" />
            <el-option label="柜台" value="COUNTER" />
            <el-option label="入口" value="ENTRANCE" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <div class="actions">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>
          添加设备
        </el-button>
        <el-button @click="handleRefresh">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
        <el-button :disabled="!selectedDevices.length" @click="handleBatchEnable">
          批量启用
        </el-button>
        <el-button :disabled="!selectedDevices.length" @click="handleBatchDisable">
          批量禁用
        </el-button>
      </div>
    </el-card>

    <!-- 设备列表表格 -->
    <el-card class="table-card">
      <el-table
        :data="deviceList"
        v-loading="loading"
        @selection-change="handleSelectionChange"
        stripe
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="device_code" label="设备编码" width="150" />
        <el-table-column prop="device_name" label="设备名称" width="180" />
        <el-table-column prop="type" label="设备类型" width="100">
          <template #default="{ row }">
            <el-tag>{{ getDeviceType(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="location" label="位置" width="150" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="电量" width="120">
          <template #default="{ row }">
            <el-progress
              :percentage="row.battery_level || 0"
              :status="getBatteryStatus(row.battery_level)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="trigger_mode" label="触发模式" width="120">
          <template #default="{ row }">
            {{ getTriggerMode(row.trigger_mode) }}
          </template>
        </el-table-column>
        <el-table-column prop="last_heartbeat" label="最后心跳" width="180">
          <template #default="{ row }">
            {{ formatTime(row.last_heartbeat) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleConfig(row)">配置</el-button>
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
      />
    </el-card>

    <!-- 添加/编辑设备对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form :model="deviceForm" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="设备编码" prop="device_code">
          <el-input v-model="deviceForm.device_code" :disabled="isEdit" placeholder="请输入设备编码" />
        </el-form-item>
        <el-form-item label="设备名称" prop="device_name">
          <el-input v-model="deviceForm.device_name" placeholder="请输入设备名称" />
        </el-form-item>
        <el-form-item label="设备类型" prop="type">
          <el-select v-model="deviceForm.type" placeholder="请选择设备类型" style="width: 100%">
            <el-option label="桌台" value="TABLE" />
            <el-option label="墙面" value="WALL" />
            <el-option label="柜台" value="COUNTER" />
            <el-option label="入口" value="ENTRANCE" />
          </el-select>
        </el-form-item>
        <el-form-item label="设备位置" prop="location">
          <el-input v-model="deviceForm.location" placeholder="请输入设备位置" />
        </el-form-item>
        <el-form-item label="设备描述">
          <el-input
            v-model="deviceForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入设备描述（可选）"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 设备配置对话框 -->
    <el-dialog
      v-model="configDialogVisible"
      title="设备配置"
      width="700px"
      @close="handleConfigDialogClose"
    >
      <el-form :model="configForm" label-width="120px">
        <el-form-item label="触发模式">
          <el-select v-model="configForm.trigger_mode" placeholder="请选择触发模式" style="width: 100%">
            <el-option label="视频生成" value="VIDEO" />
            <el-option label="优惠券" value="COUPON" />
            <el-option label="WiFi连接" value="WIFI" />
            <el-option label="好友添加" value="CONTACT" />
            <el-option label="菜单展示" value="MENU" />
          </el-select>
        </el-form-item>

        <el-form-item label="内容模板" v-if="configForm.trigger_mode === 'VIDEO'">
          <el-select v-model="configForm.template_id" placeholder="请选择内容模板" style="width: 100%">
            <el-option label="默认模板" :value="0" />
            <el-option
              v-for="template in templateList"
              :key="template.id"
              :label="template.name"
              :value="template.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="跳转链接" v-if="['COUPON', 'MENU'].includes(configForm.trigger_mode)">
          <el-input v-model="configForm.redirect_url" placeholder="请输入跳转链接" />
        </el-form-item>

        <el-form-item label="WiFi名称" v-if="configForm.trigger_mode === 'WIFI'">
          <el-input v-model="configForm.wifi_ssid" placeholder="请输入WiFi名称" />
        </el-form-item>

        <el-form-item label="WiFi密码" v-if="configForm.trigger_mode === 'WIFI'">
          <el-input v-model="configForm.wifi_password" type="password" placeholder="请输入WiFi密码" show-password />
        </el-form-item>

        <el-form-item label="企业微信" v-if="configForm.trigger_mode === 'CONTACT'">
          <el-input v-model="configForm.contact_qr" placeholder="请输入企业微信二维码地址" />
        </el-form-item>

        <el-form-item label="设备状态">
          <el-radio-group v-model="configForm.status">
            <el-radio :label="1">在线</el-radio>
            <el-radio :label="0">离线</el-radio>
            <el-radio :label="2">维护</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="configDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveConfig" :loading="configSubmitting">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import { nfcApi, contentApi } from '@/api'

// 搜索表单
const searchForm = reactive({
  keyword: '',
  status: '',
  type: ''
})

// 设备列表
const deviceList = ref([])
const loading = ref(false)
const selectedDevices = ref([])

// 分页
const pagination = reactive({
  page: 1,
  limit: 20,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('添加设备')
const isEdit = ref(false)
const formRef = ref(null)
const submitting = ref(false)

// 设备表单
const deviceForm = reactive({
  id: null,
  device_code: '',
  device_name: '',
  type: 'TABLE',
  location: '',
  description: ''
})

// 配置对话框
const configDialogVisible = ref(false)
const configSubmitting = ref(false)
const configForm = reactive({
  deviceId: null,
  trigger_mode: 'VIDEO',
  template_id: 0,
  redirect_url: '',
  wifi_ssid: '',
  wifi_password: '',
  contact_qr: '',
  status: 1
})

// 模板列表
const templateList = ref([])

// 表单验证规则
const rules = {
  device_code: [
    { required: true, message: '请输入设备编码', trigger: 'blur' },
    { min: 3, max: 50, message: '长度在 3 到 50 个字符', trigger: 'blur' }
  ],
  device_name: [
    { required: true, message: '请输入设备名称', trigger: 'blur' },
    { min: 2, max: 100, message: '长度在 2 到 100 个字符', trigger: 'blur' }
  ],
  type: [
    { required: true, message: '请选择设备类型', trigger: 'change' }
  ],
  location: [
    { required: true, message: '请输入设备位置', trigger: 'blur' }
  ]
}

// 加载设备列表
const loadDevices = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      limit: pagination.limit
    }

    // 添加搜索条件
    if (searchForm.keyword) {
      params.keyword = searchForm.keyword
    }
    if (searchForm.status !== '') {
      params.status = searchForm.status
    }
    if (searchForm.type) {
      params.type = searchForm.type
    }

    const res = await nfcApi.getDevices(params)

    if (res.data && Array.isArray(res.data)) {
      deviceList.value = res.data
      pagination.total = res.pagination?.total || res.data.length
    } else {
      deviceList.value = []
      pagination.total = 0
    }
  } catch (error) {
    console.error('加载设备列表失败:', error)
    ElMessage.error(error.message || '加载设备列表失败')
  } finally {
    loading.value = false
  }
}

// 加载模板列表
const loadTemplates = async () => {
  try {
    const res = await contentApi.getTemplates({ page: 1, limit: 100 })
    if (res.data && Array.isArray(res.data)) {
      templateList.value = res.data
    }
  } catch (error) {
    console.error('加载模板列表失败:', error)
  }
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadDevices()
}

// 重置
const handleReset = () => {
  Object.assign(searchForm, {
    keyword: '',
    status: '',
    type: ''
  })
  handleSearch()
}

// 刷新
const handleRefresh = () => {
  loadDevices()
  ElMessage.success('刷新成功')
}

// 添加设备
const handleAdd = () => {
  dialogTitle.value = '添加设备'
  isEdit.value = false
  Object.assign(deviceForm, {
    id: null,
    device_code: '',
    device_name: '',
    type: 'TABLE',
    location: '',
    description: ''
  })
  dialogVisible.value = true
}

// 编辑设备
const handleEdit = (row) => {
  dialogTitle.value = '编辑设备'
  isEdit.value = true
  Object.assign(deviceForm, {
    id: row.id,
    device_code: row.device_code,
    device_name: row.device_name,
    type: row.type || 'TABLE',
    location: row.location || '',
    description: row.description || ''
  })
  dialogVisible.value = true
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate()

  submitting.value = true
  try {
    const data = { ...deviceForm }
    delete data.id

    if (isEdit.value) {
      await nfcApi.updateDevice(deviceForm.id, data)
      ElMessage.success('更新成功')
    } else {
      await nfcApi.createDevice(data)
      ElMessage.success('添加成功')
    }
    dialogVisible.value = false
    loadDevices()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 配置设备
const handleConfig = async (row) => {
  try {
    // 获取设备详情和配置
    const res = await nfcApi.getDevice(row.id)

    Object.assign(configForm, {
      deviceId: row.id,
      trigger_mode: res.data?.trigger_mode || 'VIDEO',
      template_id: res.data?.template_id || 0,
      redirect_url: res.data?.redirect_url || '',
      wifi_ssid: res.data?.wifi_ssid || '',
      wifi_password: res.data?.wifi_password || '',
      contact_qr: res.data?.contact_qr || '',
      status: res.data?.status ?? 1
    })

    // 加载模板列表
    await loadTemplates()

    configDialogVisible.value = true
  } catch (error) {
    console.error('获取配置失败:', error)
    ElMessage.error(error.message || '获取配置失败')
  }
}

// 保存配置
const handleSaveConfig = async () => {
  configSubmitting.value = true
  try {
    const data = { ...configForm }
    const deviceId = data.deviceId
    delete data.deviceId

    await nfcApi.updateDevice(deviceId, data)
    ElMessage.success('配置保存成功')
    configDialogVisible.value = false
    loadDevices()
  } catch (error) {
    console.error('保存配置失败:', error)
    ElMessage.error(error.message || '保存配置失败')
  } finally {
    configSubmitting.value = false
  }
}

// 删除设备
const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定删除设备 "${row.device_name}" 吗？此操作不可恢复。`,
      '提示',
      {
        type: 'warning',
        confirmButtonText: '确定',
        cancelButtonText: '取消'
      }
    )

    await nfcApi.deleteDevice(row.id)
    ElMessage.success('删除成功')
    loadDevices()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.message || '删除失败')
    }
  }
}

// 选择变化
const handleSelectionChange = (selection) => {
  selectedDevices.value = selection
}

// 批量启用
const handleBatchEnable = async () => {
  try {
    await ElMessageBox.confirm(
      `确定批量启用选中的 ${selectedDevices.value.length} 个设备吗？`,
      '提示',
      {
        type: 'info',
        confirmButtonText: '确定',
        cancelButtonText: '取消'
      }
    )

    const promises = selectedDevices.value.map(device =>
      nfcApi.updateDevice(device.id, { status: 1 })
    )

    await Promise.all(promises)
    ElMessage.success('批量启用成功')
    loadDevices()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量启用失败:', error)
      ElMessage.error(error.message || '批量启用失败')
    }
  }
}

// 批量禁用
const handleBatchDisable = async () => {
  try {
    await ElMessageBox.confirm(
      `确定批量禁用选中的 ${selectedDevices.value.length} 个设备吗？`,
      '提示',
      {
        type: 'warning',
        confirmButtonText: '确定',
        cancelButtonText: '取消'
      }
    )

    const promises = selectedDevices.value.map(device =>
      nfcApi.updateDevice(device.id, { status: 0 })
    )

    await Promise.all(promises)
    ElMessage.success('批量禁用成功')
    loadDevices()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量禁用失败:', error)
      ElMessage.error(error.message || '批量禁用失败')
    }
  }
}

// 分页变化
const handleSizeChange = () => {
  pagination.page = 1
  loadDevices()
}

const handleCurrentChange = () => {
  loadDevices()
}

// 对话框关闭
const handleDialogClose = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
}

const handleConfigDialogClose = () => {
  Object.assign(configForm, {
    deviceId: null,
    trigger_mode: 'VIDEO',
    template_id: 0,
    redirect_url: '',
    wifi_ssid: '',
    wifi_password: '',
    contact_qr: '',
    status: 1
  })
}

// 工具方法
const getDeviceType = (type) => {
  const map = {
    TABLE: '桌台',
    WALL: '墙面',
    COUNTER: '柜台',
    ENTRANCE: '入口'
  }
  return map[type] || type
}

const getStatusType = (status) => {
  const map = {
    0: 'danger',
    1: 'success',
    2: 'warning'
  }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = {
    0: '离线',
    1: '在线',
    2: '维护'
  }
  return map[status] || '未知'
}

const getBatteryStatus = (level) => {
  if (!level) return 'exception'
  if (level > 50) return 'success'
  if (level > 20) return 'warning'
  return 'exception'
}

const getTriggerMode = (mode) => {
  const map = {
    VIDEO: '视频生成',
    COUPON: '优惠券',
    WIFI: 'WiFi连接',
    CONTACT: '好友添加',
    MENU: '菜单展示'
  }
  return map[mode] || mode || '-'
}

const formatTime = (time) => {
  if (!time) return '-'
  return time.replace('T', ' ').substring(0, 19)
}

// 定时刷新
let refreshTimer = null

onMounted(() => {
  loadDevices()
  // 每30秒自动刷新
  refreshTimer = setInterval(() => {
    loadDevices()
  }, 30000)
})

onBeforeUnmount(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
})
</script>

<style lang="scss" scoped>
.device-container {
  padding: 20px;
}

.search-card {
  margin-bottom: 20px;

  .actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
  }
}

.table-card {
  :deep(.el-pagination) {
    margin-top: 20px;
    justify-content: flex-end;
  }
}
</style>

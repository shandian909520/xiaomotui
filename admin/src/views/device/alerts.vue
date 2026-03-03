<template>
  <div class="alerts-container">
    <!-- 页面标题栏 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">告警管理</h1>
        <el-tag v-if="pendingCount > 0" type="danger" effect="dark">
          {{ pendingCount }} 条待处理
        </el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Check" type="success" @click="handleBatchResolve" :disabled="!hasSelection">
          批量解决
        </el-button>
        <el-button :icon="Delete" type="danger" @click="handleBatchIgnore" :disabled="!hasSelection">
          批量忽略
        </el-button>
        <el-button :icon="Refresh" @click="handleRefresh">刷新</el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="告警级别">
          <el-select v-model="filterForm.level" placeholder="全部" clearable @change="handleFilterChange">
            <el-option label="全部" value="" />
            <el-option label="严重" value="critical" />
            <el-option label="高级" value="high" />
            <el-option label="中级" value="medium" />
            <el-option label="低级" value="low" />
          </el-select>
        </el-form-item>

        <el-form-item label="告警类型">
          <el-select v-model="filterForm.type" placeholder="全部" clearable @change="handleFilterChange">
            <el-option label="全部" value="" />
            <el-option label="设备离线" value="offline" />
            <el-option label="电池电量低" value="low_battery" />
            <el-option label="响应超时" value="response_timeout" />
            <el-option label="设备故障" value="device_error" />
            <el-option label="信号弱" value="signal_weak" />
            <el-option label="温度异常" value="temperature" />
          </el-select>
        </el-form-item>

        <el-form-item label="告警状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable @change="handleFilterChange">
            <el-option label="全部" value="" />
            <el-option label="待处理" value="pending" />
            <el-option label="已确认" value="acknowledged" />
            <el-option label="已解决" value="resolved" />
            <el-option label="已忽略" value="ignored" />
          </el-select>
        </el-form-item>

        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :shortcuts="dateShortcuts"
            @change="handleFilterChange"
          />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 告警列表 -->
    <el-card class="table-card" shadow="never">
      <el-table
        ref="tableRef"
        v-loading="loading"
        :data="alerts"
        stripe
        @selection-change="handleSelectionChange"
        :default-sort="{ prop: 'trigger_time', order: 'descending' }"
      >
        <el-table-column type="selection" width="55" />

        <el-table-column prop="id" label="ID" width="80" />

        <el-table-column label="级别" width="100">
          <template #default="{ row }">
            <el-tag :type="getLevelType(row.alert_level)" size="small">
              {{ getLevelText(row.alert_level) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="类型" width="140">
          <template #default="{ row }">
            <el-tag type="info" size="small" effect="plain">
              {{ getTypeText(row.alert_type) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="alert_title" label="标题" min-width="180" show-overflow-tooltip />

        <el-table-column prop="alert_message" label="描述" min-width="220" show-overflow-tooltip />

        <el-table-column prop="device_code" label="设备" width="140" show-overflow-tooltip />

        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="trigger_time" label="触发时间" width="170" sortable />

        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'pending'"
              type="primary"
              size="small"
              :icon="View"
              @click="handleView(row)"
            >
              详情
            </el-button>
            <el-button
              v-if="row.status === 'pending' || row.status === 'acknowledged'"
              type="success"
              size="small"
              :icon="Check"
              @click="handleResolve(row)"
            >
              解决
            </el-button>
            <el-button
              v-if="row.status === 'pending' || row.status === 'acknowledged'"
              type="warning"
              size="small"
              :icon="Close"
              @click="handleIgnore(row)"
            >
              忽略
            </el-button>
            <el-tag v-if="row.status === 'resolved'" type="success" size="small">
              已解决
            </el-tag>
            <el-tag v-if="row.status === 'ignored'" type="info" size="small">
              已忽略
            </el-tag>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>

    <!-- 告警详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="告警详情"
      width="700px"
      destroy-on-close
    >
      <div v-if="currentAlert" class="alert-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="告警ID">
            {{ currentAlert.id }}
          </el-descriptions-item>
          <el-descriptions-item label="告警级别">
            <el-tag :type="getLevelType(currentAlert.alert_level)">
              {{ getLevelText(currentAlert.alert_level) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="告警类型">
            {{ getTypeText(currentAlert.alert_type) }}
          </el-descriptions-item>
          <el-descriptions-item label="告警状态">
            <el-tag :type="getStatusType(currentAlert.status)">
              {{ getStatusText(currentAlert.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="设备编码" :span="2">
            {{ currentAlert.device_code }}
          </el-descriptions-item>
          <el-descriptions-item label="告警标题" :span="2">
            {{ currentAlert.alert_title }}
          </el-descriptions-item>
          <el-descriptions-item label="告警描述" :span="2">
            {{ currentAlert.alert_message }}
          </el-descriptions-item>
          <el-descriptions-item label="触发时间" :span="2">
            {{ currentAlert.trigger_time }}
          </el-descriptions-item>
          <el-descriptions-item v-if="currentAlert.alert_data" label="详细信息" :span="2">
            <pre class="alert-data">{{ JSON.stringify(currentAlert.alert_data, null, 2) }}</pre>
          </el-descriptions-item>
        </el-descriptions>
      </div>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="detailDialogVisible = false">关闭</el-button>
          <el-button
            v-if="currentAlert && (currentAlert.status === 'pending' || currentAlert.status === 'acknowledged')"
            type="success"
            :icon="Check"
            @click="handleResolveFromDialog"
          >
            标记为已解决
          </el-button>
          <el-button
            v-if="currentAlert && (currentAlert.status === 'pending' || currentAlert.status === 'acknowledged')"
            type="warning"
            :icon="Close"
            @click="handleIgnoreFromDialog"
          >
            标记为已忽略
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 处理备注对话框 -->
    <el-dialog
      v-model="remarkDialogVisible"
      :title="remarkDialogTitle"
      width="500px"
      destroy-on-close
    >
      <el-input
        v-model="remarkForm.remark"
        type="textarea"
        :rows="4"
        placeholder="请输入处理备注(可选)"
      />
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="remarkDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleConfirmRemark">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Search,
  Refresh,
  Check,
  Close,
  Delete,
  View
} from '@element-plus/icons-vue'
import { getAlerts, resolveAlert, ignoreAlert, batchResolveAlerts, batchIgnoreAlerts } from '@/api/device'
import { useWebSocket } from '@/composables/useWebSocket'

// WebSocket连接
const { connected, connect: connectWs } = useWebSocket({
  onMessage: (message) => {
    if (message.type === 'alert') {
      // 收到新告警,刷新列表
      handleRefresh()
    }
  }
})

// 日期范围
const dateRange = ref([
  new Date(new Date().getTime() - 7 * 24 * 3600 * 1000),
  new Date()
])

// 日期快捷选项
const dateShortcuts = [
  {
    text: '最近7天',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
      return [start, end]
    }
  },
  {
    text: '最近30天',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 30)
      return [start, end]
    }
  }
]

// 筛选表单
const filterForm = reactive({
  level: '',
  type: '',
  status: ''
})

// 加载状态
const loading = ref(false)

// 告警列表
const alerts = ref([])

// 表格ref
const tableRef = ref(null)

// 选中的告警
const selectedAlerts = ref([])

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 待处理数量
const pendingCount = ref(0)

// 是否有选中项
const hasSelection = computed(() => selectedAlerts.value.length > 0)

// 详情对话框
const detailDialogVisible = ref(false)
const currentAlert = ref(null)

// 备注对话框
const remarkDialogVisible = ref(false)
const remarkDialogTitle = ref('')
const remarkForm = reactive({
  remark: ''
})
const pendingAction = ref(null) // 'resolve' or 'ignore'

// 格式化日期
const formatDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// 获取查询参数
const getQueryParams = () => {
  const params = {
    page: pagination.page,
    pageSize: pagination.pageSize
  }

  if (filterForm.level) {
    params.level = filterForm.level
  }
  if (filterForm.type) {
    params.type = filterForm.type
  }
  if (filterForm.status) {
    params.status = filterForm.status
  }
  if (dateRange.value && dateRange.value.length === 2) {
    params.startDate = formatDate(dateRange.value[0])
    params.endDate = formatDate(dateRange.value[1])
  }

  return params
}

// 加载告警列表
const loadAlerts = async () => {
  loading.value = true
  try {
    const params = getQueryParams()
    const res = await getAlerts(params)

    if (res.code === 200) {
      alerts.value = res.data.list || []
      pagination.total = res.data.total || 0
      pendingCount.value = res.data.pendingCount || 0
    }
  } catch (error) {
    console.error('加载告警列表失败:', error)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

// 处理筛选变化
const handleFilterChange = () => {
  pagination.page = 1
  loadAlerts()
}

// 处理查询
const handleSearch = () => {
  pagination.page = 1
  loadAlerts()
}

// 处理重置
const handleReset = () => {
  filterForm.level = ''
  filterForm.type = ''
  filterForm.status = ''
  dateRange.value = [
    new Date(new Date().getTime() - 7 * 24 * 3600 * 1000),
    new Date()
  ]
  pagination.page = 1
  loadAlerts()
}

// 处理刷新
const handleRefresh = () => {
  loadAlerts()
  ElMessage.success('刷新成功')
}

// 处理分页大小变化
const handleSizeChange = (val) => {
  pagination.pageSize = val
  pagination.page = 1
  loadAlerts()
}

// 处理页码变化
const handlePageChange = (val) => {
  pagination.page = val
  loadAlerts()
}

// 处理选择变化
const handleSelectionChange = (selection) => {
  selectedAlerts.value = selection
}

// 查看详情
const handleView = (row) => {
  currentAlert.value = row
  detailDialogVisible.value = true
}

// 解决告警
const handleResolve = (row) => {
  currentAlert.value = row
  pendingAction.value = 'resolve'
  remarkDialogTitle.value = '解决告警'
  remarkForm.remark = ''
  remarkDialogVisible.value = true
}

// 忽略告警
const handleIgnore = (row) => {
  currentAlert.value = row
  pendingAction.value = 'ignore'
  remarkDialogTitle.value = '忽略告警'
  remarkForm.remark = ''
  remarkDialogVisible.value = true
}

// 从对话框解决
const handleResolveFromDialog = () => {
  currentAlert.value = detailDialogVisible.value ? currentAlert.value : null
  pendingAction.value = 'resolve'
  remarkDialogTitle.value = '解决告警'
  remarkForm.remark = ''
  detailDialogVisible.value = false
  remarkDialogVisible.value = true
}

// 从对话框忽略
const handleIgnoreFromDialog = () => {
  currentAlert.value = detailDialogVisible.value ? currentAlert.value : null
  pendingAction.value = 'ignore'
  remarkDialogTitle.value = '忽略告警'
  remarkForm.remark = ''
  detailDialogVisible.value = false
  remarkDialogVisible.value = true
}

// 确认备注
const handleConfirmRemark = async () => {
  try {
    const alertId = currentAlert.value.id
    const remark = remarkForm.remark

    if (pendingAction.value === 'resolve') {
      await resolveAlert(alertId, remark)
      ElMessage.success('告警已解决')
    } else {
      await ignoreAlert(alertId, remark)
      ElMessage.success('告警已忽略')
    }

    remarkDialogVisible.value = false
    loadAlerts()
  } catch (error) {
    console.error('操作失败:', error)
    ElMessage.error('操作失败')
  }
}

// 批量解决
const handleBatchResolve = async () => {
  try {
    await ElMessageBox.confirm(
      `确定要解决选中的 ${selectedAlerts.value.length} 条告警吗？`,
      '批量解决',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const alertIds = selectedAlerts.value.map(item => item.id)
    await batchResolveAlerts(alertIds)

    ElMessage.success(`已解决 ${alertIds.length} 条告警`)
    tableRef.value.clearSelection()
    loadAlerts()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量解决失败:', error)
      ElMessage.error('操作失败')
    }
  }
}

// 批量忽略
const handleBatchIgnore = async () => {
  try {
    await ElMessageBox.confirm(
      `确定要忽略选中的 ${selectedAlerts.value.length} 条告警吗？`,
      '批量忽略',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const alertIds = selectedAlerts.value.map(item => item.id)
    await batchIgnoreAlerts(alertIds)

    ElMessage.success(`已忽略 ${alertIds.length} 条告警`)
    tableRef.value.clearSelection()
    loadAlerts()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量忽略失败:', error)
      ElMessage.error('操作失败')
    }
  }
}

// 获取级别类型
const getLevelType = (level) => {
  const typeMap = {
    critical: 'danger',
    high: 'warning',
    medium: '',
    low: 'info'
  }
  return typeMap[level] || ''
}

// 获取级别文本
const getLevelText = (level) => {
  const textMap = {
    critical: '严重',
    high: '高级',
    medium: '中级',
    low: '低级'
  }
  return textMap[level] || level
}

// 获取类型文本
const getTypeText = (type) => {
  const textMap = {
    offline: '设备离线',
    low_battery: '电池电量低',
    response_timeout: '响应超时',
    device_error: '设备故障',
    signal_weak: '信号弱',
    temperature: '温度异常'
  }
  return textMap[type] || type
}

// 获取状态类型
const getStatusType = (status) => {
  const typeMap = {
    pending: 'warning',
    acknowledged: 'info',
    resolved: 'success',
    ignored: 'info'
  }
  return typeMap[status] || ''
}

// 获取状态文本
const getStatusText = (status) => {
  const textMap = {
    pending: '待处理',
    acknowledged: '已确认',
    resolved: '已解决',
    ignored: '已忽略'
  }
  return textMap[status] || status
}

// 组件挂载
onMounted(() => {
  loadAlerts()
  // 连接WebSocket
  connectWs()
})

// 组件卸载
onBeforeUnmount(() => {
  // WebSocket会在useWebSocket的onUnmounted中自动清理
})
</script>

<style lang="scss" scoped>
.alerts-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;

      .page-title {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #303133;
      }
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }
  }

  .filter-card {
    margin-bottom: 20px;

    .filter-form {
      :deep(.el-form-item) {
        margin-bottom: 0;
      }
    }
  }

  .table-card {
    .pagination-container {
      margin-top: 20px;
      display: flex;
      justify-content: flex-end;
    }
  }

  .alert-detail {
    .alert-data {
      background: #f5f7fa;
      padding: 12px;
      border-radius: 4px;
      font-size: 12px;
      max-height: 300px;
      overflow-y: auto;
    }
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }
}

// 响应式设计
@media (max-width: 768px) {
  .alerts-container {
    padding: 12px;

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;

      .header-actions {
        width: 100%;

        :deep(.el-button) {
          flex: 1;
        }
      }
    }

    .filter-card {
      .filter-form {
        :deep(.el-form-item) {
          display: flex;
          flex-direction: column;
          width: 100%;

          .el-form-item__label {
            text-align: left;
          }

          .el-form-item__content {
            margin-left: 0 !important;
          }
        }
      }
    }
  }
}
</style>

<template>
  <div class="audit-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">内容审核</h1>
        <el-tag v-if="pendingCount > 0" type="danger" effect="dark">
          {{ pendingCount }} 条待审核
        </el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="handleRefresh">刷新</el-button>
      </div>
    </div>

    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="审核状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable @change="handleFilterChange">
            <el-option label="全部" value="" />
            <el-option label="待审核" value="PENDING" />
            <el-option label="已通过" value="CONFIRMED" />
            <el-option label="已拒绝" value="DISMISSED" />
          </el-select>
        </el-form-item>

        <el-form-item label="违规类型">
          <el-select v-model="filterForm.violation_type" placeholder="全部" clearable @change="handleFilterChange">
            <el-option label="全部" value="" />
            <el-option label="色情" value="porn" />
            <el-option label="暴力" value="violence" />
            <el-option label="违法" value="illegal" />
            <el-option label="广告" value="ad" />
            <el-option label="欺诈" value="fraud" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>

        <el-form-item label="严重程度">
          <el-select v-model="filterForm.severity" placeholder="全部" clearable @change="handleFilterChange">
            <el-option label="全部" value="" />
            <el-option label="严重" value="HIGH" />
            <el-option label="中等" value="MEDIUM" />
            <el-option label="轻微" value="LOW" />
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

    <el-card class="table-card" shadow="never">
      <el-table
        ref="tableRef"
        v-loading="loading"
        :data="violationList"
        stripe
        :default-sort="{ prop: 'create_time', order: 'descending' }"
      >
        <el-table-column prop="id" label="内容ID" width="80" />

        <el-table-column label="类型" width="120">
          <template #default="{ row }">
            <el-tag type="info" size="small" effect="plain">
              {{ getViolationTypeText(row.violation_type) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="严重程度" width="100">
          <template #default="{ row }">
            <el-tag :type="getSeverityType(row.severity)" size="small">
              {{ getSeverityText(row.severity) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="description" label="违规描述" min-width="200" show-overflow-tooltip />

        <el-table-column prop="material_name" label="关联素材" min-width="150" show-overflow-tooltip />

        <el-table-column label="来源" width="100">
          <template #default="{ row }">
            <el-tag type="info" size="small" effect="plain">
              {{ getSourceText(row.source) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="create_time" label="提交时间" width="170" sortable />

        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'PENDING'">
              <el-button type="success" size="small" :icon="Check" @click="handleApprove(row)">
                通过
              </el-button>
              <el-button type="danger" size="small" :icon="Close" @click="handleReject(row)">
                拒绝
              </el-button>
            </template>
            <template v-else>
              <el-button type="primary" size="small" :icon="View" @click="handleView(row)">
                详情
              </el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

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

    <el-dialog
      v-model="detailDialogVisible"
      title="审核详情"
      width="700px"
      destroy-on-close
    >
      <div v-if="currentItem" class="audit-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="违规ID">
            {{ currentItem.id }}
          </el-descriptions-item>
          <el-descriptions-item label="严重程度">
            <el-tag :type="getSeverityType(currentItem.severity)">
              {{ getSeverityText(currentItem.severity) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="违规类型">
            {{ getViolationTypeText(currentItem.violation_type) }}
          </el-descriptions-item>
          <el-descriptions-item label="审核状态">
            <el-tag :type="getStatusType(currentItem.status)">
              {{ getStatusText(currentItem.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="关联素材" :span="2">
            {{ currentItem.material_name || currentItem.material_id }}
          </el-descriptions-item>
          <el-descriptions-item label="违规描述" :span="2">
            {{ currentItem.description || currentItem.violation_description }}
          </el-descriptions-item>
          <el-descriptions-item label="来源">
            {{ getSourceText(currentItem.source) }}
          </el-descriptions-item>
          <el-descriptions-item label="提交时间">
            {{ currentItem.create_time }}
          </el-descriptions-item>
          <el-descriptions-item v-if="currentItem.resolve_comment" label="处理备注" :span="2">
            {{ currentItem.resolve_comment }}
          </el-descriptions-item>
          <el-descriptions-item v-if="currentItem.resolve_time" label="处理时间" :span="2">
            {{ currentItem.resolve_time }}
          </el-descriptions-item>
        </el-descriptions>
      </div>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="detailDialogVisible = false">关闭</el-button>
          <el-button
            v-if="currentItem && currentItem.status === 'PENDING'"
            type="success"
            :icon="Check"
            @click="handleApproveFromDialog"
          >
            通过
          </el-button>
          <el-button
            v-if="currentItem && currentItem.status === 'PENDING'"
            type="danger"
            :icon="Close"
            @click="handleRejectFromDialog"
          >
            拒绝
          </el-button>
        </span>
      </template>
    </el-dialog>

    <el-dialog
      v-model="rejectDialogVisible"
      title="拒绝原因"
      width="500px"
      destroy-on-close
    >
      <el-input
        v-model="rejectForm.reason"
        type="textarea"
        :rows="4"
        placeholder="请输入拒绝原因"
      />
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="rejectDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleConfirmReject">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Check, Close, View } from '@element-plus/icons-vue'
import { getViolationHistory, reviewViolation } from '@/api/violation'

const dateRange = ref([
  new Date(new Date().getTime() - 7 * 24 * 3600 * 1000),
  new Date()
])

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

const filterForm = reactive({
  status: '',
  violation_type: '',
  severity: ''
})

const loading = ref(false)
const violationList = ref([])
const tableRef = ref(null)
const pendingCount = ref(0)

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const detailDialogVisible = ref(false)
const currentItem = ref(null)

const rejectDialogVisible = ref(false)
const rejectForm = reactive({ reason: '' })

const formatDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const getQueryParams = () => {
  const params = {
    page: pagination.page,
    limit: pagination.pageSize
  }

  if (filterForm.status) params.status = filterForm.status
  if (filterForm.violation_type) params.violation_type = filterForm.violation_type
  if (filterForm.severity) params.severity = filterForm.severity
  if (dateRange.value && dateRange.value.length === 2) {
    params.start_date = formatDate(dateRange.value[0])
    params.end_date = formatDate(dateRange.value[1])
  }

  return params
}

const loadList = async () => {
  loading.value = true
  try {
    const params = getQueryParams()
    const res = await getViolationHistory(params)
    const data = res || {}
    violationList.value = data.list || data.data || []
    pagination.total = data.total || 0
    pendingCount.value = (data.list || data.data || []).filter(i => i.status === 'PENDING').length
  } catch (error) {
    console.error('加载审核列表失败:', error)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

const handleFilterChange = () => {
  pagination.page = 1
  loadList()
}

const handleSearch = () => {
  pagination.page = 1
  loadList()
}

const handleReset = () => {
  filterForm.status = ''
  filterForm.violation_type = ''
  filterForm.severity = ''
  dateRange.value = [
    new Date(new Date().getTime() - 7 * 24 * 3600 * 1000),
    new Date()
  ]
  pagination.page = 1
  loadList()
}

const handleRefresh = () => {
  loadList()
  ElMessage.success('刷新成功')
}

const handleSizeChange = (val) => {
  pagination.pageSize = val
  pagination.page = 1
  loadList()
}

const handlePageChange = (val) => {
  pagination.page = val
  loadList()
}

const handleApprove = async (row) => {
  try {
    await ElMessageBox.confirm('确定通过该内容审核吗？', '审核确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'success'
    })
    await reviewViolation(row.id, { confirmed: true, comment: '审核通过' })
    ElMessage.success('审核通过')
    loadList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('审核失败:', error)
      ElMessage.error('操作失败')
    }
  }
}

const handleReject = (row) => {
  currentItem.value = row
  rejectForm.reason = ''
  rejectDialogVisible.value = true
}

const handleConfirmReject = async () => {
  if (!rejectForm.reason.trim()) {
    ElMessage.warning('请输入拒绝原因')
    return
  }
  try {
    await reviewViolation(currentItem.value.id, {
      confirmed: false,
      comment: rejectForm.reason
    })
    ElMessage.success('已拒绝')
    rejectDialogVisible.value = false
    loadList()
  } catch (error) {
    console.error('拒绝失败:', error)
    ElMessage.error('操作失败')
  }
}

const handleView = (row) => {
  currentItem.value = row
  detailDialogVisible.value = true
}

const handleApproveFromDialog = async () => {
  const row = currentItem.value
  detailDialogVisible.value = false
  await handleApprove(row)
}

const handleRejectFromDialog = () => {
  detailDialogVisible.value = false
  handleReject(currentItem.value)
}

const getViolationTypeText = (type) => {
  const map = {
    porn: '色情',
    violence: '暴力',
    illegal: '违法',
    ad: '广告',
    fraud: '欺诈',
    other: '其他'
  }
  return map[type] || type || '未知'
}

const getSeverityType = (severity) => {
  const map = { HIGH: 'danger', MEDIUM: 'warning', LOW: 'info' }
  return map[severity] || ''
}

const getSeverityText = (severity) => {
  const map = { HIGH: '严重', MEDIUM: '中等', LOW: '轻微' }
  return map[severity] || severity
}

const getSourceText = (source) => {
  const map = { AUTO: '系统', REPORT: '举报', MANUAL: '手动' }
  return map[source] || source || '系统'
}

const getStatusType = (status) => {
  const map = { PENDING: 'warning', CONFIRMED: 'success', DISMISSED: 'danger' }
  return map[status] || ''
}

const getStatusText = (status) => {
  const map = { PENDING: '待审核', CONFIRMED: '已通过', DISMISSED: '已拒绝' }
  return map[status] || status
}

onMounted(() => {
  loadList()
})
</script>

<style lang="scss" scoped>
.audit-container {
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

  .audit-detail {
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

@media (max-width: 768px) {
  .audit-container {
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

<template>
  <div class="tasks-container">
    <!-- 顶部操作栏 -->
    <div class="operation-bar">
      <div class="left-section">
        <span class="section-title">任务中心</span>
      </div>
      <div class="right-actions">
        <el-button icon="Refresh" @click="handleRefresh">刷新</el-button>
      </div>
    </div>

    <!-- 状态筛选 -->
    <div class="status-filter">
      <el-radio-group v-model="filterStatus" @change="handleStatusChange">
        <el-radio-button label="">全部</el-radio-button>
        <el-radio-button label="PENDING">
          <el-badge value="3" :hidden="pendingCount === 0" type="info" />
          排队中
        </el-radio-button>
        <el-radio-button label="PROCESSING">
          <el-badge value="2" :hidden="processingCount === 0" type="primary" />
          处理中
        </el-radio-button>
        <el-radio-button label="SUCCESS">
          <el-badge :value="successCount" :hidden="successCount === 0" type="success" />
          成功
        </el-radio-button>
        <el-radio-button label="FAILED">
          <el-badge :value="failedCount" :hidden="failedCount === 0" type="danger" />
          失败
        </el-radio-button>
      </el-radio-group>
    </div>

    <!-- 任务列表 -->
    <div v-loading="loading" class="task-list">
      <el-table :data="taskList" border style="width: 100%">
        <el-table-column prop="id" label="任务ID" width="100" align="center" />
        <el-table-column prop="task_name" label="任务名称" min-width="180" show-overflow-tooltip />
        <el-table-column prop="task_type" label="任务类型" width="120" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ getTaskTypeName(row.task_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusName(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="progress" label="进度" width="200" align="center">
          <template #default="{ row }">
            <el-progress
              v-if="row.status === 'PROCESSING'"
              :percentage="row.progress || 0"
              :status="getProgressStatus(row.status)"
            />
            <span v-else class="progress-text">
              {{ row.status === 'SUCCESS' ? '100%' : (row.progress || 0) + '%' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="180" align="center" />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleDetail(row)">详情</el-button>
            <el-button
              v-if="row.status === 'FAILED'"
              link
              type="danger"
              @click="handleRetry(row)"
            >
              重试
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 空状态 -->
      <div v-if="!loading && taskList.length === 0" class="empty-state">
        <el-empty description="暂无任务数据" />
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="total > 0" class="pagination-container">
      <el-pagination
        v-model:current-page="queryParams.page"
        v-model:page-size="queryParams.limit"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSearch"
        @current-change="handleSearch"
      />
    </div>

    <!-- 任务详情抽屉 -->
    <el-drawer
      v-model="detailDrawerVisible"
      title="任务详情"
      size="500px"
      direction="rtl"
    >
      <div v-if="currentTask" class="task-detail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="任务ID">
            {{ currentTask.id }}
          </el-descriptions-item>
          <el-descriptions-item label="任务名称">
            {{ currentTask.task_name }}
          </el-descriptions-item>
          <el-descriptions-item label="任务类型">
            <el-tag size="small">{{ getTaskTypeName(currentTask.task_type) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentTask.status)" size="small">
              {{ getStatusName(currentTask.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="进度">
            {{ currentTask.progress || 0 }}%
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">
            {{ currentTask.create_time }}
          </el-descriptions-item>
          <el-descriptions-item label="完成时间">
            {{ currentTask.finish_time || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="失败原因" v-if="currentTask.error_msg">
            <span class="error-msg">{{ currentTask.error_msg }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <div v-if="currentTask.status === 'PROCESSING'" class="processing-hint">
          <el-alert type="info" :closable="false">
            任务处理中，请稍候...
          </el-alert>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getTasks, retryTask, getTaskDetail, cancelTask } from '@/api/tasks'
import { normalizeListPayload, normalizePagination } from '@/utils/responseHelper'


// 状态
const loading = ref(false)
const taskList = ref([])
const total = ref(0)
const filterStatus = ref('')
const detailDrawerVisible = ref(false)
const currentTask = ref(null)

const queryParams = reactive({
  page: 1,
  limit: 10,
  status: ''
})

// 各状态数量统计 - 从当前列表数据计算
const pendingCount = computed(() => taskList.value.filter(t => t.status === 'PENDING').length)
const processingCount = computed(() => taskList.value.filter(t => t.status === 'PROCESSING').length)
const successCount = computed(() => taskList.value.filter(t => t.status === 'SUCCESS').length)
const failedCount = computed(() => taskList.value.filter(t => t.status === 'FAILED').length)

// 任务类型映射
const taskTypeMap = {
  VIDEO_EDIT: '视频剪辑',
  AI_GENERATE: 'AI生成',
  BATCH_PUBLISH: '批量发布',
  VOICE_OVER: '视频配音',
  SUBTITLE: '字幕生成',
  VIDEO_COMPOSE: '视频合成',
  TOPIC_PUBLISH: '话题发布'
}

// 状态映射
const statusMap = {
  PENDING: '排队中',
  PROCESSING: '处理中',
  SUCCESS: '成功',
  FAILED: '失败'
}

// 获取任务类型名称
const getTaskTypeName = (type) => {
  return taskTypeMap[type] || type
}

// 获取状态名称
const getStatusName = (status) => {
  return statusMap[status] || status
}

// 获取状态标签类型
const getStatusType = (status) => {
  const typeMap = {
    PENDING: 'info',
    PROCESSING: 'primary',
    SUCCESS: 'success',
    FAILED: 'danger'
  }
  return typeMap[status] || ''
}

// 获取进度条状态
const getProgressStatus = (status) => {
  const progressStatusMap = {
    SUCCESS: 'success',
    FAILED: 'exception'
  }
  return progressStatusMap[status] || ''
}

// 获取任务列表
const getList = async () => {
  loading.value = true
  try {
    const params = {
      page: queryParams.page,
      limit: queryParams.limit,
      status: queryParams.status === 'SUCCESS' ? 'COMPLETED' : (queryParams.status || undefined)
    }
    const res = await getTasks(params)
    const { list, total: totalCount } = normalizePagination(res)
    // 映射后端 ContentTask 字段到前端期望的字段名
    taskList.value = list.map(item => {
      const status = (item.status || 'PENDING').toUpperCase()
      // 后端 COMPLETED 等同于前端 SUCCESS
      const normalizedStatus = status === 'COMPLETED' ? 'SUCCESS' : status
      // 根据 status 计算进度
      let progress = item.progress ?? 0
      if (progress === 0) {
        if (normalizedStatus === 'SUCCESS') progress = 100
        else if (normalizedStatus === 'PROCESSING') progress = 50
      }
      return {
        id: item.id,
        task_name: item.taskName || item.title || item.type || '-',
        task_type: item.taskType || item.type || 'OTHER',
        status: normalizedStatus,
        progress,
        create_time: item.createTime || item.create_time || '-',
        finish_time: item.completeTime || item.complete_time || item.finishTime || '',
        error_msg: item.errorMessage || item.error_message || item.errorMsg || ''
      }
    })
    total.value = totalCount
  } catch (error) {
    console.error('获取任务列表失败:', error)
    taskList.value = []
    total.value = 0
    ElMessage.error('获取任务列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  queryParams.page = 1
  getList()
}

// 刷新
const handleRefresh = () => {
  queryParams.page = 1
  getList()
}

// 状态筛选
const handleStatusChange = (val) => {
  queryParams.status = val
  handleSearch()
}

// 查看详情
const handleDetail = async (row) => {
  try {
    const res = await getTaskDetail(row.id)
    currentTask.value = res?.data || res || { ...row }
  } catch (error) {
    console.error('获取任务详情失败:', error)
    currentTask.value = null
    ElMessage.error('获取任务详情失败，请稍后重试')
  }
  detailDrawerVisible.value = true
}

// 重试
const handleRetry = async (row) => {
  try {
    await retryTask(row.id)
    ElMessage.success('任务已重新加入队列')
    getList()
  } catch (error) {
    console.error('重试任务失败:', error)
    ElMessage.error('重试失败')
  }
}

onMounted(() => {
  getList()
})
</script>

<style scoped lang="scss">
.tasks-container {
  padding: 20px;
  background-color: #fff;
  border-radius: 4px;
  min-height: calc(100vh - 120px);

  .operation-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .left-section {
      .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #303133;
      }
    }
  }

  .status-filter {
    margin-bottom: 20px;
    padding: 16px;
    background: #f5f7fa;
    border-radius: 8px;

    :deep(.el-radio-button__inner) {
      display: flex;
      align-items: center;
      gap: 4px;
    }
  }

  .task-list {
    min-height: 300px;
  }

  .progress-text {
    color: #909399;
    font-size: 13px;
  }

  .empty-state {
    padding: 60px 0;
  }

  .pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }

  .task-detail {
    padding: 0 20px;

    .error-msg {
      color: #F56C6C;
    }

    .processing-hint {
      margin-top: 20px;
    }
  }
}
</style>
<template>
  <div class="user-task-list">
    <!-- 筛选栏 -->
    <el-card class="search-card">
      <div class="search-bar">
        <el-select v-model="searchStatus" placeholder="任务状态" clearable style="width: 160px" @change="handleSearch">
          <el-option label="已创建" value="CREATED" />
          <el-option label="进行中" value="IN_PROGRESS" />
          <el-option label="已完成" value="COMPLETED" />
          <el-option label="已过期" value="EXPIRED" />
          <el-option label="已放弃" value="ABANDONED" />
        </el-select>
        <el-select v-model="searchBundleId" placeholder="任务包" clearable filterable style="width: 240px" @change="handleSearch">
          <el-option v-for="b in bundleOptions" :key="b.id" :label="b.bundleName" :value="b.id" />
        </el-select>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
      </div>
    </el-card>

    <!-- 列表 -->
    <el-card class="table-card">
      <el-table :data="instanceList" v-loading="loading" stripe>
        <el-table-column type="expand">
          <template #default="{ row }">
            <div class="progress-detail">
              <el-descriptions :column="3" border size="small" title="进度详情">
                <el-descriptions-item label="任务包">{{ row.bundleTitle || row.bundle_title || '-' }}</el-descriptions-item>
                <el-descriptions-item label="用户">{{ row.openid || row.openId || '-' }}</el-descriptions-item>
                <el-descriptions-item label="完成进度">
                  {{ completedCount(row) }} / {{ totalCount(row) }}
                </el-descriptions-item>
              </el-descriptions>
              <div class="progress-json">
                <div class="progress-title">progress 原始数据：</div>
                <pre>{{ formatProgress(row.progress) }}</pre>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="bundleTitle" label="任务包" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.bundleTitle || row.bundle_title || '-' }}</template>
        </el-table-column>
        <el-table-column prop="openid" label="用户 openid" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ row.openid || row.openId || '-' }}</template>
        </el-table-column>
        <el-table-column label="动作进度" width="110" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="completedCount(row) >= totalCount(row) ? 'success' : 'info'">
              {{ completedCount(row) }} / {{ totalCount(row) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="任务状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="奖励状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="rewardTagType(row.rewardStatus ?? row.reward_status)" size="small">
              {{ rewardStatusText(row.rewardStatus ?? row.reward_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间" width="170">
          <template #default="{ row }">{{ row.createTime || row.create_time || '-' }}</template>
        </el-table-column>
        <el-table-column label="过期时间" width="170">
          <template #default="{ row }">{{ row.expiredAt || row.expired_at || row.expire_time || '-' }}</template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getInstanceList, getBundleList } from '@/api/task'
import { normalizePagination } from '@/utils/responseHelper'

const loading = ref(false)
const instanceList = ref([])
const bundleOptions = ref([])
const searchStatus = ref('')
const searchBundleId = ref('')
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const statusMap = {
  CREATED: '已创建',
  IN_PROGRESS: '进行中',
  COMPLETED: '已完成',
  EXPIRED: '已过期',
  ABANDONED: '已放弃'
}
const statusTagMap = {
  CREATED: 'info',
  IN_PROGRESS: 'primary',
  COMPLETED: 'success',
  EXPIRED: 'info',
  ABANDONED: 'info'
}
const statusText = (s) => statusMap[s] || s || '-'
const statusTagType = (s) => statusTagMap[s] || 'info'

const rewardStatusText = (s) => {
  const map = { PENDING: '待发放', ISSUED: '已发放', FAILED: '发放失败', SKIPPED: '无奖励' }
  return map[s] ?? (s || '-')
}
const rewardTagType = (s) => {
  const map = { PENDING: 'warning', ISSUED: 'success', FAILED: 'danger', SKIPPED: 'info' }
  return map[s] || 'info'
}

const completedCount = (row) => {
  const c = row.completedCount ?? row.completed_count
  if (c !== undefined && c !== null) return c
  return countProgress(row, true)
}

const totalCount = (row) => {
  const t = row.totalCount ?? row.total_count
  if (t !== undefined && t !== null) return t
  return countProgress(row, false)
}

const countProgress = (row, completedOnly) => {
  const p = row.progress
  if (Array.isArray(p)) {
    return completedOnly ? p.filter(x => x && x.state === 'COMPLETED').length : p.length
  }
  if (p && typeof p === 'object') {
    const values = Object.values(p)
    return completedOnly ? values.filter(x => x && x.state === 'COMPLETED').length : values.length
  }
  return 0
}

const formatProgress = (progress) => {
  if (progress === undefined || progress === null || progress === '') return '（无进度数据）'
  if (typeof progress === 'string') {
    try {
      return JSON.stringify(JSON.parse(progress), null, 2)
    } catch {
      return progress
    }
  }
  return JSON.stringify(progress, null, 2)
}

const loadBundleOptions = async () => {
  try {
    const res = await getBundleList({ page: 1, limit: 100 })
    const { list } = normalizePagination(res)
    bundleOptions.value = list
  } catch (err) {
    console.error('获取任务包选项失败:', err)
    bundleOptions.value = []
  }
}

const loadList = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (searchStatus.value) params.status = searchStatus.value
    if (searchBundleId.value) params.bundle_id = searchBundleId.value
    const res = await getInstanceList(params)
    const { list, total } = normalizePagination(res)
    instanceList.value = list
    pagination.total = total
  } catch (err) {
    console.error('获取用户任务列表失败:', err)
    instanceList.value = []
    pagination.total = 0
    ElMessage.error('获取用户任务列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.page = 1
  loadList()
}

const handleReset = () => {
  searchStatus.value = ''
  searchBundleId.value = ''
  pagination.page = 1
  loadList()
}

onMounted(() => {
  loadBundleOptions()
  loadList()
})
</script>

<style scoped lang="scss">
.user-task-list {
  padding: 20px;

  .search-card {
    margin-bottom: 20px;

    .search-bar {
      display: flex;
      align-items: center;
      gap: 10px;
    }
  }

  .table-card {
    :deep(.el-pagination) {
      margin-top: 20px;
      justify-content: flex-end;
    }
  }

  .progress-detail {
    padding: 12px 20px;

    .progress-json {
      margin-top: 12px;

      .progress-title {
        font-size: 13px;
        color: #909399;
        margin-bottom: 6px;
      }

      pre {
        background: #f5f7fa;
        border-radius: 6px;
        padding: 12px;
        font-size: 12px;
        max-height: 300px;
        overflow: auto;
        margin: 0;
      }
    }
  }
}
</style>

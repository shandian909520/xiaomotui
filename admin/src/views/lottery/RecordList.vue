<template>
  <div class="lottery-record-list">
    <el-card class="search-card">
      <div class="search-bar">
        <el-select v-model="query.activityId" placeholder="活动" clearable filterable style="width: 220px" @change="handleSearch">
          <el-option v-for="a in activityOptions" :key="a.id" :label="a.name" :value="a.id" />
        </el-select>
        <el-date-picker
          v-model="dateRange"
          type="datetimerange"
          range-separator="至"
          start-placeholder="开始时间"
          end-placeholder="结束时间"
          value-format="YYYY-MM-DD HH:mm:ss"
          style="width: 360px"
        />
        <el-select v-model="query.status" placeholder="状态" clearable style="width: 140px" @change="handleSearch">
          <el-option label="未兑奖" value="PENDING" />
          <el-option label="已兑奖" value="CLAIMED" />
          <el-option label="已过期" value="EXPIRED" />
        </el-select>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
      </div>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" stripe border>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="createdAt" label="抽奖时间" width="170" align="center" />
        <el-table-column label="活动" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <el-tag type="primary">{{ row.activityName || ('#' + row.activityId) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="奖项" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.prizeName || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="设备" width="100" align="center">
          <template #default="{ row }">{{ row.deviceId ?? '-' }}</template>
        </el-table-column>
        <el-table-column label="用户哈希(脱敏)" width="180" align="center">
          <template #default="{ row }">
            <span class="hash">{{ mask(row.userHash) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="claimCode" label="兑奖码" width="140" align="center">
          <template #default="{ row }">{{ row.claimCode || '-' }}</template>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { lotteryAdminApi } from '@/api/index'
import { normalizePagination } from '@/utils/responseHelper'

const loading = ref(false)
const query = reactive({ activityId: null, status: '' })
const dateRange = ref([])
const pagination = reactive({ page: 1, limit: 10, total: 0 })
const tableData = ref([])
const activityOptions = ref([])

const statusText = (s) => ({ PENDING: '未兑奖', CLAIMED: '已兑奖', EXPIRED: '已过期', pending: '未兑奖', claimed: '已兑奖', expired: '已过期' }[s] || s || '-')
const statusTag = (s) => ({ PENDING: 'warning', CLAIMED: 'success', EXPIRED: 'info', pending: 'warning', claimed: 'success', expired: 'info' }[s] || 'info')

const mask = (hash) => {
  if (!hash) return '-'
  const s = String(hash)
  if (s.length <= 8) return s.slice(0, 4) + '****'
  return s.slice(0, 4) + '****' + s.slice(-4)
}

const loadData = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (query.activityId) params.activity_id = query.activityId
    if (query.status) params.status = query.status
    if (dateRange.value?.length === 2) {
      params.start_at = dateRange.value[0]
      params.end_at = dateRange.value[1]
    }
    const res = await lotteryAdminApi.records(params)
    const { list, total } = normalizePagination(res)
    tableData.value = list
    pagination.total = total
  } catch (err) {
    console.error('加载中奖记录失败:', err)
    tableData.value = []
    pagination.total = 0
    ElMessage.error('加载中奖记录失败')
  } finally {
    loading.value = false
  }
}

const loadActivityOptions = async () => {
  try {
    const res = await lotteryAdminApi.activityList({ page: 1, limit: 200 })
    const data = normalizePagination(res)
    activityOptions.value = data.list
  } catch (_) {
    activityOptions.value = []
  }
}

const handleSearch = () => { pagination.page = 1; loadData() }
const handleReset = () => {
  query.activityId = null; query.status = ''; dateRange.value = []
  pagination.page = 1; loadData()
}

onMounted(() => {
  loadActivityOptions()
  loadData()
})
</script>

<style lang="scss" scoped>
.lottery-record-list {
  padding: 20px;

  .search-card {
    margin-bottom: 16px;
    .search-bar {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    }
  }

  .table-card {
    :deep(.el-pagination) { margin-top: 16px; justify-content: flex-end; }
  }

  .hash { font-family: monospace; color: #606266; }
}
</style>

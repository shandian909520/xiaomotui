<template>
  <div class="operation-logs">
    <!-- 搜索筛选 -->
    <el-card shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="操作人">
          <el-input v-model="queryParams.username" placeholder="请输入操作人" clearable />
        </el-form-item>
        <el-form-item label="模块">
          <el-select v-model="queryParams.module" placeholder="全部" clearable>
            <el-option label="认证管理" value="认证管理" />
            <el-option label="设备管理" value="device" />
            <el-option label="NFC管理" value="nfc" />
            <el-option label="内容管理" value="content" />
            <el-option label="券码管理" value="coupon" />
            <el-option label="商户管理" value="商户管理" />
            <el-option label="系统管理" value="admin" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
          <el-button type="success" @click="handleExport">导出</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 数据表格 -->
    <el-card shadow="never" style="margin-top: 16px">
      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="username" label="操作人" width="100" show-overflow-tooltip />
        <el-table-column prop="module" label="模块" width="100" />
        <el-table-column prop="action" label="操作" width="80" />
        <el-table-column prop="description" label="描述" min-width="160" show-overflow-tooltip />
        <el-table-column prop="method" label="方法" width="70" align="center">
          <template #default="{ row }">
            <el-tag
              :type="methodTagType(row.method)"
              size="small"
            >{{ row.method }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="url" label="URL" min-width="180" show-overflow-tooltip />
        <el-table-column prop="ip" label="IP" width="130" />
        <el-table-column prop="create_time" label="时间" width="170" />
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="queryParams.page"
          v-model:page-size="queryParams.page_size"
          :total="total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchData"
          @current-change="fetchData"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { systemApi } from '@/api/system'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const dateRange = ref([])

const queryParams = reactive({
  page: 1,
  page_size: 20,
  username: '',
  module: '',
  start_date: '',
  end_date: ''
})

const methodTagType = (m) => {
  const map = { POST: 'success', PUT: 'warning', DELETE: 'danger' }
  return map[m] || 'info'
}

const handleDateChange = (val) => {
  queryParams.start_date = val ? val[0] : ''
  queryParams.end_date = val ? val[1] : ''
}

const handleSearch = () => {
  queryParams.page = 1
  fetchData()
}

const handleReset = () => {
  Object.assign(queryParams, {
    username: '', module: '',
    start_date: '', end_date: '', page: 1
  })
  dateRange.value = []
  fetchData()
}

const handleExport = async () => {
  try {
    const res = await systemApi.exportOperationLogs(queryParams)
    const blob = new Blob([res], { type: 'text/csv;charset=utf-8' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `operation_logs_${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    window.URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (e) {
    ElMessage.error('导出失败')
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await systemApi.getOperationLogs(queryParams)
    if (res.code === 200) {
      tableData.value = res.data?.list || []
      total.value = res.data?.total || 0
    }
  } catch (e) {
    ElMessage.error('获取操作日志失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchData())
</script>

<style scoped>
.operation-logs { padding: 20px; }
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
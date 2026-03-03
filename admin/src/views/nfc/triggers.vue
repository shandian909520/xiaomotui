<template>
  <div class="trigger-records">
    <!-- 搜索筛选 -->
    <el-card class="filter-card" shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="设备名称">
          <el-input v-model="queryParams.device_name" placeholder="请输入设备名称" clearable />
        </el-form-item>
        <el-form-item label="触发类型">
          <el-select v-model="queryParams.trigger_mode" placeholder="全部" clearable>
            <el-option label="视频展示" value="VIDEO" />
            <el-option label="优惠券" value="COUPON" />
            <el-option label="WiFi连接" value="WIFI" />
            <el-option label="联系方式" value="CONTACT" />
            <el-option label="菜单展示" value="MENU" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.success" placeholder="全部" clearable>
            <el-option label="成功" :value="1" />
            <el-option label="失败" :value="0" />
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
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 数据表格 -->
    <el-card shadow="never" style="margin-top: 16px">
      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="device_name" label="设备名称" min-width="120" show-overflow-tooltip />
        <el-table-column prop="trigger_mode" label="触发类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getModeTagType(row.trigger_mode)" size="small">
              {{ getModeText(row.trigger_mode) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="user_openid" label="用户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.user_openid || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="success" label="结果" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.success ? 'success' : 'danger'" size="small">
              {{ row.success ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="response_time" label="响应时间" width="100" align="center">
          <template #default="{ row }">
            {{ row.response_time ? row.response_time + 'ms' : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="client_ip" label="IP" width="130" />
        <el-table-column prop="create_time" label="触发时间" width="170" />
        <el-table-column label="操作" width="80" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="showDetail(row)">详情</el-button>
          </template>
        </el-table-column>
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

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" title="触发详情" width="500px">
      <el-descriptions :column="1" border v-if="currentRow">
        <el-descriptions-item label="设备名称">{{ currentRow.device_name }}</el-descriptions-item>
        <el-descriptions-item label="设备编码">{{ currentRow.device_code }}</el-descriptions-item>
        <el-descriptions-item label="触发类型">{{ getModeText(currentRow.trigger_mode) }}</el-descriptions-item>
        <el-descriptions-item label="响应类型">{{ currentRow.response_type }}</el-descriptions-item>
        <el-descriptions-item label="结果">
          <el-tag :type="currentRow.success ? 'success' : 'danger'" size="small">
            {{ currentRow.success ? '成功' : '失败' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="响应时间">{{ currentRow.response_time }}ms</el-descriptions-item>
        <el-descriptions-item label="用户OpenID">{{ currentRow.user_openid || '-' }}</el-descriptions-item>
        <el-descriptions-item label="客户端IP">{{ currentRow.client_ip }}</el-descriptions-item>
        <el-descriptions-item label="触发时间">{{ currentRow.create_time }}</el-descriptions-item>
        <el-descriptions-item label="错误信息" v-if="currentRow.error_message">
          <span style="color: #f56c6c">{{ currentRow.error_message }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { nfcTriggerApi } from '@/api/nfc'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const dateRange = ref([])
const detailVisible = ref(false)
const currentRow = ref(null)

const queryParams = reactive({
  page: 1,
  page_size: 20,
  device_name: '',
  trigger_mode: '',
  success: '',
  start_date: '',
  end_date: ''
})

const modeMap = {
  VIDEO: '视频展示',
  COUPON: '优惠券',
  WIFI: 'WiFi连接',
  CONTACT: '联系方式',
  MENU: '菜单展示'
}

const modeTagMap = {
  VIDEO: '',
  COUPON: 'success',
  WIFI: 'info',
  CONTACT: 'warning',
  MENU: 'danger'
}

const getModeText = (mode) => modeMap[mode] || mode || '未知'
const getModeTagType = (mode) => modeTagMap[mode] || ''

const handleDateChange = (val) => {
  if (val) {
    queryParams.start_date = val[0]
    queryParams.end_date = val[1]
  } else {
    queryParams.start_date = ''
    queryParams.end_date = ''
  }
}

const handleSearch = () => {
  queryParams.page = 1
  fetchData()
}

const handleReset = () => {
  queryParams.device_name = ''
  queryParams.trigger_mode = ''
  queryParams.success = ''
  queryParams.start_date = ''
  queryParams.end_date = ''
  dateRange.value = []
  queryParams.page = 1
  fetchData()
}

const showDetail = (row) => {
  currentRow.value = row
  detailVisible.value = true
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await nfcTriggerApi.getTriggerRecords(queryParams)
    if (res.code === 200) {
      tableData.value = res.data?.list || res.data?.data || []
      total.value = res.data?.total || 0
    }
  } catch (e) {
    ElMessage.error('获取触发记录失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchData())
</script>

<style scoped>
.trigger-records {
  padding: 20px;
}
.filter-card {
  margin-bottom: 0;
}
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>

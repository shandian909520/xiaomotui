<template>
  <div class="coupon-users">
    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">总领取</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-item">
            <div class="stat-value" style="color: #67c23a">{{ stats.used }}</div>
            <div class="stat-label">已使用</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-item">
            <div class="stat-value" style="color: #409eff">{{ stats.unused }}</div>
            <div class="stat-label">未使用</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-item">
            <div class="stat-value" style="color: #909399">{{ stats.expired }}</div>
            <div class="stat-label">已过期</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 搜索筛选 -->
    <el-card shadow="never" style="margin-top: 16px">
      <el-form :model="queryParams" inline>
        <el-form-item label="券码">
          <el-select v-model="queryParams.coupon_id" placeholder="选择券码" clearable>
            <el-option
              v-for="item in couponList"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="使用状态">
          <el-select v-model="queryParams.use_status" placeholder="全部" clearable>
            <el-option label="未使用" :value="0" />
            <el-option label="已使用" :value="1" />
            <el-option label="已过期" :value="2" />
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
        <el-table-column prop="user_nickname" label="用户昵称" min-width="100" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.user?.nickname || row.user_nickname || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="coupon_name" label="券码名称" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.coupon?.name || row.coupon_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="coupon_code" label="券码" width="140" />
        <el-table-column prop="received_source" label="领取来源" width="100">
          <template #default="{ row }">
            {{ getSourceText(row.received_source) }}
          </template>
        </el-table-column>
        <el-table-column prop="use_status" label="使用状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.use_status)" size="small">
              {{ getStatusText(row.use_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="领取时间" width="170" />
        <el-table-column prop="used_time" label="使用时间" width="170">
          <template #default="{ row }">
            {{ row.used_time || '-' }}
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { couponUserApi } from '@/api/system'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const dateRange = ref([])
const couponList = ref([])

const stats = reactive({
  total: 0,
  used: 0,
  unused: 0,
  expired: 0
})

const queryParams = reactive({
  page: 1,
  page_size: 20,
  coupon_id: '',
  use_status: '',
  start_date: '',
  end_date: ''
})

const statusMap = { 0: '未使用', 1: '已使用', 2: '已过期' }
const statusTypeMap = { 0: 'info', 1: 'success', 2: 'warning' }
const sourceMap = {
  nfc_device: 'NFC设备',
  promotion: '活动领取',
  gift: '赠送',
  sign_in: '签到',
  share: '分享'
}

const getStatusText = (s) => statusMap[s] || '未知'
const getStatusType = (s) => statusTypeMap[s] || ''
const getSourceText = (s) => sourceMap[s] || s || '其他'

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
    coupon_id: '', use_status: '',
    start_date: '', end_date: '', page: 1
  })
  dateRange.value = []
  fetchData()
}

const fetchCouponList = async () => {
  try {
    const res = await couponUserApi.getCouponList({ page: 1, page_size: 100 })
    if (res.code === 200) {
      couponList.value = res.data?.list || res.data?.data || []
    }
  } catch (e) { /* ignore */ }
}

const fetchData = async () => {
  loading.value = true
  try {
    if (!queryParams.coupon_id) {
      // 没有选择券码时，显示空列表提示
      tableData.value = []
      total.value = 0
      loading.value = false
      return
    }
    const res = await couponUserApi.getCouponUsage(queryParams.coupon_id, queryParams)
    if (res.code === 200) {
      const d = res.data || {}
      tableData.value = d.list || d.data || []
      total.value = d.total || 0
      stats.total = d.stats?.total || tableData.value.length
      stats.used = d.stats?.used || 0
      stats.unused = d.stats?.unused || 0
      stats.expired = d.stats?.expired || 0
    }
  } catch (e) {
    ElMessage.error('获取领取记录失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchCouponList()
})
</script>

<style scoped>
.coupon-users { padding: 20px; }
.stat-cards { margin-bottom: 0; }
.stat-item { text-align: center; padding: 10px 0; }
.stat-value { font-size: 28px; font-weight: 600; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
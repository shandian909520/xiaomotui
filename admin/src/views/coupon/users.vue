<template>
  <div class="coupon-users">
    <!-- 顶部统计条 -->
    <div class="stats-bar">
      <div class="stat-pill">
        <span class="num">{{ stats.total }}</span>
        <span class="lbl">总领取</span>
      </div>
      <div class="stat-pill ok">
        <span class="num">{{ stats.used }}</span>
        <span class="lbl">已使用</span>
      </div>
      <div class="stat-pill info">
        <span class="num">{{ stats.unused }}</span>
        <span class="lbl">未使用</span>
      </div>
      <div class="stat-pill warn">
        <span class="num">{{ stats.expired }}</span>
        <span class="lbl">已过期</span>
      </div>
    </div>

    <!-- 筛选 -->
    <el-card shadow="never" class="filter-card">
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

    <!-- 卡片列表 -->
    <div v-loading="loading" class="card-grid">
      <div
        v-for="row in tableData"
        :key="row.id"
        class="coupon-card"
        :class="['use-' + (row.use_status !== undefined ? row.use_status : '')]"
      >
        <div class="card-top">
          <div class="user-line">
            <div class="avatar">{{ (row.user?.nickname || row.user_nickname || '?').charAt(0) }}</div>
            <div class="user-info">
              <div class="user-name">{{ row.user?.nickname || row.user_nickname || '匿名用户' }}</div>
              <div class="coupon-label">{{ row.coupon?.name || row.coupon_name || '-' }}</div>
            </div>
          </div>
          <el-tag :type="getStatusType(row.use_status)" size="small" effect="dark">
            {{ getStatusText(row.use_status) }}
          </el-tag>
        </div>

        <div class="code-line">
          <span class="code-label">券码</span>
          <span class="code-val">{{ row.coupon_code || '-' }}</span>
        </div>

        <div class="meta-grid">
          <div class="meta-cell">
            <span class="cell-label">领取来源</span>
            <span class="cell-value">{{ getSourceText(row.received_source) }}</span>
          </div>
          <div class="meta-cell">
            <span class="cell-label">领取时间</span>
            <span class="cell-value">{{ formatTime(row.create_time) }}</span>
          </div>
          <div class="meta-cell full">
            <span class="cell-label">使用时间</span>
            <span class="cell-value">{{ formatTime(row.used_time) }}</span>
          </div>
        </div>
      </div>

      <el-empty v-if="!loading && tableData.length === 0" description="暂无领取记录" class="empty" />
    </div>

    <!-- 分页 -->
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

// bug B3 兜底: 是否已选具体券码(影响 stats 是否取数)
const noCouponSelected = computed(() => !queryParams.coupon_id)

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

const formatTime = (t) => {
  if (!t) return '-'
  const d = new Date(t)
  if (isNaN(d.getTime())) return t
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getMonth() + 1}/${d.getDate()} ${pad(d.getHours())}:${pad(d.getMinutes())}`
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
    coupon_id: '', use_status: '',
    start_date: '', end_date: '', page: 1
  })
  dateRange.value = []
  fetchData()
}

const fetchCouponList = async () => {
  try {
    const res = await couponUserApi.getCouponList({ page: 1, page_size: 100 })
    const list = res?.list || res?.data || []
    couponList.value = Array.isArray(list) ? list : []
  } catch (e) { /* ignore */ }
}

const fetchData = async () => {
  loading.value = true
  try {
    // bug B3: 未选具体 coupon_id 时清空明细 + stats, 让 UI 明确不适用
    // 真实"全量 stats"端点后端尚未提供(P1 留 TODO), 暂不做合并查询。
    if (!queryParams.coupon_id) {
      tableData.value = []
      total.value = 0
      stats.total = 0; stats.used = 0; stats.unused = 0; stats.expired = 0
      return
    }
    const res = await couponUserApi.getCouponUsage(queryParams.coupon_id, queryParams)
    const d = res || {}
    const list = d.list || d.data || []
    tableData.value = Array.isArray(list) ? list : []
    total.value = d.total || d.pagination?.total || 0
    stats.total = d.stats?.total || tableData.value.length
    stats.used = d.stats?.used || 0
    stats.unused = d.stats?.unused || 0
    stats.expired = d.stats?.expired || 0
  } catch (e) {
    ElMessage.error('获取领取记录失败')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchCouponList()
  // bug B3: 显式拉一次(默认无 coupon_id 时上面 fetchData 早退, 不会真发请求, 这里保留调用以兼容未来 controller)
  fetchData()
})
</script>

<style lang="scss" scoped>
.coupon-users {
  padding: 20px;
}

.stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;

  .stat-pill {
    background: #fff;
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: baseline;
    gap: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border-left: 4px solid #FF6B35;

    .num { font-size: 26px; font-weight: 700; color: #303133; }
    .lbl { font-size: 13px; color: #909399; }

    &.ok { border-left-color: #67c23a; .num { color: #67c23a; } }
    &.info { border-left-color: #409eff; .num { color: #409eff; } }
    &.warn { border-left-color: #e6a23c; .num { color: #e6a23c; } }
  }
}

.filter-card {
  margin-bottom: 16px;
  border-radius: 16px;
}

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
  min-height: 200px;
  margin-bottom: 16px;

  .empty { grid-column: 1 / -1; }
}

.coupon-card {
  background: #fff;
  border-radius: 16px;
  padding: 16px 18px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border-left: 4px solid #FF6B35;
  transition: all 0.2s;

  &:hover {
    box-shadow: 0 4px 16px rgba(255, 107, 53, 0.15);
    transform: translateY(-2px);
  }

  &.use-1 { border-left-color: #67c23a; }
  &.use-2 { border-left-color: #e6a23c; }
  &.use-0 { border-left-color: #409eff; }

  .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }

  .user-line {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;

    .avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #FF6B35, #FF8E53);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .user-info {
      flex: 1;
      min-width: 0;

      .user-name {
        font-size: 14px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .coupon-label {
        font-size: 12px;
        color: #909399;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }
  }

  .code-line {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: linear-gradient(90deg, rgba(255, 107, 53, 0.06), rgba(255, 107, 53, 0.02));
    border-radius: 8px;
    margin-bottom: 12px;

    .code-label { font-size: 11px; color: #c0c4cc; }
    .code-val {
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      color: #FF6B35;
      font-weight: 600;
      letter-spacing: 0.5px;
    }
  }

  .meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 16px;

    .meta-cell {
      .cell-label {
        font-size: 11px;
        color: #c0c4cc;
        display: block;
        margin-bottom: 2px;
      }
      .cell-value {
        font-size: 13px;
        color: #303133;
        font-weight: 500;
      }
      &.full { grid-column: span 2; }
    }
  }
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
}

:deep(.el-button--primary) {
  background: linear-gradient(135deg, #FF6B35, #FF8E53);
  border-color: #FF6B35;
}
</style>

<template>
  <div class="trigger-records">
    <!-- 顶部统计条 -->
    <div class="stats-bar">
      <div class="stat-pill"><span class="num">{{ total }}</span><span class="lbl">总触发</span></div>
      <div class="stat-pill ok"><span class="num">{{ successCount }}</span><span class="lbl">成功</span></div>
      <div class="stat-pill err"><span class="num">{{ failCount }}</span><span class="lbl">失败</span></div>
    </div>

    <!-- 筛选条件（折叠卡片） -->
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

    <!-- 卡片瀑布流 -->
    <div v-loading="loading" class="card-grid">
      <div
        v-for="row in tableData"
        :key="row.id || (row.device_code + row.create_time)"
        class="trigger-card"
        :class="['mode-' + (row.trigger_mode || '').toLowerCase(), row.success ? 'is-success' : 'is-fail']"
        @click="showDetail(row)"
      >
        <div class="card-head">
          <span class="mode-chip" :class="['chip-' + (row.trigger_mode || '').toLowerCase()]">
            {{ getModeText(row.trigger_mode) }}
          </span>
          <span class="result-tag" :class="row.success ? 'ok' : 'err'">
            <el-icon><component :is="row.success ? 'CircleCheckFilled' : 'CircleCloseFilled'" /></el-icon>
            {{ row.success ? '成功' : '失败' }}
          </span>
        </div>
        <div class="device-line">
          <el-icon><Monitor /></el-icon>
          <span class="device-name" :title="row.device_name">{{ row.device_name || '-' }}</span>
          <!-- bug B4: 触发的 block 标签(数据来源 trigger_mode) -->
          <span class="block-tag" :title="`触发的区块: ${row.trigger_mode}`">
            {{ getBlockTag(row.trigger_mode) }}
          </span>
        </div>
        <div class="device-sub">
          <span class="code">{{ row.device_code || '-' }}</span>
          <span class="time">{{ formatTime(row.create_time) }}</span>
        </div>
        <div class="meta-row">
          <div class="meta-cell">
            <span class="cell-label">响应</span>
            <span class="cell-value">{{ row.response_time ? row.response_time + 'ms' : '-' }}</span>
          </div>
          <div class="meta-cell">
            <span class="cell-label">IP</span>
            <span class="cell-value mono">{{ row.client_ip || '-' }}</span>
          </div>
        </div>
        <!-- bug B4: 用户 hash(脱敏) + 已发放奖励 -->
        <div class="reward-line">
          <span class="reward-label">用户</span>
          <span class="reward-user mono" :title="row.user_openid || row.user_hash || '-'">
            {{ maskUserHash(row.user_openid || row.user_hash) }}
          </span>
          <span class="reward-label">奖励</span>
          <el-tag
            :type="getRewardType(row)"
            size="small"
            effect="plain"
          >
            {{ getRewardText(row) }}
          </el-tag>
        </div>
      </div>

      <el-empty v-if="!loading && tableData.length === 0" description="暂无触发记录" class="empty" />
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
import { ref, reactive, onMounted, computed } from 'vue'
import { Monitor, User, CircleCheckFilled, CircleCloseFilled } from '@element-plus/icons-vue'
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

const getModeText = (mode) => modeMap[mode] || mode || '未知'

// bug B4: block 标签 - 触发的区域(数据归一: trigger_mode → 中文 + 业务标签)
const blockTagMap = {
  VIDEO:   { tag: '视频块',   color: '#FF6B35' },
  COUPON:  { tag: '券块',     color: '#ff9860' },
  WIFI:    { tag: 'Wi-Fi块',  color: '#409eff' },
  CONTACT: { tag: '私域块',   color: '#e6a23c' },
  MENU:    { tag: '菜单块',   color: '#909399' }
}
const getBlockTag = (mode) => (blockTagMap[mode] && blockTagMap[mode].tag) || '其它块'

// bug B4: 用户 hash 脱敏显示(只显示前 6 + 后 4)
const maskUserHash = (s) => {
  if (!s) return '匿名'
  const str = String(s)
  if (str.length <= 10) return str.slice(0, 6) + '***'
  return str.slice(0, 6) + '***' + str.slice(-4)
}

// bug B4: 已发放奖励 - 从 response_data 提取
const getRewardText = (row) => {
  if (Number(row.success) !== 1) return '未触发'
  const rd = row.response_data
  if (rd && typeof rd === 'object') {
    if (rd.coupon_code) return '券码 ' + rd.coupon_code
    if (rd.prize_name) return rd.prize_name
    if (rd.reward_text) return rd.reward_text
    if (rd.points) return '+' + rd.points + ' 积分'
  }
  return '已发放'
}
const getRewardType = (row) => {
  if (Number(row.success) !== 1) return 'info'
  const rd = row.response_data
  if (rd && typeof rd === 'object' && (rd.coupon_code || rd.prize_name)) return 'success'
  return 'warning'
}

const successCount = computed(() => tableData.value.filter((r) => r.success).length)
const failCount = computed(() => tableData.value.length - successCount.value)

const formatTime = (t) => {
  if (!t) return '-'
  const d = new Date(t)
  if (isNaN(d.getTime())) return t
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getMonth() + 1}/${d.getDate()} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

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
    const list = res?.list || res?.data || []
    tableData.value = Array.isArray(list) ? list : []
    total.value = res?.total || res?.pagination?.total || 0
  } catch (e) {
    ElMessage.error('获取触发记录失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchData())
</script>

<style lang="scss" scoped>
.trigger-records {
  padding: 20px;
}

.stats-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;

  .stat-pill {
    flex: 1;
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: baseline;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);

    .num {
      font-size: 24px;
      font-weight: 700;
      color: #303133;
    }
    .lbl { font-size: 13px; color: #909399; }
    &.ok .num { color: #67c23a; }
    &.err .num { color: #f56c6c; }
  }
}

.filter-card {
  margin-bottom: 16px;
  border-radius: 12px;
}

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
  min-height: 200px;
  margin-bottom: 16px;

  .empty {
    grid-column: 1 / -1;
  }
}

.trigger-card {
  background: #fff;
  border-radius: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border-top: 3px solid #FF6B35;

  &:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }

  &.mode-wifi { border-top-color: #409eff; }
  &.mode-coupon { border-top-color: #ff9860; }
  &.mode-contact { border-top-color: #e6a23c; }
  &.mode-menu { border-top-color: #909399; }
  &.mode-video { border-top-color: #FF6B35; }
  &.is-fail { border-top-color: #f56c6c; }

  .card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }

  .mode-chip {
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 12px;
    background: rgba(255, 107, 53, 0.1);
    color: #FF6B35;
    font-weight: 500;
    &.chip-wifi { background: rgba(64, 158, 255, 0.1); color: #409eff; }
    &.chip-coupon { background: rgba(255, 152, 96, 0.1); color: #ff9860; }
    &.chip-contact { background: rgba(230, 162, 60, 0.1); color: #e6a23c; }
    &.chip-menu { background: rgba(144, 147, 153, 0.1); color: #909399; }
  }

  .result-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: 500;
    &.ok { background: rgba(103, 194, 58, 0.1); color: #67c23a; }
    &.err { background: rgba(245, 108, 108, 0.1); color: #f56c6c; }
  }

  .device-line {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;

    .device-name {
      font-size: 14px;
      font-weight: 600;
      color: #303133;
      max-width: 140px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    // bug B4: block 标签样式
    .block-tag {
      margin-left: 6px;
      padding: 2px 8px;
      border-radius: 4px;
      background: rgba(255, 107, 53, 0.1);
      color: #FF6B35;
      font-size: 11px;
      font-weight: 500;
      line-height: 1.4;
    }
  }

  // bug B4: 奖励/用户 hash 行
  .reward-line {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #f0f0f0;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #909399;
    flex-wrap: wrap;

    .reward-label {
      color: #c0c4cc;
    }
    .reward-user {
      max-width: 120px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: #606266;
    }
  }

  .device-sub {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #909399;
    margin-bottom: 10px;
    .code { font-family: 'JetBrains Mono', monospace; }
  }

  .meta-row {
    display: flex;
    gap: 16px;
    padding: 8px 0;
    border-top: 1px dashed #f0f0f0;

    .meta-cell {
      flex: 1;
      .cell-label { font-size: 11px; color: #c0c4cc; display: block; }
      .cell-value {
        font-size: 13px;
        color: #303133;
        font-weight: 500;
        &.mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
      }
    }
  }

  .user-line {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #f0f0f0;
    font-size: 11px;
    color: #909399;
    display: flex;
    align-items: center;
    gap: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    span { font-family: 'JetBrains Mono', monospace; }
  }
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
}
</style>

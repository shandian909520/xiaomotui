<template>
  <div class="employee-management">
    <!-- 顶部标题 -->
    <div class="page-header">
      <div class="header-content">
        <h2>员工管理</h2>
        <p class="subtitle">连锁版员工数据统计与排行榜</p>
      </div>
    </div>

    <!-- 时间筛选 -->
    <el-card class="filter-card">
      <div class="filter-row">
        <el-radio-group v-model="timePeriod" @change="handleTimeChange">
          <el-radio-button label="今天" value="today" />
          <el-radio-button label="本周" value="week" />
          <el-radio-button label="本月" value="month" />
          <el-radio-button label="本季度" value="quarter" />
          <el-radio-button label="近半年" value="halfYear" />
          <el-radio-button label="今年" value="year" />
          <el-radio-button label="自定义" value="custom" />
        </el-radio-group>
        <el-date-picker
          v-if="timePeriod === 'custom'"
          v-model="customDateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 280px; margin-left: 12px"
          @change="handleSearch"
        />
      </div>
    </el-card>

    <!-- 主内容区 -->
    <div class="main-content">
      <div class="content-left">
        <el-card>
          <el-tabs v-model="activeTab" @tab-change="handleTabChange">
            <el-tab-pane label="员工维度" name="employee">
              <el-table :data="employeeStats" v-loading="loading" stripe>
                <el-table-column prop="employee_name" label="员工姓名" min-width="120" />
                <el-table-column prop="employee_id" label="员工ID" width="100" />
                <el-table-column prop="total_completed" label="总完成数" width="100" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_completed) }}</template>
                </el-table-column>
                <el-table-column prop="total_exposure" label="总曝光" width="120" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_exposure) }}</template>
                </el-table-column>
                <el-table-column prop="total_likes" label="总点赞" width="100" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_likes) }}</template>
                </el-table-column>
                <el-table-column prop="total_published" label="总发布" width="100" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_published) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right" align="center">
                  <template #default="{ row }">
                    <el-button size="small" type="primary" link @click="handleViewPublishDetail(row)">发布明细</el-button>
                  </template>
                </el-table-column>
              </el-table>
            </el-tab-pane>

            <el-tab-pane label="门店维度" name="store">
              <el-table :data="storeStats" v-loading="loading" stripe>
                <el-table-column prop="store_name" label="门店名称" min-width="140" />
                <el-table-column prop="store_id" label="门店ID" width="100" />
                <el-table-column prop="employee_count" label="员工数" width="80" align="center" />
                <el-table-column prop="total_completed" label="总完成数" width="100" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_completed) }}</template>
                </el-table-column>
                <el-table-column prop="total_exposure" label="总曝光" width="120" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_exposure) }}</template>
                </el-table-column>
                <el-table-column prop="total_likes" label="总点赞" width="100" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_likes) }}</template>
                </el-table-column>
                <el-table-column prop="total_published" label="总发布" width="100" align="center">
                  <template #default="{ row }">{{ formatNumber(row.total_published) }}</template>
                </el-table-column>
              </el-table>
            </el-tab-pane>

            <el-tab-pane label="任务维度" name="task">
              <div class="task-cards" v-loading="loading">
                <div v-for="task in taskStats" :key="task.task_type" class="task-card">
                  <div class="task-header">
                    <span class="task-type">{{ task.task_type_name }}</span>
                  </div>
                  <div class="task-metrics">
                    <div class="metric">
                      <div class="metric-label">目标数</div>
                      <div class="metric-value">{{ formatNumber(task.target) }}</div>
                    </div>
                    <div class="metric">
                      <div class="metric-label">已完成</div>
                      <div class="metric-value success">{{ formatNumber(task.completed) }}</div>
                    </div>
                    <div class="metric">
                      <div class="metric-label">曝光量</div>
                      <div class="metric-value primary">{{ formatNumber(task.exposure) }}</div>
                    </div>
                    <div class="metric">
                      <div class="metric-label">点赞数</div>
                      <div class="metric-value warning">{{ formatNumber(task.likes) }}</div>
                    </div>
                    <div class="metric">
                      <div class="metric-label">发布数</div>
                      <div class="metric-value danger">{{ formatNumber(task.published) }}</div>
                    </div>
                  </div>
                  <el-progress
                    :percentage="task.target ? Math.min(Math.round((task.completed / task.target) * 100), 100) : 0"
                    :stroke-width="8"
                    style="margin-top: 12px"
                  />
                </div>
              </div>
              <el-empty v-if="!loading && taskStats.length === 0" description="暂无任务统计数据" />
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </div>

      <!-- 排行榜面板 -->
      <div class="content-right">
        <el-card>
          <template #header>
            <span class="ranking-title">排行榜</span>
          </template>
          <el-radio-group v-model="rankingType" size="small" @change="loadRankings" style="margin-bottom: 16px">
            <el-radio-button label="高产创作者" value="high_yield" />
            <el-radio-button label="高互动创作者" value="high_interact" />
            <el-radio-button label="未发布员工" value="unpublished" />
            <el-radio-button label="发布排行榜" value="publish_rank" />
          </el-radio-group>
          <div v-loading="rankingLoading" class="ranking-list">
            <div v-for="(item, index) in rankingList" :key="index" class="ranking-item">
              <span class="ranking-index" :class="{ top: index < 3 }">{{ index + 1 }}</span>
              <span class="ranking-name">{{ item.name }}</span>
              <span class="ranking-value">{{ formatNumber(item.value) }}</span>
            </div>
            <el-empty v-if="!rankingLoading && rankingList.length === 0" description="暂无排行数据" :image-size="60" />
          </div>
        </el-card>
      </div>
    </div>

    <!-- 发布明细弹窗 -->
    <el-dialog v-model="publishDetailVisible" title="发布明细" width="800px">
      <div class="detail-filter">
        <el-input v-model="publishSearchName" placeholder="按姓名搜索" clearable style="width: 200px" @input="loadPublishDetails" />
      </div>
      <el-table :data="publishDetails" v-loading="publishLoading" stripe max-height="400">
        <el-table-column prop="employee_name" label="员工" width="100" />
        <el-table-column prop="task_type_name" label="任务类型" width="120" />
        <el-table-column prop="content_title" label="内容标题" min-width="180" show-overflow-tooltip />
        <el-table-column prop="exposure" label="曝光量" width="100" align="center">
          <template #default="{ row }">{{ formatNumber(row.exposure) }}</template>
        </el-table-column>
        <el-table-column prop="likes" label="点赞数" width="90" align="center">
          <template #default="{ row }">{{ formatNumber(row.likes) }}</template>
        </el-table-column>
        <el-table-column prop="published_at" label="发布时间" width="160" />
      </el-table>
      <template #footer>
        <el-button @click="publishDetailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getEmployeeStatsByEmployee,
  getEmployeeStatsByStore,
  getEmployeeStatsByTask,
  getEmployeeRankings,
  getEmployeePublishDetails
} from '@/api/index'
import { normalizeListPayload } from '@/utils/responseHelper'

const loading = ref(false)
const rankingLoading = ref(false)
const publishLoading = ref(false)
const activeTab = ref('employee')
const timePeriod = ref('month')
const customDateRange = ref(null)
const rankingType = ref('high_yield')

const employeeStats = ref([])
const storeStats = ref([])
const taskStats = ref([])
const rankingList = ref([])

const publishDetailVisible = ref(false)
const publishSearchName = ref('')
const publishDetails = ref([])
const currentEmployeeId = ref(null)


const getTimeParams = () => {
  const params = {}
  if (timePeriod.value === 'custom' && customDateRange.value?.length === 2) {
    params.startDate = customDateRange.value[0]
    params.endDate = customDateRange.value[1]
  } else if (timePeriod.value !== 'custom') {
    params.period = timePeriod.value
  }
  return params
}

const formatNumber = (num) => {
  if (!num) return '0'
  if (num >= 10000) return (num / 10000).toFixed(1) + '万'
  return num.toLocaleString()
}

const handleTimeChange = () => {
  if (timePeriod.value !== 'custom') handleSearch()
}

const handleSearch = () => {
  handleTabChange(activeTab.value)
  loadRankings()
}

const handleTabChange = (tab) => {
  if (tab === 'employee') loadEmployeeStats()
  else if (tab === 'store') loadStoreStats()
  else if (tab === 'task') loadTaskStats()
}

const loadEmployeeStats = async () => {
  loading.value = true
  try {
    const res = await getEmployeeStatsByEmployee(getTimeParams())
    employeeStats.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取员工统计失败:', err)
    employeeStats.value = []
    ElMessage.error('获取员工统计失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const loadStoreStats = async () => {
  loading.value = true
  try {
    const res = await getEmployeeStatsByStore(getTimeParams())
    storeStats.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取门店统计失败:', err)
    storeStats.value = []
    ElMessage.error('获取门店统计失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const loadTaskStats = async () => {
  loading.value = true
  try {
    const res = await getEmployeeStatsByTask(getTimeParams())
    taskStats.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取任务统计失败:', err)
    taskStats.value = []
    ElMessage.error('获取任务统计失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const loadRankings = async () => {
  rankingLoading.value = true
  try {
    const params = { ...getTimeParams(), type: rankingType.value }
    const res = await getEmployeeRankings(params)
    rankingList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取排行榜失败:', err)
    rankingList.value = []
    ElMessage.error('获取排行榜失败，请稍后重试')
  } finally {
    rankingLoading.value = false
  }
}

const handleViewPublishDetail = (row) => {
  currentEmployeeId.value = row.employee_id
  publishSearchName.value = row.employee_name
  publishDetailVisible.value = true
  loadPublishDetails()
}

const loadPublishDetails = async () => {
  publishLoading.value = true
  try {
    const params = { ...getTimeParams() }
    if (currentEmployeeId.value) params.employee_id = currentEmployeeId.value
    if (publishSearchName.value) params.name = publishSearchName.value
    const res = await getEmployeePublishDetails(params)
    publishDetails.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取发布明细失败:', err)
    publishDetails.value = []
    ElMessage.error('获取发布明细失败，请稍后重试')
  } finally {
    publishLoading.value = false
  }
}

onMounted(() => {
  loadEmployeeStats()
  loadRankings()
})
</script>

<style scoped lang="scss">
.employee-management {
  padding: 20px;

  .page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 28px 32px;
    margin-bottom: 20px;
    color: #fff;

    h2 {
      font-size: 28px;
      font-weight: 700;
      margin: 0 0 6px;
    }

    .subtitle {
      font-size: 14px;
      opacity: 0.85;
      margin: 0;
    }
  }

  .filter-card {
    margin-bottom: 20px;

    .filter-row {
      display: flex;
      align-items: center;
    }
  }

  .main-content {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 20px;
  }

  .task-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    padding: 8px 0;
  }

  .task-card {
    background: #fafafa;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;

    .task-header {
      margin-bottom: 12px;

      .task-type {
        font-size: 15px;
        font-weight: 600;
        color: #303133;
      }
    }

    .task-metrics {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 8px;
      text-align: center;
    }

    .metric {
      .metric-label {
        font-size: 12px;
        color: #909399;
        margin-bottom: 4px;
      }

      .metric-value {
        font-size: 16px;
        font-weight: 600;
        color: #303133;

        &.success { color: #67c23a; }
        &.primary { color: #409eff; }
        &.warning { color: #e6a23c; }
        &.danger { color: #f56c6c; }
      }
    }
  }

  .ranking-title {
    font-size: 16px;
    font-weight: 600;
  }

  .ranking-list {
    min-height: 200px;
  }

  .ranking-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;

    &:last-child {
      border-bottom: none;
    }

    .ranking-index {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      background: #f0f0f0;
      color: #909399;
      margin-right: 12px;
      flex-shrink: 0;

      &.top {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
      }
    }

    .ranking-name {
      flex: 1;
      font-size: 14px;
      color: #303133;
    }

    .ranking-value {
      font-size: 14px;
      font-weight: 600;
      color: #7b50ff;
    }
  }

  .detail-filter {
    margin-bottom: 16px;
  }
}

@media (max-width: 1200px) {
  .employee-management .main-content {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .employee-management {
    padding: 12px;

    .task-cards {
      grid-template-columns: 1fr;
    }
  }
}
</style>

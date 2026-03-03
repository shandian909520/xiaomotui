<template>
  <div class="statistics-container">
    <!-- 页面标题栏 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">数据统计</h1>
        <el-tag v-if="autoRefresh" type="success" effect="plain">
          <el-icon><Clock /></el-icon>
          自动刷新中
        </el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Download" @click="handleExport">导出报表</el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :shortcuts="dateShortcuts"
            @change="handleDateChange"
          />
        </el-form-item>

        <el-form-item label="商家筛选" v-if="isAdmin">
          <el-select
            v-model="filterForm.merchant_id"
            placeholder="全部商家"
            clearable
            @change="handleFilterChange"
          >
            <el-option
              v-for="merchant in merchantList"
              :key="merchant.id"
              :label="merchant.name"
              :value="merchant.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleRefresh">刷新</el-button>
          <el-switch
            v-model="autoRefresh"
            active-text="自动刷新"
            @change="handleAutoRefreshChange"
          />
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 核心指标卡片 -->
    <el-row :gutter="20" class="metrics-row">
      <el-col :xs="24" :sm="12" :md="6" v-for="metric in metrics" :key="metric.key">
        <stat-card
          :title="metric.title"
          :value="metric.value"
          :icon="metric.icon"
          :icon-color="metric.color"
          :trend="metric.trend"
          :trend-percent="metric.trendPercent"
          :description="metric.description"
          :unit="metric.unit"
        />
      </el-col>
    </el-row>

    <!-- 图表区域 -->
    <el-row :gutter="20" class="charts-row">
      <!-- 触发量趋势图 -->
      <el-col :xs="24" :lg="12">
        <chart-container
          title="触发量趋势"
          :icon="TrendCharts"
          :loading="loading.trend"
          :empty="isEmpty.trend"
          @refresh="loadTrendData"
          @download="downloadChart('trend')"
        >
          <div ref="trendChartRef" class="chart"></div>
        </chart-container>
      </el-col>

      <!-- 转化率饼图 -->
      <el-col :xs="24" :lg="12">
        <chart-container
          title="转化率分布"
          :icon="PieChart"
          :loading="loading.conversion"
          :empty="isEmpty.conversion"
          @refresh="loadConversionData"
          @download="downloadChart('conversion')"
        >
          <div ref="conversionChartRef" class="chart"></div>
        </chart-container>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="charts-row">
      <!-- 设备统计柱状图 -->
      <el-col :xs="24" :lg="12">
        <chart-container
          title="设备触发排行"
          :icon="Monitor"
          :loading="loading.device"
          :empty="isEmpty.device"
          @refresh="loadDeviceData"
          @download="downloadChart('device')"
        >
          <div ref="deviceChartRef" class="chart"></div>
        </chart-container>
      </el-col>

      <!-- 用户活跃度时段分布 -->
      <el-col :xs="24" :lg="12">
        <chart-container
          title="用户活跃度"
          :icon="User"
          :loading="loading.userBehavior"
          :empty="isEmpty.userBehavior"
          @refresh="loadUserBehaviorData"
          @download="downloadChart('userBehavior')"
        >
          <div ref="userBehaviorChartRef" class="chart"></div>
        </chart-container>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="charts-row">
      <!-- 转化漏斗图 -->
      <el-col :xs="24">
        <chart-container
          title="转化漏斗"
          :icon="DataAnalysis"
          :loading="loading.funnel"
          :empty="isEmpty.funnel"
          @refresh="loadFunnelData"
          @download="downloadChart('funnel')"
        >
          <div ref="funnelChartRef" class="chart"></div>
          <template #footer>
            <div class="funnel-stats">
              <span>总体转化率: <strong>{{ funnelConversionRate }}%</strong></span>
              <span class="divider">|</span>
              <span>最高流失环节: <strong>{{ maxLossStage }}</strong></span>
            </div>
          </template>
        </chart-container>
      </el-col>
    </el-row>

    <!-- 营销洞察和预警 -->
    <el-row :gutter="20" class="insights-row">
      <el-col :xs="24" :lg="12">
        <el-card class="insight-card" shadow="hover">
          <template #header>
            <div class="card-header">
              <el-icon color="#E6A23C"><Warning /></el-icon>
              <span>数据预警</span>
            </div>
          </template>
          <el-empty v-if="alerts.length === 0" description="暂无预警信息" />
          <div v-else class="alert-list">
            <el-alert
              v-for="alert in alerts"
              :key="alert.id"
              :title="alert.title"
              :type="alert.type"
              :description="alert.description"
              show-icon
              :closable="false"
              class="alert-item"
            />
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :lg="12">
        <el-card class="insight-card" shadow="hover">
          <template #header>
            <div class="card-header">
              <el-icon color="#67C23A"><Lightbulb /></el-icon>
              <span>营销建议</span>
            </div>
          </template>
          <el-empty v-if="insights.length === 0" description="暂无营销建议" />
          <div v-else class="insight-list">
            <div
              v-for="(insight, index) in insights"
              :key="index"
              class="insight-item"
            >
              <div class="insight-icon">{{ index + 1 }}</div>
              <div class="insight-content">
                <div class="insight-title">{{ insight.title }}</div>
                <div class="insight-desc">{{ insight.description }}</div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Search,
  Refresh,
  Download,
  Clock,
  TrendCharts,
  PieChart,
  Monitor,
  User,
  DataAnalysis,
  Warning,
  Lamp,
  DataLine,
  Edit,
  Share,
  CircleCheck
} from '@element-plus/icons-vue'
import StatCard from '@/components/StatCard.vue'
import ChartContainer from '@/components/ChartContainer.vue'
import { useEcharts, getLineChartOption, getPieChartOption, getBarChartOption, getAreaChartOption, getFunnelChartOption } from '@/composables/useEcharts'
import {
  getOverview,
  getTrend,
  getDeviceStats,
  getConversionStats,
  getUserBehavior,
  exportReport,
  getMarketingInsights,
  getAlerts
} from '@/api/statistics'

// 日期范围
const dateRange = ref([
  new Date(new Date().getTime() - 7 * 24 * 3600 * 1000), // 7天前
  new Date() // 今天
])

// 日期快捷选项
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
  },
  {
    text: '最近90天',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 90)
      return [start, end]
    }
  }
]

// 筛选表单
const filterForm = reactive({
  merchant_id: null
})

// 是否为管理员
const isAdmin = ref(true) // TODO: 从用户store获取

// 商家列表
const merchantList = ref([])

// 自动刷新
const autoRefresh = ref(false)
let refreshTimer = null

// 加载状态
const loading = reactive({
  trend: false,
  conversion: false,
  device: false,
  userBehavior: false,
  funnel: false
})

// 空状态
const isEmpty = reactive({
  trend: false,
  conversion: false,
  device: false,
  userBehavior: false,
  funnel: false
})

// 核心指标数据
const metrics = ref([
  {
    key: 'trigger',
    title: '总触发量',
    value: 0,
    icon: DataLine,
    color: '#409EFF',
    trend: 'up',
    trendPercent: 0,
    description: '较上周期',
    unit: '次'
  },
  {
    key: 'generate',
    title: '内容生成量',
    value: 0,
    icon: Edit,
    color: '#67C23A',
    trend: 'up',
    trendPercent: 0,
    description: '较上周期',
    unit: '个'
  },
  {
    key: 'distribute',
    title: '平台分发量',
    value: 0,
    icon: Share,
    color: '#E6A23C',
    trend: 'down',
    trendPercent: 0,
    description: '较上周期',
    unit: '次'
  },
  {
    key: 'conversion',
    title: '成功率',
    value: 0,
    icon: CircleCheck,
    color: '#F56C6C',
    trend: 'flat',
    trendPercent: 0,
    description: '较上周期',
    unit: '%',
    formatter: (value) => `${value.toFixed(2)}%`
  }
])

// 图表ref
const trendChartRef = ref(null)
const conversionChartRef = ref(null)
const deviceChartRef = ref(null)
const userBehaviorChartRef = ref(null)
const funnelChartRef = ref(null)

// 初始化图表
const trendChart = useEcharts(trendChartRef)
const conversionChart = useEcharts(conversionChartRef)
const deviceChart = useEcharts(deviceChartRef)
const userBehaviorChart = useEcharts(userBehaviorChartRef)
const funnelChart = useEcharts(funnelChartRef)

// 漏斗转化率
const funnelConversionRate = ref(0)
const maxLossStage = ref('-')

// 预警信息
const alerts = ref([])

// 营销建议
const insights = ref([])

// 格式化日期
const formatDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// 获取查询参数
const getQueryParams = () => {
  return {
    start_date: formatDate(dateRange.value[0]),
    end_date: formatDate(dateRange.value[1]),
    merchant_id: filterForm.merchant_id || undefined
  }
}

// 加载概览数据
const loadOverviewData = async () => {
  try {
    const params = getQueryParams()
    const res = await getOverview(params)

    if (res.code === 200) {
      const data = res.data

      // 更新核心指标
      metrics.value[0].value = data.trigger_count || 0
      metrics.value[0].trend = data.trigger_trend || 'flat'
      metrics.value[0].trendPercent = data.trigger_trend_percent || 0

      metrics.value[1].value = data.generate_count || 0
      metrics.value[1].trend = data.generate_trend || 'flat'
      metrics.value[1].trendPercent = data.generate_trend_percent || 0

      metrics.value[2].value = data.distribute_count || 0
      metrics.value[2].trend = data.distribute_trend || 'flat'
      metrics.value[2].trendPercent = data.distribute_trend_percent || 0

      metrics.value[3].value = data.success_rate || 0
      metrics.value[3].trend = data.success_rate_trend || 'flat'
      metrics.value[3].trendPercent = data.success_rate_trend_percent || 0
    }
  } catch (error) {
    console.error('加载概览数据失败:', error)
  }
}

// 加载趋势数据
const loadTrendData = async () => {
  loading.trend = true
  try {
    const params = getQueryParams()
    const res = await getTrend(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.trend = !data.dates || data.dates.length === 0

      if (!isEmpty.trend) {
        const option = getLineChartOption(
          data.dates,
          [
            { name: '触发量', data: data.trigger_data },
            { name: '生成量', data: data.generate_data },
            { name: '分发量', data: data.distribute_data }
          ],
          {
            legend: {
              data: ['触发量', '生成量', '分发量']
            }
          }
        )
        trendChart.setOption(option)
      }
    } else {
      isEmpty.trend = true
    }
  } catch (error) {
    console.error('加载趋势数据失败:', error)
    isEmpty.trend = true
  } finally {
    loading.trend = false
  }
}

// 加载转化数据
const loadConversionData = async () => {
  loading.conversion = true
  try {
    const params = getQueryParams()
    const res = await getConversionStats(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.conversion = !data.conversion_data || data.conversion_data.length === 0

      if (!isEmpty.conversion) {
        const option = getPieChartOption(data.conversion_data, {
          series: [{
            name: '转化分布'
          }]
        })
        conversionChart.setOption(option)
      }
    } else {
      isEmpty.conversion = true
    }
  } catch (error) {
    console.error('加载转化数据失败:', error)
    isEmpty.conversion = true
  } finally {
    loading.conversion = false
  }
}

// 加载设备统计数据
const loadDeviceData = async () => {
  loading.device = true
  try {
    const params = { ...getQueryParams(), limit: 10 }
    const res = await getDeviceStats(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.device = !data.devices || data.devices.length === 0

      if (!isEmpty.device) {
        const option = getBarChartOption(
          data.devices.map(item => item.name),
          [{ name: '触发次数', data: data.devices.map(item => item.count) }],
          {
            xAxis: { type: 'value' },
            yAxis: { type: 'category' }
          }
        )
        deviceChart.setOption(option)
      }
    } else {
      isEmpty.device = true
    }
  } catch (error) {
    console.error('加载设备数据失败:', error)
    isEmpty.device = true
  } finally {
    loading.device = false
  }
}

// 加载用户行为数据
const loadUserBehaviorData = async () => {
  loading.userBehavior = true
  try {
    const params = getQueryParams()
    const res = await getUserBehavior(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.userBehavior = !data.hours || data.hours.length === 0

      if (!isEmpty.userBehavior) {
        const option = getAreaChartOption(
          data.hours.map(h => `${h}:00`),
          [{ name: '活跃用户数', data: data.active_users }],
          {
            legend: {
              data: ['活跃用户数']
            }
          }
        )
        userBehaviorChart.setOption(option)
      }
    } else {
      isEmpty.userBehavior = true
    }
  } catch (error) {
    console.error('加载用户行为数据失败:', error)
    isEmpty.userBehavior = true
  } finally {
    loading.userBehavior = false
  }
}

// 加载漏斗数据
const loadFunnelData = async () => {
  loading.funnel = true
  try {
    const params = getQueryParams()
    const res = await getConversionStats(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.funnel = !data.funnel_data || data.funnel_data.length === 0

      if (!isEmpty.funnel) {
        const option = getFunnelChartOption(data.funnel_data)
        funnelChart.setOption(option)

        // 计算总体转化率
        if (data.funnel_data.length > 0) {
          const first = data.funnel_data[0].value
          const last = data.funnel_data[data.funnel_data.length - 1].value
          funnelConversionRate.value = first > 0 ? ((last / first) * 100).toFixed(2) : 0
        }

        // 计算最高流失环节
        maxLossStage.value = data.max_loss_stage || '-'
      }
    } else {
      isEmpty.funnel = true
    }
  } catch (error) {
    console.error('加载漏斗数据失败:', error)
    isEmpty.funnel = true
  } finally {
    loading.funnel = false
  }
}

// 加载预警和建议
const loadInsightsAndAlerts = async () => {
  try {
    const params = getQueryParams()

    // 加载预警
    const alertRes = await getAlerts(params)
    if (alertRes.code === 200) {
      alerts.value = alertRes.data || []
    }

    // 加载营销建议
    const insightRes = await getMarketingInsights(params)
    if (insightRes.code === 200) {
      insights.value = insightRes.data || []
    }
  } catch (error) {
    console.error('加载洞察数据失败:', error)
  }
}

// 加载所有数据
const loadAllData = async () => {
  await Promise.all([
    loadOverviewData(),
    loadTrendData(),
    loadConversionData(),
    loadDeviceData(),
    loadUserBehaviorData(),
    loadFunnelData(),
    loadInsightsAndAlerts()
  ])
}

// 处理日期变化
const handleDateChange = () => {
  loadAllData()
}

// 处理筛选变化
const handleFilterChange = () => {
  loadAllData()
}

// 处理查询
const handleSearch = () => {
  loadAllData()
}

// 处理刷新
const handleRefresh = () => {
  loadAllData()
  ElMessage.success('数据已刷新')
}

// 处理自动刷新
const handleAutoRefreshChange = (value) => {
  if (value) {
    // 开启自动刷新，每30秒刷新一次
    refreshTimer = setInterval(() => {
      loadAllData()
    }, 30000)
    ElMessage.info('已开启自动刷新')
  } else {
    // 关闭自动刷新
    if (refreshTimer) {
      clearInterval(refreshTimer)
      refreshTimer = null
    }
    ElMessage.info('已关闭自动刷新')
  }
}

// 导出报表
const handleExport = async () => {
  try {
    await ElMessageBox.confirm('确定要导出当前统计报表吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    const params = {
      ...getQueryParams(),
      format: 'excel',
      metrics: ['overview', 'trend', 'conversion', 'device', 'user_behavior']
    }

    const res = await exportReport(params)

    // 创建下载链接
    const blob = new Blob([res], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `统计报表_${formatDate(new Date())}.xlsx`
    link.click()
    window.URL.revokeObjectURL(url)

    ElMessage.success('导出成功')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('导出失败:', error)
      ElMessage.error('导出失败')
    }
  }
}

// 下载单个图表
const downloadChart = (chartType) => {
  let chartInstance = null
  let filename = ''

  switch (chartType) {
    case 'trend':
      chartInstance = trendChart.getInstance()
      filename = '触发量趋势图'
      break
    case 'conversion':
      chartInstance = conversionChart.getInstance()
      filename = '转化率分布图'
      break
    case 'device':
      chartInstance = deviceChart.getInstance()
      filename = '设备触发排行'
      break
    case 'userBehavior':
      chartInstance = userBehaviorChart.getInstance()
      filename = '用户活跃度'
      break
    case 'funnel':
      chartInstance = funnelChart.getInstance()
      filename = '转化漏斗图'
      break
  }

  if (chartInstance) {
    const url = chartInstance.getDataURL({
      type: 'png',
      pixelRatio: 2,
      backgroundColor: '#fff'
    })

    const link = document.createElement('a')
    link.href = url
    link.download = `${filename}_${formatDate(new Date())}.png`
    link.click()

    ElMessage.success('图表已下载')
  }
}

// 组件挂载
onMounted(() => {
  loadAllData()
})

// 组件卸载
onBeforeUnmount(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
})
</script>

<style lang="scss" scoped>
.statistics-container {
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

  .metrics-row {
    margin-bottom: 20px;
  }

  .charts-row {
    margin-bottom: 20px;

    .chart {
      width: 100%;
      height: 400px;
    }
  }

  .insights-row {
    .insight-card,
    .alert-card {
      height: 100%;

      .card-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
        font-weight: 600;
      }

      .alert-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 400px;
        overflow-y: auto;

        .alert-item {
          margin: 0;
        }
      }

      .insight-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
        max-height: 400px;
        overflow-y: auto;

        .insight-item {
          display: flex;
          gap: 12px;
          padding: 12px;
          background: #f5f7fa;
          border-radius: 8px;

          .insight-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #409EFF;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
          }

          .insight-content {
            flex: 1;

            .insight-title {
              font-size: 14px;
              font-weight: 600;
              color: #303133;
              margin-bottom: 4px;
            }

            .insight-desc {
              font-size: 13px;
              color: #606266;
              line-height: 1.5;
            }
          }
        }
      }
    }
  }

  .funnel-stats {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 14px;
    color: #606266;

    .divider {
      color: #DCDFE6;
    }

    strong {
      color: #303133;
      font-weight: 600;
    }
  }
}

// 响应式设计
@media (max-width: 768px) {
  .statistics-container {
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

    .charts-row {
      .chart {
        height: 300px;
      }
    }
  }
}
</style>

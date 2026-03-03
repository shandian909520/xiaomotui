<template>
  <div class="promo-stats-container">
    <!-- 页面标题栏 -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">推广统计报表</h1>
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
        <el-form-item label="日期范围">
          <el-radio-group v-model="dateRangeType" @change="handleDateRangeTypeChange">
            <el-radio-button label="today">今日</el-radio-button>
            <el-radio-button label="7days">近7天</el-radio-button>
            <el-radio-button label="30days">近30天</el-radio-button>
            <el-radio-button label="custom">自定义</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="dateRangeType === 'custom'" label="选择日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :shortcuts="dateShortcuts"
            value-format="YYYY-MM-DD"
            @change="handleDateChange"
          />
        </el-form-item>

        <el-form-item label="数据粒度">
          <el-select v-model="filterForm.granularity" placeholder="选择粒度" @change="handleGranularityChange">
            <el-option label="按日" value="day" />
            <el-option label="按周" value="week" />
            <el-option label="按月" value="month" />
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
      <!-- 趋势折线图 -->
      <el-col :xs="24" :lg="16">
        <chart-container
          title="趋势分析"
          :icon="TrendCharts"
          :loading="loading.trend"
          :empty="isEmpty.trend"
          @refresh="loadTrendData"
          @download="downloadChart('trend')"
        >
          <div ref="trendChartRef" class="chart"></div>
        </chart-container>
      </el-col>

      <!-- 平台分布饼图 -->
      <el-col :xs="24" :lg="8">
        <chart-container
          title="平台分布"
          :icon="PieChart"
          :loading="loading.platform"
          :empty="isEmpty.platform"
          @refresh="loadPlatformData"
          @download="downloadChart('platform')"
        >
          <div ref="platformChartRef" class="chart"></div>
        </chart-container>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="charts-row">
      <!-- 设备排行表格 -->
      <el-col :xs="24" :lg="12">
        <el-card class="device-ranking-card" shadow="hover">
          <template #header>
            <div class="card-header">
              <div class="card-title-wrapper">
                <el-icon :size="20" class="title-icon"><Monitor /></el-icon>
                <span class="card-title">设备排行 TOP 10</span>
              </div>
              <el-button type="primary" link @click="showAllDevices = true">
                查看更多
              </el-button>
            </div>
          </template>
          <el-table
            :data="deviceRanking"
            v-loading="loading.device"
            stripe
            :max-height="400"
          >
            <el-table-column type="index" label="排名" width="60" />
            <el-table-column prop="device_name" label="设备名称" min-width="150" show-overflow-tooltip />
            <el-table-column prop="trigger_count" label="触发次数" width="100" align="center">
              <template #default="{ row }">
                <el-tag type="primary" effect="plain">{{ row.trigger_count }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="publish_count" label="发布次数" width="100" align="center">
              <template #default="{ row }">
                <el-tag type="success" effect="plain">{{ row.publish_count }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="conversion_rate" label="转化率" width="100" align="center">
              <template #default="{ row }">
                <span :class="getConversionClass(row.conversion_rate)">
                  {{ row.conversion_rate }}%
                </span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <!-- 活动对比柱状图 -->
      <el-col :xs="24" :lg="12">
        <chart-container
          title="活动对比分析"
          :icon="DataAnalysis"
          :loading="loading.campaign"
          :empty="isEmpty.campaign"
          @refresh="loadCampaignData"
          @download="downloadChart('campaign')"
        >
          <template #actions>
            <el-select
              v-model="selectedCampaigns"
              multiple
              collapse-tags
              collapse-tags-tooltip
              placeholder="选择活动对比"
              style="width: 280px"
              @change="handleCampaignSelect"
            >
              <el-option
                v-for="campaign in campaignList"
                :key="campaign.id"
                :label="campaign.name"
                :value="campaign.id"
              />
            </el-select>
          </template>
          <div ref="campaignChartRef" class="chart"></div>
        </chart-container>
      </el-col>
    </el-row>

    <!-- 今日实时数据 -->
    <el-row :gutter="20" class="today-stats-row">
      <el-col :span="24">
        <el-card class="today-stats-card" shadow="hover">
          <template #header>
            <div class="card-header">
              <div class="card-title-wrapper">
                <el-icon :size="20" class="title-icon"><Clock /></el-icon>
                <span class="card-title">今日实时数据</span>
              </div>
              <el-button :icon="Refresh" circle size="small" @click="loadTodayStats" />
            </div>
          </template>
          <el-row :gutter="20">
            <el-col :xs="12" :sm="6" v-for="item in todayStats" :key="item.key">
              <div class="today-stat-item">
                <div class="today-stat-label">{{ item.label }}</div>
                <div class="today-stat-value">{{ item.value }}</div>
                <div class="today-stat-compare" :class="item.trend">
                  <el-icon v-if="item.trend === 'up'"><ArrowUp /></el-icon>
                  <el-icon v-else-if="item.trend === 'down'"><ArrowDown /></el-icon>
                  <span>{{ item.compare }}</span>
                </div>
              </div>
            </el-col>
          </el-row>
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
  DataAnalysis,
  ArrowUp,
  ArrowDown,
  Promotion,
  VideoPlay,
  Present,
  CircleCheck
} from '@element-plus/icons-vue'
import StatCard from '@/components/StatCard.vue'
import ChartContainer from '@/components/ChartContainer.vue'
import { useEcharts, getLineChartOption, getPieChartOption, getBarChartOption } from '@/composables/useEcharts'
import {
  getOverview,
  getTrendData,
  getPlatformDistribution,
  getDeviceRanking,
  getCampaignComparison,
  getTodayStats,
  getCampaignList
} from '@/api/promo-stats'

// 日期范围类型
const dateRangeType = ref('7days')

// 日期范围
const dateRange = ref([])

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
  granularity: 'day'
})

// 自动刷新
const autoRefresh = ref(false)
let refreshTimer = null

// 加载状态
const loading = reactive({
  trend: false,
  platform: false,
  device: false,
  campaign: false
})

// 空状态
const isEmpty = reactive({
  trend: false,
  platform: false,
  device: false,
  campaign: false
})

// 核心指标数据
const metrics = ref([
  {
    key: 'campaign',
    title: '总活动数',
    value: 0,
    icon: Promotion,
    color: '#409EFF',
    trend: 'flat',
    trendPercent: 0,
    description: '较上周期',
    unit: '个'
  },
  {
    key: 'trigger',
    title: '总触发数',
    value: 0,
    icon: VideoPlay,
    color: '#67C23A',
    trend: 'flat',
    trendPercent: 0,
    description: '较上周期',
    unit: '次'
  },
  {
    key: 'publish',
    title: '总发布数',
    value: 0,
    icon: Present,
    color: '#E6A23C',
    trend: 'flat',
    trendPercent: 0,
    description: '较上周期',
    unit: '次'
  },
  {
    key: 'reward',
    title: '奖励发放数',
    value: 0,
    icon: CircleCheck,
    color: '#F56C6C',
    trend: 'flat',
    trendPercent: 0,
    description: '较上周期',
    unit: '次'
  }
])

// 图表ref
const trendChartRef = ref(null)
const platformChartRef = ref(null)
const campaignChartRef = ref(null)

// 初始化图表
const trendChart = useEcharts(trendChartRef)
const platformChart = useEcharts(platformChartRef)
const campaignChart = useEcharts(campaignChartRef)

// 设备排行数据
const deviceRanking = ref([])

// 活动列表
const campaignList = ref([])
const selectedCampaigns = ref([])

// 今日统计数据
const todayStats = ref([
  { key: 'trigger', label: '今日触发', value: 0, compare: '较昨日持平', trend: 'flat' },
  { key: 'publish', label: '今日发布', value: 0, compare: '较昨日持平', trend: 'flat' },
  { key: 'reward', label: '今日奖励', value: 0, compare: '较昨日持平', trend: 'flat' },
  { key: 'conversion', label: '转化率', value: '0%', compare: '较昨日持平', trend: 'flat' }
])

// 格式化日期
const formatDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// 获取日期范围
const getDateRange = () => {
  const today = new Date()
  const formatDateStr = (d) => {
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  switch (dateRangeType.value) {
    case 'today':
      return {
        start_date: formatDateStr(today),
        end_date: formatDateStr(today)
      }
    case '7days': {
      const start = new Date()
      start.setDate(start.getDate() - 6)
      return {
        start_date: formatDateStr(start),
        end_date: formatDateStr(today)
      }
    }
    case '30days': {
      const start = new Date()
      start.setDate(start.getDate() - 29)
      return {
        start_date: formatDateStr(start),
        end_date: formatDateStr(today)
      }
    }
    case 'custom':
      if (dateRange.value && dateRange.value.length === 2) {
        return {
          start_date: dateRange.value[0],
          end_date: dateRange.value[1]
        }
      }
      return {}
    default:
      return {}
  }
}

// 获取查询参数
const getQueryParams = () => {
  return {
    ...getDateRange(),
    granularity: filterForm.granularity
  }
}

// 加载概览数据
const loadOverviewData = async () => {
  try {
    const params = getQueryParams()
    const res = await getOverview(params)

    if (res.code === 200 && res.data) {
      const data = res.data

      // 更新核心指标
      metrics.value[0].value = data.campaign_count || 0
      metrics.value[0].trend = data.campaign_trend || 'flat'
      metrics.value[0].trendPercent = data.campaign_trend_percent || 0

      metrics.value[1].value = data.trigger_count || 0
      metrics.value[1].trend = data.trigger_trend || 'flat'
      metrics.value[1].trendPercent = data.trigger_trend_percent || 0

      metrics.value[2].value = data.publish_count || 0
      metrics.value[2].trend = data.publish_trend || 'flat'
      metrics.value[2].trendPercent = data.publish_trend_percent || 0

      metrics.value[3].value = data.reward_count || 0
      metrics.value[3].trend = data.reward_trend || 'flat'
      metrics.value[3].trendPercent = data.reward_trend_percent || 0
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
    const res = await getTrendData(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.trend = !data.dates || data.dates.length === 0

      if (!isEmpty.trend) {
        const option = getLineChartOption(
          data.dates,
          [
            { name: '触发', data: data.trigger_data, smooth: true },
            { name: '发布', data: data.publish_data, smooth: true },
            { name: '奖励', data: data.reward_data, smooth: true }
          ],
          {
            legend: {
              data: ['触发', '发布', '奖励']
            },
            tooltip: {
              trigger: 'axis'
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

// 加载平台分布数据
const loadPlatformData = async () => {
  loading.platform = true
  try {
    const params = getQueryParams()
    const res = await getPlatformDistribution(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.platform = !data.platforms || data.platforms.length === 0

      if (!isEmpty.platform) {
        const pieData = data.platforms.map(item => ({
          value: item.count,
          name: item.name
        }))

        const option = getPieChartOption(pieData, {
          tooltip: {
            trigger: 'item',
            formatter: '{b}: {c} ({d}%)'
          },
          legend: {
            orient: 'vertical',
            left: 'left'
          }
        })
        platformChart.setOption(option)
      }
    } else {
      isEmpty.platform = true
    }
  } catch (error) {
    console.error('加载平台分布数据失败:', error)
    isEmpty.platform = true
  } finally {
    loading.platform = false
  }
}

// 加载设备排行数据
const loadDeviceData = async () => {
  loading.device = true
  try {
    const params = { ...getQueryParams(), limit: 10 }
    const res = await getDeviceRanking(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.device = !data.devices || data.devices.length === 0

      if (!isEmpty.device) {
        deviceRanking.value = data.devices
      }
    } else {
      isEmpty.device = true
      deviceRanking.value = []
    }
  } catch (error) {
    console.error('加载设备排行数据失败:', error)
    isEmpty.device = true
    deviceRanking.value = []
  } finally {
    loading.device = false
  }
}

// 加载活动对比数据
const loadCampaignData = async () => {
  if (selectedCampaigns.value.length === 0) {
    isEmpty.campaign = true
    return
  }

  loading.campaign = true
  try {
    const params = {
      ...getQueryParams(),
      campaign_ids: selectedCampaigns.value.join(',')
    }
    const res = await getCampaignComparison(params)

    if (res.code === 200 && res.data) {
      const data = res.data
      isEmpty.campaign = !data.campaigns || data.campaigns.length === 0

      if (!isEmpty.campaign) {
        const campaignNames = data.campaigns.map(c => c.name)
        const triggerData = data.campaigns.map(c => c.trigger_count)
        const publishData = data.campaigns.map(c => c.publish_count)
        const rewardData = data.campaigns.map(c => c.reward_count)

        const option = getBarChartOption(
          campaignNames,
          [
            { name: '触发', data: triggerData },
            { name: '发布', data: publishData },
            { name: '奖励', data: rewardData }
          ],
          {
            legend: {
              data: ['触发', '发布', '奖励']
            }
          }
        )
        campaignChart.setOption(option)
      }
    } else {
      isEmpty.campaign = true
    }
  } catch (error) {
    console.error('加载活动对比数据失败:', error)
    isEmpty.campaign = true
  } finally {
    loading.campaign = false
  }
}

// 加载今日统计数据
const loadTodayStats = async () => {
  try {
    const res = await getTodayStats()

    if (res.code === 200 && res.data) {
      const data = res.data
      todayStats.value = [
        {
          key: 'trigger',
          label: '今日触发',
          value: data.trigger_count || 0,
          compare: data.trigger_compare || '较昨日持平',
          trend: data.trigger_trend || 'flat'
        },
        {
          key: 'publish',
          label: '今日发布',
          value: data.publish_count || 0,
          compare: data.publish_compare || '较昨日持平',
          trend: data.publish_trend || 'flat'
        },
        {
          key: 'reward',
          label: '今日奖励',
          value: data.reward_count || 0,
          compare: data.reward_compare || '较昨日持平',
          trend: data.reward_trend || 'flat'
        },
        {
          key: 'conversion',
          label: '转化率',
          value: data.conversion_rate || '0%',
          compare: data.conversion_compare || '较昨日持平',
          trend: data.conversion_trend || 'flat'
        }
      ]
    }
  } catch (error) {
    console.error('加载今日统计数据失败:', error)
  }
}

// 加载活动列表
const loadCampaignList = async () => {
  try {
    const res = await getCampaignList({ limit: 50 })

    if (res.code === 200 && res.data) {
      campaignList.value = res.data.campaigns || []
    }
  } catch (error) {
    console.error('加载活动列表失败:', error)
  }
}

// 加载所有数据
const loadAllData = async () => {
  await Promise.all([
    loadOverviewData(),
    loadTrendData(),
    loadPlatformData(),
    loadDeviceData(),
    loadCampaignData(),
    loadTodayStats(),
    loadCampaignList()
  ])
}

// 处理日期范围类型变化
const handleDateRangeTypeChange = () => {
  if (dateRangeType.value !== 'custom') {
    loadAllData()
  }
}

// 处理日期变化
const handleDateChange = () => {
  loadAllData()
}

// 处理粒度变化
const handleGranularityChange = () => {
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
    refreshTimer = setInterval(() => {
      loadAllData()
    }, 30000)
    ElMessage.info('已开启自动刷新')
  } else {
    if (refreshTimer) {
      clearInterval(refreshTimer)
      refreshTimer = null
    }
    ElMessage.info('已关闭自动刷新')
  }
}

// 处理活动选择
const handleCampaignSelect = () => {
  loadCampaignData()
}

// 获取转化率样式类
const getConversionClass = (rate) => {
  if (rate >= 80) return 'conversion-high'
  if (rate >= 50) return 'conversion-medium'
  return 'conversion-low'
}

// 导出报表
const handleExport = async () => {
  try {
    await ElMessageBox.confirm('确定要导出当前推广统计报表吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    // TODO: 调用后端导出接口
    ElMessage.success('导出功能开发中...')
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
      filename = '趋势分析'
      break
    case 'platform':
      chartInstance = platformChart.getInstance()
      filename = '平台分布'
      break
    case 'campaign':
      chartInstance = campaignChart.getInstance()
      filename = '活动对比分析'
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
.promo-stats-container {
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

  .device-ranking-card {
    height: 100%;

    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;

      .card-title-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;

        .title-icon {
          color: #409EFF;
        }

        .card-title {
          font-size: 16px;
          font-weight: 600;
          color: #303133;
        }
      }
    }

    .conversion-high {
      color: #67C23A;
      font-weight: 600;
    }

    .conversion-medium {
      color: #E6A23C;
      font-weight: 600;
    }

    .conversion-low {
      color: #F56C6C;
      font-weight: 600;
    }
  }

  .today-stats-row {
    .today-stats-card {
      .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;

        .card-title-wrapper {
          display: flex;
          align-items: center;
          gap: 8px;

          .title-icon {
            color: #409EFF;
          }

          .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #303133;
          }
        }
      }

      .today-stat-item {
        text-align: center;
        padding: 16px 0;

        .today-stat-label {
          font-size: 14px;
          color: #909399;
          margin-bottom: 8px;
        }

        .today-stat-value {
          font-size: 28px;
          font-weight: 600;
          color: #303133;
          margin-bottom: 8px;
        }

        .today-stat-compare {
          font-size: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 4px;

          &.up {
            color: #67C23A;
          }

          &.down {
            color: #F56C6C;
          }

          &.flat {
            color: #909399;
          }
        }
      }
    }
  }
}

// 响应式设计
@media (max-width: 768px) {
  .promo-stats-container {
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

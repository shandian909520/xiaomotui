<template>
  <div class="recommendation-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">推荐引擎</h1>
        <el-tag type="success" effect="plain">运行中</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadAllData">刷新</el-button>
        <el-button :icon="Delete" @click="handleClearCache">清除缓存</el-button>
      </div>
    </div>

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
      <el-col :xs="24" :lg="12">
        <chart-container
          title="覆盖率趋势"
          :icon="TrendCharts"
          :loading="loading.coverage"
          :empty="isEmpty.coverage"
          @refresh="loadCoverageData"
        >
          <div ref="coverageChartRef" class="chart"></div>
        </chart-container>
      </el-col>

      <el-col :xs="24" :lg="12">
        <chart-container
          title="算法对比"
          :icon="PieChart"
          :loading="loading.algorithm"
          :empty="isEmpty.algorithm"
          @refresh="loadAlgorithmData"
        >
          <div ref="algorithmChartRef" class="chart"></div>
        </chart-container>
      </el-col>
    </el-row>

    <!-- 缓存统计 -->
    <el-card class="cache-card" shadow="hover">
      <template #header>
        <div class="card-header">
          <span>缓存统计</span>
          <el-button type="primary" size="small" @click="loadCacheStats">刷新</el-button>
        </div>
      </template>
      <el-row :gutter="20">
        <el-col :xs="12" :sm="6" v-for="item in cacheStatsItems" :key="item.label">
          <div class="cache-stat-item">
            <div class="cache-stat-value">{{ item.value }}</div>
            <div class="cache-stat-label">{{ item.label }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 最近推荐记录 -->
    <el-card class="table-card" shadow="hover">
      <template #header>
        <div class="card-header">
          <span>最近推荐记录</span>
        </div>
      </template>
      <el-table :data="recentRecords" v-loading="loading.records" stripe>
        <el-table-column prop="user_id" label="用户ID" width="100" />
        <el-table-column prop="algorithm" label="算法" width="120">
          <template #default="{ row }">
            <el-tag :type="getAlgorithmTagType(row.algorithm)">{{ row.algorithm }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="count" label="推荐数" width="100" />
        <el-table-column prop="click_rate" label="点击率" width="100">
          <template #default="{ row }">{{ (row.click_rate * 100).toFixed(1) }}%</template>
        </el-table-column>
        <el-table-column prop="conversion_rate" label="转化率" width="100">
          <template #default="{ row }">{{ (row.conversion_rate * 100).toFixed(1) }}%</template>
        </el-table-column>
        <el-table-column prop="create_time" label="时间" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Refresh,
  Delete,
  TrendCharts,
  PieChart,
  DataLine,
  View,
  MagicStick,
  SetUp
} from '@element-plus/icons-vue'
import StatCard from '@/components/StatCard.vue'
import ChartContainer from '@/components/ChartContainer.vue'
import { useEcharts, getLineChartOption, getPieChartOption } from '@/composables/useEcharts'
import { getCoverage, getAlgorithmComparison, getCacheStats, clearCache } from '@/api/recommendation'

const metrics = ref([
  { key: 'recommend_count', title: '推荐次数', value: 0, icon: DataLine, color: '#409EFF', trend: 'flat', trendPercent: 0, description: '较上周期', unit: '次' },
  { key: 'click_rate', title: '点击率', value: 0, icon: View, color: '#67C23A', trend: 'flat', trendPercent: 0, description: '较上周期', unit: '%' },
  { key: 'conversion_rate', title: '转化率', value: 0, icon: MagicStick, color: '#E6A23C', trend: 'flat', trendPercent: 0, description: '较上周期', unit: '%' },
  { key: 'coverage', title: '覆盖率', value: 0, icon: SetUp, color: '#F56C6C', trend: 'flat', trendPercent: 0, description: '较上周期', unit: '%' }
])

const loading = reactive({ coverage: false, algorithm: false, records: false })
const isEmpty = reactive({ coverage: false, algorithm: false })

const coverageChartRef = ref(null)
const algorithmChartRef = ref(null)
const coverageChart = useEcharts(coverageChartRef)
const algorithmChart = useEcharts(algorithmChartRef)

const cacheStatsItems = ref([
  { label: '总缓存数', value: 0 },
  { label: '活跃缓存', value: 0 },
  { label: '过期缓存', value: 0 },
  { label: '命中率', value: '0%' }
])

const recentRecords = ref([])

const getAlgorithmTagType = (algorithm) => {
  const map = { hybrid: '', collaborative: 'success', content: 'warning', popularity: 'info' }
  return map[algorithm] || 'info'
}

const loadCoverageData = async () => {
  loading.coverage = true
  try {
    const res = await getCoverage({ days: 30 })
    const data = res || {}
    isEmpty.coverage = !data.coverage
    if (data.coverage !== undefined) {
      metrics.value[3].value = (data.coverage * 100).toFixed(2)
    }
    const days = Array.from({ length: 30 }, (_, i) => {
      const d = new Date()
      d.setDate(d.getDate() - 29 + i)
      return `${d.getMonth() + 1}/${d.getDate()}`
    })
    const trendData = data.trend || data.daily_coverage || []
    const coverageData = trendData.length > 0
      ? trendData.map(v => Number(v) || 0)
      : Array(30).fill(data.coverage || 0.6)
    const option = getLineChartOption(days, [{ name: '覆盖率', data: coverageData }], {
      yAxis: { type: 'value', axisLabel: { formatter: (v) => `${(v * 100).toFixed(0)}%` } }
    })
    coverageChart.setOption(option)
  } catch {
    isEmpty.coverage = true
  } finally {
    loading.coverage = false
  }
}

const loadAlgorithmData = async () => {
  loading.algorithm = true
  try {
    const res = await getAlgorithmComparison({ days: 30 })
    const data = res || {}
    const comparison = data.comparison || []
    isEmpty.algorithm = comparison.length === 0
    if (!isEmpty.algorithm) {
      const pieData = comparison.map(item => ({
        name: item.algorithm || 'unknown',
        value: item.score || item.count || 0
      }))
      const option = getPieChartOption(pieData)
      algorithmChart.setOption(option)
    }
  } catch {
    isEmpty.algorithm = true
  } finally {
    loading.algorithm = false
  }
}

const loadCacheStats = async () => {
  try {
    const res = await getCacheStats()
    const data = res || {}
    cacheStatsItems.value = [
      { label: '总缓存数', value: data.total || 0 },
      { label: '活跃缓存', value: data.active || 0 },
      { label: '过期缓存', value: data.expired || 0 },
      { label: '命中率', value: data.hit_rate ? `${(data.hit_rate * 100).toFixed(1)}%` : '0%' }
    ]
  } catch (e) {
    console.error('加载缓存统计失败:', e)
  }
}

const handleClearCache = async () => {
  try {
    await ElMessageBox.confirm('确定要清除推荐缓存吗？', '提示', { type: 'warning' })
    const res = await clearCache({})
    ElMessage.success(`已清除 ${res?.cleared || 0} 条缓存`)
    loadCacheStats()
  } catch { /* cancel */ }
}

const loadAllData = async () => {
  await Promise.all([loadCoverageData(), loadAlgorithmData(), loadCacheStats()])
}

onMounted(() => {
  loadAllData()
})
</script>

<style lang="scss" scoped>
.recommendation-container {
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

  .cache-card {
    margin-bottom: 20px;

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 16px;
      font-weight: 600;
    }

    .cache-stat-item {
      text-align: center;
      padding: 16px 0;

      .cache-stat-value {
        font-size: 28px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 8px;
      }

      .cache-stat-label {
        font-size: 14px;
        color: #909399;
      }
    }
  }

  .table-card {
    .card-header {
      font-size: 16px;
      font-weight: 600;
    }
  }
}

@media (max-width: 768px) {
  .recommendation-container {
    padding: 12px;

    .charts-row .chart {
      height: 300px;
    }
  }
}
</style>

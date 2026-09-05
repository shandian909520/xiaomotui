<template>
  <div class="group-buy-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">拼团管理</h1>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            @change="loadData"
          />
        </el-form-item>
        <el-form-item label="设备">
          <el-input v-model="filterForm.device_id" placeholder="设备ID" clearable style="width: 150px;" />
        </el-form-item>
        <el-form-item label="平台">
          <el-select v-model="filterForm.platform" placeholder="全部" clearable>
            <el-option label="美团" value="MEITUAN" />
            <el-option label="抖音团购" value="DOUYIN" />
            <el-option label="饿了么" value="ELEME" />
            <el-option label="自定义" value="CUSTOM" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadData">查询</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="metrics-row">
      <el-col :xs="24" :sm="12" :md="6" v-for="metric in metrics" :key="metric.key">
        <stat-card
          :title="metric.title"
          :value="metric.value"
          :icon="metric.icon"
          :icon-color="metric.color"
          :unit="metric.unit"
        />
      </el-col>
    </el-row>

    <!-- 图表区域 -->
    <el-row :gutter="20" class="charts-row">
      <el-col :xs="24" :lg="12">
        <chart-container
          title="每日点击趋势"
          :icon="TrendCharts"
          :loading="chartLoading"
          @refresh="loadData"
        >
          <div ref="trendChartRef" class="chart"></div>
        </chart-container>
      </el-col>
      <el-col :xs="24" :lg="12">
        <chart-container
          title="平台分布"
          :icon="PieChart"
          :loading="chartLoading"
          @refresh="loadData"
        >
          <div ref="platformChartRef" class="chart"></div>
        </chart-container>
      </el-col>
    </el-row>

    <!-- 设备TOP排行 -->
    <el-card shadow="hover" style="margin-bottom: 20px;">
      <template #header>
        <div class="card-header"><span>设备点击排行</span></div>
      </template>
      <el-table :data="topDevices" stripe>
        <el-table-column type="index" label="排名" width="60" />
        <el-table-column prop="device_name" label="设备名称" min-width="150" />
        <el-table-column prop="device_code" label="设备编号" width="150" />
        <el-table-column prop="click_count" label="点击次数" width="120">
          <template #default="{ row }">
            <el-tag type="danger">{{ row.click_count }}</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 团购配置管理 -->
    <el-card shadow="hover">
      <template #header>
        <div class="card-header">
          <span>团购跳转配置</span>
          <el-button type="primary" size="small" :icon="Plus" @click="showConfigDialog = true">配置设备</el-button>
        </div>
      </template>
      <el-form :inline="true" style="margin-bottom: 16px;">
        <el-form-item label="设备ID">
          <el-input v-model="configDeviceId" placeholder="输入设备ID" style="width: 200px;" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadDeviceConfig">查询配置</el-button>
        </el-form-item>
      </el-form>

      <el-descriptions :column="2" border v-if="deviceConfig" title="当前配置">
        <el-descriptions-item label="平台">{{ getPlatformName(deviceConfig.platform) }}</el-descriptions-item>
        <el-descriptions-item label="团购ID">{{ deviceConfig.deal_id || '-' }}</el-descriptions-item>
        <el-descriptions-item label="团购名称">{{ deviceConfig.deal_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="原价">{{ deviceConfig.original_price ? `¥${deviceConfig.original_price}` : '-' }}</el-descriptions-item>
        <el-descriptions-item label="团购价">{{ deviceConfig.group_price ? `¥${deviceConfig.group_price}` : '-' }}</el-descriptions-item>
        <el-descriptions-item label="自定义URL">{{ deviceConfig.custom_url || '-' }}</el-descriptions-item>
      </el-descriptions>
    </el-card>

    <!-- 配置设备对话框 -->
    <el-dialog v-model="showConfigDialog" title="配置团购跳转" width="600px">
      <el-form :model="configForm" label-width="120px">
        <el-form-item label="设备ID" required>
          <el-input v-model="configForm.device_id" placeholder="输入设备ID" />
        </el-form-item>
        <el-form-item label="平台类型" required>
          <el-select v-model="configForm.platform" style="width: 100%;">
            <el-option label="美团" value="MEITUAN" />
            <el-option label="抖音团购" value="DOUYIN" />
            <el-option label="饿了么" value="ELEME" />
            <el-option label="自定义" value="CUSTOM" />
          </el-select>
        </el-form-item>
        <el-form-item label="团购ID" v-if="configForm.platform !== 'CUSTOM'">
          <el-input v-model="configForm.deal_id" placeholder="输入团购ID" />
        </el-form-item>
        <el-form-item label="团购名称">
          <el-input v-model="configForm.deal_name" placeholder="输入团购名称" />
        </el-form-item>
        <el-form-item label="原价">
          <el-input-number v-model="configForm.original_price" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="团购价">
          <el-input-number v-model="configForm.group_price" :min="0" :precision="2" style="width: 100%;" />
        </el-form-item>
        <el-form-item label="自定义URL" v-if="configForm.platform === 'CUSTOM'">
          <el-input v-model="configForm.custom_url" placeholder="https://..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showConfigDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSaveConfig">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  Refresh,
  Search,
  Plus,
  TrendCharts,
  PieChart,
  DataLine,
  Mouse,
  User,
  ShoppingBag
} from '@element-plus/icons-vue'
import StatCard from '@/components/StatCard.vue'
import ChartContainer from '@/components/ChartContainer.vue'
import { useEcharts, getLineChartOption, getPieChartOption } from '@/composables/useEcharts'
import { getGroupBuyStatistics, getGroupBuyConfig, configureGroupBuy } from '@/api/group-buy'

const dateRange = ref([
  new Date(Date.now() - 30 * 86400000),
  new Date()
])
const filterForm = reactive({ device_id: '', platform: '' })
const chartLoading = ref(false)
const showConfigDialog = ref(false)
const configDeviceId = ref('')
const deviceConfig = ref(null)
const topDevices = ref([])

const metrics = ref([
  { key: 'total_clicks', title: '总点击量', value: 0, icon: Mouse, color: '#409EFF', unit: '次' },
  { key: 'today_clicks', title: '今日点击', value: 0, icon: DataLine, color: '#67C23A', unit: '次' },
  { key: 'unique_users', title: '独立用户', value: 0, icon: User, color: '#E6A23C', unit: '人' },
  { key: 'avg_daily', title: '日均点击', value: 0, icon: ShoppingBag, color: '#F56C6C', unit: '次' }
])

const configForm = reactive({
  device_id: '',
  platform: 'MEITUAN',
  deal_id: '',
  deal_name: '',
  original_price: 0,
  group_price: 0,
  custom_url: ''
})

const trendChartRef = ref(null)
const platformChartRef = ref(null)
const trendChart = useEcharts(trendChartRef)
const platformChart = useEcharts(platformChartRef)

const formatDate = (date) => {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const getPlatformName = (platform) => {
  const names = { MEITUAN: '美团', DOUYIN: '抖音团购', ELEME: '饿了么', CUSTOM: '自定义' }
  return names[platform] || platform || '-'
}

const loadData = async () => {
  chartLoading.value = true
  try {
    const params = {
      start_date: formatDate(dateRange.value[0]),
      end_date: formatDate(dateRange.value[1]),
      device_id: filterForm.device_id || undefined,
      platform: filterForm.platform || undefined
    }
    const res = await getGroupBuyStatistics(params)
    const data = res || {}

    metrics.value[0].value = data.total_clicks || 0
    metrics.value[1].value = data.today_clicks || 0
    metrics.value[2].value = data.unique_users || 0
    metrics.value[3].value = data.avg_daily_clicks || 0
    topDevices.value = data.top_devices || []

    // 趋势图
    const trend = data.daily_trend || []
    if (trend.length > 0) {
      const option = getLineChartOption(
        trend.map(t => t.date),
        [{ name: '点击量', data: trend.map(t => t.count) }]
      )
      trendChart.setOption(option)
    }

    // 平台分布图
    const breakdown = data.platform_breakdown || {}
    const pieData = Object.entries(breakdown).map(([name, value]) => ({
      name: getPlatformName(name),
      value
    }))
    if (pieData.length > 0) {
      const option = getPieChartOption(pieData)
      platformChart.setOption(option)
    }
  } catch (e) {
    console.error('加载拼团统计失败:', e)
  } finally {
    chartLoading.value = false
  }
}

const loadDeviceConfig = async () => {
  if (!configDeviceId.value) {
    ElMessage.warning('请输入设备ID')
    return
  }
  try {
    const res = await getGroupBuyConfig(configDeviceId.value)
    deviceConfig.value = res || null
  } catch (e) {
    deviceConfig.value = null
    ElMessage.error('获取配置失败')
  }
}

const handleSaveConfig = async () => {
  if (!configForm.device_id || !configForm.platform) {
    ElMessage.warning('请填写完整信息')
    return
  }
  try {
    await configureGroupBuy(configForm.device_id, {
      platform: configForm.platform,
      deal_id: configForm.deal_id,
      deal_name: configForm.deal_name,
      original_price: configForm.original_price,
      group_price: configForm.group_price,
      custom_url: configForm.custom_url
    })
    ElMessage.success('配置保存成功')
    showConfigDialog.value = false
  } catch (e) {
    ElMessage.error('配置保存失败')
  }
}

onMounted(() => {
  loadData()
})
</script>

<style lang="scss" scoped>
.group-buy-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .header-left {
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

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 16px;
    font-weight: 600;
  }
}

@media (max-width: 768px) {
  .group-buy-container {
    padding: 12px;

    .charts-row .chart {
      height: 300px;
    }
  }
}
</style>

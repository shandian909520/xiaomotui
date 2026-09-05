<template>
  <div class="experiment-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">A/B测试</h1>
      </div>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="showCreateDialog">创建实验</el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <el-card class="filter-card" shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="算法A">
          <el-select v-model="filterForm.algorithm_a" placeholder="选择算法" clearable>
            <el-option label="混合推荐" value="hybrid" />
            <el-option label="协同过滤" value="collaborative" />
            <el-option label="内容推荐" value="content" />
            <el-option label="热门推荐" value="popularity" />
          </el-select>
        </el-form-item>
        <el-form-item label="算法B">
          <el-select v-model="filterForm.algorithm_b" placeholder="选择算法" clearable>
            <el-option label="混合推荐" value="hybrid" />
            <el-option label="协同过滤" value="collaborative" />
            <el-option label="内容推荐" value="content" />
            <el-option label="热门推荐" value="popularity" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间范围">
          <el-select v-model="filterForm.days" placeholder="选择周期">
            <el-option label="最近7天" :value="7" />
            <el-option label="最近30天" :value="30" />
            <el-option label="最近90天" :value="90" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadExperiments">查询</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 实验结果对比 -->
    <el-card shadow="hover" style="margin-bottom: 20px;">
      <template #header>
        <div class="card-header">
          <span>实验结果对比</span>
        </div>
      </template>
      <div ref="comparisonChartRef" class="chart" v-loading="loading"></div>
    </el-card>

    <!-- 实验列表 -->
    <el-card shadow="hover">
      <template #header>
        <div class="card-header">
          <span>实验记录</span>
        </div>
      </template>
      <el-table :data="experiments" v-loading="loading" stripe>
        <el-table-column prop="name" label="实验名称" min-width="150" />
        <el-table-column prop="algorithm_a" label="算法A" width="120">
          <template #default="{ row }">
            <el-tag>{{ row.algorithm_a }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="algorithm_b" label="算法B" width="120">
          <template #default="{ row }">
            <el-tag type="success">{{ row.algorithm_b }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'running' ? 'warning' : row.status === 'completed' ? 'success' : 'info'">
              {{ row.status === 'running' ? '进行中' : row.status === 'completed' ? '已完成' : '待开始' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="winner" label="胜出" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.winner" type="success">{{ row.winner }}</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="confidence" label="置信度" width="100">
          <template #default="{ row }">
            {{ row.confidence ? `${(row.confidence * 100).toFixed(1)}%` : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="start_time" label="开始时间" width="170" />
        <el-table-column prop="end_time" label="结束时间" width="170" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="viewDetail(row)">详情</el-button>
            <el-button link type="danger" v-if="row.status === 'running'" @click="stopExperiment(row)">停止</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 创建实验对话框 -->
    <el-dialog v-model="createDialogVisible" title="创建A/B测试实验" width="600px">
      <el-form :model="createForm" label-width="100px">
        <el-form-item label="实验名称" required>
          <el-input v-model="createForm.name" placeholder="请输入实验名称" />
        </el-form-item>
        <el-form-item label="算法A" required>
          <el-select v-model="createForm.algorithm_a" placeholder="选择对照算法" style="width: 100%;">
            <el-option label="混合推荐" value="hybrid" />
            <el-option label="协同过滤" value="collaborative" />
            <el-option label="内容推荐" value="content" />
            <el-option label="热门推荐" value="popularity" />
          </el-select>
        </el-form-item>
        <el-form-item label="算法B" required>
          <el-select v-model="createForm.algorithm_b" placeholder="选择实验算法" style="width: 100%;">
            <el-option label="混合推荐" value="hybrid" />
            <el-option label="协同过滤" value="collaborative" />
            <el-option label="内容推荐" value="content" />
            <el-option label="热门推荐" value="popularity" />
          </el-select>
        </el-form-item>
        <el-form-item label="测试周期">
          <el-select v-model="createForm.days" style="width: 100%;">
            <el-option label="7天" :value="7" />
            <el-option label="14天" :value="14" />
            <el-option label="30天" :value="30" />
          </el-select>
        </el-form-item>
        <el-form-item label="流量比例">
          <el-slider v-model="createForm.traffic_ratio" :min="10" :max="50" :step="5" show-input />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Search } from '@element-plus/icons-vue'
import { useEcharts, getBarChartOption } from '@/composables/useEcharts'
import { getAbTestAnalysis, getExperiments, createExperiment, stopExperiment as stopExperimentApi } from '@/api/recommendation'

const loading = ref(false)
const createDialogVisible = ref(false)

const filterForm = reactive({
  algorithm_a: 'hybrid',
  algorithm_b: 'popularity',
  days: 30
})

const createForm = reactive({
  name: '',
  algorithm_a: 'hybrid',
  algorithm_b: 'popularity',
  days: 30,
  traffic_ratio: 50
})

const experiments = ref([])

const comparisonChartRef = ref(null)
const comparisonChart = useEcharts(comparisonChartRef)

const loadExperiments = async () => {
  loading.value = true
  try {
    const [abRes, listRes] = await Promise.all([
      getAbTestAnalysis({
        algorithm_a: filterForm.algorithm_a,
        algorithm_b: filterForm.algorithm_b,
        days: filterForm.days
      }),
      getExperiments({
        algorithm_a: filterForm.algorithm_a || undefined,
        algorithm_b: filterForm.algorithm_b || undefined,
        days: filterForm.days
      })
    ])
    const data = abRes || {}
    experiments.value = listRes?.list || listRes || []
    // 对比图
    const metrics = ['点击率', '转化率', '覆盖率', '满意度']
    const algorithmAData = [data.metrics?.a?.click_rate || 0.15, data.metrics?.a?.conversion_rate || 0.08, data.metrics?.a?.coverage || 0.6, data.metrics?.a?.satisfaction || 0.7]
    const algorithmBData = [data.metrics?.b?.click_rate || 0.12, data.metrics?.b?.conversion_rate || 0.06, data.metrics?.b?.coverage || 0.5, data.metrics?.b?.satisfaction || 0.65]
    const option = getBarChartOption(
      metrics,
      [
        { name: filterForm.algorithm_a, data: algorithmAData.map(v => (v * 100).toFixed(1)) },
        { name: filterForm.algorithm_b, data: algorithmBData.map(v => (v * 100).toFixed(1)) }
      ]
    )
    comparisonChart.setOption(option)
  } catch {
    experiments.value = []
  } finally {
    loading.value = false
  }
}

const showCreateDialog = () => {
  createForm.name = ''
  createDialogVisible.value = true
}

const handleCreate = async () => {
  if (!createForm.name || !createForm.algorithm_a || !createForm.algorithm_b) {
    ElMessage.warning('请填写完整信息')
    return
  }
  try {
    await createExperiment({ ...createForm })
    ElMessage.success('实验创建成功')
    createDialogVisible.value = false
    loadExperiments()
  } catch (e) {
    ElMessage.error('实验创建失败')
  }
}

const viewDetail = (row) => {
  ElMessage.info(`查看实验: ${row.name}`)
}

const stopExperiment = async (row) => {
  try {
    await stopExperimentApi(row.id)
    ElMessage.success('实验已停止')
    row.status = 'completed'
  } catch (e) {
    ElMessage.error('停止实验失败')
  }
}

onMounted(() => {
  loadExperiments()
})
</script>

<style lang="scss" scoped>
.experiment-container {
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
  }

  .card-header {
    font-size: 16px;
    font-weight: 600;
  }

  .chart {
    width: 100%;
    height: 400px;
  }
}

@media (max-width: 768px) {
  .experiment-container {
    padding: 12px;

    .chart {
      height: 300px;
    }
  }
}
</style>
